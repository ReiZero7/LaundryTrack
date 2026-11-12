<?php if (($_GET['account_removed'] ?? '') === '1'): ?><div class="alert" role="status">✓ ลบบัญชีแล้ว</div><?php endif; ?>
<div class="ocean-landing">
    <section class="hero ocean-hero" aria-labelledby="ocean-hero-title">
        <div class="hero-content ocean-hero-copy">
            <p class="ocean-hero-kicker"><span aria-hidden="true">🫧</span> บริการซักรีดที่ใช้ง่าย</p>
            <h1 id="ocean-hero-title">ซักง่าย<br><span>สบายทุกวัน</span></h1>
            <p class="ocean-hero-subtitle">🧺 สั่งซัก &nbsp;·&nbsp; 📍 ติดตาม &nbsp;·&nbsp; ✅ รับผ้า</p>
            <div class="hero-actions ocean-hero-actions">
                <a class="btn btn-primary ocean-cta" href="<?= e(url('order-start')) ?>"><span aria-hidden="true">🧺</span> เริ่มสั่งซัก <span aria-hidden="true">→</span></a>
                <a class="btn btn-light ocean-cta-secondary" href="#steps"><span aria-hidden="true">✨</span> ดูวิธีใช้งาน</a>
            </div>
            <p class="ocean-dropoff-note"><span aria-hidden="true">📍</span> สั่งผ่านเว็บ แล้วนำผ้ามาส่งที่ร้าน</p>
        </div>
        <div class="ocean-hero-scene" aria-hidden="true">
            <div class="ocean-bubble ocean-bubble-one"></div>
            <div class="ocean-bubble ocean-bubble-two"></div>
            <div class="ocean-bubble ocean-bubble-three"></div>
            <div class="ocean-floating-badge ocean-badge-one">🫧 สะอาดสดชื่น</div>
            <div class="ocean-floating-badge ocean-badge-two">✅ พร้อมรับผ้า</div>
            <div class="ocean-washer">
                <div class="ocean-washer-controls">
                    <span class="ocean-washer-brand">LaundryTrack</span>
                    <span class="ocean-washer-display"></span>
                    <span class="ocean-washer-knob"></span>
                </div>
                <div class="ocean-washer-door">
                    <div class="ocean-washer-water"><span>🫧</span><span>🫧</span></div>
                </div>
                <div class="ocean-washer-feet"></div>
            </div>
            <span class="ocean-laundry-basket">🧺</span>
            <span class="ocean-sparkle ocean-sparkle-one">✦</span>
            <span class="ocean-sparkle ocean-sparkle-two">✦</span>
        </div>
    </section>

    <div class="ocean-promise-strip" aria-label="จุดเด่นของ LaundryTrack">
        <span>🧼 เลือกบริการง่าย</span>
        <span>📱 ดูสถานะผ่านเว็บ</span>
        <span>🔔 แจ้งเมื่อพร้อมรับ</span>
    </div>

    <section class="how-it-works ocean-section" id="steps" aria-labelledby="usage-title">
        <div class="ocean-section-heading">
            <div><p class="ocean-section-kicker">HOW IT WORKS</p><h2 id="usage-title">3 ขั้นตอนง่าย ๆ</h2></div>
            <p>ทุกขั้นตอน จบในเว็บเดียว</p>
        </div>
        <div class="usage-steps">
            <article class="usage-step ocean-step">
                <span class="usage-step-number" aria-hidden="true">🧺</span>
                <small>STEP 01</small>
                <strong>เลือกบริการ</strong>
                <p>ซัก อบ หรือรีด</p>
            </article>
            <article class="usage-step ocean-step">
                <span class="usage-step-number" aria-hidden="true">🫧</span>
                <small>STEP 02</small>
                <strong>ส่งผ้า · ติดตาม</strong>
                <p>นำผ้ามาที่ร้าน เช็กงานผ่าน QR</p>
            </article>
            <article class="usage-step ocean-step">
                <span class="usage-step-number" aria-hidden="true">✅</span>
                <small>STEP 03</small>
                <strong>รับผ้า</strong>
                <p>พนักงานยืนยันส่งมอบจริง</p>
            </article>
        </div>
    </section>

    <section class="ocean-section ocean-services-section" id="services" aria-labelledby="ocean-services-title">
        <div class="ocean-section-heading">
            <div><p class="ocean-section-kicker">OUR SERVICES</p><h2 id="ocean-services-title">บริการซักรีด</h2></div>
            <a class="ocean-section-link" href="<?= e(url('order-start')) ?>">เลือกบริการ <span aria-hidden="true">→</span></a>
        </div>
        <div class="ocean-services-grid">
            <article class="ocean-service-card ocean-service-wash">
                <span class="ocean-service-art" aria-hidden="true">🫧</span>
                <h3>ซักผ้า</h3><p>สะอาด สดชื่น</p>
            </article>
            <article class="ocean-service-card ocean-service-dry">
                <span class="ocean-service-art" aria-hidden="true">🌬️</span>
                <h3>อบผ้า</h3><p>แห้ง พร้อมใช้</p>
            </article>
            <article class="ocean-service-card ocean-service-iron">
                <span class="ocean-service-art" aria-hidden="true">👔</span>
                <h3>รีดผ้า</h3><p>เรียบ เป็นระเบียบ</p>
            </article>
        </div>
        <p class="ocean-service-note">* บริการที่เลือกได้และราคาจริงแสดงในหน้าสั่งซัก</p>
    </section>

    <section class="ocean-status-section" id="status-example" aria-labelledby="ocean-status-title">
        <div class="ocean-status-copy">
            <p class="ocean-section-kicker">TRACK YOUR LAUNDRY</p>
            <h2 id="ocean-status-title">รู้สถานะผ้า<br><span>ทุกขั้นตอน</span></h2>
            <p>สั่งแล้วติดตามได้ผ่านบัญชีหรือ QR ของออร์เดอร์</p>
            <a class="btn btn-primary" href="<?= e(url('login')) ?>">📍 เข้าสู่ระบบเพื่อติดตาม <span aria-hidden="true">→</span></a>
        </div>
        <div class="ocean-status-card">
            <div class="ocean-status-card-head">
                <span>🧾 ตัวอย่างขั้นตอนการซัก</span>
                <span class="ocean-demo-tag">ตัวอย่าง</span>
            </div>
            <ol class="ocean-status-flow">
                <li><span aria-hidden="true">📥</span><strong>รับผ้า</strong></li>
                <li><span aria-hidden="true">🫧</span><strong>กำลังซัก</strong></li>
                <li><span aria-hidden="true">🌬️</span><strong>กำลังอบ</strong></li>
                <li><span aria-hidden="true">✅</span><strong>พร้อมรับ</strong></li>
            </ol>
            <p class="ocean-status-footnote">สถานะจริงของคุณจะแสดงในหน้าติดตามงาน</p>
        </div>
    </section>

    <section class="ocean-last-cta" aria-label="เริ่มใช้ LaundryTrack">
        <div><strong>🧺 พร้อมสั่งซักแล้วหรือยัง?</strong><p>เริ่มได้ทั้ง Guest และ Member</p></div>
        <a class="btn btn-primary" href="<?= e(url('order-start')) ?>">เริ่มสั่งซัก <span aria-hidden="true">→</span></a>
    </section>
</div>
