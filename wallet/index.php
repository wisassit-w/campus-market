<?php
require_once __DIR__ . '/../includes/header.php';

requireLogin('../auth/login.php');

$user_id = $currentUserId;

$balanceStmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE user_id = ?");
$balanceStmt->execute([$user_id]);
$balance = (float) $balanceStmt->fetchColumn();

$historyStmt = $pdo->prepare("
    (SELECT 'topup' as feed_type, request_id as ref_id, amount, status, requested_at as created_at, NULL as note
     FROM wallet_topup_requests WHERE user_id = ?)
    UNION ALL
    (SELECT type as feed_type, wallet_tx_id as ref_id, amount, 'done' as status, created_at, note
     FROM wallet_transactions WHERE user_id = ?)
    ORDER BY created_at DESC
    LIMIT 50
");
$historyStmt->execute([$user_id, $user_id]);
$history = $historyStmt->fetchAll();

$feedLabel = [
    'topup' => '💰 เติมเครดิต',
    'payment' => '🛒 จ่ายค่าสินค้า',
    'receive' => '💵 ได้รับเงินค่าขาย',
    'refund' => '↩️ คืนเงิน',
    'admin_adjust' => '🛠️ แอดมินปรับยอด',
];

$statusLabel = [
    'pending' => ['text' => 'รอตรวจสอบ', 'class' => 'status-pending'],
    'approved' => ['text' => 'อนุมัติแล้ว', 'class' => 'status-completed'],
    'rejected' => ['text' => 'ถูกปฏิเสธ', 'class' => 'status-cancelled'],
    'done' => ['text' => 'สำเร็จ', 'class' => 'status-completed'],
];
?>

<h2>H Wallet</h2>

<?php if (($_GET['topup'] ?? '') === 'success'): ?>
    <p class="success-msg" style="max-width:520px;">ส่งคำขอเติมเครดิตแล้ว รอแอดมินตรวจสอบภายใน 24 ชม.</p>
<?php endif; ?>

<div class="info-card" style="max-width:420px; text-align:center; background:linear-gradient(135deg, var(--hu-green-dark), var(--hu-green)); border:none; color:#fff; padding:28px;">
    <p style="font-size:13.5px; opacity:0.85; margin-bottom:6px;">ยอดเครดิตคงเหลือ</p>
    <p style="font-size:38px; font-weight:800; margin-bottom:18px;"><?= number_format($balance, 2) ?> <span style="font-size:16px; font-weight:600;">เครดิต</span></p>
    <a href="topup.php" style="display:inline-block; background:#fff; color:var(--hu-green-dark); padding:11px 30px; border-radius:999px; text-decoration:none; font-weight:700; font-size:14.5px;">
        + เติมเครดิต
    </a>
</div>

<h3>ประวัติการทำรายการ</h3>

<?php if (empty($history)): ?>
    <p style="color:var(--text-muted); font-size:14px;">ยังไม่มีประวัติการทำรายการ</p>
<?php endif; ?>

<div style="display:flex; flex-direction:column; gap:10px; max-width:520px;">
    <?php foreach ($history as $h): ?>
        <?php
            $isCredit = in_array($h['feed_type'], ['topup', 'receive', 'refund'], true);
            $sign = $isCredit ? '+' : '-';
            $color = $isCredit ? 'var(--hu-green-dark)' : '#C0392B';
            $st = $statusLabel[$h['status']] ?? ['text' => $h['status'], 'class' => ''];
        ?>
        <div class="info-card" style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px;">
            <div>
                <p style="font-weight:700; font-size:14.5px;"><?= htmlspecialchars($feedLabel[$h['feed_type']] ?? $h['feed_type']) ?></p>
                <p style="font-size:12.5px; color:var(--text-muted); margin-top:2px;">
                    <?= htmlspecialchars(date('d/m/Y H:i', strtotime($h['created_at']))) ?>
                    <?php if (!empty($h['note'])): ?> · <?= htmlspecialchars($h['note']) ?><?php endif; ?>
                </p>
            </div>
            <div style="text-align:right;">
                <p style="font-weight:800; font-size:15.5px; color:<?= $color ?>;"><?= $sign ?><?= number_format((float) $h['amount'], 2) ?></p>
                <span class="status-badge <?= htmlspecialchars($st['class']) ?>" style="margin-top:4px; display:inline-block;"><?= htmlspecialchars($st['text']) ?></span>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
