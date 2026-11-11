<?php
if (is_admin()) { go('new-order'); }
if (current_user()) { go('self-order'); }
?>
<div class="page-heading"><div>
<p class="eyebrow">LAUNDRYTRACK SELF-SERVICE</p>
<h1>ยินดีต้อนรับสู่ร้านซักรีด</h1>
<p>สแกน QR ของร้านหรือเปิดลิงก์นี้เพื่อเริ่มสร้างออร์เดอร์ได้ด้วยตัวเอง</p>
</div></div>
<div class="self-start-options">
<section class="panel self-start-card">
    <div class="self-start-icon">👤</div>
    <h2>มีบัญชีสมาชิกแล้ว</h2>
    <p>เข้าสู่ระบบเพื่อสร้างออร์เดอร์และดูประวัติการซักรีดในบัญชีของคุณ</p>
    <a class="btn btn-primary btn-block" href="<?= e(url('login', ['next' => 'self-order'])) ?>">เข้าสู่ระบบสมาชิก</a>
</section>
<section class="panel self-start-card">
    <div class="self-start-icon">🧺</div>
    <h2>สั่งบริการเลย ไม่ต้องสมัคร</h2>
    <p>กรอกชื่อ เบอร์โทร เลือกบริการ จากนั้นรับลิงก์ติดตามสถานะ และสมัครสมาชิกภายหลังได้</p>
    <a class="btn btn-primary btn-block" href="<?= e(url('self-order')) ?>">สร้างออร์เดอร์ทันที</a>
</section>
</div>
