<?php
$customers = db()->query('SELECT id,full_name,email FROM laundry__users_tbl WHERE role=\'customer\' ORDER BY full_name')->fetchAll();
$services = db()->query('SELECT id,name,unit,price FROM laundry__services_tbl WHERE is_active=1 ORDER BY name')->fetchAll();
$defaultDue = date('Y-m-d\TH:i', strtotime('+2 days'));
?>
<div class="page-heading"><div><p class="eyebrow">➕ สำหรับพนักงาน</p><h1>สร้างออร์เดอร์</h1><p>รับผ้าหน้าร้าน</p></div><a class="btn btn-light" href="<?= e(url('orders')) ?>">← รายการทั้งหมด</a></div>
<section class="panel order-form-panel"><form method="post" action="index.php" id="new-order-form" class="form-stack"><?= csrf_field() ?><input type="hidden" name="action" value="order-create"><p class="form-help" role="note">✅ ตรวจรับผ้าจริง → 👤 เลือกลูกค้า → 🫧 เลือกบริการ</p><div class="form-grid"><div class="customer-mode-picker">
    <label><input type="radio" name="customer_mode" value="walkin" checked> 👤 Guest</label>
    <label><input type="radio" name="customer_mode" value="existing"> 👤 Member</label>
    <div id="walkin-customer-fields" class="walkin-customer-fields">
        <label>ชื่อลูกค้า <input name="walkin_name" maxlength="100" minlength="2" placeholder="ชื่อสำหรับระบุออร์เดอร์" required></label>
        <label>เบอร์โทร (ไม่บังคับ) <input name="walkin_phone" maxlength="20" placeholder="ไม่จำเป็นต้องกรอก"></label>
        <p class="muted">▦ ระบบสร้าง QR ติดตามให้</p>
    </div>
    <div id="existing-customer-fields" hidden>
        <label>เลือกลูกค้าสมาชิก<select name="customer_id"><option value="">-- เลือกลูกค้า --</option><?php foreach ($customers as $c): ?><?php if ($c['email'] !== null): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['full_name']) ?> — <?= e($c['email']) ?></option><?php endif; ?><?php endforeach; ?></select></label>
    </div>
</div><label>⏰ วันรับผ้า<input type="datetime-local" name="due_at" required value="<?= e($defaultDue) ?>"></label></div><div class="section-heading"><h2>🫧 บริการ</h2><p>เพิ่มได้หลายรายการ</p></div><div id="order-items"><div class="order-item-row"><label>บริการ<select name="service_id[]" class="service-select" required><option value="">-- เลือกบริการ --</option><?php foreach ($services as $s): ?><option value="<?= (int)$s['id'] ?>" data-price="<?= e((string)$s['price']) ?>"><?= e($s['name']) ?> (<?= money($s['price']) ?> บาท / <?= e($s['unit']) ?>)</option><?php endforeach; ?></select></label><label>จำนวน<input type="number" name="quantity[]" class="quantity-input" required min="0.01" max="10000" step="0.01" value="1" inputmode="decimal"></label><span class="line-total">฿0.00</span><button type="button" class="btn btn-icon remove-row" aria-label="ลบรายการ">×</button></div></div><button type="button" id="add-order-item" class="btn btn-light btn-add">➕ เพิ่มบริการ</button><label>หมายเหตุ<textarea name="notes" maxlength="2000" rows="3" placeholder="รายละเอียดเพิ่มเติม เช่น สีถุงผ้า"></textarea></label><div class="order-totals"><span>💰 ยอดประมาณการ</span><strong id="order-total" role="status" aria-live="polite" aria-atomic="true">฿0.00</strong></div><button class="btn btn-primary" type="submit" <?= !$services ? 'disabled' : '' ?>>✅ บันทึกออร์เดอร์</button><?php if (!$services): ?><p class="muted">ต้องมีบริการที่เปิดใช้งานก่อนจึงจะสร้างออร์เดอร์ได้</p><?php endif; ?></form></section>
