<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/upload_helper.php';

requireLogin('../auth/login.php');

$error = '';
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$allowedConditions = ['new', 'like_new', 'good', 'fair'];
$MAX_IMAGES = 6;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price       = $_POST['price'] ?? '';
    $category_id = intInput($_POST, 'category_id', 0);
    $condition   = $_POST['condition'] ?? '';
    $seller_id   = $currentUserId;

    $categoryIds = array_column($categories, 'category_id');

    if ($title === '' || mb_strlen($title) > 150) {
        $error = "กรุณากรอกชื่อสินค้าให้ถูกต้อง (ไม่เกิน 150 ตัวอักษร)";
    } elseif (!is_numeric($price) || (float) $price <= 0 || (float) $price > 1000000) {
        $error = "กรุณากรอกราคาสินค้าให้ถูกต้อง";
    } elseif (!in_array($condition, $allowedConditions, true)) {
        $error = "กรุณาเลือกสภาพสินค้าที่ถูกต้อง";
    } elseif ($category_id !== 0 && !in_array($category_id, $categoryIds, true)) {
        $error = "หมวดหมู่สินค้าไม่ถูกต้อง";
    } elseif (count($_FILES['images']['name'] ?? []) > $MAX_IMAGES) {
        $error = "อัปโหลดรูปภาพได้สูงสุด {$MAX_IMAGES} รูป";
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO products (seller_id, category_id, title, description, price, `condition`) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$seller_id, $category_id ?: null, $title, $description, $price, $condition]);
            $product_id = (int) $pdo->lastInsertId();

            $uploadDir = __DIR__ . '/../assets/uploads/';
            if (!empty($_FILES['images']['name'][0])) {
                $count = count($_FILES['images']['name']);
                for ($i = 0; $i < $count; $i++) {
                    $file = [
                        'name'     => $_FILES['images']['name'][$i],
                        'type'     => $_FILES['images']['type'][$i],
                        'tmp_name' => $_FILES['images']['tmp_name'][$i],
                        'error'    => $_FILES['images']['error'][$i],
                        'size'     => $_FILES['images']['size'][$i],
                    ];
                    $relativePath = secureImageUpload($file, $uploadDir, 'product_');
                    if ($relativePath !== null) {
                        $imgStmt = $pdo->prepare("INSERT INTO product_images (product_id, image_url) VALUES (?, ?)");
                        $imgStmt->execute([$product_id, $relativePath]);
                    }
                }
            }

            $pdo->commit();
            header("Location: detail.php?id=" . $product_id);
            exit;
        } catch (UploadException $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('[products/create] ' . $e->getMessage());
            $error = "เกิดข้อผิดพลาดในการลงขายสินค้า กรุณาลองใหม่";
        }
    }
}
?>

<h2>ลงขายสินค้า</h2>
<?php if ($error): ?>
    <p class="error-msg"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
    <?= csrfField() ?>
    <label>ชื่อสินค้า</label>
    <input type="text" name="title" required maxlength="150" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"><br>

    <label>รายละเอียด</label>
    <textarea name="description" rows="4" maxlength="3000"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea><br>

    <label>ราคา (บาท)</label>
    <input type="number" name="price" step="0.01" min="0.01" max="1000000" required value="<?= htmlspecialchars($_POST['price'] ?? '') ?>"><br>

    <label>หมวดหมู่</label>
    <select name="category_id">
        <?php foreach ($categories as $cat): ?>
            <option value="<?= (int) $cat['category_id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
        <?php endforeach; ?>
    </select><br>

    <label>สภาพสินค้า</label>
    <select name="condition">
        <option value="new">ใหม่</option>
        <option value="like_new">เหมือนใหม่</option>
        <option value="good" selected>สภาพดี</option>
        <option value="fair">พอใช้</option>
    </select><br>

    <label>รูปสินค้า (สูงสุด <?= $MAX_IMAGES ?> รูป, JPG/PNG/WEBP/GIF ไม่เกิน 5MB ต่อรูป)</label>
    <input type="file" name="images[]" multiple accept="image/png,image/jpeg,image/webp,image/gif"><br>

    <button type="submit">ลงขายสินค้า</button>
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>
