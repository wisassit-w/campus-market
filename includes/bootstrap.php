<?php
/**
 * Bootstrap ที่รวม session/security config ไว้ที่เดียว
 * ทุกไฟล์ควร require_once ไฟล์นี้เป็นบรรทัดแรกสุด (ก่อนมี output ใด ๆ)
 */

require_once __DIR__ . '/../config/database.php';

// ---------- Session hardening ----------
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps, // ส่ง cookie เฉพาะ HTTPS เมื่อรันบน HTTPS จริง
        'httponly' => true,     // กัน JavaScript อ่าน session cookie (ลดผลกระทบของ XSS)
        'samesite' => 'Lax',    // ลดความเสี่ยง CSRF จากการฝัง request ข้ามไซต์
    ]);
    session_start();
}

// ---------- Security headers ----------
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // หมายเหตุ: หน้า track.php โหลด Leaflet จาก unpkg.com จึงไม่ตั้ง CSP ที่เข้มงวดมากในตัวอย่างนี้
    // เมื่อขึ้น production จริง แนะนำ self-host Leaflet แล้วเปิด CSP แบบเข้มงวดเพิ่มเติม
}

// ---------- Output buffering ----------
// header.php จะ echo HTML ทันที (nav bar) ทำให้ header("Location: ...") ที่เรียกในหน้าอื่น ๆ
// หลัง include header.php ล้มเหลวเพราะ "headers already sent" เปิด output buffering ที่นี่
// เพื่อให้ redirect ทำงานได้ถูกต้องเสมอ แม้จะมี HTML ถูก echo ออกไปก่อนหน้าแล้วก็ตาม
if (!ob_get_level()) {
    ob_start();
}

$isLoggedIn = isset($_SESSION['user_id']);
$currentUserId = $_SESSION['user_id'] ?? null;
$currentRole   = $_SESSION['role'] ?? null;

/**
 * บังคับให้ต้อง login ก่อนเข้าหน้านี้ ถ้าไม่ login จะ redirect ไป login.php ทันที
 */
function requireLogin(string $loginPathFromRoot = '/campus-market/auth/login.php'): void
{
    global $isLoggedIn;
    if (!$isLoggedIn) {
        header("Location: {$loginPathFromRoot}");
        exit;
    }
}

/**
 * บังคับสิทธิ์ admin เท่านั้น
 */
function requireAdmin(string $loginPathFromRoot = '/campus-market/auth/login.php'): void
{
    global $isLoggedIn, $currentRole;
    if (!$isLoggedIn || $currentRole !== 'admin') {
        header("Location: {$loginPathFromRoot}");
        exit;
    }
}

// ---------- CSRF protection ----------
/**
 * คืนค่า CSRF token ของ session ปัจจุบัน (สร้างใหม่ถ้ายังไม่มี)
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * คืนค่า HTML hidden input สำหรับฝังใน <form>
 */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

/**
 * ตรวจสอบ CSRF token จาก POST (เรียกที่จุดเริ่มต้นของทุก endpoint ที่เปลี่ยนแปลงข้อมูล)
 * ถ้าไม่ผ่านจะหยุดการทำงานทันทีด้วย HTTP 403
 */
function verifyCsrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submitted)) {
        http_response_code(403);
        die('คำขอไม่ถูกต้อง (CSRF token invalid) กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }
}

/**
 * Helper: ดึงค่า int แบบปลอดภัยจาก array (GET/POST) พร้อมค่า default
 */
/**
 * เช็คว่าไฟล์รูปภาพที่บันทึก path ไว้ใน DB มีอยู่จริงบนดิสก์หรือไม่
 * แก้ปัญหา: เดิมเช็คแค่ "มีค่าใน DB ไหม" ถ้าไฟล์เคยถูกลบ/อัปโหลดไม่สำเร็จแต่ path
 * ยังค้างอยู่ใน DB จะโดน browser แสดงไอคอนรูปหักแทนกล่อง placeholder ที่ตั้งใจไว้
 * ใช้ได้กับไฟล์ที่เรียกจากโฟลเดอร์ระดับ 1 ชั้นใต้ root เท่านั้น (เช่น products/, shops/, cart/, chat/)
 */
function productImageExists(?string $relativePath): bool
{
    if (!$relativePath) {
        return false;
    }
    return is_file(__DIR__ . '/../' . $relativePath);
}

function intInput(array $source, string $key, int $default = 0): int
{
    return isset($source[$key]) && is_numeric($source[$key]) ? (int) $source[$key] : $default;
}
