<?php
require_once __DIR__ . '/../includes/header.php';

requireLogin('../auth/login.php');

$user_id = $currentUserId;

$stmt = $pdo->prepare("
    SELECT ci.cart_id, p.product_id, p.title, p.price, p.status, u.name as seller_name,
    (SELECT image_url FROM product_images WHERE product_id = p.product_id LIMIT 1) as thumbnail
    FROM cart_items ci
    JOIN products p ON ci.product_id = p.product_id
    JOIN users u ON p.seller_id = u.user_id
    WHERE ci.user_id = ?
    ORDER BY ci.added_at DESC
");
$stmt->execute([$user_id]);
$items = $stmt->fetchAll();

$total = 0;
$soldCount = 0;
foreach ($items as $i) {
    if ($i['status'] === 'available') $total += $i['price'];
    else $soldCount++;
}

$csrf = csrfToken();
?>

<h2>ตะกร้าของฉัน</h2>

<?php if (empty($items)): ?>
    <div class="chat-empty">
        <p>🛒 ตะกร้าว่างเปล่า</p>
        <p style="font-size:14px; margin-top:6px;">เลือกสินค้าที่สนใจแล้วกด "เพิ่มลงตะกร้า" ได้เลย</p>
    </div>
<?php else: ?>

    <div style="max-width:640px; margin:0 auto;">

        <?php if ($soldCount > 0): ?>
            <div style="display:flex; justify-content:space-between; align-items:center; background:#FADCDC; padding:10px 16px; border-radius:10px; margin-bottom:14px; font-size:13.5px; color:#8B1F1F;">
                <span>มีสินค้าที่ขายไปแล้ว <?= $soldCount ?> รายการในตะกร้า</span>
                <a href="clear_sold.php?csrf=<?= urlencode($csrf) ?>" style="color:#8B1F1F; font-weight:600;">ล้างออกทั้งหมด</a>
            </div>
        <?php endif; ?>

        <?php foreach ($items as $i): ?>
            <div class="info-card" style="display:flex; gap:14px; align-items:center; <?= $i['status'] !== 'available' ? 'opacity:0.6;' : '' ?>">
                <?php if ($i['thumbnail'] && productImageExists($i['thumbnail'])): ?>
                    <img src="../<?= htmlspecialchars($i['thumbnail']) ?>" style="width:64px; height:64px; object-fit:cover; border-radius:10px; flex-shrink:0;">
                <?php else: ?>
                    <div class="thumb-placeholder small">📦</div>
                <?php endif; ?>
                <div style="flex:1; min-width:0;">
                    <p style="font-weight:700; margin-bottom:2px;"><?= htmlspecialchars($i['title']) ?></p>
                    <p style="color:var(--hu-green-dark); font-weight:600; font-size:15px;"><?= number_format((float) $i['price'], 2) ?> บาท</p>
                    <p style="font-size:12.5px; color:var(--text-muted); margin-top:2px;">
                        ผู้ขาย: <?= htmlspecialchars($i['seller_name']) ?>
                        <?php if ($i['status'] !== 'available'): ?>
                            <span class="status-badge status-cancelled" style="margin-left:6px;">ขายแล้ว</span>
                        <?php endif; ?>
                    </p>
                </div>
                <div style="display:flex; flex-direction:column; gap:8px; flex-shrink:0;">
                    <a href="../products/detail.php?id=<?= (int) $i['product_id'] ?>" style="font-size:12.5px; text-align:center; border:1.5px solid var(--hu-green); color:var(--hu-green-dark); padding:5px 12px; border-radius:999px; text-decoration:none;">ดูรายละเอียด</a>
                    <a href="remove.php?id=<?= (int) $i['cart_id'] ?>&csrf=<?= urlencode($csrf) ?>" style="font-size:12.5px; text-align:center; color:#C0392B; text-decoration:none;" data-confirm="ลบออกจากตะกร้า?">ลบ</a>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="info-card" style="display:flex; justify-content:space-between; align-items:center; background:var(--hu-green-light); border:none;">
            <strong>รวมทั้งหมด (เฉพาะสินค้าที่ยังขายอยู่)</strong>
            <strong style="font-size:18px; color:var(--hu-green-dark);"><?= number_format($total, 2) ?> บาท</strong>
        </div>

        <p style="text-align:center; color:var(--text-muted); font-size:13.5px; margin-top:8px;">
            💡 กด "ดูรายละเอียด" แล้วแชทกับผู้ขายแต่ละคนเพื่อนัดรับสินค้า
        </p>
    </div>

<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
