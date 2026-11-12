'use strict';

// Mobile navigation: accessible drawer with Esc, backdrop and keyboard support.
// Without JavaScript, the original horizontal navigation remains available.
(() => {
  const drawer = document.getElementById('site-navigation');
  const toggle = document.querySelector('[data-mobile-menu]');
  const closeButton = document.querySelector('[data-mobile-close]');
  const backdrop = document.querySelector('[data-mobile-backdrop]');
  const content = document.querySelector('.content-area');
  if (!drawer || !toggle || !closeButton || !backdrop || !content) return;

  const mobile = window.matchMedia('(max-width: 780px)');
  let opened = false;
  document.documentElement.classList.add('mobile-menu-ready');

  function updateNavigation() {
    const showDrawer = mobile.matches && opened;
    document.body.classList.toggle('mobile-nav-open', showDrawer);
    toggle.setAttribute('aria-expanded', String(showDrawer));
    toggle.setAttribute('aria-label', showDrawer ? 'ปิดเมนูนำทาง' : 'เปิดเมนูนำทาง');
    backdrop.hidden = !showDrawer;
    // Keep keyboard and assistive-technology focus inside the open drawer.
    content.inert = showDrawer;
    if (mobile.matches && !showDrawer) {
      drawer.setAttribute('inert', '');
      drawer.setAttribute('aria-hidden', 'true');
    } else {
      drawer.removeAttribute('inert');
      drawer.removeAttribute('aria-hidden');
    }
  }

  function closeNavigation(restoreFocus = true) {
    if (!opened) return;
    opened = false;
    updateNavigation();
    if (restoreFocus && mobile.matches) toggle.focus();
  }

  toggle.addEventListener('click', () => {
    opened = !opened;
    updateNavigation();
    if (opened) closeButton.focus();
  });
  closeButton.addEventListener('click', () => closeNavigation());
  backdrop.addEventListener('click', () => closeNavigation());
  drawer.querySelectorAll('.nav-item').forEach(link => {
    link.addEventListener('click', () => closeNavigation(false));
  });

  document.addEventListener('keydown', event => {
    if (!opened || !mobile.matches) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      closeNavigation();
    } else if (event.key === 'Tab') {
      const nodes = Array.from(drawer.querySelectorAll('a[href], button:not([disabled])'))
        .filter(node => node.getClientRects().length);
      if (!nodes.length) return;
      const first = nodes[0];
      const last = nodes[nodes.length - 1];
      if (event.shiftKey && (document.activeElement === first || !drawer.contains(document.activeElement))) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && (document.activeElement === last || !drawer.contains(document.activeElement))) {
        event.preventDefault();
        first.focus();
      }
    }
  });

  const onResize = () => {
    if (!mobile.matches) opened = false;
    updateNavigation();
  };
  if (mobile.addEventListener) mobile.addEventListener('change', onResize);
  else mobile.addListener(onResize);
  updateNavigation();
})();

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
    next.querySelector('.service-select').focus();
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
  const pendingMessage = document.getElementById('guest-pickup-pending');
  let previousStatus = panel.dataset.initialStatus;
  let inFlight = false;
  let stopped = false;

  const classes = {
    ready: 'is-good', picked_up: 'is-good', cancelled: 'is-muted',
    washing: 'is-progress', drying: 'is-progress',
    received: 'is-pending', requested: 'is-pending'
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
      if (confirmForm) confirmForm.hidden = data.status !== 'ready' || data.pickup_requested === true;
      if (pendingMessage) pendingMessage.hidden = data.status !== 'ready' || data.pickup_requested !== true;
      if (Array.isArray(data.services)) {
        for (const item of data.services) {
          if (!Number.isSafeInteger(item.id) || !['pending','working','done'].includes(item.status)) continue;
          const row = panel.querySelector('[data-guest-service-item="' + item.id + '"]');
          const label = row?.querySelector('[data-service-progress-label]');
          if (label) {
            label.textContent = typeof item.label === 'string' ? item.label : item.status;
            label.className = 'service-work-badge service-work-' + item.status;
          }
        }
      }
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

// Member progress page polls only its authenticated customer's orders.
// The server validates session ownership for every response.
(() => {
  const page = document.getElementById('member-progress-page');
  if (!page) return;
  let inFlight = false;
  let stopped = false;
  let previous = new Map();
  const guidance = {
    requested: 'กรุณานำผ้ามาส่งที่ร้าน เมื่อพนักงานตรวจรับแล้วสถานะจะอัปเดต',
    received: 'ร้านได้รับผ้าแล้ว และจะเริ่มดำเนินการซักตามคิว',
    washing: 'กำลังซักผ้าของคุณ',
    drying: 'กำลังอบหรือตากผ้าของคุณ',
    ready: '🔔 ผ้าพร้อมรับแล้ว กรุณาดูรายละเอียดเพื่อแจ้งพนักงานว่าต้องการรับผ้า',
    picked_up: 'พนักงานยืนยันการส่งมอบผ้าแล้ว ขอบคุณที่ใช้บริการ',
    cancelled: 'ออร์เดอร์นี้ถูกยกเลิก'
  };
  const classes = {
    requested: 'is-pending', received: 'is-pending', washing: 'is-progress',
    drying: 'is-progress', ready: 'is-good', picked_up: 'is-good', cancelled: 'is-muted'
  };
  async function refresh() {
    if (stopped || inFlight || document.visibilityState !== 'visible') return;
    inFlight = true;
    try {
      const response = await fetch(page.dataset.feed, {
        credentials: 'same-origin', cache: 'no-store', headers: {'Accept': 'application/json'}
      });
      if (response.status === 401 || response.status === 403) { stopped = true; return; }
      if (!response.ok) return;
      const data = await response.json();
      if (!Array.isArray(data.orders)) return;
      let changed = false;
      for (const item of data.orders) {
        if (!Number.isSafeInteger(item.id) || typeof item.status !== 'string'
          || !Object.prototype.hasOwnProperty.call(classes, item.status)
          || !Number.isSafeInteger(item.stage) || item.stage < 0 || item.stage > 6) continue;
        const card = page.querySelector('[data-progress-order="' + item.id + '"]');
        if (!card) { changed = true; continue; }
        const old = previous.get(item.id);
        if (old !== undefined && old !== item.status) changed = true;
        previous.set(item.id, item.status);
        const statusBadge = card.querySelector('[data-progress-status]');
        if (statusBadge) {
          statusBadge.textContent = typeof item.label === 'string' ? item.label : item.status;
          statusBadge.className = 'status ' + classes[item.status];
        }
        const mainLabel = card.querySelector('[data-progress-main-label]');
        if (mainLabel) mainLabel.textContent = typeof item.label === 'string' ? item.label : item.status;
        const count = card.querySelector('[data-progress-count]');
        if (count) count.textContent = item.stage + '/6 ขั้นตอน';
        const fill = card.querySelector('[data-progress-fill]');
        if (fill) fill.className = 'member-progress-track-fill stage-' + item.stage;
        const bar = card.querySelector('[role="progressbar"]');
        if (bar) bar.setAttribute('aria-valuenow', String(item.stage));
        card.querySelectorAll('[data-progress-step]').forEach(node => {
          const step = Number(node.dataset.progressStep);
          node.classList.toggle('completed', step <= item.stage);
          node.classList.toggle('current', step === item.stage);
          const dot = node.querySelector('.member-step-dot');
          if (dot) dot.textContent = step < item.stage ? '✓' : String(step);
        });
        if (Array.isArray(item.services)) {
          for (const task of item.services) {
            if (!Number.isSafeInteger(task.id) || !['pending','working','done'].includes(task.status)) continue;
            const row = card.querySelector('[data-member-service-item="' + task.id + '"]');
            const label = row?.querySelector('[data-service-progress-label]');
            if (label) {
              label.textContent = typeof task.label === 'string' ? task.label : task.status;
              label.className = 'service-work-badge service-work-' + task.status;
            }
          }
        }
        const note = card.querySelector('[data-progress-guidance]');
        if (note) note.textContent = item.status === 'ready' && item.pickup_requested === true
          ? '✓ แจ้งขอรับผ้าแล้ว กรุณารอพนักงานยืนยันการส่งมอบที่ร้าน'
          : guidance[item.status];
      }
      if (changed) {
        let banner = document.getElementById('member-progress-changed');
        if (!banner) {
          banner = document.createElement('a');
          banner.id = 'member-progress-changed';
          banner.className = 'btn btn-light';
          banner.href = 'index.php?page=progress';
          banner.textContent = 'มีการเปลี่ยนสถานะหรือรายการใหม่ ดูความคืบหน้าล่าสุด';
          page.querySelector('.member-progress-hero-actions')?.prepend(banner);
        }
      }
    } catch (_) {
      // Retry at the next interval if the connection is unavailable.
    } finally { inFlight = false; }
  }
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') refresh();
  });
  window.addEventListener('focus', refresh);
  window.setInterval(refresh, 30000);
  refresh();
})();
