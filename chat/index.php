<?php
require_once __DIR__ . '/../includes/header.php';

requireLogin('../auth/login.php');

$user_id = $currentUserId;

$stmt = $pdo->prepare("
    SELECT c.chat_id, p.title as product_title,
    (SELECT image_url FROM product_images WHERE product_id = p.product_id LIMIT 1) as product_image,
    CASE WHEN c.buyer_id = ? THEN seller.name ELSE buyer.name END as other_person,
    (SELECT message_text FROM messages WHERE chat_id = c.chat_id ORDER BY sent_at DESC LIMIT 1) as last_message,
    (SELECT sent_at FROM messages WHERE chat_id = c.chat_id ORDER BY sent_at DESC LIMIT 1) as last_time
    FROM chats c
    JOIN products p ON c.product_id = p.product_id
    JOIN users buyer ON c.buyer_id = buyer.user_id
    JOIN users seller ON c.seller_id = seller.user_id
    WHERE c.buyer_id = ? OR c.seller_id = ?
    ORDER BY last_time DESC
");
$stmt->execute([$user_id, $user_id, $user_id]);
$chats = $stmt->fetchAll();

function timeAgo(?string $datetime): string
{
    if (!$datetime) return '';
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'เมื่อสักครู่';
    if ($diff < 3600) return floor($diff / 60) . ' นาทีที่แล้ว';
    if ($diff < 86400) return floor($diff / 3600) . ' ชั่วโมงที่แล้ว';
    return date('d/m/Y', strtotime($datetime));
}
?>

<h2>แชทของฉัน</h2>

<?php if (empty($chats)): ?>
    <div class="chat-empty">
        <p>💬 ยังไม่มีแชท</p>
        <p style="font-size:14px; margin-top:6px;">เริ่มคุยกับผู้ขายได้จากหน้ารายละเอียดสินค้า</p>
    </div>
<?php endif; ?>

<div class="chat-list">
<?php foreach ($chats as $c): ?>
    <a href="room.php?id=<?= (int) $c['chat_id'] ?>" class="chat-list-item">
        <div class="chat-list-avatar">
            <?php if ($c['product_image'] && productImageExists($c['product_image'])): ?>
                <img src="../<?= htmlspecialchars($c['product_image']) ?>">
            <?php else: ?>
                <span><?= htmlspecialchars(mb_substr($c['other_person'], 0, 1)) ?></span>
            <?php endif; ?>
        </div>
        <div class="chat-list-info">
            <div class="chat-list-top">
                <span class="chat-list-name"><?= htmlspecialchars($c['other_person']) ?></span>
                <span class="chat-list-time"><?= htmlspecialchars(timeAgo($c['last_time'])) ?></span>
            </div>
            <div class="chat-list-product">📦 <?= htmlspecialchars($c['product_title']) ?></div>
            <div class="chat-list-preview">
                <?= $c['last_message'] ? htmlspecialchars(mb_substr($c['last_message'], 0, 40)) . (mb_strlen($c['last_message']) > 40 ? '...' : '') : 'ยังไม่มีข้อความ' ?>
            </div>
        </div>
    </a>
<?php endforeach; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
