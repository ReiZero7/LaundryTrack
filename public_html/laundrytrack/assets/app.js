'use strict';
(() => {
  const wrap = document.getElementById('order-items');
  const add = document.getElementById('add-order-item');
  if (!wrap || !add) return;
  const money = n => new Intl.NumberFormat('th-TH', {style: 'currency', currency: 'THB'}).format(n);
  const update = () => {
    let total = 0;
    wrap.querySelectorAll('.order-item-row').forEach(row => {
      const selected = row.querySelector('.service-select').selectedOptions[0];
      const price = Number(selected?.dataset.price || 0);
      const qty = Number(row.querySelector('.quantity-input').value || 0);
      const subtotal = Math.round((price * qty + Number.EPSILON) * 100) / 100;
      total += subtotal;
      row.querySelector('.line-total').textContent = money(subtotal);
    });
    document.getElementById('order-total').textContent = money(total);
    wrap.querySelectorAll('.remove-row').forEach(b => b.disabled = wrap.children.length <= 1);
    add.disabled = wrap.children.length >= 15;
  };
  add.addEventListener('click', () => {
    if (wrap.children.length >= 15) return;
    const first = wrap.querySelector('.order-item-row');
    const next = first.cloneNode(true);
    next.querySelector('.service-select').value = '';
    next.querySelector('.quantity-input').value = '1';
    wrap.appendChild(next);
    update();
  });
  wrap.addEventListener('input', update);
  wrap.addEventListener('change', update);
  wrap.addEventListener('click', e => {
    const button = e.target.closest('.remove-row');
    if (button && wrap.children.length > 1) { button.closest('.order-item-row').remove(); update(); }
  });
  update();
})();


// Update ready-order notifications while the customer keeps the page open.
// This is in-page polling, not a background phone/web-push notification.
(() => {
  const bell = document.getElementById('customer-notifications');
  if (!bell) return; // Guests and administrators never poll this endpoint.

  const badge = bell.querySelector('[data-ready-badge]');
  const notice = document.getElementById('pickup-live-notice');
  const list = document.getElementById('pickup-live-list');
  const countText = document.getElementById('pickup-live-count');
  const metric = document.getElementById('pickup-live-metric');
  const moreLink = document.getElementById('pickup-live-more');
  let previousCount = Number(bell.dataset.count) || 0;
  let inFlight = false;
  let stopped = false;

  const orderHref = id => 'index.php?page=order&id=' + encodeURIComponent(id);

  function showNewReadyMessage(orders) {
    document.querySelector('.pickup-toast')?.remove();
    const toast = document.createElement('div');
    toast.className = 'pickup-toast';
    toast.setAttribute('role', 'status');
    const message = document.createElement('span');
    message.textContent = '🔔 มีผ้าพร้อมรับเพิ่มแล้ว!';
    toast.append(message);
    const target = orders.find(item => Number.isSafeInteger(item.id) && item.id > 0);
    const link = document.createElement('a');
    link.href = target ? orderHref(target.id) : 'index.php?page=dashboard';
    link.textContent = 'ดูออร์เดอร์';
    toast.append(link);
    document.body.append(toast);
    window.setTimeout(() => { if (toast.isConnected) toast.remove(); }, 9000);
  }

  function renderReadyOrders(data) {
    const count = data.ready_count;
    bell.dataset.count = String(count);
    bell.setAttribute('aria-label', 'การแจ้งเตือน: มี ' + count + ' ออร์เดอร์ที่พร้อมรับผ้า');
    if (badge) {
      badge.textContent = String(count);
      badge.hidden = count === 0;
    }
    if (countText) countText.textContent = String(count);
    if (metric) metric.textContent = String(count);

    if (notice && list) {
      notice.hidden = count === 0;
      const fragment = document.createDocumentFragment();
      for (const item of data.orders) {
        if (!Number.isSafeInteger(item.id) || item.id <= 0 || typeof item.order_no !== 'string') continue;
        const row = document.createElement('div');
        row.className = 'pickup-notice-item';
        row.dataset.orderId = String(item.id);
        const name = document.createElement('strong');
        name.textContent = item.order_no;
        const link = document.createElement('a');
        link.className = 'btn btn-primary';
        link.href = orderHref(item.id);
        link.textContent = 'ดูรายละเอียดและยืนยันรับผ้า';
        row.append(name, link);
        fragment.append(row);
      }
      list.replaceChildren(fragment);
      if (moreLink) moreLink.hidden = count <= data.orders.length;
    }
  }

  async function refreshNotifications() {
    if (stopped || inFlight || document.visibilityState !== 'visible') return;
    inFlight = true;
    try {
      const response = await fetch(bell.dataset.feed, {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { 'Accept': 'application/json' }
      });
      if (response.status === 401 || response.status === 403) {
        stopped = true; // Session expired or access revoked; avoid repeated requests.
        return;
      }
      if (!response.ok) return;
      const data = await response.json();
      if (!Number.isSafeInteger(data.ready_count) || data.ready_count < 0 || !Array.isArray(data.orders)) return;

      if (data.ready_count > previousCount) showNewReadyMessage(data.orders);
      renderReadyOrders(data);
      previousCount = data.ready_count;
    } catch (_) {
      // Network errors are temporary; keep the last known state.
    } finally {
      inFlight = false;
    }
  }

  window.setInterval(refreshNotifications, 30000);
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') refreshNotifications();
  });
  window.addEventListener('focus', refreshNotifications);
  refreshNotifications();
})();
