<?php
require_once __DIR__ . '/../includes/bootstrap.php';

requireLogin('../auth/login.php');

// action นี้เปลี่ยนแปลงข้อมูล (ลบสินค้าในตะกร้า) และเดิมเรียกผ่าน GET link ตรง ๆ ซึ่งเสี่ยงต่อ
// CSRF (เช่นฝัง <img src="remove.php?id=..."> ในหน้าอื่นเพื่อหลอกให้ผู้ใช้ที่ login อยู่ลบของโดยไม่ตั้งใจ)
// จึงเพิ่มการตรวจ csrf token ที่แนบมากับลิงก์
$token = $_GET['csrf'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    die('คำขอไม่ถูกต้อง กรุณาโหลดหน้าใหม่');
}

$cart_id = intInput($_GET, 'id', 0);

if ($cart_id > 0) {
    // WHERE user_id = ? กันไม่ให้ผู้ใช้ลบ cart item ของคนอื่นด้วยการเดา cart_id (IDOR-safe)
    $stmt = $pdo->prepare("DELETE FROM cart_items WHERE cart_id = ? AND user_id = ?");
    $stmt->execute([$cart_id, $currentUserId]);
}

header("Location: index.php");
exit;
