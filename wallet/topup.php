<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/upload_helper.php';

requireLogin('../auth/login.php');

$user_id = $currentUserId;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $amount = $_POST['amount'] ?? '';

    if (!is_numeric($amount) || (float) $amount <= 0 || (float) $amount > 100000) {
        $error = "กรุณากรอกจำนวนเงินให้ถูกต้อง (สูงสุด 100,000 บาทต่อครั้ง)";
    } else {
        try {
            $slip_path = secureImageUpload($_FILES['slip'] ?? [], __DIR__ . '/../assets/uploads/', 'slip_' . $user_id . '_');

            $stmt = $pdo->prepare("INSERT INTO wallet_topup_requests (user_id, amount, slip_image, status) VALUES (?, ?, ?, 'pending')");
            $stmt->execute([$user_id, $amount, $slip_path]);

            header("Location: index.php?topup=success");
            exit;
        } catch (UploadException $e) {
            $error = $e->getMessage();
        }
    }
}
?>

<h2>เติมเครดิต H Wallet</h2>
<p style="color:var(--text-muted); font-size:14px; margin-top:-10px; margin-bottom:20px; max-width:480px;">
    โอนเงินมาที่บัญชีของแอดมิน แนบสลิปพร้อมระบุจำนวนเงิน แอดมินจะตรวจสอบและเติมเครดิตให้ภายใน 24 ชม.
</p>

<div class="info-card" style="max-width:480px; background:var(--hu-gold-light); border:none;">
    <p style="font-size:13px; color:#7A5A0A; margin-bottom:4px;">โอนเงินมาที่</p>
    <p style="font-weight:700; font-size:16px; color:#7A5A0A;">พร้อมเพย์ 0XX-XXX-XXXX (ชื่อแอดมิน)</p>
</div>

<?php if ($error): ?>
    <p class="error-msg" style="max-width:480px;"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" style="max-width:480px;">
    <?= csrfField() ?>
    <label>จำนวนเงินที่โอน (บาท)</label>
    <input type="number" name="amount" step="0.01" min="1" max="100000" required value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>">

    <label>แนบสลิปโอนเงิน (ไม่บังคับ, JPG/PNG/WEBP/GIF ไม่เกิน 5MB)</label>
    <input type="file" name="slip" accept="image/png,image/jpeg,image/webp,image/gif">

    <button type="submit">ส่งคำขอเติมเครดิต</button>
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>
