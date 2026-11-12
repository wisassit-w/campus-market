/**
 * ui.js — ระบบ UI กลางของ HU Market
 * - showToast(message, type) แทน alert()
 * - showConfirm(message) คืนค่า Promise<boolean> แทน confirm()
 * - ดักฟอร์ม/ลิงก์ที่มี data-confirm ให้เด้ง modal อัตโนมัติ (ไม่ต้องเขียน onclick เอง)
 * - เปิด/ปิดเมนูมือถือ (hamburger) และเมนู 3 ขีด (kebab) บนแถบเมนูบน
 */

// ---------- Toast ----------
function ensureToastContainer() {
    let el = document.getElementById('toastContainer');
    if (!el) {
        el = document.createElement('div');
        el.id = 'toastContainer';
        document.body.appendChild(el);
    }
    return el;
}

function showToast(message, type = 'success') {
    const container = ensureToastContainer();
    const toast = document.createElement('div');
    toast.className = 'toast toast-' + type;
    toast.innerHTML = (type === 'error' ? '⚠️ ' : '✅ ') + message.replace(/</g, '&lt;');
    container.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('toast-leaving');
        setTimeout(() => toast.remove(), 220);
    }, 3200);
}

// ---------- Confirm modal ----------
function ensureConfirmOverlay() {
    let el = document.getElementById('confirmOverlay');
    if (!el) {
        el = document.createElement('div');
        el.id = 'confirmOverlay';
        el.innerHTML = `
            <div class="confirm-box">
                <p id="confirmMessage"></p>
                <div class="confirm-actions">
                    <button type="button" class="confirm-cancel" id="confirmCancelBtn">ยกเลิก</button>
                    <button type="button" class="confirm-ok" id="confirmOkBtn">ยืนยัน</button>
                </div>
            </div>
        `;
        document.body.appendChild(el);
    }
    return el;
}

function showConfirm(message) {
    return new Promise((resolve) => {
        const overlay = ensureConfirmOverlay();
        overlay.querySelector('#confirmMessage').textContent = message;
        overlay.classList.add('open');

        const okBtn = overlay.querySelector('#confirmOkBtn');
        const cancelBtn = overlay.querySelector('#confirmCancelBtn');

        function cleanup(result) {
            overlay.classList.remove('open');
            okBtn.removeEventListener('click', onOk);
            cancelBtn.removeEventListener('click', onCancel);
            overlay.removeEventListener('click', onOverlayClick);
            resolve(result);
        }
        function onOk() { cleanup(true); }
        function onCancel() { cleanup(false); }
        function onOverlayClick(e) { if (e.target === overlay) cleanup(false); }

        okBtn.addEventListener('click', onOk);
        cancelBtn.addEventListener('click', onCancel);
        overlay.addEventListener('click', onOverlayClick);
    });
}

// ---------- ดัก form/link ที่มี data-confirm ให้เด้ง modal เองอัตโนมัติ ----------
// ใช้กับ: <form data-confirm="ข้อความ">...</form> หรือ <a data-confirm="ข้อความ" href="...">
document.addEventListener('submit', function (e) {
    const form = e.target;
    if (form.dataset && form.dataset.confirm && !form.dataset.confirmed) {
        e.preventDefault();
        showConfirm(form.dataset.confirm).then((ok) => {
            if (ok) {
                form.dataset.confirmed = '1';
                form.requestSubmit ? form.requestSubmit() : form.submit();
            }
        });
    }
}, true);

document.addEventListener('click', function (e) {
    const link = e.target.closest('a[data-confirm]');
    if (link) {
        e.preventDefault();
        showConfirm(link.dataset.confirm).then((ok) => {
            if (ok) window.location.href = link.href;
        });
    }
}, true);

// ---------- เมนูมือถือ (hamburger) ----------
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('navToggle');
    const links = document.getElementById('navLinks');
    if (toggle && links) {
        toggle.addEventListener('click', () => {
            links.classList.toggle('open');
        });
        document.addEventListener('click', (e) => {
            if (!toggle.contains(e.target) && !links.contains(e.target)) {
                links.classList.remove('open');
            }
        });
    }

    // ---------- เมนู 3 ขีด (kebab) สำหรับลิงก์รอง ----------
    const kebabBtn = document.getElementById('kebabToggle');
    const kebabMenu = document.getElementById('kebabMenu');
    if (kebabBtn && kebabMenu) {
        kebabBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            kebabMenu.classList.toggle('open');
        });
        document.addEventListener('click', (e) => {
            if (!kebabMenu.contains(e.target) && !kebabBtn.contains(e.target)) {
                kebabMenu.classList.remove('open');
            }
        });
    }
});
