<?php
require_once __DIR__ . '/../includes/bootstrap.php';

requireLogin('../auth/login.php');

/**
 * === สรุปช่องโหว่ระดับ Critical ที่พบในไฟล์นี้ (ก่อนแก้ไข) ===
 *
 * 1) CSRF: endpoint นี้เปลี่ยนสถานะธุรกรรมและ "โอนเงินในกระเป๋า" ได้ แต่เดิมทำงานผ่าน
 *    GET link ธรรมดา (update_status.php?id=..&status=completed) — ผู้โจมตีสามารถฝัง
 *    <img src="..."> หรือลิงก์ในหน้าอื่นเพื่อหลอกให้เหยื่อที่ login ค้างอยู่กดโอนเงินโดยไม่ตั้งใจ
 *
 * 2) Double-spend / Replay (Race Condition): โค้ดเดิมไม่เช็คว่าสถานะปัจจุบันเป็น 'confirmed'
 *    ก่อนเปลี่ยนเป็น 'completed' และไม่ได้ lock แถวข้อมูลระหว่างตรวจสอบยอดเงินกับตัดยอดเงิน
 *    (TOCTOU) ทำให้หาก "ยิง request status=completed ซ้ำ 2 ครั้งพร้อมกัน" (หรือแค่กดปุ่ม/รีเฟรชซ้ำ)
 *    ระบบจะตัดเงินผู้ซื้อและโอนเงินให้ผู้ขาย "ซ้ำสอง" ได้ทันที นี่คือช่องโหว่ทางการเงินที่ร้ายแรงที่สุดในระบบ
 *
 * 3) State machine bypass: ไม่มีการบังคับลำดับสถานะที่ถูกต้อง (pending -> confirmed -> completed)
 *    ทำให้ผู้ซื้อสามารถยิง request ตรงไปที่ status=completed ได้เลยโดยผู้ขายไม่เคยกด "ยืนยันนัดหมาย" มาก่อน
 *
 * แก้ไขทั้งหมดด้วย: บังคับ POST+CSRF, ใช้ SELECT...FOR UPDATE ภายใน DB transaction เพื่อ lock แถว,
 * ตรวจสอบสถานะปัจจุบันตาม state machine ที่ถูกต้องก่อนเปลี่ยนสถานะทุกครั้ง (รวมถึง idempotency check)
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: list.php");
    exit;
}
verifyCsrf();

$id = intInput($_POST, 'id', 0);
$status = $_POST['status'] ?? '';
$allowed = ['confirmed', 'completed', 'cancelled'];
$user_id = $currentUserId;

if (!in_array($status, $allowed, true) || $id <= 0) {
    header("Location: list.php");
    exit;
}

try {
    $pdo->beginTransaction();

    // SELECT ... FOR UPDATE ล็อกแถวธุรกรรมนี้ไว้ตลอด transaction กันไม่ให้ request คู่ขนาน
    // (เช่นดับเบิลคลิก หรือ replay CSRF) เข้ามาประมวลผลทับซ้อนกันได้ (แก้ Race Condition / Double-spend)
    $stmt = $pdo->prepare("
        SELECT t.*, p.price, p.seller_id, p.product_id
        FROM transactions t
        JOIN products p ON t.product_id = p.product_id
        WHERE t.transaction_id = ?
        FOR UPDATE
    ");
    $stmt->execute([$id]);
    $tx = $stmt->fetch();

    // กันคนอื่นยิง URL มาแก้ธุรกรรมที่ไม่ใช่ของตัวเอง (IDOR)
    if (!$tx || ($tx['buyer_id'] != $user_id && $tx['seller_id'] != $user_id)) {
        $pdo->rollBack();
        header("Location: list.php");
        exit;
    }

    // ---- บังคับ state machine ที่ถูกต้อง + สิทธิ์ตามบทบาท ----
    $isBuyer = ($tx['buyer_id'] == $user_id);
    $isSeller = ($tx['seller_id'] == $user_id);
    $currentStatus = $tx['status'];
    $transitionAllowed = false;

    if ($status === 'confirmed') {
        // เฉพาะผู้ขายเท่านั้นที่ยืนยันนัดหมายได้ และต้องอยู่ในสถานะ pending เท่านั้น
        $transitionAllowed = ($isSeller && $currentStatus === 'pending');
    } elseif ($status === 'completed') {
        // ต้องยืนยันแล้วเท่านั้นถึงจะปิดรายการได้ (กันข้ามขั้นตอน + กันยิงซ้ำ/double-spend)
        $transitionAllowed = ($currentStatus === 'confirmed');
    } elseif ($status === 'cancelled') {
        // ยกเลิกได้ทั้งสองฝ่าย แต่ต้องยังไม่จบรายการ
        $transitionAllowed = in_array($currentStatus, ['pending', 'confirmed'], true);
    }

    if (!$transitionAllowed) {
        $pdo->rollBack();
        header("Location: list.php");
        exit;
    }

    if ($status === 'completed') {
        if ($tx['payment_method'] === 'wallet') {
            // ล็อกแถวผู้ซื้อ/ผู้ขายด้วย FOR UPDATE เพื่อตัด-เติมยอดเงินอย่างปลอดภัยจาก race condition
            $buyerRow = $pdo->prepare("SELECT wallet_balance FROM users WHERE user_id = ? FOR UPDATE");
            $buyerRow->execute([$tx['buyer_id']]);
            $buyerBalance = (float) $buyerRow->fetchColumn();

            if ($buyerBalance < $tx['price']) {
                $pdo->rollBack();
                header("Location: list.php?error=insufficient_credit");
                exit;
            }

            $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE user_id = ?")
                ->execute([$tx['price'], $tx['buyer_id']]);
            $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE user_id = ?")
                ->execute([$tx['price'], $tx['seller_id']]);

            $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, related_transaction_id, note) VALUES (?, 'payment', ?, ?, ?)")
                ->execute([$tx['buyer_id'], $tx['price'], $id, 'จ่ายค่าสินค้า']);
            $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, related_transaction_id, note) VALUES (?, 'receive', ?, ?, ?)")
                ->execute([$tx['seller_id'], $tx['price'], $id, 'ได้รับเงินค่าขาย']);
        }

        $pdo->prepare("UPDATE transactions SET status = 'completed', completed_at = NOW() WHERE transaction_id = ?")
            ->execute([$id]);
        $pdo->prepare("UPDATE products SET status = 'sold' WHERE product_id = ?")
            ->execute([$tx['product_id']]);
    } else {
        $pdo->prepare("UPDATE transactions SET status = ? WHERE transaction_id = ?")
            ->execute([$status, $id]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[transactions/update_status] ' . $e->getMessage());
    header("Location: list.php");
    exit;
}

header("Location: list.php");
exit;
