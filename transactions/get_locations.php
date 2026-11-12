<?php
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json');

if (!$isLoggedIn) {
    echo json_encode(['success' => false]);
    exit;
}

$transaction_id = intInput($_GET, 'transaction_id', 0);
$user_id = $currentUserId;

/**
 * แก้ช่องโหว่ร้ายแรง (IDOR / Privacy leak): โค้ดเดิมไม่ตรวจสอบเลยว่าผู้เรียก endpoint นี้
 * เป็นผู้ซื้อหรือผู้ขายในธุรกรรมนั้นจริงหรือไม่ — ตรวจแค่ login เท่านั้น ทำให้ผู้ใช้คนใดก็ตาม
 * สามารถไล่เดข transaction_id (ตัวเลขไล่ลำดับ) เพื่อดูพิกัด GPS แบบเรียลไทม์ของคนอื่นที่ไม่เกี่ยวข้องได้เลย
 * แก้โดยเพิ่มการตรวจสอบสิทธิ์แบบเดียวกับ update_location.php ก่อน query ตำแหน่งเสมอ
 */
$accessStmt = $pdo->prepare("
    SELECT 1 FROM transactions t
    JOIN products p ON t.product_id = p.product_id
    WHERE t.transaction_id = ? AND (t.buyer_id = ? OR p.seller_id = ?)
");
$accessStmt->execute([$transaction_id, $user_id, $user_id]);

if (!$accessStmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT l.user_id, l.latitude, l.longitude, l.updated_at, u.name
    FROM live_locations l
    JOIN users u ON l.user_id = u.user_id
    WHERE l.transaction_id = ?
");
$stmt->execute([$transaction_id]);
$locations = $stmt->fetchAll();

echo json_encode(['success' => true, 'locations' => $locations, 'my_user_id' => $user_id]);
