<?php
require_once __DIR__ . '/../includes/header.php';

requireLogin('../auth/login.php');

$transaction_id = intInput($_GET, 'transaction_id', 0) ?: intInput($_POST, 'transaction_id', 0);

$stmt = $pdo->prepare("
    SELECT t.*, p.seller_id
    FROM transactions t JOIN products p ON t.product_id = p.product_id
    WHERE t.transaction_id = ? AND t.buyer_id = ? AND t.status = 'completed'
");
$stmt->execute([$transaction_id, $currentUserId]);
$transaction = $stmt->fetch();

if (!$transaction) {
    echo "<div class='error-msg' style='max-width:560px; margin:0 auto;'>ไม่พบรายการที่สามารถรีวิวได้</div>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

// กันรีวิวซ้ำ: เช็กว่า transaction นี้ถูกรีวิวไปแล้วหรือยัง
$checkStmt = $pdo->prepare("SELECT COUNT(*) FROM reviews WHERE transaction_id = ?");
$checkStmt->execute([$transaction_id]);
$alreadyReviewed = $checkStmt->fetchColumn() > 0;

if ($alreadyReviewed) {
    echo "<div class='info-card' style='max-width:560px; text-align:center;'>
            <p style='font-size:17px; font-weight:700; margin-bottom:6px;'>✅ คุณรีวิวรายการนี้ไปแล้ว</p>
            <p style='color:var(--text-muted); font-size:14px;'>รีวิวได้ 1 ครั้งต่อ 1 รายการที่สำเร็จเท่านั้น</p>
            <a href='../transactions/list.php' style='display:inline-block; margin-top:14px; color:var(--hu-green); font-weight:700;'>← กลับไปหน้าการนัดรับของ</a>
          </div>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // ป้องกันการกดส่งซ้อน / race condition ที่ระดับ DB ด้วย unique constraint บน transaction_id อยู่แล้ว (schema.sql)
    // แต่ยังคงเช็กซ้ำที่ระดับ application เพื่อ UX ที่ดี พร้อม catch duplicate key เผื่อแข่งกันจริง ๆ
    $checkStmt->execute([$transaction_id]);
    if ($checkStmt->fetchColumn() > 0) {
        header("Location: ../transactions/list.php");
        exit;
    }

    $rating = intInput($_POST, 'rating', 0);
    $comment = trim($_POST['comment'] ?? '');
    $comment = mb_substr($comment, 0, 1000);

    if ($rating < 1 || $rating > 5) {
        $error = "กรุณาให้คะแนนระหว่าง 1-5";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO reviews (reviewer_id, reviewed_id, transaction_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$currentUserId, $transaction['seller_id'], $transaction_id, $rating, $comment !== '' ? $comment : null]);

            header("Location: ../transactions/list.php");
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                // ถูกรีวิวไปแล้วโดย request คู่ขนาน (unique constraint กันซ้ำที่ DB)
                header("Location: ../transactions/list.php");
                exit;
            }
            throw $e;
        }
    }
}
?>

<h2>เขียนรีวิวผู้ขาย</h2>
<?php if ($error): ?>
    <p class="error-msg"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>
<form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="transaction_id" value="<?= (int) $transaction_id ?>">

    <label>คะแนน (1-5)</label>
    <select name="rating" required>
        <option value="5">5 - ดีมาก</option>
        <option value="4">4 - ดี</option>
        <option value="3">3 - ปานกลาง</option>
        <option value="2">2 - แย่</option>
        <option value="1">1 - แย่มาก</option>
    </select>

    <label>ความคิดเห็น</label>
    <textarea name="comment" rows="4" maxlength="1000"></textarea>

    <button type="submit">ส่งรีวิว</button>
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>
