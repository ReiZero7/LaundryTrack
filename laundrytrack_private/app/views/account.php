<?php
require_login();
$me = current_user();
$stmt = db()->prepare("SELECT COUNT(*) FROM laundry__orders_tbl
    WHERE customer_id=? AND status NOT IN ('picked_up','cancelled')");
$stmt->execute([(int)$me['id']]);
$openOrderCount = (int)$stmt->fetchColumn();
$canDelete = !($me['role'] === 'customer' && $openOrderCount > 0);
?>
<div class="page-heading">
    <div><p class="eyebrow">ACCOUNT SETTINGS</p><h1>จัดการบัญชีของฉัน</h1>
      <p>เปลี่ยนรหัสผ่านหรือจัดการบัญชีของตัวเองได้ ทั้งลูกค้าและผู้ดูแลระบบ</p></div>
</div>
<div class="account-page-layout">
<section class="panel">
  <div class="panel-header"><h2>ข้อมูลบัญชี</h2></div>
  <div class="info-grid">
    <div><small>ชื่อบัญชี</small><strong><?= e($me['full_name']) ?></strong></div>
    <div><small>อีเมล</small><strong><?= e($me['email']) ?></strong></div>
    <div><small>ประเภท</small><strong><?= $me['role'] === 'admin' ? 'ผู้ดูแลระบบ' : 'ลูกค้า' ?></strong></div>
  </div>
  <p class="form-help">หากเปลี่ยนรหัสผ่าน อุปกรณ์อื่นที่เคยเข้าสู่ระบบไว้จะต้องเข้าสู่ระบบใหม่</p>
  <h2 class="account-section-title">เปลี่ยนรหัสผ่าน</h2>
  <form method="post" action="index.php" class="form-stack">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="account-password-change">
    <label>รหัสผ่านปัจจุบัน
      <input type="password" name="current_password" autocomplete="current-password" required maxlength="4096">
    </label>
    <label>รหัสผ่านใหม่ (อย่างน้อย 12 ตัวอักษร)
      <input type="password" name="new_password" autocomplete="new-password" minlength="12" maxlength="4096" required>
    </label>
    <label>ยืนยันรหัสผ่านใหม่
      <input type="password" name="confirm_password" autocomplete="new-password" minlength="12" maxlength="4096" required>
    </label>
    <button class="btn btn-primary" type="submit">บันทึกรหัสผ่านใหม่</button>
  </form>
</section>
<section class="panel account-delete-panel">
  <div class="panel-header"><h2>ลบบัญชีของฉัน</h2></div>
  <p>หลังยืนยันลบบัญชี จะไม่สามารถเข้าสู่ระบบด้วยบัญชีนี้ได้อีก และระบบจะนำชื่อ อีเมล เบอร์โทร และรหัสผ่านออกจากข้อมูลบัญชี</p>
  <p class="form-help">เพื่อรักษาความถูกต้องของรายการซักและประวัติการทำงาน ระบบอาจเก็บออร์เดอร์ ประวัติสถานะ และหมายเหตุที่เกี่ยวข้องไว้โดยเปลี่ยนข้อมูลบัญชีเป็น “บัญชีที่ลบแล้ว” การดำเนินการนี้ไม่ใช่การลบประวัติออร์เดอร์ทั้งหมด</p>
  <?php if ($me['role'] === 'customer' && $openOrderCount > 0): ?>
    <p class="account-warning" role="status">ยังมีออร์เดอร์ไม่สิ้นสุด <?= $openOrderCount ?> รายการ ต้องรับผ้าหรือยกเลิกออร์เดอร์ให้เรียบร้อยก่อนลบบัญชี</p>
  <?php elseif ($me['role'] === 'admin'): ?>
    <p class="account-warning">บัญชีแอดมินคนสุดท้ายจะไม่สามารถลบได้ เพื่อป้องกันร้านสูญเสียสิทธิ์เข้าจัดการระบบ</p>
  <?php endif; ?>
  <form method="post" action="index.php" class="form-stack">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="account-delete">
    <label>ยืนยันรหัสผ่านปัจจุบัน
      <input type="password" name="current_password" autocomplete="current-password" required maxlength="4096" <?= $canDelete ? '' : 'disabled' ?>>
    </label>
    <label class="dropoff-confirm-check account-delete-confirm">
      <input type="checkbox" name="delete_confirmed" value="yes" required <?= $canDelete ? '' : 'disabled' ?>>
      <span>ฉันเข้าใจว่าการลบบัญชีจะทำให้เข้าสู่ระบบด้วยบัญชีนี้ไม่ได้อีก และต้องการลบบัญชีของตัวเอง</span>
    </label>
    <button class="btn btn-danger" type="submit" <?= $canDelete ? '' : 'disabled' ?>>ยืนยันลบบัญชีอย่างถาวร</button>
  </form>
</section>
</div>
