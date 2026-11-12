<?php
// Authenticated staff use the staff form; authenticated members already
// have a verified identity, so they continue directly to member self-order.
if (is_admin()) { go('new-order'); }
if (current_user()) { go('self-order'); }
?>
<div class="page-heading">
  <div>
    <p class="eyebrow">START YOUR LAUNDRY ORDER</p>
    <h1>เลือกประเภทผู้ใช้</h1>
    <p>เริ่มต้นสั่งซักรีดด้วย Member หรือ Guest</p>
  </div>
</div>
<div class="self-start-options order-start-choices" aria-label="ประเภทผู้ใช้บริการ">
  <section class="panel self-start-card order-start-choice">
    <div class="self-start-icon" aria-hidden="true">👤</div>
    <h2>Member</h2>
    <p>มีบัญชีอยู่แล้ว? เข้าสู่ระบบเพื่อสั่งซัก ติดตามสถานะ และดูประวัติรายการของคุณ</p>
    <a class="btn btn-primary btn-block" href="<?= e(url('login', ['next'=>'self-order'])) ?>">เข้าสู่ระบบ Member</a>
  </section>
  <section class="panel self-start-card order-start-choice">
    <div class="self-start-icon" aria-hidden="true">🧺</div>
    <h2>Guest</h2>
    <p>ยังไม่มีบัญชี? สั่งบริการได้ทันทีโดยกรอกข้อมูลติดต่อ และติดตามออร์เดอร์ผ่าน QR Code</p>
    <a class="btn btn-primary btn-block" href="<?= e(url('self-order')) ?>">สั่งซักแบบ Guest</a>
  </section>
</div>
