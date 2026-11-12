<?php
require_once __DIR__ . '/../includes/bootstrap.php';

requireLogin('../auth/login.php');

$token = $_GET['csrf'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    die('คำขอไม่ถูกต้อง กรุณาโหลดหน้าใหม่');
}

$stmt = $pdo->prepare("
    DELETE ci FROM cart_items ci
    JOIN products p ON ci.product_id = p.product_id
    WHERE ci.user_id = ? AND p.status != 'available'
");
$stmt->execute([$currentUserId]);

header("Location: index.php");
exit;
