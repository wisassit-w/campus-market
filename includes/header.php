<?php
require_once __DIR__ . '/bootstrap.php';

function isActive(string $path): string
{
    return strpos($_SERVER['REQUEST_URI'] ?? '', $path) !== false ? 'active' : '';
}

// รวม query cart count + wallet balance เป็นรอบเดียว (ลดจำนวน round-trip ไป DB ต่อการโหลดหน้าเว็บทุกหน้า)
$cartCount = 0;
$walletBalance = 0.0;
$pendingTopupCount = 0;

if ($isLoggedIn) {
    $navStmt = $pdo->prepare("
        SELECT
            (SELECT COUNT(*) FROM cart_items WHERE user_id = :uid1) AS cart_count,
            (SELECT wallet_balance FROM users WHERE user_id = :uid2) AS wallet_balance
    ");
    $navStmt->execute(['uid1' => $currentUserId, 'uid2' => $currentUserId]);
    $navRow = $navStmt->fetch();
    $cartCount = (int) ($navRow['cart_count'] ?? 0);
    $walletBalance = (float) ($navRow['wallet_balance'] ?? 0);

    if ($currentRole === 'admin') {
        $pendingTopupCount = (int) $pdo->query("SELECT COUNT(*) FROM wallet_topup_requests WHERE status = 'pending'")->fetchColumn();
    }
}

/**
 * ย่อตัวเลขยอดเครดิตให้อ่านง่ายในพื้นที่จำกัด (เช่น 10,602,012 -> 10.6M)
 * ใช้เฉพาะจุดแสดงผลในแถบเมนู/badge เท่านั้น หน้า wallet/index.php ยังโชว์ตัวเต็มตามเดิม
 */
function formatCreditsShort(float $amount): string
{
    if ($amount >= 1000000) {
        return number_format($amount / 1000000, 1) . 'M';
    }
    if ($amount >= 10000) {
        return number_format($amount / 1000, 1) . 'K';
    }
    return number_format($amount, 0);
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HU Market — ตลาดนัดนักศึกษา ม.หาดใหญ่</title>
    <link rel="stylesheet" href="/campus-market/assets/css/style.css">
</head>

<body>
    <nav>
        <div class="nav-inner">
            <a href="/campus-market/index.php" class="brand">HU Market</a>

            <?php if ($isLoggedIn): ?>
                <button type="button" class="nav-toggle" id="navToggle" aria-label="เปิดเมนู">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
                </button>

                <div class="nav-links" id="navLinks">
                    <!-- เมนูหลัก: แสดงตลอดทั้งบนจอใหญ่และในเมนูมือถือ -->
                    <a href="/campus-market/products/list.php" class="<?= isActive('/products/list.php') ?> bottom-nav-mirror">สินค้าทั้งหมด</a>
                    <a href="/campus-market/shops/list.php" class="<?= isActive('/shops/list.php') ?> bottom-nav-mirror">🏪 ร้านค้า</a>
                    <a href="/campus-market/products/create.php" class="<?= isActive('/products/create.php') ?>">ลงขาย</a>
                    <a href="/campus-market/chat/index.php" class="<?= isActive('/chat/') ?> bottom-nav-mirror">แชท</a>
                    <a href="/campus-market/cart/index.php" class="<?= isActive('/cart/') ?> bottom-nav-mirror">
                        🛒 ตะกร้า
                        <?php if ($cartCount > 0): ?><span class="badge"><?= $cartCount ?></span><?php endif; ?>
                    </a>
                    <a href="/campus-market/wallet/index.php" class="<?= isActive('/wallet/') ?> bottom-nav-mirror">💰 <?= formatCreditsShort($walletBalance) ?></a>

                    <!-- เมนูรอง: ซ่อนบนจอใหญ่ (ไปโผล่ในเมนู 3 ขีดแทน) แต่ยังอยู่ในเมนูมือถือ -->
                    <a href="/campus-market/shops/view.php?seller_id=<?= (int) $currentUserId ?>" class="nav-secondary">ร้านของฉัน</a>
                    <a href="/campus-market/transactions/list.php" class="<?= isActive('/transactions/') ?> nav-secondary">นัดรับของ</a>
                    <?php if ($currentRole === 'admin'): ?>
                        <a href="/campus-market/admin/dashboard.php" class="<?= isActive('/admin/dashboard.php') ?> nav-secondary">Admin</a>
                        <a href="/campus-market/admin/wallet_requests.php" class="<?= isActive('/admin/wallet_requests.php') ?> nav-secondary">
                            💳 คำขอเติมเครดิต
                            <?php if ($pendingTopupCount > 0): ?><span class="badge"><?= $pendingTopupCount ?></span><?php endif; ?>
                        </a>
                    <?php endif; ?>
                    <a href="/campus-market/auth/logout.php" class="logout-link nav-secondary">ออกจากระบบ</a>
                </div>

                <!-- เมนู 3 ขีด: ซ้ำเมนูรองไว้อีกชุดสำหรับจอใหญ่ (มือถือใช้เมนู hamburger ด้านบนแทน ไม่โชว์ปุ่มนี้) -->
                <div class="kebab-wrap">
                    <button type="button" class="kebab-btn" id="kebabToggle" aria-label="เมนูเพิ่มเติม">⋮</button>
                    <div class="kebab-menu" id="kebabMenu">
                        <a href="/campus-market/shops/view.php?seller_id=<?= (int) $currentUserId ?>">🏪 ร้านของฉัน</a>
                        <a href="/campus-market/transactions/list.php">📦 นัดรับของ</a>
                        <?php if ($currentRole === 'admin'): ?>
                            <a href="/campus-market/admin/dashboard.php">🛠️ Admin</a>
                            <a href="/campus-market/admin/wallet_requests.php">
                                💳 คำขอเติมเครดิต
                                <?php if ($pendingTopupCount > 0): ?><span class="kebab-badge"><?= $pendingTopupCount ?></span><?php endif; ?>
                            </a>
                        <?php endif; ?>
                        <a href="/campus-market/auth/logout.php" class="logout-item">🚪 ออกจากระบบ</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="nav-links">
                    <a href="/campus-market/auth/login.php">เข้าสู่ระบบ</a>
                    <a href="/campus-market/auth/register.php" class="btn-primary-nav">สมัครสมาชิก</a>
                </div>
            <?php endif; ?>
        </div>
    </nav>
    <div class="page-container">
