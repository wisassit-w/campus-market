<?php
require_once __DIR__ . '/../includes/header.php';

$search      = trim($_GET['search'] ?? '');
$category_id = intInput($_GET, 'category_id', 0);
$min_price   = $_GET['min_price'] ?? '';
$max_price   = $_GET['max_price'] ?? '';
$condition   = $_GET['condition'] ?? '';

$allowedConditions = ['new', 'like_new', 'good', 'fair'];
if (!in_array($condition, $allowedConditions, true)) {
    $condition = '';
}
if ($min_price !== '' && !is_numeric($min_price)) $min_price = '';
if ($max_price !== '' && !is_numeric($max_price)) $max_price = '';

// จำกัดความยาวคำค้นหาเพื่อกัน query ที่หนักเกินจำเป็น
$search = mb_substr($search, 0, 100);

// --- Pagination (เดิมโหลดสินค้าทั้งหมดในตารางทีเดียวโดยไม่มี LIMIT — จะช้าลงเรื่อย ๆ เมื่อข้อมูลโต) ---
$perPage = 24;
$page = max(1, intInput($_GET, 'page', 1));
$offset = ($page - 1) * $perPage;

$where = " WHERE p.status = 'available' ";
$params = [];

if ($search !== '') {
    $where .= " AND p.title LIKE ? ";
    $params[] = "%$search%";
}
if ($category_id !== 0) {
    $where .= " AND p.category_id = ? ";
    $params[] = $category_id;
}
if ($min_price !== '') {
    $where .= " AND p.price >= ? ";
    $params[] = $min_price;
}
if ($max_price !== '') {
    $where .= " AND p.price <= ? ";
    $params[] = $max_price;
}
if ($condition !== '') {
    $where .= " AND p.condition = ? ";
    $params[] = $condition;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p" . $where);
$countStmt->execute($params);
$totalCount = (int) $countStmt->fetchColumn();

$sql = "
    SELECT p.*, u.name as seller_name,
    (SELECT image_url FROM product_images WHERE product_id = p.product_id LIMIT 1) as thumbnail
    FROM products p
    JOIN users u ON p.seller_id = u.user_id
    {$where}
    ORDER BY p.created_at DESC
    LIMIT {$perPage} OFFSET {$offset}
";
// หมายเหตุ: $perPage และ $offset เป็นค่า int ที่ผ่านการ cast/validate แล้วเท่านั้น (ไม่ใช่ input ดิบ) จึงปลอดภัยจาก SQL Injection
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

$conditionText = [
    'new' => 'ใหม่',
    'like_new' => 'เหมือนใหม่',
    'good' => 'สภาพดี',
    'fair' => 'พอใช้',
];

$totalPages = max(1, (int) ceil($totalCount / $perPage));

function buildPageUrl(int $p): string
{
    $q = $_GET;
    $q['page'] = $p;
    return 'list.php?' . http_build_query($q);
}
?>

<h2>สินค้าทั้งหมด</h2>
<p style="margin-bottom:16px;"><a href="../shops/list.php" style="color:var(--hu-green); font-weight:700; text-decoration:none;">🏪 ดูตามร้านค้าแทน →</a></p>

<form method="GET">
    <div>
        <label>ค้นหาชื่อสินค้า</label>
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="เช่น หนังสือ, ปากกา..." style="width:200px;" maxlength="100">
    </div>

    <div>
        <label>หมวดหมู่</label>
        <select name="category_id" style="width:150px;">
            <option value="">ทั้งหมด</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= (int) $cat['category_id'] ?>" <?= $category_id === (int) $cat['category_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label>สภาพสินค้า</label>
        <select name="condition" style="width:130px;">
            <option value="">ทั้งหมด</option>
            <option value="new" <?= $condition === 'new' ? 'selected' : '' ?>>ใหม่</option>
            <option value="like_new" <?= $condition === 'like_new' ? 'selected' : '' ?>>เหมือนใหม่</option>
            <option value="good" <?= $condition === 'good' ? 'selected' : '' ?>>สภาพดี</option>
            <option value="fair" <?= $condition === 'fair' ? 'selected' : '' ?>>พอใช้</option>
        </select>
    </div>

    <div>
        <label>ราคาต่ำสุด</label>
        <input type="number" name="min_price" value="<?= htmlspecialchars((string) $min_price) ?>" style="width:100px;" min="0">
    </div>

    <div>
        <label>ราคาสูงสุด</label>
        <input type="number" name="max_price" value="<?= htmlspecialchars((string) $max_price) ?>" style="width:100px;" min="0">
    </div>

    <div>
        <button type="submit" style="margin-top:0;">ค้นหา</button>
    </div>
    <div>
        <a href="list.php" style="display:inline-block; margin-top:0; padding:10px 16px; color:#444441;">ล้างตัวกรอง</a>
    </div>
</form>

<p style="color:#5F5E5A; margin-bottom:12px;">พบสินค้า <?= $totalCount ?> รายการ</p>

<div class="product-list">
    <?php if (empty($products)): ?>
        <p>ไม่พบสินค้าตามเงื่อนไขที่ค้นหา</p>
    <?php endif; ?>

    <?php foreach ($products as $p): ?>
        <div class="product-card">
            <?php if ($p['thumbnail'] && productImageExists($p['thumbnail'])): ?>
                <img src="../<?= htmlspecialchars($p['thumbnail']) ?>" loading="lazy">
            <?php else: ?>
                <div class="thumb-placeholder">📦</div>
            <?php endif; ?>
            <h4><?= htmlspecialchars($p['title']) ?></h4>
            <p class="price"><?= number_format((float) $p['price'], 2) ?> บาท</p>
            <p style="font-size:13px; color:#5F5E5A;"><?= htmlspecialchars($conditionText[$p['condition']] ?? '-') ?></p>
            <p style="font-size:13px;">ผู้ขาย: <?= htmlspecialchars($p['seller_name']) ?></p>
            <div style="display:flex; gap:6px; margin-top:10px;">
                <a href="detail.php?id=<?= (int) $p['product_id'] ?>" style="flex:1; text-align:center;">ดูรายละเอียด</a>
                <?php if ($isLoggedIn && $currentUserId != $p['seller_id']): ?>
                    <button type="button" onclick="addToCart(<?= (int) $p['product_id'] ?>, this)" style="padding:6px 12px; border-radius:999px; background:#fff; border:1.5px solid var(--hu-green); color:var(--hu-green); cursor:pointer; font-size:14px;">
                        🛒
                    </button>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($totalPages > 1): ?>
    <div style="display:flex; gap:8px; justify-content:center; margin-top:24px; flex-wrap:wrap;">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="<?= htmlspecialchars(buildPageUrl($p)) ?>" style="padding:8px 14px; border-radius:8px; text-decoration:none; <?= $p === $page ? 'background:var(--hu-green); color:#fff;' : 'background:#fff; border:1px solid var(--border); color:var(--text-main);' ?>">
                <?= $p ?>
            </a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
