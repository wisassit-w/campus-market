<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$error = '';
$MAX_ATTEMPTS = 5;
$LOCK_MINUTES = 15;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $login_input = trim($_POST['login_input'] ?? '');
    $password    = (string) ($_POST['password'] ?? '');

    if ($login_input === '' || $password === '') {
        $error = "กรุณากรอกข้อมูลให้ครบถ้วน";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR student_id = ?");
        $stmt->execute([$login_input, $login_input]);
        $user = $stmt->fetch();

        $isLocked = $user && !empty($user['locked_until']) && strtotime($user['locked_until']) > time();

        if ($isLocked) {
            $error = "บัญชีนี้ถูกล็อกชั่วคราวเนื่องจากพยายามเข้าสู่ระบบผิดหลายครั้ง กรุณาลองใหม่ภายหลัง";
        } elseif ($user && password_verify($password, $user['password'])) {
            // ป้องกัน Session Fixation: สร้าง session id ใหม่ทุกครั้งหลัง login สำเร็จ
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['role']    = $user['role'];

            // reset ตัวนับ failed attempts
            $pdo->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE user_id = ?")
                ->execute([$user['user_id']]);

            header("Location: ../products/list.php");
            exit;
        } else {
            // ข้อความ error กว้าง ๆ เหมือนกันไม่ว่าจะผิดที่ email หรือ password
            // (กัน user enumeration)
            $error = "อีเมล/รหัสนักศึกษา หรือรหัสผ่านไม่ถูกต้อง";

            if ($user) {
                $attempts = (int) $user['failed_login_attempts'] + 1;
                if ($attempts >= $MAX_ATTEMPTS) {
                    $pdo->prepare("UPDATE users SET failed_login_attempts = ?, locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE user_id = ?")
                        ->execute([$attempts, $LOCK_MINUTES, $user['user_id']]);
                } else {
                    $pdo->prepare("UPDATE users SET failed_login_attempts = ? WHERE user_id = ?")
                        ->execute([$attempts, $user['user_id']]);
                }
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<h2>เข้าสู่ระบบ</h2>
<?php if (isset($_GET['registered'])): ?>
    <p class="success-msg">สมัครสมาชิกสำเร็จ กรุณาเข้าสู่ระบบ</p>
<?php endif; ?>
<?php if ($error): ?>
    <p class="error-msg"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="POST">
    <?= csrfField() ?>
    <label>อีเมล หรือ รหัสนักศึกษา</label>
    <input type="text" name="login_input" required maxlength="150" value="<?= htmlspecialchars($_POST['login_input'] ?? '') ?>">

    <label>รหัสผ่าน</label>
    <input type="password" name="password" required maxlength="255">

    <button type="submit">เข้าสู่ระบบ</button>
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>
