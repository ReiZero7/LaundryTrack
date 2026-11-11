<?php
if (current_user()) go('dashboard');
$pending = membership_claim();
$ready = $pending !== null;
?>
<?php if (!$ready): ?>
<div class="empty-state"><h1>ไม่พบออร์เดอร์ที่เชื่อมกับบัญชีได้</h1><a class="btn btn-primary" href="<?= e(url('order-start')) ?>">เริ่มต้นใหม่</a></div>
<?php else: ?>
<div class="auth-layout">
    <section class="auth-intro"><p class="eyebrow">JOIN LAUNDRYTRACK</p><h1>สมัครสมาชิก<br><span>เก็บประวัติการซักรีด</span></h1>
        <p>เมื่อยืนยันอีเมลสำเร็จ ออร์เดอร์นี้จะถูกเชื่อมกับบัญชีสมาชิกใหม่ โดย QR ติดตามเดิมยังใช้งานได้จนกว่าจะยืนยันสำเร็จ</p>
    </section>
    <section class="form-panel auth-panel">
        <h2>สร้างบัญชีสมาชิก</h2>
        <form method="post" action="index.php" class="form-stack">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="self-order-join">
            <label>อีเมล<input type="email" name="email" required maxlength="150" autocomplete="email"></label>
            <label>รหัสผ่าน (อย่างน้อย 8 ตัวอักษร)<input type="password" name="password" required minlength="8" maxlength="4096" autocomplete="new-password"></label>
            <button class="btn btn-primary btn-block" type="submit">สมัครสมาชิกและรับอีเมลยืนยัน</button>
        </form>
        <p class="auth-footer"><a href="<?= e(url($pending['source'] === 'guest' ? 'track-portal' : 'order-complete')) ?>">ยังไม่สมัคร กลับไปติดตามออร์เดอร์</a></p>
    </section>
</div>
<?php endif; ?>
