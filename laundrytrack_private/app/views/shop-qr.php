<?php
require_admin();
$shopOrderUrl = shop_order_link();
?>
<div class="page-heading">
  <div>
    <p class="eyebrow">STORE SELF-SERVICE</p>
    <h1>QR Code และลิงก์ประจำร้าน</h1>
    <p>ให้ลูกค้าสแกนเพื่อเลือกระหว่าง Member และ Guest ก่อนสร้างออร์เดอร์</p>
  </div>
  <a class="btn btn-light" href="<?= e(url('dashboard')) ?>">← กลับหน้า Dashboard</a>
</div>
<section class="panel shop-qr-page">
  <div class="panel-header"><div>
    <h2>QR Code สำหรับเริ่มสั่งบริการ</h2>
    <p>ใช้ QR ประจำร้านนี้ร่วมกันได้ ไม่ใช่ QR ลับของออร์เดอร์ลูกค้า</p>
  </div></div>
  <div class="shop-qr-layout">
    <div class="shop-qr-preview" data-qr-link="<?= e($shopOrderUrl) ?>">
      <div id="order-qr-code" aria-label="QR Code สำหรับเปิดหน้า Member และ Guest"></div>
    </div>
    <div class="shop-qr-instructions">
      <h3>วิธีใช้งาน</h3>
      <p>นำ QR Code นี้ติดที่เคาน์เตอร์ ลูกค้าสแกนเพื่อเข้าหน้าเลือก Member หรือ Guest ได้ทันที</p>
      <label class="shop-qr-link-label" for="order-tracking-url">ลิงก์เริ่มสั่งบริการ
        <input id="order-tracking-url" type="text" readonly value="<?= e($shopOrderUrl) ?>">
      </label>
      <div class="shop-qr-actions">
        <button class="btn btn-primary" type="button" id="copy-order-tracking-url">คัดลอกลิงก์ร้าน</button>
        <a class="btn btn-light" href="<?= e(url('order-start')) ?>">เปิดหน้าสำหรับลูกค้า</a>
      </div>
      <p class="muted">QR สำหรับติดตามออร์เดอร์รายคนจะแยกอยู่ในหน้ารายละเอียดออร์เดอร์ และอาจเปลี่ยนเมื่อสร้างลิงก์ใหม่</p>
    </div>
  </div>
</section>
