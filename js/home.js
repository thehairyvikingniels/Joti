/**
 * js/home.js ??? Home dashboard event log loader, vehicle tracking, invulgegevens, and announcement modal.
 */

async function gebeurtenissen(str = '6') {
    const icon = document.getElementById('gebeurtenissen_icon');
    if (icon) icon.classList.add('w3-spin');
    try {
        const response = await fetch(`functies.php?gebeurtenissen=${encodeURIComponent(str)}`);
        if (response.ok) {
            const html = await response.text();
            const el = document.getElementById('gebeurtenissen');
            if (el) el.innerHTML = html;
        }
    } catch (err) {
        console.error('Error fetching gebeurtenissen:', err);
    } finally {
        if (icon) {
            setTimeout(() => { icon.classList.remove('w3-spin'); }, 1000);
        }
    }
}

async function autosonderweg(str = '6') {
    try {
        const response = await fetch(`functies.php?autosonderweg=${encodeURIComponent(str)}`);
        if (response.ok) {
            const html = await response.text();
            const el = document.getElementById('autosonderweg');
            if (el) el.innerHTML = html;
        }
    } catch (err) {
        console.error('Error fetching autosonderweg:', err);
    }
}

async function invulgegevens(str = '6') {
    try {
        const response = await fetch(`functies.php?invulgegevens=${encodeURIComponent(str)}`);
        if (response.ok) {
            const html = await response.text();
            const el = document.getElementById('invulgegevens');
            if (el) el.innerHTML = html;
        }
    } catch (err) {
        console.error('Error fetching invulgegevens:', err);
    }
}

let welcomeRaf = null;

function openWelcomeModal(withCountdown = false) {
    const modal = document.getElementById('welcomeModal');
    const modalContent = document.getElementById('welcomeModalContent');
    if (!modal) return;

    modal.classList.remove('hidden');
    requestAnimationFrame(() => {
        modal.classList.remove('opacity-0');
        if (modalContent) modalContent.classList.remove('-translate-y-4');
    });

    const closeBtn = document.getElementById('welcomeClose');
    const topCloseBtn = document.getElementById('welcomeTopClose');
    const progress = document.getElementById('welcomeProgress');
    const countdownEl = document.getElementById('closeCountdown');

    if (!withCountdown) {
        if (closeBtn) {
            closeBtn.disabled = false;
            closeBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            closeBtn.classList.add('hover:bg-green-600');
        }
        if (progress) progress.style.display = 'none';
        if (countdownEl) countdownEl.style.display = 'none';
        if (topCloseBtn) topCloseBtn.classList.remove('hidden');
        return;
    }

    const duration = 5000;
    const start = Date.now();

    if (closeBtn) {
        closeBtn.disabled = true;
        closeBtn.classList.add('opacity-50', 'cursor-not-allowed');
        closeBtn.classList.remove('hover:bg-green-600');
    }
    if (progress) {
        progress.style.display = 'block';
        progress.style.width = '0%';
    }
    if (countdownEl) {
        countdownEl.style.display = 'inline';
        countdownEl.textContent = '(5s)';
    }

    if (welcomeRaf) cancelAnimationFrame(welcomeRaf);

    function tick() {
        const elapsed = Date.now() - start;
        const pct = Math.min(100, (elapsed / duration) * 100);
        if (progress) progress.style.width = `${pct}%`;

        const remaining = Math.max(0, Math.ceil((duration - elapsed) / 1000));
        if (countdownEl) {
            if (remaining > 0) {
                countdownEl.textContent = `(${remaining}s)`;
            } else {
                countdownEl.style.display = 'none';
            }
        }

        if (elapsed < duration) {
            welcomeRaf = requestAnimationFrame(tick);
        } else {
            if (closeBtn) {
                closeBtn.disabled = false;
                closeBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                closeBtn.classList.add('hover:bg-green-600');
            }
            if (progress) progress.style.display = 'none';
            if (topCloseBtn) topCloseBtn.classList.remove('hidden');
        }
    }
    welcomeRaf = requestAnimationFrame(tick);
}

function closeWelcomeModal() {
    const modal = document.getElementById('welcomeModal');
    const modalContent = document.getElementById('welcomeModalContent');
    const closeBtn = document.getElementById('welcomeClose');
    if (!modal) return;
    if (closeBtn && closeBtn.disabled) return;

    modal.classList.add('opacity-0');
    if (modalContent) modalContent.classList.add('-translate-y-4');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

function initWelcomeModal(withCountdown = true) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => openWelcomeModal(withCountdown));
    } else {
        openWelcomeModal(withCountdown);
    }
}

function initHome(hasPrivilege = false) {
    gebeurtenissen();
    autosonderweg();
    if (hasPrivilege) {
        invulgegevens();
    }
    setInterval(() => {
        gebeurtenissen();
        autosonderweg();
        if (hasPrivilege) {
            invulgegevens();
        }
    }, 11111);
}
