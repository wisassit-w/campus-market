<?php
require_once __DIR__ . '/../includes/header.php';

$stmt = $pdo->query("
    SELECT s.*, u.name as owner_name,
    (SELECT COUNT(*) FROM products WHERE seller_id = s.seller_id AND status = 'available') as product_count,
    (SELECT AVG(rating) FROM reviews WHERE reviewed_id = s.seller_id) as avg_rating,
    (SELECT COUNT(*) FROM reviews WHERE reviewed_id = s.seller_id) as review_count
    FROM shops s
    JOIN users u ON s.seller_id = u.user_id
    ORDER BY s.created_at DESC
");
$shops = $stmt->fetchAll();
?>

<h2>ร้านค้าทั้งหมด</h2>
<p style="color:#5F5E5A; margin-bottom:16px;">
    พบร้านค้า <?= count($shops) ?> ร้าน ·
    <a href="../products/list.php" style="color:var(--hu-green); font-weight:700; text-decoration:none;">ค้นหาสินค้าทั้งหมด →</a>
</p>

<div class="product-list">
    <?php if (empty($shops)): ?>
        <p>ยังไม่มีร้านค้าเปิดในระบบ</p>
    <?php endif; ?>

    <?php foreach ($shops as $s): ?>
        <a href="view.php?seller_id=<?= (int) $s['seller_id'] ?>" style="text-decoration:none; color:inherit;">
            <div class="product-card" style="text-align:center;">
                <?php if ($s['logo_url'] && productImageExists($s['logo_url'])): ?>
                    <img src="../<?= htmlspecialchars($s['logo_url']) ?>" style="width:100%; aspect-ratio:1; object-fit:cover; border-radius:12px;" loading="lazy">
                <?php else: ?>
                    <div style="width:100%; aspect-ratio:1; border-radius:12px; background:var(--hu-green-light); display:flex; align-items:center; justify-content:center; font-size:36px; color:var(--hu-green-dark);">🏪</div>
                <?php endif; ?>
                <h4 style="margin-top:10px;"><?= htmlspecialchars($s['shop_name']) ?></h4>
                <p style="font-size:13px; color:#5F5E5A;">โดย <?= htmlspecialchars($s['owner_name']) ?></p>
                <p style="font-size:13px; color:#5F5E5A;"><?= (int) $s['product_count'] ?> สินค้า</p>
                <?php if ($s['review_count'] > 0): ?>
                    <p style="font-size:13px; color:var(--hu-gold);">⭐ <?= number_format((float) $s['avg_rating'], 1) ?> (<?= (int) $s['review_count'] ?> รีวิว)</p>
                <?php else: ?>
                    <p style="font-size:13px; color:#5F5E5A;">ยังไม่มีรีวิว</p>
                <?php endif; ?>
            </div>
        </a>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
