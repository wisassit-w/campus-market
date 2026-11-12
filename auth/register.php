<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$error = '';
$old = ['student_id' => '', 'name' => '', 'email' => '', 'phone' => '', 'faculty' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $student_id = trim($_POST['student_id'] ?? '');
    $name       = trim($_POST['name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $faculty    = trim($_POST['faculty'] ?? '');
    $password   = (string) ($_POST['password'] ?? '');

    $old = compact('student_id', 'name', 'email', 'phone', 'faculty');

    if (!preg_match('/^[0-9A-Za-z\-]{4,20}$/', $student_id)) {
        $error = "รหัสนักศึกษาไม่ถูกต้อง";
    } elseif ($name === '' || mb_strlen($name) > 100) {
        $error = "กรุณากรอกชื่อ-นามสกุลให้ถูกต้อง";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || !str_ends_with(strtolower($email), '@hu.ac.th')) {
        $error = "กรุณาใช้อีเมลมหาวิทยาลัย (@hu.ac.th) ที่ถูกต้อง";
    } elseif ($phone !== '' && !preg_match('/^[0-9\-+() ]{6,20}$/', $phone)) {
        $error = "เบอร์โทรไม่ถูกต้อง";
    } elseif (mb_strlen($password) < 8) {
        $error = "รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร";
    } else {
        $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ? OR student_id = ?");
        $check->execute([$email, $student_id]);

        if ($check->rowCount() > 0) {
            $error = "อีเมลหรือรหัสนักศึกษานี้ถูกใช้งานแล้ว";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare("INSERT INTO users (student_id, name, email, phone, password, faculty) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$student_id, $name, $email, $phone !== '' ? $phone : null, $hashed, $faculty !== '' ? $faculty : null]);

                header("Location: login.php?registered=1");
                exit;
            } catch (PDOException $e) {
                // เผื่อกรณี race condition: สอง request สมัครพร้อมกันด้วยอีเมล/รหัสนักศึกษาเดียวกัน
                // UNIQUE constraint ในฐานข้อมูลจะกันซ้ำให้ที่ระดับ DB (error code 23000)
                if ($e->getCode() === '23000') {
                    $error = "อีเมลหรือรหัสนักศึกษานี้ถูกใช้งานแล้ว";
                } else {
                    throw $e;
                }
            }
        }
    }
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<h2>สมัครสมาชิก</h2>
<?php if ($error): ?>
    <p class="error-msg"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="POST">
    <?= csrfField() ?>
    <label>รหัสนักศึกษา</label>
    <input type="text" name="student_id" required maxlength="20" value="<?= htmlspecialchars($old['student_id']) ?>"><br>

    <label>ชื่อ-นามสกุล</label>
    <input type="text" name="name" required maxlength="100" value="<?= htmlspecialchars($old['name']) ?>"><br>

    <label>อีเมลมหาลัย (@hu.ac.th)</label>
    <input type="email" name="email" required maxlength="150" value="<?= htmlspecialchars($old['email']) ?>"><br>

    <label>เบอร์โทร</label>
    <input type="text" name="phone" maxlength="20" value="<?= htmlspecialchars($old['phone']) ?>"><br>

    <label>คณะ</label>
    <input type="text" name="faculty" maxlength="100" value="<?= htmlspecialchars($old['faculty']) ?>"><br>

    <label>รหัสผ่าน (อย่างน้อย 8 ตัวอักษร)</label>
    <input type="password" name="password" required minlength="8" maxlength="255"><br>

    <button type="submit">สมัครสมาชิก</button>
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>
