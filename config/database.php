<?php
/**
 * Database connection (hardened)
 * - Credentials read from environment variables (never hardcoded in source).
 * - PDO configured with real prepared statements + exceptions.
 * - No sensitive error detail is ever echoed to the client.
 */

// โหลดค่าจาก ENV ก่อน ถ้าไม่มีค่อย fallback (fallback ใช้สำหรับ local dev เท่านั้น
// ในเซิร์ฟเวอร์จริงให้ตั้งค่า ENV เหล่านี้ผ่าน Apache/Nginx/.env จริง ๆ ห้าม commit รหัสผ่านลง repo)
$host     = getenv('DB_HOST') ?: '127.0.0.1';
$dbname   = getenv('DB_NAME') ?: 'marketplace_db';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';
$charset  = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false, // ใช้ prepared statement จริงของ MySQL ป้องกัน edge-case ของ SQL Injection
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset}",
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    // ห้าม echo $e->getMessage() ออกไปสู่ผู้ใช้เด็ดขาด (leak host/user/schema)
    error_log('[DB CONNECTION ERROR] ' . $e->getMessage());
    http_response_code(500);
    die('ระบบขัดข้องชั่วคราว กรุณาลองใหม่ภายหลัง');
}

/**
 * Global handler กันไม่ให้ PDOException / Exception ที่ไม่ได้ catch ไว้ ทำให้เกิดการรั่วไหลของ
 * SQL query, path, stack trace ออกสู่ผู้ใช้ (Information Disclosure)
 */
set_exception_handler(function (Throwable $e) {
    error_log('[UNCAUGHT] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo 'เกิดข้อผิดพลาดบางอย่าง กรุณาลองใหม่อีกครั้ง';
    exit;
});

// ปิดการแสดง error โดยตรงบนหน้าเว็บ (production) — เปิด log แทน
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
