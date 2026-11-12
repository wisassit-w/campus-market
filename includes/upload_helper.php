<?php
/**
 * Secure image upload helper
 * แก้ช่องโหว่ร้ายแรง (Critical): เดิมโค้ดใช้ชื่อไฟล์จากผู้ใช้ตรง ๆ (time().'_'.$_FILES['x']['name'])
 * โดยไม่ตรวจสอบนามสกุล/ชนิดไฟล์เลย -> ผู้โจมตีสามารถอัปโหลดไฟล์ .php (webshell) เข้าไปใน
 * assets/uploads/ ซึ่ง Apache/PHP จะรันได้ทันที = Remote Code Execution (RCE)
 * และในกรณี wallet/topup.php ยังใช้ชื่อไฟล์ผู้ใช้ตรง ๆ โดยไม่ผ่าน basename() -> Path Traversal
 *
 * ฟังก์ชันนี้:
 *  - ใช้ basename() ตัด path ทิ้งเสมอ (กัน Path Traversal)
 *  - ตรวจนามสกุลไฟล์กับ whitelist
 *  - ตรวจ MIME type จริงด้วย finfo (ไม่เชื่อ Content-Type ที่ browser ส่งมา)
 *  - ตรวจว่าเป็นรูปภาพจริงด้วย getimagesize()
 *  - สุ่มชื่อไฟล์ใหม่ทั้งหมดด้วย random_bytes (ไม่ใช้ชื่อเดิมของผู้ใช้เลย)
 *  - จำกัดขนาดไฟล์
 */

class UploadException extends RuntimeException {}

/**
 * @param array  $file      องค์ประกอบเดียวจาก $_FILES (เช่น $_FILES['logo'])
 * @param string $uploadDir path เต็มของโฟลเดอร์ปลายทาง (ต้องมี trailing slash)
 * @param string $prefix    คำนำหน้าชื่อไฟล์ เช่น 'shop_', 'slip_'
 * @return string|null      relative path ('assets/uploads/xxx.jpg') หรือ null ถ้าไม่มีไฟล์แนบ
 * @throws UploadException  ถ้าไฟล์ไม่ผ่านการตรวจสอบ
 */
function secureImageUpload(array $file, string $uploadDir, string $prefix = ''): ?string
{
    // ไม่มีไฟล์แนบมา (optional upload) — ไม่ใช่ error
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new UploadException('อัปโหลดไฟล์ไม่สำเร็จ (error code: ' . $file['error'] . ')');
    }

    $maxBytes = 5 * 1024 * 1024; // 5 MB
    if ($file['size'] > $maxBytes) {
        throw new UploadException('ไฟล์มีขนาดใหญ่เกินไป (สูงสุด 5MB)');
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        throw new UploadException('ไฟล์ที่อัปโหลดไม่ถูกต้อง');
    }

    // ตรวจ MIME จริงจากเนื้อไฟล์ ไม่เชื่อ header ที่ client ส่งมา
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    $allowedMimeToExt = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    if (!isset($allowedMimeToExt[$mime])) {
        throw new UploadException('รองรับเฉพาะไฟล์รูปภาพ JPG, PNG, WEBP, GIF เท่านั้น');
    }

    // ยืนยันซ้ำว่าเป็นรูปภาพจริง (กัน polyglot ที่ปลอมนามสกุล/MIME)
    if (@getimagesize($file['tmp_name']) === false) {
        throw new UploadException('ไฟล์ที่อัปโหลดไม่ใช่รูปภาพที่ถูกต้อง');
    }

    $ext = $allowedMimeToExt[$mime];
    // สุ่มชื่อไฟล์ใหม่ทั้งหมด ไม่ใช้ basename($file['name']) เลย เพื่อตัดปัญหาทั้ง
    // path traversal และการปลอมนามสกุลไฟล์ในชื่อ (เช่น "a.jpg.php")
    $newFileName = $prefix . bin2hex(random_bytes(16)) . '.' . $ext;
    $destination = rtrim($uploadDir, '/') . '/' . $newFileName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new UploadException('ไม่สามารถบันทึกไฟล์ได้ กรุณาลองใหม่');
    }

    @chmod($destination, 0644);

    return 'assets/uploads/' . $newFileName;
}
