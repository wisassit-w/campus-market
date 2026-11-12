<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$_SESSION = [];

// ลบ session cookie ฝั่ง browser ด้วย ไม่ใช่แค่ session_destroy() ฝั่งเซิร์ฟเวอร์
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_destroy();
header("Location: ../index.php");
exit;
