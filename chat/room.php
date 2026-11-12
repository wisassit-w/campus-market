<?php
require_once __DIR__ . '/../includes/header.php';

requireLogin('../auth/login.php');

$chat_id = intInput($_GET, 'id', 0) ?: intInput($_POST, 'id', 0);
$user_id = $currentUserId;

$stmt = $pdo->prepare("SELECT * FROM chats WHERE chat_id = ? AND (buyer_id = ? OR seller_id = ?)");
$stmt->execute([$chat_id, $user_id, $user_id]);
$chat = $stmt->fetch();

if (!$chat) {
    echo "<p class='error-msg' style='max-width:640px; margin:0 auto;'>ไม่มีสิทธิ์เข้าถึงแชทนี้</p>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$statusTextMap = ['pending' => 'รอยืนยัน', 'confirmed' => 'ยืนยันแล้ว', 'completed' => 'สำเร็จ', 'cancelled' => 'ยกเลิก'];

$txStmt = $pdo->prepare("
    SELECT * FROM transactions
    WHERE product_id = ? AND buyer_id = ? AND status != 'cancelled'
    ORDER BY created_at DESC LIMIT 1
");
$txStmt->execute([$chat['product_id'], $chat['buyer_id']]);
$existingTx = $txStmt->fetch();

$isChatLocked = false;
if ($existingTx && $existingTx['status'] === 'completed' && $existingTx['completed_at']) {
    $hoursPassed = (time() - strtotime($existingTx['completed_at'])) / 3600;
    if ($hoursPassed > 24) $isChatLocked = true;
}

if (!$isChatLocked && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['message'])) {
    verifyCsrf();

    $messageText = trim($_POST['message']);
    $messageText = mb_substr($messageText, 0, 2000); // จำกัดความยาวข้อความ กัน DoS/DB abuse

    if ($messageText !== '') {
        $stmt = $pdo->prepare("INSERT INTO messages (chat_id, sender_id, message_text) VALUES (?, ?, ?)");
        $stmt->execute([$chat_id, $user_id, $messageText]);
    }
    header("Location: room.php?id=" . $chat_id);
    exit;
}

$stmt = $pdo->prepare("
    SELECT m.*, u.name as sender_name
    FROM messages m
    JOIN users u ON m.sender_id = u.user_id
    WHERE m.chat_id = ?
    ORDER BY m.sent_at ASC
");
$stmt->execute([$chat_id]);
$messages = $stmt->fetchAll();
?>

<h2>ห้องแชท</h2>

<div class="chat-box" id="chatBox">
    <?php foreach ($messages as $m): ?>
        <?php $isMine = $m['sender_id'] == $user_id; ?>
        <div class="msg-row <?= $isMine ? 'mine' : 'theirs' ?>">
            <div class="msg-avatar"><?= htmlspecialchars(mb_substr($m['sender_name'], 0, 1)) ?></div>
            <div class="msg-content">
                <div class="msg-bubble"><?= htmlspecialchars($m['message_text']) ?></div>
                <div class="msg-time"><?= htmlspecialchars(date('H:i', strtotime($m['sent_at']))) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($isChatLocked): ?>
    <p class="error-msg" style="max-width:640px;">
        แชทนี้ปิดการสนทนาแล้ว (เกิน 24 ชั่วโมงหลังนัดรับสินค้าสำเร็จ) — ดูประวัติได้อย่างเดียว
    </p>
<?php else: ?>
    <form method="POST" class="chat-input-form" id="chatForm">
        <?= csrfField() ?>
        <input type="hidden" name="id" value="<?= (int) $chat_id ?>">
        <button type="button" class="emoji-toggle" id="emojiBtn">😊</button>
        <div class="emoji-panel" id="emojiPanel" style="display:none;">
            <?php
            $emojis = ['😀', '😂', '😍', '👍', '🙏', '😢', '😮', '😡', '🎉', '❤️', '🔥', '👏', '😴', '🤔', '✅', '❌', '💰', '📦', '🚗', '⏰'];
            foreach ($emojis as $e): ?>
                <button type="button" class="emoji-item"><?= $e ?></button>
            <?php endforeach; ?>
        </div>
        <input type="text" name="message" id="messageInput" placeholder="พิมพ์ข้อความ..." required maxlength="2000" autocomplete="off">
        <button type="submit">ส่ง</button>
    </form>
<?php endif; ?>

<?php if (!$existingTx || $existingTx['status'] === 'cancelled'): ?>
    <?php if ($user_id == $chat['buyer_id']): ?>
        <p style="margin-top:16px;">
            <a href="../transactions/create.php?chat_id=<?= (int) $chat_id ?>">นัดรับสินค้า</a>
        </p>
    <?php endif; ?>
<?php elseif ($existingTx['status'] === 'completed'): ?>
    <p style="margin-top:16px; color:var(--text-muted);">
        นัดรับสินค้าสำเร็จแล้ว — <a href="../transactions/list.php">ดูรายละเอียด</a>
    </p>
<?php else: ?>
    <p style="margin-top:16px; color:var(--text-muted);">
        มีการนัดหมายอยู่แล้ว (สถานะ: <?= htmlspecialchars($statusTextMap[$existingTx['status']] ?? $existingTx['status']) ?>) — <a href="../transactions/list.php">ดูรายละเอียด</a>
    </p>
<?php endif; ?>

<script>
    const chatBox = document.getElementById('chatBox');
    if (chatBox) chatBox.scrollTop = chatBox.scrollHeight;

    const emojiBtn = document.getElementById('emojiBtn');
    const emojiPanel = document.getElementById('emojiPanel');
    const messageInput = document.getElementById('messageInput');

    if (emojiBtn) {
        emojiBtn.addEventListener('click', () => {
            emojiPanel.style.display = emojiPanel.style.display === 'none' ? 'grid' : 'none';
        });

        document.querySelectorAll('.emoji-item').forEach(btn => {
            btn.addEventListener('click', () => {
                const start = messageInput.selectionStart;
                const end = messageInput.selectionEnd;
                const text = messageInput.value;
                messageInput.value = text.slice(0, start) + btn.textContent + text.slice(end);
                messageInput.focus();
                emojiPanel.style.display = 'none';
            });
        });

        document.addEventListener('click', (e) => {
            if (!emojiBtn.contains(e.target) && !emojiPanel.contains(e.target)) {
                emojiPanel.style.display = 'none';
            }
        });
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
