<?php
// Confirm by POST, not by GET: link scanners must not consume tokens.
$token = $_GET['token'] ?? '';
$validToken = is_string($token) && email_verification_hash($token) !== null;
?>
<div class="auth-layout">
    <section class="auth-intro">
        <p class="eyebrow">EMAIL VERIFICATION</p>
        <h1>ยืนยันอีเมล<br><span>ก่อนใช้งานบัญชี</span></h1>
        <p>เพื่อป้องกันการนำอีเมลของผู้อื่นมาสมัครสมาชิก กรุณายืนยันว่าอีเมลนี้เป็นของคุณ</p>
    </section>
    <section class="form-panel auth-panel">
        <h2>ยืนยันบัญชี LaundryTrack</h2>
        <?php if (!$validToken): ?>
            <p class="muted">ลิงก์ไม่ถูกต้อง กรุณาขอลิงก์ยืนยันใหม่</p>
            <a class="btn btn-primary btn-block" href="<?= e(url('resend-verification')) ?>">ขอลิงก์ใหม่</a>
        <?php elseif (current_user()): ?>
            <p class="muted">กรุณาออกจากระบบก่อนยืนยันบัญชีอื่น</p>
        <?php else: ?>
            <p>กดปุ่มด้านล่างเพื่อยืนยันอีเมลและเปิดใช้งานบัญชี ลิงก์มีอายุ 24 ชั่วโมงและใช้ได้ครั้งเดียว</p>
            <form method="post" action="index.php" class="form-stack">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="email-verify">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <button class="btn btn-primary btn-block" type="submit">ยืนยันอีเมลและเปิดใช้งานบัญชี</button>
            </form>
        <?php endif; ?>
    </section>
</div>
