<?php
// Authenticated staff use the staff form; authenticated members already
// have a verified identity, so they continue directly to member self-order.
if (is_admin()) { go('new-order'); }
if (current_user()) { go('self-order'); }
?>
<div class="page-heading">
  <div>
    <p class="eyebrow">เริ่มสั่งบริการ</p>
    <h1>สั่งซักแบบไหน?</h1>
    <p>เลือกได้เลย 👇</p>
  </div>
</div>
<div class="self-start-options order-start-choices" aria-label="ประเภทผู้ใช้บริการ">
  <section class="panel self-start-card order-start-choice">
    <div class="self-start-icon" aria-hidden="true">👤</div>
    <h2>Member</h2>
    <p>มีบัญชีแล้ว · เก็บประวัติได้</p>
    <a class="btn btn-primary btn-block" href="<?= e(url('login', ['next'=>'self-order'])) ?>">👤 เข้าสู่ระบบ</a>
  </section>
  <section class="panel self-start-card order-start-choice">
    <div class="self-start-icon" aria-hidden="true">🧺</div>
    <h2>Guest</h2>
    <p>ไม่ต้องสมัคร · ติดตามผ่าน QR</p>
    <a class="btn btn-primary btn-block" href="<?= e(url('self-order')) ?>">🧺 สั่งซักเลย</a>
  </section>
</div>
