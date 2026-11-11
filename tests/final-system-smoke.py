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

print("ALL FINAL SYSTEM HTTP SMOKE TESTS PASSED: %d checks" % total)
