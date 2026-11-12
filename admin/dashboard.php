<?php
require_once __DIR__ . '/../includes/header.php';

requireAdmin('../auth/login.php');

// เพิ่ม LIMIT ป้องกันหน้าโหลดช้าลงเรื่อย ๆ เมื่อข้อมูลสินค้า/ผู้ใช้เพิ่มขึ้นมาก (เดิมดึงทั้งตารางไม่มี LIMIT)
$products = $pdo->query("
    SELECT p.*, u.name as seller_name
    FROM products p JOIN users u ON p.seller_id = u.user_id
    ORDER BY p.created_at DESC
    LIMIT 200
")->fetchAll();

$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 200")->fetchAll();

$totalProducts = (int) $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$soldCount = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'sold'")->fetchColumn();
$totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$availableCount = $totalProducts - $soldCount;

$csrf = csrfToken();
?>

<h2>Admin Dashboard</h2>

<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:14px; margin-bottom:20px;">
    <div class="info-card" style="text-align:center; margin:0;">
        <p style="font-size:13px; color:var(--text-muted);">สินค้าทั้งหมด</p>
        <p style="font-size:24px; font-weight:700; color:var(--hu-green-dark);"><?= $totalProducts ?></p>
    </div>
    <div class="info-card" style="text-align:center; margin:0;">
        <p style="font-size:13px; color:var(--text-muted);">ขายแล้ว</p>
        <p style="font-size:24px; font-weight:700; color:var(--hu-gold);"><?= $soldCount ?></p>
    </div>
    <div class="info-card" style="text-align:center; margin:0;">
        <p style="font-size:13px; color:var(--text-muted);">ยังขายอยู่</p>
        <p style="font-size:24px; font-weight:700; color:var(--hu-green);"><?= $availableCount ?></p>
    </div>
    <div class="info-card" style="text-align:center; margin:0;">
        <p style="font-size:13px; color:var(--text-muted);">ผู้ใช้ทั้งหมด</p>
        <p style="font-size:24px; font-weight:700; color:var(--hu-green-dark);"><?= $totalUsers ?></p>
    </div>
</div>

<div style="margin-bottom:20px;">
    <a href="chats.php" style="display:inline-flex; align-items:center; gap:8px; background:var(--hu-green); color:#fff; padding:10px 20px; border-radius:10px; text-decoration:none; font-weight:600; font-size:14.5px;">
        💬 จัดการห้องแชท
    </a>
</div>

<h3 style="padding:0 24px;">รายการสินค้า (แสดงล่าสุด <?= count($products) ?> จาก <?= $totalProducts ?>)</h3>
<table>
    <thead>
        <tr>
            <th>ชื่อสินค้า</th>
            <th>ผู้ขาย</th>
            <th>ราคา</th>
            <th>สถานะ</th>
            <th>จัดการ</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($products as $p): ?>
        <tr>
            <td data-label="ชื่อสินค้า"><?= htmlspecialchars($p['title']) ?></td>
            <td data-label="ผู้ขาย"><?= htmlspecialchars($p['seller_name']) ?></td>
            <td data-label="ราคา"><?= number_format((float) $p['price'], 2) ?></td>
            <td data-label="สถานะ">
                <?php if ($p['status'] === 'sold'): ?>
                    <span class="status-badge status-cancelled">ขายแล้ว</span>
                <?php else: ?>
                    <span class="status-badge status-completed">ยังขายอยู่</span>
                <?php endif; ?>
            </td>
            <td data-label="จัดการ">
                <form method="POST" action="delete_product.php" class="action-form" data-confirm="ลบสินค้านี้?">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= (int) $p['product_id'] ?>">
                    <button type="submit" style="background:none; border:none; color:#C0392B; text-decoration:underline; cursor:pointer; padding:0; font:inherit;">ลบ</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<h3 style="padding:0 24px;">รายชื่อผู้ใช้ (แสดงล่าสุด <?= count($users) ?> จาก <?= $totalUsers ?>)</h3>
<table>
    <thead>
        <tr>
            <th>ชื่อ</th>
            <th>อีเมล</th>
            <th>คณะ</th>
            <th>สิทธิ์</th>
            <th>จัดการ</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($users as $u): ?>
        <tr>
            <td data-label="ชื่อ"><?= htmlspecialchars($u['name']) ?></td>
            <td data-label="อีเมล"><?= htmlspecialchars($u['email']) ?></td>
            <td data-label="คณะ"><?= htmlspecialchars($u['faculty'] ?? '-') ?></td>
            <td data-label="สิทธิ์">
                <?php if ($u['role'] === 'admin'): ?>
                    <span class="status-badge status-confirmed">Admin</span>
                <?php else: ?>
                    <span class="status-badge status-pending">Student</span>
                <?php endif; ?>
            </td>
            <td data-label="จัดการ">
                <?php if ($u['user_id'] != $currentUserId): ?>
                    <form method="POST" action="delete_user.php" class="action-form" data-confirm="ลบผู้ใช้นี้?">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $u['user_id'] ?>">
                        <button type="submit" style="background:none; border:none; color:#C0392B; text-decoration:underline; cursor:pointer; padding:0; font:inherit;">ลบ</button>
                    </form>
                <?php else: ?>
                    (คุณ)
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php include __DIR__ . '/../includes/footer.php'; ?>
