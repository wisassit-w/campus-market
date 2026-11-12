<?php
require_once __DIR__ . '/../includes/header.php';

requireLogin('../auth/login.php');

$chat_id = intInput($_GET, 'chat_id', 0) ?: intInput($_POST, 'chat_id', 0);
$stmt = $pdo->prepare("SELECT * FROM chats WHERE chat_id = ? AND (buyer_id = ? OR seller_id = ?)");
$stmt->execute([$chat_id, $currentUserId, $currentUserId]);
$chat = $stmt->fetch();

if (!$chat) {
    echo "<p class='error-msg' style='max-width:640px; margin:0 auto;'>ไม่มีสิทธิ์เข้าถึง</p>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

// เฉพาะผู้ซื้อเท่านั้นที่นัดหมายได้ (ของเดิมอนุญาตทั้งผู้ซื้อ/ผู้ขาย ทำให้ผู้ขายนัดหมายแทนผู้ซื้อได้เอง)
if ($chat['buyer_id'] != $currentUserId) {
    echo "<p class='error-msg' style='max-width:640px; margin:0 auto;'>เฉพาะผู้ซื้อเท่านั้นที่สร้างการนัดหมายได้</p>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$productStmt = $pdo->prepare("SELECT title, price, status FROM products WHERE product_id = ?");
$productStmt->execute([$chat['product_id']]);
$product = $productStmt->fetch();

$balanceStmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE user_id = ?");
$balanceStmt->execute([$chat['buyer_id']]);
$buyerBalance = (float) $balanceStmt->fetchColumn();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $location = trim($_POST['location'] ?? '');
    $timeRaw = $_POST['meetup_time'] ?? '';
    $payment_method = ($_POST['payment_method'] ?? 'cash') === 'wallet' ? 'wallet' : 'cash';

    // แก้ช่องโหว่: เดิมไม่ validate รูปแบบ meetup_time เลย ทำให้ผู้ใช้ส่งค่าใด ๆ ก็ได้ (ไม่จำเป็นต้องเป็น datetime จริง)
    // เข้าไปเก็บใน DB แล้วถูก echo ออกมาแบบไม่ escape ในหน้า list.php/track.php => Stored XSS
    // จึงตรวจสอบรูปแบบวันที่ให้เข้มงวด และในหน้าที่แสดงผลก็ escape ด้วย htmlspecialchars เป็นการป้องกัน 2 ชั้น
    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $timeRaw);
    $validDateTime = $dt && $dt->format('Y-m-d\TH:i') === $timeRaw;

    if (!$product || $product['status'] !== 'available') {
        $error = "สินค้านี้ไม่พร้อมสำหรับนัดหมายแล้ว";
    } elseif ($location === '' || mb_strlen($location) > 255) {
        $error = "กรุณากรอกสถานที่นัดรับให้ถูกต้อง";
    } elseif (!$validDateTime || $dt < new DateTime()) {
        $error = "กรุณาเลือกวันเวลานัดหมายที่ถูกต้องและเป็นเวลาในอนาคต";
    } else {
        // กันสร้างนัดหมายซ้ำซ้อนสำหรับสินค้าเดียวกัน
        $dupStmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE product_id = ? AND buyer_id = ? AND status IN ('pending','confirmed')");
        $dupStmt->execute([$chat['product_id'], $chat['buyer_id']]);
        if ($dupStmt->fetchColumn() > 0) {
            $error = "มีการนัดหมายที่ยังไม่เสร็จสิ้นสำหรับสินค้านี้อยู่แล้ว";
        } else {
            $stmt = $pdo->prepare("INSERT INTO transactions (product_id, buyer_id, meetup_location, meetup_time, status, payment_method) VALUES (?, ?, ?, ?, 'pending', ?)");
            $stmt->execute([$chat['product_id'], $chat['buyer_id'], $location, $dt->format('Y-m-d H:i:s'), $payment_method]);

            header("Location: list.php");
            exit;
        }
    }
}
?>

<h2>นัดรับสินค้า</h2>

<?php if ($error): ?>
    <p class="error-msg" style="max-width:560px;"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<?php if ($product): ?>
    <div class="info-card" style="max-width:560px;">
        <p style="font-size:13px; color:var(--text-muted);">สินค้า</p>
        <p style="font-weight:700; font-size:16px;"><?= htmlspecialchars($product['title']) ?> — <?= number_format((float) $product['price'], 2) ?> บาท</p>
    </div>
<?php endif; ?>

<form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="chat_id" value="<?= (int) $chat_id ?>">

    <label>สถานที่นัดรับ</label>
    <input type="text" name="location" placeholder="เช่น หน้าตึกคณะ..." required maxlength="255">

    <label>วันเวลานัด</label>
    <input type="datetime-local" name="meetup_time" required>

    <label>วิธีชำระเงิน</label>
    <div style="display:flex; gap:10px; margin-top:8px;">
        <label style="flex:1; display:flex; align-items:center; gap:8px; border:1.5px solid var(--border); border-radius:var(--radius-sm); padding:12px 14px; cursor:pointer; font-weight:600; font-size:14px; margin-top:0; background:var(--bg);">
            <input type="radio" name="payment_method" value="cash" checked style="width:auto; margin:0;">
            💵 เงินสด
        </label>
        <label style="flex:1; display:flex; align-items:center; gap:8px; border:1.5px solid var(--border); border-radius:var(--radius-sm); padding:12px 14px; cursor:pointer; font-weight:600; font-size:14px; margin-top:0; background:var(--bg);">
            <input type="radio" name="payment_method" value="wallet" style="width:auto; margin:0;">
            💰 เครดิต (คงเหลือ <?= number_format($buyerBalance, 0) ?>)
        </label>
    </div>

    <button type="submit">ยืนยันการนัดหมาย</button>
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>
