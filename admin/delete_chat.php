<?php
require_once __DIR__ . '/../includes/bootstrap.php';

requireAdmin('../auth/login.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: chats.php");
    exit;
}
verifyCsrf();

$id = intInput($_POST, 'id', 0);
if ($id > 0) {
    $stmt = $pdo->prepare("DELETE FROM chats WHERE chat_id = ?");
    $stmt->execute([$id]);
}

header("Location: chats.php");
exit;
