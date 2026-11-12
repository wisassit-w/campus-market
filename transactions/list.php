<?php
require_once __DIR__ . '/../includes/header.php';

requireLogin('../auth/login.php');

$user_id = $currentUserId;

$stmt = $pdo->prepare("
    SELECT t.*, p.title as product_title, p.seller_id,
    buyer.name as buyer_name, seller.name as seller_name
    FROM transactions t
    JOIN products p ON t.product_id = p.product_id
    JOIN users buyer ON t.buyer_id = buyer.user_id
    JOIN users seller ON p.seller_id = seller.user_id
    WHERE t.buyer_id = ? OR p.seller_id = ?
    ORDER BY t.created_at DESC
");
$stmt->execute([$user_id, $user_id]);
$transactions = $stmt->fetchAll();

// ปรับ query ให้ดึงเฉพาะ transaction_id ที่เกี่ยวข้องกับ transaction ของ user คนนี้เท่านั้น
// (เดิมดึง transaction_id ที่ถูกรีวิวไปแล้ว "ทั้งระบบ" ทุก user โดยไม่จำเป็น)
$txIds = array_column($transactions, 'transaction_id');
$reviewedIds = [];
if (!empty($txIds)) {
    $placeholders = implode(',', array_fill(0, count($txIds), '?'));
    $revStmt = $pdo->prepare("SELECT transaction_id FROM reviews WHERE transaction_id IN ($placeholders)");
    $revStmt->execute($txIds);
    $reviewedIds = $revStmt->fetchAll(PDO::FETCH_COLUMN);
}

$statusText = ['pending' => 'รอยืนยัน', 'confirmed' => 'ยืนยันแล้ว', 'completed' => 'สำเร็จ', 'cancelled' => 'ยกเลิก'];
$csrf = csrfToken();
?>

<h2>การนัดรับสินค้า</h2>

<?php if (($_GET['error'] ?? '') === 'insufficient_credit'): ?>
    <p class="error-msg" style="max-width:640px;">⚠️ ยอดเครดิตไม่พอสำหรับทำรายการนี้ กรุณาเติมเครดิตก่อน หรือเปลี่ยนไปจ่ายเงินสดแทน</p>
<?php endif; ?>

<?php if (empty($transactions)): ?>
    <div class="chat-empty">
        <p>📦 ยังไม่มีรายการนัดรับสินค้า</p>
        <p style="font-size:14px; margin-top:8px;">เมื่อคุณซื้อหรือขายสินค้าและนัดรับของ รายการจะแสดงที่นี่</p>
    </div>
<?php endif; ?>

<div style="display:flex; flex-direction:column; gap:14px; max-width:640px;">
    <?php foreach ($transactions as $t): ?>
        <div class="info-card">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px; margin-bottom:10px;">
                <p style="font-size:17px; font-weight:700;"><?= htmlspecialchars($t['product_title']) ?></p>
                <span class="status-badge status-<?= htmlspecialchars($t['status']) ?>"><?= htmlspecialchars($statusText[$t['status']] ?? $t['status']) ?></span>
            </div>

            <p style="font-size:13.5px; color:var(--text-muted); margin-bottom:3px;">
                👤 ผู้ซื้อ: <?= htmlspecialchars($t['buyer_name']) ?> &nbsp;·&nbsp; ผู้ขาย: <?= htmlspecialchars($t['seller_name']) ?>
            </p>
            <p style="font-size:13.5px; color:var(--text-muted); margin-bottom:3px;">
                📍 <?= htmlspecialchars($t['meetup_location']) ?>
            </p>
            <p style="font-size:13.5px; color:var(--text-muted); margin-bottom:14px;">
                🕐 <?= htmlspecialchars($t['meetup_time']) ?>
                &nbsp;·&nbsp; <?= $t['payment_method'] === 'wallet' ? '💰 จ่ายด้วยเครดิต' : '💵 จ่ายเงินสด' ?>
            </p>

            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <?php if ($t['status'] === 'pending' && $t['seller_id'] == $user_id): ?>
                    <form method="POST" action="update_status.php" class="action-form">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $t['transaction_id'] ?>">
                        <input type="hidden" name="status" value="confirmed">
                        <button type="submit" style="background:var(--hu-green); color:#fff; padding:9px 20px; border-radius:999px; font-weight:700; font-size:13.5px; border:none; cursor:pointer;">
                            ยืนยันนัดหมาย
                        </button>
                    </form>
                    <form method="POST" action="update_status.php" class="action-form" data-confirm="ยกเลิกนัดหมายนี้?">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $t['transaction_id'] ?>">
                        <input type="hidden" name="status" value="cancelled">
                        <button type="submit" style="background:#fff; color:#C0392B; border:1.5px solid #F0B8B8; padding:8px 20px; border-radius:999px; font-weight:700; font-size:13.5px; cursor:pointer;">
                            ยกเลิก
                        </button>
                    </form>

                <?php elseif ($t['status'] === 'pending' && $t['buyer_id'] == $user_id): ?>
                    <form method="POST" action="update_status.php" class="action-form" data-confirm="ยกเลิกนัดหมายนี้?">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $t['transaction_id'] ?>">
                        <input type="hidden" name="status" value="cancelled">
                        <button type="submit" style="background:#fff; color:#C0392B; border:1.5px solid #F0B8B8; padding:8px 20px; border-radius:999px; font-weight:700; font-size:13.5px; cursor:pointer;">
                            ยกเลิก
                        </button>
                    </form>
                    <span style="color:var(--text-muted); font-size:13.5px; align-self:center;">รอผู้ขายยืนยัน</span>

                <?php elseif ($t['status'] === 'confirmed'): ?>
                    <form method="POST" action="update_status.php" class="action-form" data-confirm="ยืนยันว่ารับ-ส่งสินค้าเรียบร้อยแล้ว?">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $t['transaction_id'] ?>">
                        <input type="hidden" name="status" value="completed">
                        <button type="submit" style="background:var(--hu-green); color:#fff; padding:9px 20px; border-radius:999px; font-weight:700; font-size:13.5px; border:none; cursor:pointer;">
                            ทำรายการสำเร็จ
                        </button>
                    </form>
                    <a href="track.php?id=<?= (int) $t['transaction_id'] ?>"
                        style="background:#fff; color:var(--hu-green-dark); border:1.5px solid var(--hu-green); padding:8px 20px; border-radius:999px; text-decoration:none; font-weight:700; font-size:13.5px;">
                        📍 ติดตามตำแหน่ง
                    </a>

                <?php elseif ($t['status'] === 'completed'): ?>
                    <?php if ($t['buyer_id'] == $user_id): ?>
                        <?php if (in_array($t['transaction_id'], $reviewedIds)): ?>
                            <span style="background:var(--hu-green-light); color:var(--hu-green-dark); padding:8px 20px; border-radius:999px; font-weight:700; font-size:13.5px;">
                                ✅ รีวิวแล้ว
                            </span>
                        <?php else: ?>
                            <a href="../reviews/create.php?transaction_id=<?= (int) $t['transaction_id'] ?>"
                                style="background:#fff; color:#7A5A0A; border:1.5px solid var(--hu-gold); padding:8px 20px; border-radius:999px; text-decoration:none; font-weight:700; font-size:13.5px;">
                                ⭐ เขียนรีวิว
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="color:var(--text-muted); font-size:13.5px;">รายการนี้เสร็จสมบูรณ์แล้ว</span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
