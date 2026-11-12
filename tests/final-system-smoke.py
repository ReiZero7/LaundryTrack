#!/usr/bin/env python3
"""Full-system HTTP smoke tests against a disposable local CI MySQL instance only.

Refuses to run against real LaundryTrack URLs or databases. Never generate
synthetic customer orders against production Angsila.
"""
import hashlib
import html
import http.cookiejar
import os
import re
import subprocess
import sys
from datetime import datetime, timedelta
from urllib.error import HTTPError
from urllib.parse import parse_qs, urlencode, urlparse
from urllib.request import HTTPCookieProcessor, Request, build_opener
from zoneinfo import ZoneInfo

BASE = "http://127.0.0.1:18081"
EXPECTED_DB = "laundrytrack_ci"
if (os.environ.get("APP_ENV") != "ci"
        or os.environ.get("DB_NAME") != EXPECTED_DB
        or os.environ.get("LAUNDRY_SMOKE_LOCAL_ONLY") != "yes"):
    sys.exit("REFUSED: smoke tests require APP_ENV=ci, disposable DB, and local-only guard")


def query(sql):
    env = dict(os.environ, MYSQL_PWD="ci-local-only")
    out = subprocess.run(
        ["mysql", "-h", "127.0.0.1", "-P", "3306", "-u", "root",
         "-D", EXPECTED_DB, "-N", "-B", "-e", sql],
        env=env, text=True, capture_output=True, check=True,
    )
    return out.stdout.strip()


if query("SELECT DATABASE()") != EXPECTED_DB:
    sys.exit("REFUSED: MySQL is not the disposable smoke-test database")

total = 0


def ok(condition, reason):
    global total
    if not condition:
        sys.exit("FAIL: " + reason)
    total += 1
    print("PASS:", reason, flush=True)


def status(order_id):
    return query("SELECT status FROM laundry__orders_tbl WHERE id=%d" % order_id)


def order_for_hash(token):
    hashed = hashlib.sha256(token.encode("ascii")).hexdigest()
    result = query(
        "SELECT id FROM laundry__orders_tbl WHERE tracking_token_hash='%s'" % hashed
    )
    return int(result) if result else None


def count(statement):
    return int(query(statement))


class Browser:
    def __init__(self):
        self.jar = http.cookiejar.CookieJar()
        self.opener = build_opener(HTTPCookieProcessor(self.jar))

    def request(self, path="", data=None):
        url = BASE + "/index.php" + path
        payload = None if data is None else urlencode(data, doseq=True).encode()
        request = Request(url, data=payload)
        request.add_header("User-Agent", "LaundryTrack-CI-Smoke/1.0")
        try:
            with self.opener.open(request, timeout=15) as response:
                return response.status, response.geturl(), response.read().decode("utf-8")
        except HTTPError as err:
            return err.code, err.geturl(), err.read().decode("utf-8")

    def get(self, page="home", **params):
        return self.request("?" + urlencode(dict(page=page, **params)))

    def post(self, values, csrf_page="self-order", **page_params):
        code, _url, page = self.get(csrf_page, **page_params)
        ok(code == 200, "CSRF form page available: " + csrf_page)
        match = re.search(r'name="csrf"\s+value="([^"]+)"', page)
        ok(match is not None, "CSRF token rendered: " + csrf_page)
        data = dict(values)
        data["csrf"] = html.unescape(match.group(1))
        return self.request("", data)


def extract_tracking_token(page):
    match = re.search(r'id="order-tracking-url"[^>]*value="([^"]+)"', page)
    ok(match is not None, "checkout returns one-time private tracking link")
    link = html.unescape(match.group(1))
    token = parse_qs(urlparse(link).query).get("token", [""])[0]
    ok(re.fullmatch(r"[a-f0-9]{64}", token) is not None, "tracking token has 32 random bytes")
    return token


future = (datetime.now(ZoneInfo("Asia/Bangkok")) + timedelta(days=3)).strftime("%Y-%m-%dT%H:%M")


def new_self_order(browser, guest=True):
    body = {
        "action": "self-order-create",
        "due_at": future,
        "service_id[]": ["1"],
        "quantity[]": ["2"],
        "notes": "CI smoke order",
        "website": "",
    }
    if guest:
        body.update(full_name="CI Guest", phone="0812345678")
    return browser.post(body, "self-order")


def sign_up_and_login(email, password):
    browser = Browser()
    code, url, response = browser.post({
        "action": "register", "full_name": "CI Member",
        "email": email, "phone": "0812345678", "password": password,
    }, "register")
    ok(code == 200 and "page=login" in url, "new customer registration")
    code, url, response = browser.post({
        "action": "login", "email": email, "password": password,
    }, "login")
    ok(code == 200 and "page=dashboard" not in url and "page=progress" in url,
       "customer login redirects to progress")
    return browser


# Start with core GET and CSRF/role protections.
guest = Browser()
code, url, page = guest.get("home")
ok(code == 200 and "LaundryTrack" in page, "public homepage available")
ok('class="usage-steps"' in page and "พนักงานยืนยันส่งมอบจริง" in page,
   "public homepage explains the three-step guest laundry journey")
ok('href="#main-content"' in page and 'id="main-content"' in page,
   "skip-to-content link and target are included")
code, url, page = guest.get("progress")
ok(code == 200 and "page=login" in url, "unauthenticated member progress redirects to login")
code, url, page = guest.request("", {
    "action": "self-order-create", "csrf": "invalid", "due_at": future,
})
ok(code == 419, "invalid CSRF token rejected before processing order")

# Guest creates a new requested order and receives QR.
code, url, page = new_self_order(guest)
ok(code == 200 and "page=order-complete" in url, "guest checkout finishes successfully")
token = extract_tracking_token(page)
guest_order = order_for_hash(token)
ok(guest_order is not None and status(guest_order) == "requested",
   "guest order persisted as requested")
ok(count("SELECT COUNT(*) FROM laundry__order_items_tbl WHERE order_id=%d" % guest_order) == 1,
   "guest order item persisted")
code, url, page = guest.get("track", token=token)
ok(code == 200 and "CI Guest" not in page and "0812345678" not in page,
   "QR landing page does not show customer name/phone")
ok("ติดตามสถานะ" in page, "QR landing page shows status information")

# A fresh incognito browser cannot claim membership merely from a QR.
outsider = Browser()
code, url, page = outsider.post({"action": "guest-track-login", "token": token},
                                "track", token=token)
ok(code == 200 and "page=track-portal" in url, "fresh browser can follow valid QR")
code, url, page = outsider.get("join-after-order")
ok(code == 200 and "ไม่พบออร์เดอร์" in page,
   "fresh QR browser cannot claim membership before pickup")
code, url, page = outsider.get("track-feed")
ok(code == 200 and '"status":"requested"' in page, "guest tracking JSON is scoped to token")
code, url, page = Browser().get("track-feed")
ok(code == 401, "track feed rejects no-session browser")

# Admin uses an actual password-hash account created only in CI.
admin = Browser()
code, url, page = admin.post({
    "action": "login", "email": "staff@example.test", "password": "ci-admin-passphrase",
}, "login")
ok(code == 200 and "page=dashboard" in url, "admin login accepted")
ok('data-mobile-menu' in page and 'aria-expanded="false"' in page
   and 'data-mobile-close' in page and 'data-mobile-backdrop' in page,
   "authenticated admin can access mobile navigation controls")
code, url, page = admin.get("orders")
ok(code == 200 and 'class="orders-mobile-table"' in page,
   "order listing includes responsive mobile table/card structure")
ok('data-label="หมายเลขออร์เดอร์"' in page
   and 'data-label="รายละเอียด"' in page,
   "order listing includes visible mobile field labels")
ok('aria-current="page"' in page, "active navigation link is marked for accessibility")
# This staff queue has a distinct URL, no completed jobs, and the oldest live job first.
code, url, page = Browser().get("manage-orders")
ok("page=login" in url, "anonymous users cannot open Admin work queue")
admin_id = int(query("SELECT id FROM laundry__users_tbl WHERE email='staff@example.test'"))
query("INSERT INTO laundry__users_tbl(full_name,email,password_hash,role) "
      "VALUES('CI Queue Customer','queue-customer@example.test','unused','customer')")
queue_customer_id = int(query("SELECT id FROM laundry__users_tbl WHERE email='queue-customer@example.test'"))
for suffix, received, state in [
        ("OLD-1", "2026-10-19 10:00:00", "washing"),
        ("OLD-2", "2026-10-20 10:00:00", "received"),
        ("DONE", "2026-10-18 10:00:00", "picked_up")]:
    query("INSERT INTO laundry__orders_tbl(order_no,customer_id,created_by,"
          "received_at,due_at,total_amount,status) VALUES("
          "'LT-CI-QUEUE-%s',%d,%d,'%s','2026-12-01 10:00:00',20,'%s')"
          % (suffix, queue_customer_id, admin_id, received, state))
code, url, page = admin.get("manage-orders")
ok(code == 200 and 'จัดการออร์เดอร์ที่ยังไม่เสร็จ' in page,
   "Admin work queue page is accessible")
ok('LT-CI-QUEUE-OLD-1' in page and 'LT-CI-QUEUE-OLD-2' in page,
   "unfinished orders are visible in Admin queue")
ok(page.find('LT-CI-QUEUE-OLD-1') < page.find('LT-CI-QUEUE-OLD-2'),
   "oldest unfinished order appears first")
ok('LT-CI-QUEUE-DONE' not in page,
   "completed pickup order is absent from Admin work queue")
code, url, page = admin.get("orders")
ok('LT-CI-QUEUE-DONE' in page, "all-order history still includes finished orders")
query("DELETE FROM laundry__orders_tbl WHERE order_no LIKE 'LT-CI-QUEUE-%'")
query("DELETE FROM laundry__users_tbl WHERE id=%d" % queue_customer_id)


code, url, page = admin.post({
    "action": "order-status", "id": str(guest_order), "status": "washing",
}, "order", id=str(guest_order))
ok(status(guest_order) == "requested", "admin cannot skip requested directly to washing")
code, url, page = admin.post({
    "action": "order-status", "id": str(guest_order), "status": "received",
}, "order", id=str(guest_order))
ok(status(guest_order) == "requested", "dropoff requires explicit staff confirmation")
code, url, page = admin.post({
    "action": "order-status", "id": str(guest_order), "status": "received",
    "dropoff_confirmed": "yes",
}, "order", id=str(guest_order))
ok(status(guest_order) == "received", "staff-confirmed actual dropoff updates status")
code, url, page = admin.post({
    "action": "order-status", "id": str(guest_order), "status": "ready",
}, "order", id=str(guest_order))
ok(status(guest_order) == "received", "admin cannot jump from received directly to ready")

for state in ["washing", "drying", "ready"]:
    code, url, page = admin.post({
        "action": "order-status", "id": str(guest_order), "status": state,
    }, "order", id=str(guest_order))
    ok(status(guest_order) == state, "admin transition reached " + state)

ok(count("SELECT COUNT(*) FROM laundry__status_history_tbl WHERE order_id=%d" % guest_order) == 5,
   "initial and four valid guest status changes recorded")
code, url, page = admin.post({
    "action": "order-status", "id": str(guest_order), "status": "picked_up",
}, "order", id=str(guest_order))
ok(status(guest_order) == "ready", "admin status transition cannot bypass staff pickup flow")

code, url, page = admin.post({
    "action": "staff-confirm-pickup", "id": str(guest_order),
    "handoff_confirmed": "yes",
}, "order", id=str(guest_order))
ok(status(guest_order) == "ready", "staff cannot close order before customer pickup request")

# The QR session requests pickup; staff verifies actual handoff afterward.
requester = Browser()
code, url, page = requester.post({
    "action": "guest-track-login", "token": token,
}, "track", token=token)
ok("page=track-portal" in url, "independent QR-only browser starts tracking ready order")
code, url, page = requester.post({"action": "guest-confirm-pickup"}, "track-portal")
ok(status(guest_order) == "ready", "guest request does not mark order picked_up")
ok(query("SELECT requested_source FROM laundry__pickup_requests_tbl WHERE order_id=%d" % guest_order) == "guest",
   "guest pickup request stored with source")
code, url, page = requester.get("track-portal")
ok("guest-pickup-pending" in page and "แจ้งความประสงค์รับผ้าแล้ว" in page,
   "guest sees staff verification pending")
code, url, page = requester.get("join-after-order")
ok("self-order-join" not in page, "QR guest cannot claim account before staff approval")
code, url, page = outsider.get("join-after-order")
ok("self-order-join" not in page, "other QR session has no membership claim")

code, url, page = requester.post({"action": "guest-confirm-pickup"}, "track-portal")
ok(status(guest_order) == "ready" and count(
    "SELECT COUNT(*) FROM laundry__pickup_requests_tbl WHERE order_id=%d" % guest_order) == 1,
   "repeated guest pickup request is idempotent")
code, url, page = admin.post({
    "action": "staff-confirm-pickup", "id": str(guest_order)
}, "order", id=str(guest_order))
ok(status(guest_order) == "ready", "staff must check physical handoff checkbox")
code, url, page = requester.post({
    "action": "staff-confirm-pickup", "id": str(guest_order),
    "handoff_confirmed": "yes"
}, "track-portal")
ok("page=login" in url and status(guest_order) == "ready",
   "anonymous QR session cannot use staff confirmation action")
code, url, page = admin.post({
    "action": "staff-confirm-pickup", "id": str(guest_order),
    "handoff_confirmed": "yes"
}, "order", id=str(guest_order))
ok(status(guest_order) == "picked_up", "staff confirmation finalizes guest handoff")
ok(query("SELECT confirmed_by IS NOT NULL FROM laundry__pickup_requests_tbl WHERE order_id=%d" % guest_order) == "1",
   "staff approver is recorded")
ok(count("SELECT COUNT(*) FROM laundry__status_history_tbl WHERE order_id=%d AND new_status='picked_up'" % guest_order) == 1,
   "only one final status history event recorded")
code, url, page = requester.get("join-after-order")
ok(code == 200 and "self-order-join" in page, "requesting guest can claim after staff handoff")
code, url, page = outsider.get("join-after-order")
ok("self-order-join" not in page, "incognito QR session cannot claim after pickup")

code, url, page = requester.post({
    "action": "self-order-join", "email": "converted@example.test",
    "password": "converted-strong-password",
}, "join-after-order")
ok(code == 200 and "page=order" in url, "pickup-confirming guest converts to member")
ok(query("SELECT tracking_token_hash IS NULL FROM laundry__orders_tbl WHERE id=%d" % guest_order) == "1",
   "successful conversion revokes guest QR")
code, url, page = outsider.get("track", token=token)
ok("ลิงก์ติดตามไม่ถูกต้อง" in page, "old tracking link invalid after member conversion")
code, url, page = requester.get("order", id=str(guest_order))
ok(code == 200 and "CI Guest" in page, "linked member can view claimed order history")

# Member-only order and account-isolation checks.
member_a = sign_up_and_login("member-a@example.test", "member-a-passphrase")
member_b = sign_up_and_login("member-b@example.test", "member-b-passphrase")
code, url, page = new_self_order(member_a, guest=False)
ok(code == 200 and "page=order" in url, "registered member can create self-service order")
member_id = int(parse_qs(urlparse(url).query)["id"][0])
ok(status(member_id) == "requested", "member new order starts in requested")
code, url, page = member_a.get("progress")
ok(code == 200 and "data-progress-order" in page, "member progress shows own active order")
ok('data-mobile-menu' in page and 'id="site-navigation"' in page,
   "member portal also provides mobile drawer navigation")
code, url, page = member_b.get("order", id=str(member_id))
ok(code == 404, "other customer cannot read order details")
code, url, page = member_a.get("notifications-feed")
ok(code == 200 and '"ready_count":0' in page, "authenticated customer notification feed works")
code, url, page = admin.get("notifications-feed")
ok(code == 403, "admin forbidden from customer notification feed")
code, url, page = Browser().get("progress-feed")
ok(code == 401, "anonymous progress feed denied")

code, url, page = member_b.post({
    "action": "order-status", "id": str(member_id), "status": "received",
    "dropoff_confirmed": "yes",
}, "progress")
ok(code == 403 and status(member_id) == "requested",
   "customer cannot issue admin-only status transition")

for state in ["received", "washing", "drying", "ready"]:
    values = {"action": "order-status", "id": str(member_id), "status": state}
    if state == "received":
        values["dropoff_confirmed"] = "yes"
    code, url, page = admin.post(values, "order", id=str(member_id))
    ok(status(member_id) == state, "member order admin transition " + state)

code, url, page = member_b.post({
    "action": "order-confirm-pickup", "id": str(member_id)
}, "progress")
ok(status(member_id) == "ready" and count(
    "SELECT COUNT(*) FROM laundry__pickup_requests_tbl WHERE order_id=%d" % member_id) == 0,
   "different member cannot create pickup request")
code, url, page = member_a.post({
    "action": "order-confirm-pickup", "id": str(member_id)
}, "order", id=str(member_id))
ok(status(member_id) == "ready", "member requests pickup without changing final status")
ok(query("SELECT requested_source FROM laundry__pickup_requests_tbl WHERE order_id=%d" % member_id) == "member",
   "member request recorded")
code, url, page = member_a.post({
    "action": "order-confirm-pickup", "id": str(member_id)
}, "order", id=str(member_id))
ok(count("SELECT COUNT(*) FROM laundry__pickup_requests_tbl WHERE order_id=%d" % member_id) == 1,
   "duplicate member request creates no second row")
code, url, page = member_b.post({
    "action": "staff-confirm-pickup", "id": str(member_id), "handoff_confirmed": "yes"
}, "progress")
ok(code == 403 and status(member_id) == "ready", "member cannot impersonate staff handoff")
code, url, page = admin.post({
    "action": "staff-confirm-pickup", "id": str(member_id), "handoff_confirmed": "yes"
}, "order", id=str(member_id))
ok(status(member_id) == "picked_up", "staff finalizes member handoff after request")
code, url, page = admin.post({
    "action": "staff-confirm-pickup", "id": str(member_id), "handoff_confirmed": "yes"
}, "order", id=str(member_id))
ok(status(member_id) == "picked_up", "duplicate staff confirmation leaves final status")
ok(count("SELECT COUNT(*) FROM laundry__status_history_tbl "
         "WHERE order_id=%d AND new_status='picked_up'" % member_id) == 1,
   "duplicate staff confirmation creates no duplicate history")

# Account-level login lockout after repeated guesses, without exposing account existence.
attacker = Browser()
for i in range(13):
    code, url, page = attacker.post({
        "action": "login", "email": "nonexistent-locked@example.test",
        "password": "incorrect-password",
    }, "login")
    ok(code == 200 and "page=login" in url,
       "invalid login attempt returns login page (%d)" % (i + 1))
    if i == 12:
        ok("มากเกินไป" in page,
           "thirteenth login attempt receives generic temporary quota message")

# A different account must not be blocked by that account-level lockout.
independent = Browser()
code, url, page = independent.post({
    "action": "login", "email": "member-a@example.test",
    "password": "member-a-passphrase",
}, "login")
ok(code == 200 and "page=progress" in url,
   "unrelated valid account still logs in after account-specific lockout")

# Both Admin and Member can open their own account settings.
code, url, page = admin.get("account")
ok(code == 200 and "เปลี่ยนรหัสผ่าน" in page and "ลบบัญชีของฉัน" in page,
   "Admin can access account settings")
code, url, page = member_a.get("account")
ok(code == 200 and "เปลี่ยนรหัสผ่าน" in page,
   "Member can access account settings")
code, url, page = Browser().get("account")
ok("page=login" in url, "anonymous visitors cannot open account settings")
code, url, page = member_a.get("manage-orders")
ok(code == 403, "Member is forbidden from Admin work queue")

# Changing password requires old password and matching confirmation.
code, url, page = member_a.post({
    "action": "account-password-change", "current_password": "wrong",
    "new_password": "member-a-new-passphrase",
    "confirm_password": "member-a-new-passphrase",
}, "account")
ok(code == 200 and "page=account" in url and "ไม่ถูกต้อง" in page,
   "wrong current password cannot change member password")
code, url, page = member_a.post({
    "action": "account-password-change", "current_password": "member-a-passphrase",
    "new_password": "member-a-new-passphrase",
    "confirm_password": "member-a-new-passphrase",
}, "account")
ok(code == 200 and "page=account" in url and "เปลี่ยนรหัสผ่านเรียบร้อย" in page,
   "member changes their own password")
code, url, page = member_a.get("account")
ok(code == 200, "current browser remains logged in after password change")
code, url, page = independent.get("progress")
ok("page=login" in url, "old browser session revoked after password change")

old_login = Browser()
code, url, page = old_login.post({
    "action":"login", "email":"member-a@example.test",
    "password":"member-a-passphrase"
}, "login")
ok("page=login" in url, "old member password no longer logs in")
new_login = Browser()
code, url, page = new_login.post({
    "action":"login", "email":"member-a@example.test",
    "password":"member-a-new-passphrase"
}, "login")
ok("page=progress" in url, "new member password can log in")

# Deletion is self-service only, password protected and blocks unfinished orders.
pending_member = sign_up_and_login("pending-delete@example.test", "pending-member-passphrase")
code, url, page = new_self_order(pending_member, guest=False)
ok("page=order" in url, "pending deletion test order created in isolated MySQL")
code, url, page = pending_member.get("account")
ok("ยังมีออร์เดอร์ไม่สิ้นสุด" in page, "account UI warns about unfinished orders")
code, url, page = pending_member.post({
    "action":"account-delete", "current_password":"pending-member-passphrase",
    "delete_confirmation":"DELETE",
}, "account")
ok("page=account" in url and "ยังมีออร์เดอร์" in page,
   "customer with active order cannot delete account")
ok(query("SELECT email IS NOT NULL FROM laundry__users_tbl "
         "WHERE email='pending-delete@example.test'") == "1",
   "blocked account retains credentials and order")

# Last active Admin cannot self-delete even with the correct password.
code, url, page = admin.post({
    "action":"account-delete", "current_password":"ci-admin-passphrase",
    "delete_confirmation":"DELETE",
}, "account")
ok("page=account" in url and "แอดมินคนสุดท้าย" in page,
   "last Admin cannot remove access to the whole shop")
code, url, page = admin.get("manage-orders")
ok(code == 200, "last Admin remains logged in and retains staff access")

# An unreferenced member is physically deleted; incorrect password is rejected.
code, url, page = member_b.post({
    "action":"account-delete", "current_password":"wrong",
    "delete_confirmation":"DELETE",
}, "account")
ok("page=account" in url and "ไม่สามารถลบบัญชี" in page,
   "wrong password cannot remove account")
code, url, page = member_b.post({
    "action":"account-delete", "current_password":"member-b-passphrase",
    "delete_confirmation":"DELETE",
}, "account")
ok("account_removed=1" in url, "unreferenced member deletes own account")
ok(query("SELECT COUNT(*) FROM laundry__users_tbl WHERE email='member-b@example.test'") == "0",
   "unreferenced deleted account row removed")
code, url, page = member_b.get("account")
ok("page=login" in url, "deleted member session can no longer access account")

# When order/audit history references the member, anonymize identity and preserve history.
code, url, page = member_a.post({
    "action":"account-delete", "current_password":"member-a-new-passphrase",
    "delete_confirmation":"DELETE",
}, "account")
ok("account_removed=1" in url, "member with closed order can delete login account")
ok(query("SELECT COUNT(*) FROM laundry__users_tbl WHERE email='member-a@example.test'") == "0",
   "deleted account email is not retained")
ok(query("SELECT COUNT(*) FROM laundry__orders_tbl WHERE id=%d" % member_id) == "1",
   "completed order remains intact after account deletion")
ok(query("SELECT u.email IS NULL FROM laundry__orders_tbl o JOIN laundry__users_tbl u "
         "ON u.id=o.customer_id WHERE o.id=%d" % member_id) == "1",
   "historical member account PII is anonymized")
code, url, page = new_login.get("progress")
ok("page=login" in url, "other member browser invalid after account deletion")

print("ALL FINAL SYSTEM HTTP SMOKE TESTS PASSED: %d checks" % total)
