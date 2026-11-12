<?php
if (is_admin()) { go('new-order'); }
$services = db()->query("SELECT id,name,unit,price FROM laundry__services_tbl WHERE is_active=1 ORDER BY name")->fetchAll();
$defaultDue = date('Y-m-d\\TH:i', strtotime('+2 days'));
?>
<div class="page-heading"><div>
    <p class="eyebrow">🧺 สั่งบริการ</p>
    <h1>เลือกบริการซักรีด</h1>
    <p><?= current_user() ? '👤 สมาชิก' : '👤 Guest · ไม่ต้องสมัครสมาชิก' ?></p>
</div><a class="btn btn-light" href="<?= e(url('order-start')) ?>">← กลับ</a></div>
<section class="panel order-form-panel">
<form method="post" action="index.php" id="new-order-form" class="form-stack">
<?= csrf_field() ?>
<input type="hidden" name="action" value="self-order-create">
<p class="form-help" role="note">🧺 เลือกบริการ → ✅ ยืนยัน → 📍 นำผ้ามาส่งที่ร้าน (ยังไม่ถือว่าร้านรับผ้า)</p>
<div class="anti-bot-field" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
<?php if (current_user()): ?>
    <div class="order-self-identity">👤 สั่งในชื่อ <strong><?= e(current_user()['full_name']) ?></strong></div>
<?php else: ?>
    <div class="form-grid">
        <label>ชื่อ–นามสกุล <input name="full_name" required minlength="2" maxlength="100" autocomplete="name" placeholder="ชื่อผู้สั่งบริการ"></label>
        <label>เบอร์โทรศัพท์ <input name="phone" required minlength="8" maxlength="20" type="tel" autocomplete="tel" placeholder="เบอร์โทรสำหรับติดต่อเรื่องผ้า"></label>
    </div>
<?php endif; ?>
<label>⏰ วันรับผ้าโดยประมาณ
    <input type="datetime-local" name="due_at" required value="<?= e($defaultDue) ?>">
</label>
<div class="section-heading"><h2>🫧 บริการ</h2><p>เลือกได้หลายรายการ</p></div>
<div id="order-items"><div class="order-item-row">
    <label>บริการ<select name="service_id[]" class="service-select" required>
        <option value="">-- เลือกบริการ --</option>
        <?php foreach ($services as $service): ?>
            <option value="<?= (int)$service['id'] ?>" data-price="<?= e((string)$service['price']) ?>"><?= e($service['name']) ?> (<?= money($service['price']) ?> บาท / <?= e($service['unit']) ?>)</option>
        <?php endforeach; ?>
    </select></label>
    <label>จำนวน<input name="quantity[]" class="quantity-input" type="number" required min="0.01" max="10000" step="0.01" value="1" inputmode="decimal"></label>
    <span class="line-total">฿0.00</span>
    <button type="button" class="btn btn-icon remove-row" aria-label="ลบรายการ">×</button>
</div></div>
<button type="button" id="add-order-item" class="btn btn-light btn-add">➕ เพิ่มบริการ</button>
<label>หมายเหตุ<textarea name="notes" maxlength="2000" rows="3" placeholder="เช่น รายละเอียดเพิ่มเติมเกี่ยวกับผ้า"></textarea></label>
<div class="order-totals"><span>💰 ยอดประมาณการ</span><strong id="order-total" role="status" aria-live="polite" aria-atomic="true">฿0.00</strong></div>
<p class="muted">📍 หลังสั่งแล้ว นำผ้ามาให้ร้านตรวจรับก่อนเริ่มซัก</p>
<button class="btn btn-primary" type="submit" <?= !$services ? 'disabled' : '' ?>>✅ ยืนยันสั่งซัก</button>
<?php if (!$services): ?><p class="muted">ขณะนี้ยังไม่มีบริการที่เปิดใช้งาน</p><?php endif; ?>
</form></section>
