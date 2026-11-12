<?php
require_once __DIR__ . '/../includes/header.php';

requireAdmin('../auth/login.php');

$chats = $pdo->query("
    SELECT c.chat_id, p.title as product_title,
    buyer.name as buyer_name, seller.name as seller_name,
    (SELECT COUNT(*) FROM messages WHERE chat_id = c.chat_id) as message_count,
    c.created_at
    FROM chats c
    JOIN products p ON c.product_id = p.product_id
    JOIN users buyer ON c.buyer_id = buyer.user_id
    JOIN users seller ON c.seller_id = seller.user_id
    ORDER BY c.created_at DESC
    LIMIT 200
")->fetchAll();
?>

<h2>จัดการห้องแชท</h2>

<div style="margin-bottom:20px;">
    <a href="dashboard.php" style="display:inline-flex; align-items:center; gap:8px; background:#fff; border:1.5px solid var(--hu-green); color:var(--hu-green-dark); padding:10px 20px; border-radius:10px; text-decoration:none; font-weight:600; font-size:14.5px;">
        ← กลับไป Dashboard
    </a>
</div>

<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:14px; margin-bottom:20px;">
    <div class="info-card" style="text-align:center; margin:0;">
        <p style="font-size:13px; color:var(--text-muted);">ห้องแชททั้งหมด</p>
        <p style="font-size:24px; font-weight:700; color:var(--hu-green-dark);"><?= count($chats) ?></p>
    </div>
    <div class="info-card" style="text-align:center; margin:0;">
        <p style="font-size:13px; color:var(--text-muted);">ข้อความรวมทั้งหมด</p>
        <p style="font-size:24px; font-weight:700; color:var(--hu-green);">
            <?= array_sum(array_column($chats, 'message_count')) ?>
        </p>
    </div>
</div>

<?php if (empty($chats)): ?>
    <div class="chat-empty">
        <p>💬 ยังไม่มีห้องแชทในระบบ</p>
    </div>
<?php else: ?>

    <table>
        <thead>
        <tr>
            <th>สินค้า</th>
            <th>ผู้ซื้อ</th>
            <th>ผู้ขาย</th>
            <th>จำนวนข้อความ</th>
            <th>วันที่เริ่มแชท</th>
            <th>จัดการ</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($chats as $c): ?>
            <tr>
                <td data-label="สินค้า"><?= htmlspecialchars($c['product_title']) ?></td>
                <td data-label="ผู้ซื้อ"><?= htmlspecialchars($c['buyer_name']) ?></td>
                <td data-label="ผู้ขาย"><?= htmlspecialchars($c['seller_name']) ?></td>
                <td data-label="ข้อความ">
                    <span class="status-badge <?= $c['message_count'] > 0 ? 'status-completed' : 'status-pending' ?>">
                        <?= (int) $c['message_count'] ?> ข้อความ
                    </span>
                </td>
                <td data-label="เริ่มแชท"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($c['created_at']))) ?></td>
                <td data-label="จัดการ">
                    <form method="POST" action="delete_chat.php" class="action-form" data-confirm="ลบห้องแชทนี้? ข้อความทั้งหมดจะหายไปด้วย">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $c['chat_id'] ?>">
                        <button type="submit" style="background:none; border:none; color:#C0392B; text-decoration:underline; cursor:pointer; padding:0; font:inherit;">ลบ</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
