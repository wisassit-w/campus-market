<?php
require_once __DIR__ . '/../includes/bootstrap.php';

requireAdmin('../auth/login.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}
verifyCsrf();

$id = intInput($_POST, 'id', 0);

/**
 * แก้ช่องโหว่: โค้ดเดิมซ่อนปุ่ม "ลบ" ไว้เฉพาะฝั่ง UI เมื่อเป็นบัญชีตัวเอง แต่ backend
 * (ไฟล์นี้) ไม่มีการตรวจสอบใด ๆ เลย — หากมีคนสร้าง request ตรงมา (หรือถูกโจมตีด้วย CSRF
 * ก่อนที่จะเพิ่มการป้องกัน CSRF) ก็สามารถลบบัญชี admin ของตัวเองได้ ทำให้ระบบไม่มี admin เหลือ (lockout)
 * จึงเพิ่มการตรวจสอบฝั่ง server ทั้งกันลบตัวเอง และกันลบ admin คนสุดท้ายของระบบ
 */
if ($id === (int) $currentUserId) {
    header("Location: dashboard.php");
    exit;
}

try {
    $targetStmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
    $targetStmt->execute([$id]);
    $target = $targetStmt->fetch();

    if ($target && $target['role'] === 'admin') {
        $adminCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
        if ($adminCount <= 1) {
            // ห้ามลบ admin คนสุดท้าย
            header("Location: dashboard.php");
            exit;
        }
    }

    if ($id > 0) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->execute([$id]);
    }
} catch (Throwable $e) {
    error_log('[admin/delete_user] ' . $e->getMessage());
}

header("Location: dashboard.php");
exit;
