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
