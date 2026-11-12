<?php if (($_GET['account_removed'] ?? '') === '1'): ?><div class="alert" role="status">ลบบัญชีเรียบร้อยแล้ว ไม่สามารถเข้าสู่ระบบด้วยบัญชีเดิมได้อีก</div><?php endif; ?>
<section class="hero">
    <div class="hero-content"><p class="eyebrow">LAUNDRY MANAGEMENT MADE SIMPLE</p><h1>จัดการร้านซักรีด<br><span>เป็นระบบ ง่ายทุกขั้นตอน</span></h1><p>บันทึกรับผ้า ติดตามสถานะ และดูประวัติรายการผ่านเว็บไซต์เดียว</p><div class="hero-actions"><a class="btn btn-primary" href="<?= e(url('order-start')) ?>">สแกน QR / สร้างออร์เดอร์ →</a><a class="btn btn-light" href="<?= e(url('login')) ?>">เข้าสู่ระบบ</a><a class="btn btn-light" href="<?= e(url('register')) ?>">สมัครสมาชิก</a></div></div>
    <div class="hero-graphic"><div class="graphic-ring"></div><span>✳</span><strong>LaundryTrack</strong><small>YOUR LAUNDRY, TRACKED.</small></div>
</section>
<section class="how-it-works" aria-labelledby="usage-title">
  <div class="section-heading"><h2 id="usage-title">ใช้บริการซักรีดง่าย ๆ ใน 3 ขั้นตอน</h2><p>ไม่ว่าจะเป็นสมาชิกหรือผู้ใช้บริการหน้าร้าน ก็ติดตามสถานะได้สะดวกบนมือถือ</p></div>
  <div class="usage-steps">
    <article class="usage-step"><span class="usage-step-number" aria-hidden="true">1</span><strong>เลือกบริการ</strong><p>สร้างออร์เดอร์ผ่านเว็บไซต์ หรือให้พนักงานบันทึกที่ร้าน</p></article>
    <article class="usage-step"><span class="usage-step-number" aria-hidden="true">2</span><strong>นำผ้ามาส่งและติดตาม</strong><p>พนักงานตรวจรับผ้า แล้วดูสถานะผ่านบัญชีหรือ QR Code ได้</p></article>
    <article class="usage-step"><span class="usage-step-number" aria-hidden="true">3</span><strong>รับผ้าที่ร้าน</strong><p>เมื่อผ้าพร้อมรับ ให้แจ้งขอรับผ้าและรอพนักงานยืนยันส่งมอบจริง</p></article>
  </div>
</section>
<section class="features-grid"><article class="feature"><div class="feature-icon">▤</div><h3>รับผ้าและจัดการออร์เดอร์</h3><p>พนักงานบันทึกบริการ จำนวน และค่าบริการได้ง่าย</p></article><article class="feature"><div class="feature-icon">↗</div><h3>ติดตามสถานะ</h3><p>ลูกค้าเช็กความคืบหน้าได้โดยไม่ต้องโทรถาม</p></article><article class="feature"><div class="feature-icon">▦</div><h3>สรุปข้อมูลใน Dashboard</h3><p>ดูสถานะและยอดค่าบริการทั้งหมดในหน้าเดียว</p></article></section>
