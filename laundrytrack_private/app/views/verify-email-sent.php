<div class="auth-layout">
    <section class="auth-intro">
        <p class="eyebrow">CHECK YOUR EMAIL</p>
        <h1>อีกขั้นตอนเดียว<br><span>ก่อนเริ่มใช้งาน</span></h1>
        <p>ระบบจะเปิดให้สมาชิกใหม่เข้าสู่ระบบหลังยืนยันอีเมลเท่านั้น</p>
    </section>
    <section class="form-panel auth-panel">
        <h2>ตรวจสอบกล่องจดหมาย</h2>
        <p>หากระบบส่งอีเมลได้สำเร็จ คุณจะได้รับลิงก์ยืนยันที่ใช้ได้ภายใน 24 ชั่วโมง กรุณาตรวจสอบโฟลเดอร์สแปมด้วย</p>
        <p class="muted">หากไม่ได้รับอีเมล ให้ลองขอลิงก์ใหม่หลังผ่านไปอย่างน้อย 10 นาที หรือติดต่อร้าน</p>
        <a class="btn btn-primary btn-block" href="<?= e(url('resend-verification')) ?>">ขอลิงก์ยืนยันใหม่</a>
        <p class="auth-footer"><a href="<?= e(url('login')) ?>">กลับไปหน้าเข้าสู่ระบบ</a></p>
    </section>
</div>
