<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/upload_helper.php';

requireLogin('../auth/login.php');

$user_id = $currentUserId;
$error = '';

$stmt = $pdo->prepare("SELECT * FROM shops WHERE seller_id = ?");
$stmt->execute([$user_id]);
$shop = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $shop_name = trim($_POST['shop_name'] ?? '');
    $shop_description = trim($_POST['shop_description'] ?? '');
    $shop_description = mb_substr($shop_description, 0, 2000);

    if ($shop_name === '' || mb_strlen($shop_name) > 150) {
        $error = "กรุณากรอกชื่อร้านให้ถูกต้อง (ไม่เกิน 150 ตัวอักษร)";
    } else {
        try {
            $logo_url = $shop['logo_url'] ?? null;
            $newLogo = secureImageUpload($_FILES['logo'] ?? [], __DIR__ . '/../assets/uploads/', 'shop_' . $user_id . '_');

            if ($newLogo !== null) {
                // ลบโลโก้เก่าทิ้งเพื่อไม่ให้ไฟล์สะสมค้างในดิสก์โดยไม่จำเป็น
                if (!empty($shop['logo_url'])) {
                    $oldPath = __DIR__ . '/../' . $shop['logo_url'];
                    if (is_file($oldPath)) {
                        @unlink($oldPath);
                    }
                }
                $logo_url = $newLogo;
            }

            if ($shop) {
                $stmt = $pdo->prepare("UPDATE shops SET shop_name=?, shop_description=?, logo_url=? WHERE seller_id=?");
                $stmt->execute([$shop_name, $shop_description, $logo_url, $user_id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO shops (seller_id, shop_name, shop_description, logo_url) VALUES (?, ?, ?, ?)");
                $stmt->execute([$user_id, $shop_name, $shop_description, $logo_url]);
            }

            header("Location: view.php?seller_id=" . $user_id);
            exit;
        } catch (UploadException $e) {
            $error = $e->getMessage();
        }
    }
}
?>

<h2><?= $shop ? 'แก้ไขร้านค้า' : 'เปิดร้านค้าของฉัน' ?></h2>
<p style="color:var(--text-muted); font-size:14.5px; margin-top:-10px; margin-bottom:20px; max-width:560px;">
    ตั้งชื่อร้านและโลโก้ให้ลูกค้าจดจำได้ง่าย สินค้าที่คุณลงขายทั้งหมดจะรวมอยู่ในหน้าร้านนี้อัตโนมัติ
</p>

<?php if ($error): ?>
    <p class="error-msg" style="max-width:560px;"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
    <?= csrfField() ?>

    <label style="margin-top:0;">โลโก้ร้าน</label>
    <div style="display:flex; align-items:center; gap:16px; margin-top:8px;">
        <label for="logoInput" style="position:relative; cursor:pointer; flex-shrink:0;">
            <div id="logoPreviewWrap" style="width:84px; height:84px; border-radius:20px; overflow:hidden; background:var(--hu-green-light); display:flex; align-items:center; justify-content:center; border:1px solid var(--border);">
                <?php if (!empty($shop['logo_url']) && productImageExists($shop['logo_url'])): ?>
                    <img id="logoPreview" src="../<?= htmlspecialchars($shop['logo_url']) ?>" style="width:100%; height:100%; object-fit:cover;">
                <?php else: ?>
                    <span id="logoPlaceholder" style="font-size:32px;">🏪</span>
                    <img id="logoPreview" src="" style="width:100%; height:100%; object-fit:cover; display:none;">
                <?php endif; ?>
            </div>
            <div style="position:absolute; bottom:-4px; right:-4px; width:28px; height:28px; border-radius:50%; background:var(--hu-green); color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; border:2px solid #fff; box-shadow:var(--shadow-sm);">
                📷
            </div>
        </label>
        <div style="font-size:13px; color:var(--text-muted);">
            คลิกที่รูปเพื่ออัปโหลด<br>แนะนำไฟล์สี่เหลี่ยมจัตุรัส ไม่เกิน 5MB
        </div>
    </div>
    <input type="file" id="logoInput" name="logo" accept="image/png,image/jpeg,image/webp,image/gif" style="display:none;">

    <label>ชื่อร้าน</label>
    <input type="text" name="shop_name" value="<?= htmlspecialchars($shop['shop_name'] ?? '') ?>" placeholder="เช่น ร้านของมือสองบอม" required maxlength="150">

    <label>คำอธิบายร้าน</label>
    <textarea name="shop_description" rows="4" maxlength="2000" placeholder="บอกลูกค้าว่าร้านคุณขายอะไร มีจุดเด่นอะไรบ้าง"><?= htmlspecialchars($shop['shop_description'] ?? '') ?></textarea>

    <button type="submit"><?= $shop ? 'บันทึกการแก้ไข' : 'เปิดร้าน' ?></button>
</form>

<script>
    document.getElementById('logoInput').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        const url = URL.createObjectURL(file);
        const preview = document.getElementById('logoPreview');
        const placeholder = document.getElementById('logoPlaceholder');
        preview.src = url;
        preview.style.display = 'block';
        if (placeholder) placeholder.style.display = 'none';
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
