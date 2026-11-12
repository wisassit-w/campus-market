<?php
/**
 * หมายเหตุการ Audit: ไฟล์นี้ในแพ็กเกจต้นฉบับมีขนาด 0 ไบต์ (ไม่มีโค้ดใด ๆ)
 * ทำให้ผู้ขายไม่สามารถแก้ไขสินค้าที่ลงขายไปแล้วได้เลย ถือเป็นฟีเจอร์ที่ขาดหายไป (missing feature)
 * จึงพัฒนาใหม่ทั้งหมดให้ครบตาม flow ของระบบ พร้อมตรวจสอบสิทธิ์ความเป็นเจ้าของสินค้า (IDOR-safe)
 */

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/upload_helper.php';

requireLogin('../auth/login.php');

$id = intInput($_GET, 'id', 0) ?: intInput($_POST, 'id', 0);
$error = '';
$MAX_IMAGES = 6;

$stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    echo "<div class='error-msg' style='max-width:640px; margin:0 auto;'>ไม่พบสินค้านี้</div>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

// ป้องกัน IDOR: เฉพาะเจ้าของสินค้าเท่านั้นที่แก้ไขได้
if ($product['seller_id'] != $currentUserId) {
    echo "<div class='error-msg' style='max-width:640px; margin:0 auto;'>คุณไม่มีสิทธิ์แก้ไขสินค้านี้</div>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$allowedConditions = ['new', 'like_new', 'good', 'fair'];

$imgStmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ?");
$imgStmt->execute([$id]);
$images = $imgStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price       = $_POST['price'] ?? '';
    $category_id = intInput($_POST, 'category_id', 0);
    $condition   = $_POST['condition'] ?? '';
    $status      = ($_POST['status'] ?? '') === 'sold' ? 'sold' : 'available';
    $categoryIds = array_column($categories, 'category_id');
    $deleteImageIds = array_map('intval', $_POST['delete_images'] ?? []);

    if ($title === '' || mb_strlen($title) > 150) {
        $error = "กรุณากรอกชื่อสินค้าให้ถูกต้อง";
    } elseif (!is_numeric($price) || (float) $price <= 0 || (float) $price > 1000000) {
        $error = "กรุณากรอกราคาสินค้าให้ถูกต้อง";
    } elseif (!in_array($condition, $allowedConditions, true)) {
        $error = "กรุณาเลือกสภาพสินค้าที่ถูกต้อง";
    } elseif ($category_id !== 0 && !in_array($category_id, $categoryIds, true)) {
        $error = "หมวดหมู่สินค้าไม่ถูกต้อง";
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE products SET title=?, description=?, price=?, category_id=?, `condition`=?, status=? WHERE product_id=? AND seller_id=?");
            $stmt->execute([$title, $description, $price, $category_id ?: null, $condition, $status, $id, $currentUserId]);

            // ลบรูปที่ผู้ใช้เลือกลบ (ยืนยันว่าเป็นของสินค้านี้จริงเท่านั้น ป้องกันลบรูปสินค้าคนอื่น)
            if (!empty($deleteImageIds)) {
                $placeholders = implode(',', array_fill(0, count($deleteImageIds), '?'));
                $delStmt = $pdo->prepare("DELETE FROM product_images WHERE image_id IN ($placeholders) AND product_id = ?");
                $delStmt->execute([...$deleteImageIds, $id]);
            }

            // เพิ่มรูปใหม่ (ถ้ามี) โดยไม่เกินโควตารวม
            $remainingSlotsStmt = $pdo->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = ?");
            $remainingSlotsStmt->execute([$id]);
            $remainingSlots = $MAX_IMAGES - (int) $remainingSlotsStmt->fetchColumn();

            if (!empty($_FILES['images']['name'][0]) && $remainingSlots > 0) {
                $uploadDir = __DIR__ . '/../assets/uploads/';
                $count = min(count($_FILES['images']['name']), $remainingSlots);
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
                        $pdo->prepare("INSERT INTO product_images (product_id, image_url) VALUES (?, ?)")->execute([$id, $relativePath]);
                    }
                }
            }

            $pdo->commit();
            header("Location: detail.php?id=" . $id);
            exit;
        } catch (UploadException $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('[products/edit] ' . $e->getMessage());
            $error = "เกิดข้อผิดพลาดในการบันทึก กรุณาลองใหม่";
        }
    }

    // รีเฟรชข้อมูลรูปภาพหลัง error (ถ้ามี) เพื่อแสดงผลถูกต้อง
    $imgStmt->execute([$id]);
    $images = $imgStmt->fetchAll();
}
?>

<h2>แก้ไขสินค้า</h2>
<?php if ($error): ?>
    <p class="error-msg"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
    <?= csrfField() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">

    <label>ชื่อสินค้า</label>
    <input type="text" name="title" required maxlength="150" value="<?= htmlspecialchars($product['title']) ?>"><br>

    <label>รายละเอียด</label>
    <textarea name="description" rows="4" maxlength="3000"><?= htmlspecialchars($product['description'] ?? '') ?></textarea><br>

    <label>ราคา (บาท)</label>
    <input type="number" name="price" step="0.01" min="0.01" max="1000000" required value="<?= htmlspecialchars((string) $product['price']) ?>"><br>

    <label>หมวดหมู่</label>
    <select name="category_id">
        <?php foreach ($categories as $cat): ?>
            <option value="<?= (int) $cat['category_id'] ?>" <?= $product['category_id'] == $cat['category_id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
        <?php endforeach; ?>
    </select><br>

    <label>สภาพสินค้า</label>
    <select name="condition">
        <?php foreach (['new' => 'ใหม่', 'like_new' => 'เหมือนใหม่', 'good' => 'สภาพดี', 'fair' => 'พอใช้'] as $val => $text): ?>
            <option value="<?= $val ?>" <?= $product['condition'] === $val ? 'selected' : '' ?>><?= $text ?></option>
        <?php endforeach; ?>
    </select><br>

    <label>สถานะสินค้า</label>
    <select name="status">
        <option value="available" <?= $product['status'] === 'available' ? 'selected' : '' ?>>ยังขายอยู่</option>
        <option value="sold" <?= $product['status'] === 'sold' ? 'selected' : '' ?>>ขายแล้ว (ปิดการขาย)</option>
    </select><br>

    <?php if ($images): ?>
        <label>รูปภาพปัจจุบัน (เลือกเพื่อลบ)</label>
        <div style="display:flex; gap:10px; flex-wrap:wrap; margin:8px 0 16px;">
            <?php foreach ($images as $img): ?>
                <label style="position:relative; cursor:pointer;">
                    <img src="../<?= htmlspecialchars($img['image_url']) ?>" style="width:90px; height:90px; object-fit:cover; border-radius:10px; border:1px solid var(--border);">
                    <input type="checkbox" name="delete_images[]" value="<?= (int) $img['image_id'] ?>" style="position:absolute; top:4px; right:4px; width:auto;">
                </label>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <label>เพิ่มรูปใหม่</label>
    <input type="file" name="images[]" multiple accept="image/png,image/jpeg,image/webp,image/gif"><br>

    <button type="submit">บันทึกการแก้ไข</button>
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>
