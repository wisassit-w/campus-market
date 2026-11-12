<?php
require_once __DIR__ . '/../includes/header.php';

requireAdmin('../auth/login.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $request_id = intInput($_POST, 'request_id', 0);
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['approve', 'reject'], true) && $request_id > 0) {
        try {
            $pdo->beginTransaction();

            /**
             * แก้ช่องโหว่: โค้ดเดิม SELECT แถวคำขอโดยไม่ล็อก แล้วค่อยเช็ค status === 'pending'
             * ก่อน UPDATE — ถ้าแอดมินกดปุ่ม "อนุมัติ" สองครั้งติดกันเร็ว ๆ (หรือมีแอดมิน 2 คน
             * กดพร้อมกัน) ทั้งสอง request จะผ่านเงื่อนไข "status='pending'" พร้อมกันได้ (race condition)
             * ทำให้เติมเครดิตซ้ำสอง (double-credit) ใช้ FOR UPDATE ล็อกแถวนี้ไว้ตลอด transaction
             */
            $stmt = $pdo->prepare("SELECT * FROM wallet_topup_requests WHERE request_id = ? AND status = 'pending' FOR UPDATE");
            $stmt->execute([$request_id]);
            $req = $stmt->fetch();

            if ($req) {
                if ($action === 'approve') {
                    $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE user_id = ?")
                        ->execute([$req['amount'], $req['user_id']]);
                    $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, related_topup_id, note) VALUES (?, 'topup', ?, ?, 'เติมเครดิตอนุมัติโดยแอดมิน')")
                        ->execute([$req['user_id'], $req['amount'], $request_id]);
                    $pdo->prepare("UPDATE wallet_topup_requests SET status='approved', reviewed_at=NOW(), reviewed_by=? WHERE request_id=?")
                        ->execute([$currentUserId, $request_id]);
                } else {
                    $pdo->prepare("UPDATE wallet_topup_requests SET status='rejected', reviewed_at=NOW(), reviewed_by=? WHERE request_id=?")
                        ->execute([$currentUserId, $request_id]);
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[admin/wallet_requests] ' . $e->getMessage());
        }
    }

    header("Location: wallet_requests.php");
    exit;
}

$stmt = $pdo->query("
    SELECT wr.*, u.name as user_name, u.student_id
    FROM wallet_topup_requests wr
    JOIN users u ON wr.user_id = u.user_id
    ORDER BY (wr.status = 'pending') DESC, wr.requested_at DESC
    LIMIT 200
");
$requests = $stmt->fetchAll();

$statusMap = ['pending' => 'pending', 'approved' => 'completed', 'rejected' => 'cancelled'];
$statusText = ['pending' => 'รอตรวจสอบ', 'approved' => 'อนุมัติแล้ว', 'rejected' => 'ปฏิเสธ'];
?>

<h2>คำขอเติมเครดิต H Wallet</h2>

<?php if (empty($requests)): ?>
    <p style="color:var(--text-muted); font-size:14px;">ยังไม่มีคำขอเติมเครดิต</p>
<?php endif; ?>

<div style="display:flex; flex-direction:column; gap:12px; max-width:640px;">
    <?php foreach ($requests as $r): ?>
        <div class="info-card">
            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                <div>
                    <p style="font-weight:700; font-size:15px;">
                        <?= htmlspecialchars($r['user_name']) ?>
                        <span style="color:var(--text-muted); font-weight:400; font-size:13px;">(<?= htmlspecialchars($r['student_id']) ?>)</span>
                    </p>
                    <p style="font-size:20px; font-weight:800; color:var(--hu-green-dark); margin-top:4px;"><?= number_format((float) $r['amount'], 2) ?> บาท</p>
                    <p style="font-size:12.5px; color:var(--text-muted); margin-top:2px;"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($r['requested_at']))) ?></p>
                    <?php if ($r['slip_image'] && productImageExists($r['slip_image'])): ?>
                        <a href="../<?= htmlspecialchars($r['slip_image']) ?>" target="_blank" rel="noopener noreferrer" style="font-size:13px; color:var(--hu-green); font-weight:700;">📎 ดูสลิป</a>
                    <?php endif; ?>
                </div>
                <span class="status-badge status-<?= htmlspecialchars($statusMap[$r['status']] ?? '') ?>"><?= htmlspecialchars($statusText[$r['status']] ?? $r['status']) ?></span>
            </div>

            <?php if ($r['status'] === 'pending'): ?>
                <div style="display:flex; gap:8px; margin-top:14px;">
                    <form method="POST" class="action-form">
                        <?= csrfField() ?>
                        <input type="hidden" name="request_id" value="<?= (int) $r['request_id'] ?>">
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" style="background:var(--hu-green); color:#fff; padding:8px 20px; border-radius:999px; font-size:13.5px; margin:0;">✅ อนุมัติ</button>
                    </form>
                    <form method="POST" data-confirm="ปฏิเสธคำขอนี้?" class="action-form">
                        <?= csrfField() ?>
                        <input type="hidden" name="request_id" value="<?= (int) $r['request_id'] ?>">
                        <input type="hidden" name="action" value="reject">
                        <button type="submit" style="background:#fff; border:1.5px solid #F0B8B8; color:#C0392B; padding:8px 20px; border-radius:999px; font-size:13.5px; margin:0;">❌ ปฏิเสธ</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
