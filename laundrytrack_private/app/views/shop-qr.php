<?php
require_admin();
$shopOrderUrl = shop_order_link();
?>
<div class="page-heading">
  <div>
    <p class="eyebrow">สำหรับร้าน</p>
    <h1>▦ QR ร้าน</h1>
    <p>สแกนเพื่อเริ่มสั่งซัก</p>
  </div>
  <a class="btn btn-light" href="<?= e(url('dashboard')) ?>">← ภาพรวม</a>
</div>
<section class="panel shop-qr-page">
  <div class="panel-header"><div>
    <h2>📱 สแกนที่นี่</h2>
    <p>ใช้ร่วมกันได้ทุกคน</p>
  </div></div>
  <div class="shop-qr-layout">
    <div class="shop-qr-preview walkin-qr-output" data-qr-link="<?= e($shopOrderUrl) ?>">
      <div id="order-qr-code" aria-label="QR Code สำหรับเปิดหน้า Member และ Guest"></div>
    </div>
    <div class="shop-qr-instructions">
      <h3>📍 ติดที่เคาน์เตอร์</h3>
      <p>ลูกค้าเลือก Member หรือ Guest ได้เอง</p>
      <label class="shop-qr-link-label" for="order-tracking-url">🔗 ลิงก์สั่งซัก
        <input id="order-tracking-url" type="text" readonly value="<?= e($shopOrderUrl) ?>">
      </label>
      <div class="shop-qr-actions">
        <button class="btn btn-primary" type="button" id="copy-order-tracking-url">📋 คัดลอก</button>
        <a class="btn btn-light" href="<?= e(url('order-start')) ?>">↗ เปิดหน้าเริ่มต้น</a>
      </div>
      <p class="muted">🔒 QR นี้ใช้สั่งซัก ไม่ใช่ QR ติดตามออร์เดอร์</p>
    </div>
  </div>
</section>
