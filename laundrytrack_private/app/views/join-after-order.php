<?php
if (current_user()) go('dashboard');
$pending = $_SESSION['new_self_order'] ?? null;
$ready = is_array($pending) && is_int($pending['order_id'] ?? null)
    && is_string($pending['token_hash'] ?? null);
?>
<?php if (!$ready): ?>
<div class="empty-state"><h1>กรุณาสร้างออร์เดอร์ก่อน</h1><a class="btn btn-primary" href="<?= e(url('order-start')) ?>">เริ่มต้นใหม่</a></div>
<?php else: ?>
<div class="auth-layout">
    <section class="auth-intro"><p class="eyebrow">JOIN LAUNDRYTRACK</p><h1>สมัครสมาชิก<br><span>หลังสร้างออร์เดอร์</span></h1>
        <p>ออร์เดอร์ที่เพิ่งสร้างจะเชื่อมกับบัญชีใหม่ของคุณทันที</p>
    </section>
    <section class="form-panel auth-panel">
        <h2>สร้างบัญชีสมาชิก</h2>
        <form method="post" action="index.php" class="form-stack">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="self-order-join">
            <label>อีเมล<input type="email" name="email" required maxlength="150" autocomplete="email"></label>
            <label>รหัสผ่าน (อย่างน้อย 8 ตัวอักษร)<input type="password" name="password" required minlength="8" maxlength="4096" autocomplete="new-password"></label>
            <button class="btn btn-primary btn-block" type="submit">สมัครสมาชิกและเก็บออร์เดอร์</button>
        </form>
        <p class="auth-footer"><a href="<?= e(url('order-complete')) ?>">ยังไม่สมัคร กลับไปดู QR Code</a></p>
    </section>
</div>
<?php endif; ?>
