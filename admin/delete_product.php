<?php
require_once __DIR__ . '/../includes/bootstrap.php';

requireAdmin('../auth/login.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}
verifyCsrf();

$id = intInput($_POST, 'id', 0);

if ($id > 0) {
    try {
        // ลบไฟล์รูปภาพออกจากดิสก์ด้วย ไม่ใช่แค่ลบ record ใน DB (เดิมไม่ลบไฟล์เลย -> ไฟล์ค้างสะสมตลอดไป)
        $imgStmt = $pdo->prepare("SELECT image_url FROM product_images WHERE product_id = ?");
        $imgStmt->execute([$id]);
        $images = $imgStmt->fetchAll(PDO::FETCH_COLUMN);

        $stmt = $pdo->prepare("DELETE FROM products WHERE product_id = ?");
        $stmt->execute([$id]);

        foreach ($images as $imgPath) {
            $fullPath = __DIR__ . '/../' . $imgPath;
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }
        }
    } catch (Throwable $e) {
        // กัน uncaught exception (เช่น FK constraint) ไม่ให้หลุดออกไปเป็น stack trace ต่อผู้ใช้
        error_log('[admin/delete_product] ' . $e->getMessage());
    }
}

header("Location: dashboard.php");
exit;
