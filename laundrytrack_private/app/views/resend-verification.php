<div class="auth-layout">
    <section class="auth-intro">
        <p class="eyebrow">RESEND EMAIL</p>
        <h1>ขอลิงก์ยืนยัน<br><span>อีเมลใหม่</span></h1>
        <p>ใช้สำหรับบัญชีสมาชิกที่ยังไม่ได้ยืนยันอีเมลเท่านั้น</p>
    </section>
    <section class="form-panel auth-panel">
        <h2>ส่งลิงก์ยืนยันอีกครั้ง</h2>
        <p class="muted">ระบบจำกัดการส่งซ้ำไม่เกินหนึ่งครั้งทุก 10 นาที และไม่เกิน 5 ครั้งใน 24 ชั่วโมง</p>
        <form method="post" action="index.php" class="form-stack">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="resend-email-verification">
            <label>อีเมลที่ใช้สมัคร<input type="email" name="email" required maxlength="150" autocomplete="email"></label>
            <button type="submit" class="btn btn-primary btn-block">ขอส่งลิงก์ใหม่</button>
        </form>
        <p class="auth-footer"><a href="<?= e(url('login')) ?>">กลับหน้าเข้าสู่ระบบ</a></p>
    </section>
</div>
