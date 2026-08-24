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

function initWelcomeModal() {
    window.addEventListener('load', () => {
        const modal = document.getElementById('welcomeModal');
        const modalContent = document.getElementById('welcomeModalContent');
        if (!modal) return;

        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            if (modalContent) modalContent.classList.remove('-translate-y-4');
        }, 10);

        const closeBtn = document.getElementById('welcomeClose');
        const progress = document.getElementById('welcomeProgress');
        const countdownEl = document.getElementById('closeCountdown');
        const duration = 7000;
        const start = Date.now();

        let raf;
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
                raf = requestAnimationFrame(tick);
            } else if (closeBtn) {
                closeBtn.disabled = false;
                closeBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                closeBtn.classList.add('hover:bg-green-600');
                if (progress) progress.style.display = 'none';
                cancelAnimationFrame(raf);
            }
        }
        tick();

        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                if (!closeBtn.disabled) {
                    modal.classList.add('opacity-0');
                    if (modalContent) modalContent.classList.add('-translate-y-4');
                    setTimeout(() => {
                        modal.style.display = 'none';
                    }, 300);
                }
            });
        }
    });
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
