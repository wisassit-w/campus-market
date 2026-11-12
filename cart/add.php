<?php
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json');

if (!$isLoggedIn) {
    echo json_encode(['success' => false, 'error' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

// endpoint นี้เปลี่ยนแปลงข้อมูล (เพิ่มลงตะกร้า) จึงต้องตรวจ CSRF เช่นเดียวกับ endpoint อื่น ๆ
$submittedToken = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'คำขอไม่ถูกต้อง กรุณาโหลดหน้าใหม่']);
    exit;
}

$product_id = intInput($_POST, 'product_id', 0);
$user_id = $currentUserId;

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ข้อมูลสินค้าไม่ถูกต้อง']);
    exit;
}

$stmt = $pdo->prepare("SELECT seller_id, status FROM products WHERE product_id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบสินค้า']);
    exit;
}

if ($product['status'] !== 'available') {
    echo json_encode(['success' => false, 'error' => 'สินค้านี้ถูกขายไปแล้ว']);
    exit;
}

if ($product['seller_id'] == $user_id) {
    echo json_encode(['success' => false, 'error' => 'ไม่สามารถเพิ่มสินค้าของตัวเองได้']);
    exit;
}

$stmt = $pdo->prepare("INSERT IGNORE INTO cart_items (user_id, product_id) VALUES (?, ?)");
$stmt->execute([$user_id, $product_id]);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM cart_items WHERE user_id = ?");
$countStmt->execute([$user_id]);
$count = $countStmt->fetchColumn();

echo json_encode(['success' => true, 'count' => $count]);
