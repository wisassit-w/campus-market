<?php
require_once __DIR__ . '/../includes/header.php';

requireLogin('../auth/login.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../products/list.php");
    exit;
}

verifyCsrf();

$product_id = intInput($_POST, 'product_id', 0);
$buyer_id = $currentUserId;

/**
 * แก้ช่องโหว่ร้ายแรง (IDOR): โค้ดเดิมรับ seller_id ตรงจาก $_POST['seller_id']
 * โดยไม่ตรวจสอบเลยว่าตรงกับผู้ขายจริงของสินค้าชิ้นนั้นหรือไม่ ทำให้ผู้โจมตีสามารถ
 * ปลอมแปลง seller_id เป็นใครก็ได้ (หรือแม้แต่ตัวเอง) เพื่อสร้างห้องแชทปลอมเชื่อมโยงกับ user คนใดก็ได้
 * แก้โดยดึง seller_id จากฐานข้อมูลของสินค้านั้นโดยตรงเสมอ ไม่เชื่อค่าที่ client ส่งมา
 */
$productStmt = $pdo->prepare("SELECT seller_id, status FROM products WHERE product_id = ?");
$productStmt->execute([$product_id]);
$product = $productStmt->fetch();

if (!$product) {
    echo "<p class='error-msg' style='max-width:640px; margin:0 auto;'>ไม่พบสินค้านี้</p>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$seller_id = $product['seller_id'];

if ($seller_id == $buyer_id) {
    echo "<p class='error-msg' style='max-width:640px; margin:0 auto;'>ไม่สามารถแชทกับสินค้าของตัวเองได้</p>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

// เช็คว่ามีห้องแชทอยู่แล้วไหม
$stmt = $pdo->prepare("SELECT chat_id FROM chats WHERE product_id = ? AND buyer_id = ? AND seller_id = ?");
$stmt->execute([$product_id, $buyer_id, $seller_id]);
$chat = $stmt->fetch();

if ($chat) {
    $chat_id = $chat['chat_id'];
} else {
    $stmt = $pdo->prepare("INSERT INTO chats (buyer_id, seller_id, product_id) VALUES (?, ?, ?)");
    $stmt->execute([$buyer_id, $seller_id, $product_id]);
    $chat_id = $pdo->lastInsertId();
}

header("Location: room.php?id=" . $chat_id);
exit;
