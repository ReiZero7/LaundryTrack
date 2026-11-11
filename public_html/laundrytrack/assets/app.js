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


// Admin: select walk-in vs existing account in new order form.
(() => {
  const form = document.getElementById('new-order-form');
  if (!form) return;
  const walkin = document.getElementById('walkin-customer-fields');
  const existing = document.getElementById('existing-customer-fields');
  const name = walkin?.querySelector('input[name="walkin_name"]');
  const select = existing?.querySelector('select[name="customer_id"]');
  const radios = form.querySelectorAll('input[name="customer_mode"]');
  function syncMode() {
    const isWalkin = form.querySelector('input[name="customer_mode"]:checked')?.value === 'walkin';
    if (walkin) walkin.hidden = !isWalkin;
    if (existing) existing.hidden = isWalkin;
    if (name) name.required = isWalkin;
    if (select) select.required = !isWalkin;
  }
  radios.forEach(r => r.addEventListener('change', syncMode));
  syncMode();
})();

// Local QR generator; the secret URL is not sent to third-party QR services.
(() => {
  const output = document.querySelector('.walkin-qr-output[data-qr-link]');
  const url = output?.dataset.qrLink;
  const qr = document.getElementById('order-qr-code');
  if (!url || !qr) return;
  if (typeof QRCode === 'function') {
    new QRCode(qr, {text: url, width: 196, height: 196, correctLevel: QRCode.CorrectLevel.M});
  } else {
    qr.textContent = 'ไม่สามารถสร้างภาพ QR ได้ กรุณาคัดลอกลิงก์แทน';
  }
  document.getElementById('copy-order-tracking-url')?.addEventListener('click', async event => {
    try {
      await navigator.clipboard.writeText(url);
      event.currentTarget.textContent = 'คัดลอกแล้ว ✓';
    } catch (_) {
      const field = document.getElementById('order-tracking-url');
      if (field) { field.focus(); field.select(); }
    }
  });
})();

// Guest-scoped tracking: polling updates ONLY the order associated with its session.
(() => {
  const panel = document.getElementById('guest-track-panel');
  if (!panel) return;
  const badge = document.getElementById('guest-order-status');
  const message = document.getElementById('guest-ready-message');
  const confirmForm = document.getElementById('guest-pickup-form');
  let previousStatus = panel.dataset.initialStatus;
  let inFlight = false;
  let stopped = false;

  const classes = {
    ready: 'is-good', picked_up: 'is-good', cancelled: 'is-muted',
    washing: 'is-progress', drying: 'is-progress', received: 'is-pending'
  };
  function showReadyToast() {
    document.querySelector('.pickup-toast')?.remove();
    const toast = document.createElement('div');
    toast.className = 'pickup-toast';
    toast.setAttribute('role','status');
    toast.textContent = '🔔 ผ้าของคุณพร้อมรับแล้ว! กรุณามารับที่ร้าน';
    document.body.append(toast);
    window.setTimeout(() => toast.remove(), 9000);
  }
  async function refreshGuest() {
    if (stopped || inFlight || document.visibilityState !== 'visible') return;
    inFlight = true;
    try {
      const response = await fetch(panel.dataset.feed, {
        credentials: 'same-origin', cache: 'no-store',
        headers: {'Accept':'application/json'}
      });
      if (response.status === 401 || response.status === 403) {
        stopped = true;
        if (badge) badge.textContent = 'สิทธิ์ติดตามหมดอายุ กรุณาสแกน QR ใหม่';
        if (confirmForm) confirmForm.hidden = true;
        return;
      }
      if (!response.ok) return;
      const data = await response.json();
      if (typeof data.status !== 'string' || !Object.prototype.hasOwnProperty.call(classes, data.status)) return;
      if (badge) {
        badge.textContent = typeof data.status_label === 'string' ? data.status_label : data.status;
        badge.className = 'status ' + classes[data.status];
      }
      if (message) message.hidden = data.status !== 'ready';
      if (confirmForm) confirmForm.hidden = data.status !== 'ready';
      if (previousStatus !== 'ready' && data.status === 'ready') showReadyToast();
      previousStatus = data.status;
    } catch (_) {
      // Keep last known state on network errors.
    } finally { inFlight = false; }
  }
  window.setInterval(refreshGuest, 30000);
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') refreshGuest();
  });
  window.addEventListener('focus', refreshGuest);
  refreshGuest();
})();
