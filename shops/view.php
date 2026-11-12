<?php
require_once __DIR__ . '/../includes/header.php';

$seller_id = intInput($_GET, 'seller_id', 0);

$stmt = $pdo->prepare("
    SELECT s.*, u.name as owner_name
    FROM shops s JOIN users u ON s.seller_id = u.user_id
    WHERE s.seller_id = ?
");
$stmt->execute([$seller_id]);
$shop = $stmt->fetch();

if (!$shop) {
    if ($isLoggedIn && $currentUserId == $seller_id) {
        echo '<div class="chat-empty">
                <p>🏪 คุณยังไม่ได้เปิดร้านค้า</p>
                <p style="font-size:14px; margin:8px 0 16px;">เปิดร้านของตัวเองเพื่อให้ลูกค้าเห็นสินค้าทั้งหมดในที่เดียว</p>
                <a href="manage.php" style="display:inline-block; background:var(--hu-green); color:#fff; padding:10px 24px; border-radius:10px; text-decoration:none; font-weight:600;">เปิดร้านค้าเลย</a>
              </div>';
    } else {
        echo "<p style='padding:20px; color:var(--text-muted);'>ผู้ขายรายนี้ยังไม่ได้เปิดร้านค้า</p>";
    }
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$stmt = $pdo->prepare("
    SELECT p.*,
    (SELECT image_url FROM product_images WHERE product_id = p.product_id LIMIT 1) as thumbnail
    FROM products p
    WHERE p.seller_id = ? AND p.status = 'available'
    ORDER BY p.created_at DESC
");
$stmt->execute([$seller_id]);
$products = $stmt->fetchAll();

$avgRating = $pdo->prepare("SELECT AVG(rating) as avg_r, COUNT(*) as cnt FROM reviews WHERE reviewed_id = ?");
$avgRating->execute([$seller_id]);
$ratingData = $avgRating->fetch();
?>

<div class="info-card" style="display:flex; gap:18px; align-items:center; max-width:760px;">
    <?php if ($shop['logo_url'] && productImageExists($shop['logo_url'])): ?>
        <img src="../<?= htmlspecialchars($shop['logo_url']) ?>" style="width:80px; height:80px; object-fit:cover; border-radius:14px; flex-shrink:0;">
    <?php else: ?>
        <div style="width:80px; height:80px; border-radius:14px; background:var(--hu-green-light); display:flex; align-items:center; justify-content:center; font-size:28px; color:var(--hu-green-dark); flex-shrink:0;">🏪</div>
    <?php endif; ?>
    <div>
        <h2 style="margin:0 0 4px;"><?= htmlspecialchars($shop['shop_name']) ?></h2>
        <p style="color:var(--text-muted); font-size:14px;">โดย <?= htmlspecialchars($shop['owner_name']) ?></p>
        <?php if ($ratingData['cnt'] > 0): ?>
            <p style="font-size:14px; color:var(--hu-gold);">⭐ <?= number_format((float) $ratingData['avg_r'], 1) ?> (<?= (int) $ratingData['cnt'] ?> รีวิว)</p>
        <?php endif; ?>
        <?php if ($isLoggedIn && $currentUserId == $seller_id): ?>
            <a href="manage.php" style="font-size:13.5px;">✏️ แก้ไขร้านค้า</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($shop['shop_description']): ?>
    <p style="max-width:760px; color:var(--text-muted); margin-top:10px;"><?= nl2br(htmlspecialchars($shop['shop_description'])) ?></p>
<?php endif; ?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-top:16px;">
    <h3 style="margin:0;">สินค้าในร้าน (<?= count($products) ?>)</h3>
    <?php if ($isLoggedIn && $currentUserId == $seller_id): ?>
        <a href="../products/create.php" style="background:var(--hu-green); color:#fff; padding:8px 18px; border-radius:999px; text-decoration:none; font-weight:600; font-size:14px;">
            + เพิ่มสินค้าในร้าน
        </a>
    <?php endif; ?>
</div>

<div class="product-list">
    <?php foreach ($products as $p): ?>
        <div class="product-card">
            <?php if ($p['thumbnail'] && productImageExists($p['thumbnail'])): ?>
                <img src="../<?= htmlspecialchars($p['thumbnail']) ?>" loading="lazy">
            <?php else: ?>
                <div class="thumb-placeholder">📦</div>
            <?php endif; ?>
            <h4><?= htmlspecialchars($p['title']) ?></h4>
            <p class="price"><?= number_format((float) $p['price'], 2) ?> บาท</p>
            <a href="../products/detail.php?id=<?= (int) $p['product_id'] ?>" style="display:block; text-align:center; margin-top:10px;">ดูรายละเอียด</a>
        </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
