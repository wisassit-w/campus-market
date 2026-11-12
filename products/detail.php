<?php
require_once __DIR__ . '/../includes/header.php';

$id = intInput($_GET, 'id', 0);

if ($id <= 0) {
    echo "<div class='error-msg' style='max-width:640px; margin:0 auto;'>ไม่พบสินค้านี้</div>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$stmt = $pdo->prepare("
    SELECT p.*, u.name as seller_name, u.user_id as seller_id, c.name as category_name
    FROM products p
    JOIN users u ON p.seller_id = u.user_id
    LEFT JOIN categories c ON p.category_id = c.category_id
    WHERE p.product_id = ?
");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    echo "<div class='error-msg' style='max-width:640px; margin:0 auto;'>ไม่พบสินค้านี้</div>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$imgStmt = $pdo->prepare("SELECT image_url FROM product_images WHERE product_id = ?");
$imgStmt->execute([$id]);
// กรองเฉพาะรูปที่มีไฟล์อยู่จริงบนดิสก์ (กันไอคอนรูปหักถ้า path ค้างใน DB แต่ไฟล์ถูกลบ/หาย)
$images = array_filter($imgStmt->fetchAll(), fn($img) => productImageExists($img['image_url']));

$conditionText = [
    'new' => 'ใหม่',
    'like_new' => 'เหมือนใหม่',
    'good' => 'สภาพดี',
    'fair' => 'พอใช้',
];

$reviewStmt = $pdo->prepare("
    SELECT r.*, u.name as reviewer_name
    FROM reviews r JOIN users u ON r.reviewer_id = u.user_id
    WHERE r.reviewed_id = ?
    ORDER BY r.created_at DESC
");
$reviewStmt->execute([$product['seller_id']]);
$reviews = $reviewStmt->fetchAll();

$avgRating = count($reviews) > 0 ? array_sum(array_column($reviews, 'rating')) / count($reviews) : 0;
$isOwner = $isLoggedIn && $currentUserId == $product['seller_id'];
?>

<h2><?= htmlspecialchars($product['title']) ?></h2>

<div class="info-card" style="display:flex; gap:26px; flex-wrap:wrap; max-width:780px;">
    <div style="width:280px; flex-shrink:0;">
        <?php if ($images): ?>
            <?php foreach ($images as $img): ?>
                <img src="../<?= htmlspecialchars($img['image_url']) ?>" style="width:100%; border-radius:var(--radius-sm); margin-bottom:8px; box-shadow:var(--shadow-sm);" loading="lazy">
            <?php endforeach; ?>
        <?php else: ?>
            <div style="width:100%; height:200px; background:var(--hu-green-light); border-radius:var(--radius-sm); display:flex; align-items:center; justify-content:center; color:var(--text-muted); font-size:14px;">
                ไม่มีรูปภาพ
            </div>
        <?php endif; ?>
    </div>

    <div style="flex:1; min-width:240px;">
        <p style="font-size:24px; font-weight:800; color:var(--hu-green-dark); margin-bottom:12px;">
            <?= number_format((float) $product['price'], 2) ?> บาท
        </p>

        <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:14px;">
            <span style="background:var(--hu-green-light); color:var(--hu-green-dark); padding:5px 14px; border-radius:999px; font-size:13px; font-weight:700;">
                <?= htmlspecialchars($product['category_name'] ?? 'ไม่ระบุหมวดหมู่') ?>
            </span>
            <span style="background:var(--hu-gold-light); color:#7A5A0A; padding:5px 14px; border-radius:999px; font-size:13px; font-weight:700;">
                <?= htmlspecialchars($conditionText[$product['condition']] ?? '-') ?>
            </span>
            <?php if ($product['status'] !== 'available'): ?>
                <span class="status-badge status-cancelled">ขายแล้ว</span>
            <?php endif; ?>
        </div>

        <a href="../shops/view.php?seller_id=<?= (int) $product['seller_id'] ?>" style="display:flex; align-items:center; gap:10px; text-decoration:none; background:var(--bg); border:1px solid var(--border); border-radius:var(--radius-sm); padding:10px 14px; margin-bottom:14px;">
            <div style="width:34px; height:34px; border-radius:10px; background:var(--hu-green-light); display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0;">🏪</div>
            <div>
                <div style="font-size:13px; color:var(--text-muted);">ผู้ขาย</div>
                <div style="font-size:14.5px; font-weight:700; color:var(--text-main);"><?= htmlspecialchars($product['seller_name']) ?></div>
            </div>
        </a>

        <p style="font-size:14.5px; line-height:1.8; color:var(--text-main);"><?= nl2br(htmlspecialchars($product['description'] ?? '')) ?></p>

        <?php if ($isOwner): ?>
            <div style="display:flex; gap:10px; margin-top:18px; flex-wrap:wrap;">
                <a href="edit.php?id=<?= (int) $product['product_id'] ?>" style="flex:1; min-width:150px; text-align:center; box-sizing:border-box; padding:13px 20px; border-radius:999px; font-size:14.5px; font-weight:700; background:var(--hu-green); color:#fff; text-decoration:none;">
                    ✏️ แก้ไขสินค้า
                </a>
            </div>
        <?php elseif ($isLoggedIn && $product['status'] === 'available'): ?>
            <form id="chatStartForm" method="POST" action="../chat/start.php" style="display:none;">
                <?= csrfField() ?>
                <input type="hidden" name="product_id" value="<?= (int) $product['product_id'] ?>">
            </form>

            <div style="display:flex; gap:10px; margin-top:18px; flex-wrap:wrap;">
                <button type="button" onclick="document.getElementById('chatStartForm').submit();" style="flex:1; min-width:150px; box-sizing:border-box; padding:13px 20px; border-radius:999px; font-size:14.5px; font-weight:700; background:var(--hu-green); color:#fff; border:none; cursor:pointer; margin:0; box-shadow:0 4px 14px rgba(27,107,58,0.25);">
                    💬 แชทกับผู้ขาย
                </button>
                <button type="button" onclick="addToCart(<?= (int) $product['product_id'] ?>, this)" style="flex:1; min-width:150px; box-sizing:border-box; padding:13px 20px; border-radius:999px; background:#fff; border:1.5px solid var(--hu-gold); color:#7A5A0A; cursor:pointer; font-weight:700; font-size:14.5px; margin:0;">
                    🛒 เพิ่มลงตะกร้า
                </button>
            </div>
        <?php elseif (!$isLoggedIn): ?>
            <p style="margin-top:16px; font-size:14px;"><a href="../auth/login.php" style="color:var(--hu-green); font-weight:700;">เข้าสู่ระบบ</a> เพื่อแชทกับผู้ขาย</p>
        <?php endif; ?>
    </div>
</div>

<div style="display:flex; align-items:center; justify-content:space-between; max-width:780px; margin-top:24px;">
    <h3 style="margin:0;">รีวิวผู้ขาย</h3>
    <?php if (count($reviews) > 0): ?>
        <span style="font-size:14px; color:var(--hu-gold); font-weight:700;">⭐ <?= number_format($avgRating, 1) ?> (<?= count($reviews) ?> รีวิว)</span>
    <?php endif; ?>
</div>

<?php if (empty($reviews)): ?>
    <p style="color:var(--text-muted); max-width:640px; font-size:14px; margin-top:8px;">ยังไม่มีรีวิวสำหรับผู้ขายรายนี้</p>
<?php endif; ?>

<div style="display:flex; flex-direction:column; gap:10px; max-width:640px; margin-top:10px;">
    <?php foreach ($reviews as $r): ?>
        <div class="info-card">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <strong style="font-size:14.5px;"><?= htmlspecialchars($r['reviewer_name']) ?></strong>
                <span style="color:var(--hu-gold); font-size:13.5px; font-weight:700;">
                    <?= str_repeat('⭐', max(0, min(5, (int) $r['rating']))) ?>
                </span>
            </div>
            <?php if ($r['comment']): ?>
                <p style="margin-top:6px; color:var(--text-muted); font-size:14px;"><?= htmlspecialchars($r['comment']) ?></p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
