    </div><!-- /.page-container -->

    <?php if ($isLoggedIn): ?>
        <!-- แถบเมนูล่างสำหรับมือถือ: รวมเฉพาะเมนูที่ใช้บ่อยที่สุด 5 อัน -->
        <nav class="bottom-nav">
            <a href="/campus-market/products/list.php" class="<?= isActive('/products/list.php') ?>">
                <span class="bn-icon">🏠</span>สินค้า
            </a>
            <a href="/campus-market/shops/list.php" class="<?= isActive('/shops/list.php') ?>">
                <span class="bn-icon">🏪</span>ร้านค้า
            </a>
            <a href="/campus-market/cart/index.php" class="<?= isActive('/cart/') ?>">
                <span class="bn-icon">🛒</span>ตะกร้า
                <?php if ($cartCount > 0): ?><span class="bn-badge"><?= $cartCount ?></span><?php endif; ?>
            </a>
            <a href="/campus-market/chat/index.php" class="<?= isActive('/chat/') ?>">
                <span class="bn-icon">💬</span>แชท
            </a>
            <a href="/campus-market/wallet/index.php" class="<?= isActive('/wallet/') ?>">
                <span class="bn-icon">💰</span><?= formatCreditsShort($walletBalance) ?>
            </a>
        </nav>
    <?php endif; ?>

    <div id="toastContainer"></div>

    <script src="/campus-market/assets/js/ui.js"></script>
    <script>
        const CSRF_TOKEN = <?= json_encode(csrfToken()) ?>;

        function addToCart(productId, btnEl) {
            fetch('/campus-market/cart/add.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'product_id=' + encodeURIComponent(productId) + '&csrf_token=' + encodeURIComponent(CSRF_TOKEN)
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const badges = document.querySelectorAll('#cartBadge, .bottom-nav .bn-badge');
                        // อัปเดต badge ตะกร้าทั้งบนแถบเมนูบนและเมนูล่างมือถือให้ตรงกันทันที
                        document.querySelectorAll('a[href*="cart/index.php"]').forEach(link => {
                            let badge = link.querySelector('.badge, .bn-badge');
                            if (!badge && data.count > 0) {
                                badge = document.createElement('span');
                                badge.className = link.closest('.bottom-nav') ? 'bn-badge' : 'badge';
                                link.appendChild(badge);
                            }
                            if (badge) badge.textContent = data.count;
                        });

                        const originalText = btnEl.textContent;
                        btnEl.textContent = '✅';
                        setTimeout(() => { btnEl.textContent = originalText; }, 1200);
                        showToast('เพิ่มลงตะกร้าแล้ว');
                    } else {
                        showToast(data.error || 'เกิดข้อผิดพลาด', 'error');
                    }
                })
                .catch(() => showToast('เชื่อมต่อเซิร์ฟเวอร์ไม่ได้', 'error'));
        }
    </script>
</body>

</html>
<?php
// ปล่อย output buffer ทั้งหมดออกในขั้นตอนสุดท้าย (เปิดไว้ตั้งแต่ bootstrap.php)
if (ob_get_level()) {
    ob_end_flush();
}
