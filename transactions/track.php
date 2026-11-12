<?php
require_once __DIR__ . '/../includes/header.php';

requireLogin('../auth/login.php');

$transaction_id = intInput($_GET, 'id', 0);
$user_id = $currentUserId;

$stmt = $pdo->prepare("
    SELECT t.*, p.title as product_title, p.seller_id,
    buyer.name as buyer_name, seller.name as seller_name
    FROM transactions t
    JOIN products p ON t.product_id = p.product_id
    JOIN users buyer ON t.buyer_id = buyer.user_id
    JOIN users seller ON p.seller_id = seller.user_id
    WHERE t.transaction_id = ? AND (t.buyer_id = ? OR p.seller_id = ?)
");
$stmt->execute([$transaction_id, $user_id, $user_id]);
$tx = $stmt->fetch();

if (!$tx) {
    echo "<p class='error-msg' style='max-width:640px; margin:0 auto;'>ไม่มีสิทธิ์เข้าถึง</p>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}
if ($tx['status'] !== 'confirmed') {
    echo "<p class='error-msg' style='max-width:640px; margin:0 auto;'>แชร์ตำแหน่งได้เฉพาะตอนสถานะ 'ยืนยันแล้ว' เท่านั้น</p>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}
?>

<h2>ติดตามตำแหน่งนัดรับ — <?= htmlspecialchars($tx['product_title']) ?></h2>
<p style="margin-bottom:16px; color:var(--text-muted);">
    สถานที่นัด: <?= htmlspecialchars($tx['meetup_location']) ?> | เวลานัด: <?= htmlspecialchars($tx['meetup_time']) ?>
</p>

<div id="map" style="height:440px; border-radius:14px; max-width:700px; margin-bottom:16px; border:1px solid var(--border); box-shadow:0 2px 12px rgba(0,0,0,0.06);"></div>

<button id="shareBtn" style="margin-bottom:20px;">📍 เริ่มแชร์ตำแหน่งของฉัน</button>
<p id="statusText" style="color:var(--text-muted); margin-bottom:16px;"></p>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const transactionId = <?= (int) $transaction_id ?>;
const myUserId = <?= (int) $user_id ?>;
const CSRF_TOKEN = <?= json_encode(csrfToken()) ?>;
let map, myMarker, otherMarker, watchId;

map = L.map('map', { zoomControl: true }).setView([7.0086, 100.4747], 15);

// แก้บั๊ก: CARTO basemap (voyager) เปลี่ยนนโยบายต้องใช้ API key ถึงจะโหลดแผนที่ได้แล้ว
// ทำให้ไทล์แผนที่ขึ้นข้อความ "API KEY REQUIRED" ทับเต็มจอแทนแผนที่จริง เปลี่ยนมาใช้
// OpenStreetMap tile server มาตรฐานแทน ซึ่งใช้งานฟรีไม่ต้องขอ API key
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    subdomains: 'abc',
    maxZoom: 19
}).addTo(map);

const myIcon = L.divIcon({
    className: '',
    html: '<div style="background:#1B6B3A; width:22px; height:22px; border-radius:50%; border:3px solid #fff; box-shadow:0 2px 6px rgba(0,0,0,0.3);"></div>',
    iconSize: [22, 22],
    iconAnchor: [11, 11]
});

const otherIcon = L.divIcon({
    className: '',
    html: '<div style="background:#D4A017; width:22px; height:22px; border-radius:50%; border:3px solid #fff; box-shadow:0 2px 6px rgba(0,0,0,0.3);"></div>',
    iconSize: [22, 22],
    iconAnchor: [11, 11]
});

let watcherActive = false;

document.getElementById('shareBtn').addEventListener('click', () => {
    if (watcherActive) return; // กันกดซ้ำสร้าง watcher ซ้อนกันหลายตัว (memory/battery leak ฝั่ง client)
    if (!navigator.geolocation) {
        document.getElementById('statusText').textContent = 'เบราว์เซอร์นี้ไม่รองรับ GPS';
        return;
    }
    watcherActive = true;
    watchId = navigator.geolocation.watchPosition(pos => {
        const lat = pos.coords.latitude;
        const lng = pos.coords.longitude;

        fetch('update_location.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({transaction_id: transactionId, lat, lng, csrf_token: CSRF_TOKEN})
        });

        if (!myMarker) {
            myMarker = L.marker([lat, lng], {icon: myIcon}).addTo(map)
                .bindPopup('📍 ตำแหน่งของฉัน').openPopup();
            map.setView([lat, lng], 17);
        } else {
            myMarker.setLatLng([lat, lng]);
        }
        document.getElementById('statusText').textContent = '🟢 กำลังแชร์ตำแหน่ง...';
    }, err => {
        document.getElementById('statusText').textContent = 'ไม่สามารถเข้าถึง GPS ได้: ' + err.message;
    }, { enableHighAccuracy: true });
});

const pollInterval = setInterval(() => {
    fetch('get_locations.php?transaction_id=' + transactionId)
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            data.locations.forEach(loc => {
                if (loc.user_id != myUserId) {
                    if (!otherMarker) {
                        otherMarker = L.marker([loc.latitude, loc.longitude], {icon: otherIcon})
                            .addTo(map)
                            .bindPopup('📍 ' + loc.name);
                    } else {
                        otherMarker.setLatLng([loc.latitude, loc.longitude]);
                    }
                }
            });
        });
}, 5000);

// หยุด polling เมื่อออกจากหน้า กัน memory/network leak
window.addEventListener('beforeunload', () => {
    clearInterval(pollInterval);
    if (watchId) navigator.geolocation.clearWatch(watchId);
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
