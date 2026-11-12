<?php if (($_GET['account_removed'] ?? '') === '1'): ?><div class="alert" role="status">✓ ลบบัญชีแล้ว</div><?php endif; ?>
<section class="hero">
    <div class="hero-content">
      <p class="eyebrow">LAUNDRYTRACK</p>
      <h1>สั่งซักง่าย<br><span>ติดตามได้ทุกขั้นตอน</span></h1>
      <p>🧺 สั่งซัก · 📍 ติดตาม · ✅ รับผ้า</p>
      <div class="hero-actions">
        <a class="btn btn-primary" href="<?= e(url('order-start')) ?>"><span aria-hidden="true">🧺</span> เริ่มสั่งซัก</a>
        <a class="btn btn-light" href="<?= e(url('login')) ?>"><span aria-hidden="true">👤</span> เข้าสู่ระบบ</a>
        <a class="btn btn-light" href="<?= e(url('register')) ?>"><span aria-hidden="true">➕</span> สมัครสมาชิก</a>
      </div>
    </div>
    <div class="hero-graphic" aria-hidden="true"><div class="graphic-ring"></div><span>🫧</span><strong>LaundryTrack</strong><small>WASH · TRACK · PICK UP</small></div>
</section>
<section class="how-it-works" aria-labelledby="usage-title">
  <div class="section-heading"><h2 id="usage-title">3 ขั้นตอนง่าย ๆ</h2></div>
  <div class="usage-steps">
    <article class="usage-step"><span class="usage-step-number" aria-hidden="true">🧺</span><strong>1. เลือกบริการ</strong><p>เลือกซัก อบ หรือรีด</p></article>
    <article class="usage-step"><span class="usage-step-number" aria-hidden="true">📍</span><strong>2. ส่งผ้า · ติดตาม</strong><p>ส่งที่ร้าน ดูสถานะผ่านเว็บหรือ QR</p></article>
    <article class="usage-step"><span class="usage-step-number" aria-hidden="true">✅</span><strong>3. รับผ้า</strong><p>รับที่ร้าน พนักงานยืนยันส่งมอบจริง</p></article>
  </div>
</section>
<section class="features-grid" aria-label="สิ่งที่ทำได้">
  <article class="feature"><div class="feature-icon" aria-hidden="true">🫧</div><h3>บริการซักรีด</h3><p>เลือกหลายบริการได้</p></article>
  <article class="feature"><div class="feature-icon" aria-hidden="true">📦</div><h3>ติดตามแต่ละงาน</h3><p>เช็กสถานะได้ทันที</p></article>
  <article class="feature"><div class="feature-icon" aria-hidden="true">🔔</div><h3>แจ้งพร้อมรับ</h3><p>รู้เมื่อผ้าพร้อมรับ</p></article>
</section>
