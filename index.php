<?php
require_once __DIR__ . '/includes/header.php';

if ($isLoggedIn) {
    header("Location: products/list.php");
    exit;
}
?>

<div style="min-height:calc(100vh - 90px); display:flex; flex-direction:column; align-items:center; justify-content:center; padding:20px;">

    <div style="max-width:640px; text-align:center;">
        <div style="width:72px; height:72px; margin:0 auto 22px; border-radius:22px; background:var(--hu-green-light); display:flex; align-items:center; justify-content:center; font-size:34px;">🎓</div>

        <h1 style="font-size:34px; font-weight:800; color:var(--hu-green-dark); margin-bottom:14px; letter-spacing:-0.5px;">
            HU Market
        </h1>
        <p style="color:var(--text-muted); font-size:16px; margin-bottom:4px; line-height:1.8;">
            ตลาดนัดซื้อ-ขายของมือหนึ่ง/มือสองสำหรับนักศึกษามหาวิทยาลัยหาดใหญ่
        </p>
        <p style="color:var(--text-muted); font-size:16px; margin-bottom:36px; line-height:1.8;">
            ปลอดภัย ยืนยันตัวตนด้วยอีเมลมหาลัย นัดรับของง่าย
        </p>

        <div style="display:flex; gap:14px; justify-content:center; flex-wrap:wrap;">
            <a href="auth/login.php" style="background:var(--hu-green); color:#fff; padding:14px 36px; border-radius:999px; text-decoration:none; font-weight:700; box-shadow:0 6px 18px rgba(27,107,58,0.28);">
                เข้าสู่ระบบ
            </a>
            <a href="auth/register.php" style="background:#fff; color:var(--hu-green-dark); border:1.5px solid var(--hu-green); padding:14px 36px; border-radius:999px; text-decoration:none; font-weight:700;">
                สมัครสมาชิก
            </a>
        </div>
    </div>

    <div style="max-width:640px; margin-top:44px; display:flex; gap:14px; justify-content:center; flex-wrap:wrap;">
        <div style="background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:18px 22px; box-shadow:var(--shadow-sm); min-width:160px; text-align:center;">
            <div style="font-size:24px; margin-bottom:8px;">🛡️</div>
            <div style="font-size:13.5px; font-weight:700; color:var(--text-main);">ปลอดภัย</div>
            <div style="font-size:12px; color:var(--text-muted); margin-top:2px;">ยืนยันอีเมล @hu.ac.th</div>
        </div>
        <div style="background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:18px 22px; box-shadow:var(--shadow-sm); min-width:160px; text-align:center;">
            <div style="font-size:24px; margin-bottom:8px;">📍</div>
            <div style="font-size:13.5px; font-weight:700; color:var(--text-main);">นัดรับง่าย</div>
            <div style="font-size:12px; color:var(--text-muted); margin-top:2px;">ติดตามตำแหน่งเรียลไทม์</div>
        </div>
        <div style="background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:18px 22px; box-shadow:var(--shadow-sm); min-width:160px; text-align:center;">
            <div style="font-size:24px; margin-bottom:8px;">🏪</div>
            <div style="font-size:13.5px; font-weight:700; color:var(--text-main);">มีหน้าร้าน</div>
            <div style="font-size:12px; color:var(--text-muted); margin-top:2px;">รวมสินค้าทุกชิ้นในร้านเดียว</div>
        </div>
    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
