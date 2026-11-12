<?php
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json');

if (!$isLoggedIn) {
    echo json_encode(['success' => false, 'error' => 'ไม่ได้ login']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];

$submittedToken = $data['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'คำขอไม่ถูกต้อง']);
    exit;
}

$transaction_id = intInput($data, 'transaction_id', 0);
$lat = $data['lat'] ?? null;
$lng = $data['lng'] ?? null;
$user_id = $currentUserId;

if (!$transaction_id || !is_numeric($lat) || !is_numeric($lng)) {
    echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ครบ']);
    exit;
}

$lat = (float) $lat;
$lng = (float) $lng;
if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    echo json_encode(['success' => false, 'error' => 'พิกัดไม่ถูกต้อง']);
    exit;
}

// เช็คสิทธิ์ว่าเป็นคนในรายการนี้จริง และสถานะต้องเป็น 'confirmed' เท่านั้นถึงจะแชร์ตำแหน่งได้
$stmt = $pdo->prepare("
    SELECT t.status FROM transactions t
    JOIN products p ON t.product_id = p.product_id
    WHERE t.transaction_id = ? AND (t.buyer_id = ? OR p.seller_id = ?)
");
$stmt->execute([$transaction_id, $user_id, $user_id]);
$tx = $stmt->fetch();

if (!$tx || $tx['status'] !== 'confirmed') {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์']);
    exit;
}

$stmt = $pdo->prepare("
    INSERT INTO live_locations (transaction_id, user_id, latitude, longitude)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE latitude = ?, longitude = ?, updated_at = NOW()
");
$stmt->execute([$transaction_id, $user_id, $lat, $lng, $lat, $lng]);

echo json_encode(['success' => true]);
