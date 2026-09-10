/**
 * js/fotoopdrachten.js - Live countdown timers, dynamic tab filtering, and team dispatch for fotoopdrachten.
 */

let activeFilter = 'all';
let currentSubmitTargetId = null;

document.addEventListener('DOMContentLoaded', () => {
    initCountdownTickers();
    setInterval(initCountdownTickers, 1000);
});

/**
 * Updates all live countdown timers on the page.
 */
function initCountdownTickers() {
    const now = Math.floor(Date.now() / 1000);
    const timers = document.querySelectorAll('.countdown-timer');

    timers.forEach((el) => {
        const startTs = parseInt(el.getAttribute('data-start-ts'), 10);
        const endTs = parseInt(el.getAttribute('data-end-ts'), 10);
        const isSubmitted = el.getAttribute('data-submitted') === '1';

        if (now < startTs) {
            const diff = startTs - now;
            el.innerHTML = `<i class="fas fa-hourglass-start mr-1.5 text-amber-300"></i><span class="text-xs font-semibold text-white">Start over: ${formatTimeRemaining(diff)}</span>`;
        } else if (now < endTs) {
            const diff = endTs - now;
            if (diff < 1800) {
                el.innerHTML = `<i class="fas fa-stopwatch mr-1.5 text-red-300 animate-pulse"></i><span class="text-xs font-bold text-red-200 animate-pulse">Nog ${formatTimeRemaining(diff)}</span>`;
            } else {
                el.innerHTML = `<i class="fas fa-stopwatch mr-1.5 text-emerald-300"></i><span class="text-xs font-semibold text-white">Nog ${formatTimeRemaining(diff)}</span>`;
            }
        } else {
            el.innerHTML = `<i class="fas fa-clock mr-1.5 opacity-60"></i><span class="text-xs opacity-75 font-semibold text-white">Verlopen</span>`;
        }
    });
}

/**
 * Formats seconds into human-readable Dutch duration.
 * @param {number} sec
 * @returns {string}
 */
function formatTimeRemaining(sec) {
    if (sec <= 0) return "0s";
    const hours = Math.floor(sec / 3600);
    const minutes = Math.floor((sec % 3600) / 60);
    const seconds = sec % 60;

    if (hours > 0) {
        return `${hours}u ${String(minutes).padStart(2, '0')}m ${String(seconds).padStart(2, '0')}s`;
    }
    return `${minutes}m ${String(seconds).padStart(2, '0')}s`;
}

/**
 * Filter cards by category tab.
 * @param {string} filter
 * @param {HTMLElement} btn
 */
function setFotoFilter(filter, btn) {
    activeFilter = filter;

    document.querySelectorAll('.filter-tab-btn').forEach((b) => {
        b.classList.remove('theme-bg-primary', 'text-white', 'shadow-sm');
        b.classList.add('opacity-70', 'hover:opacity-100');
    });

    if (btn) {
        btn.classList.add('theme-bg-primary', 'text-white', 'shadow-sm');
        btn.classList.remove('opacity-70');
    }

    applyCardFilters();
}

/**
 * Filters cards based on active tab and search query.
 */
function applyCardFilters() {
    const searchInput = document.getElementById('foto-search-input');
    const query = searchInput ? searchInput.value.toLowerCase().trim() : '';

    const cards = document.querySelectorAll('.foto-card');
    let visibleCount = 0;

    cards.forEach((card) => {
        const cardStatus = card.getAttribute('data-status'); // 'active', 'submitted', 'expired', 'future'
        const title = (card.getAttribute('data-title') || '').toLowerCase();
        const desc = (card.getAttribute('data-desc') || '').toLowerCase();

        let matchesTab = false;
        if (activeFilter === 'all') {
            matchesTab = true;
        } else if (activeFilter === 'active') {
            matchesTab = (cardStatus === 'active');
        } else if (activeFilter === 'submitted') {
            matchesTab = (cardStatus === 'submitted' || cardStatus === 'graded');
        } else if (activeFilter === 'expired') {
            matchesTab = (cardStatus === 'expired');
        }

        const matchesQuery = !query || title.includes(query) || desc.includes(query);

        if (matchesTab && matchesQuery) {
            card.classList.remove('hidden');
            visibleCount++;
        } else {
            card.classList.add('hidden');
        }
    });

    const emptyNotice = document.getElementById('foto-empty-filter-notice');
    if (emptyNotice) {
        if (visibleCount === 0 && cards.length > 0) {
            emptyNotice.classList.remove('hidden');
        } else {
            emptyNotice.classList.add('hidden');
        }
    }
}

/**
 * Toggle user membership in the overarching Foto-opdrachten team.
 */
async function toggleFotoTeam() {
    const btn = document.getElementById('btn-toggle-team');
    if (!btn) return;

    btn.disabled = true;
    btn.classList.add('opacity-50');

    try {
        const formData = new FormData();
        formData.append('action', 'toggle_team');

        const resp = await fetch('fotoopdrachten_helper.php', {
            method: 'POST',
            body: formData
        });

        const data = await resp.json();
        if (data.status === 'success') {
            updateTeamMembersUI(data.team, data.is_in_team);
        } else {
            alert(data.message || 'Er is een fout opgetreden.');
        }
    } catch (err) {
        console.error('Team toggle error:', err);
    } finally {
        btn.disabled = false;
        btn.classList.remove('opacity-50');
    }
}

/**
 * Updates team avatar container and button state.
 * @param {Array} members
 * @param {boolean} isInTeam
 */
function updateTeamMembersUI(members, isInTeam) {
    const container = document.getElementById('foto-team-avatars');
    const btn = document.getElementById('btn-toggle-team');
    const countBadge = document.getElementById('foto-team-count');

    if (countBadge) {
        countBadge.innerText = members.length === 1 ? '1 lid' : `${members.length} leden`;
    }

    if (container) {
        if (!members || members.length === 0) {
            container.innerHTML = `<span class="text-xs italic" style="color: var(--theme-text); opacity: 0.7;">Nog niemand in het team</span>`;
        } else {
            let html = '';
            members.forEach((m) => {
                const fullName = `${m.voornaam.charAt(0).toUpperCase() + m.voornaam.slice(1)} ${m.achternaam.charAt(0).toUpperCase() + m.achternaam.slice(1)}`;
                let avatarContent = '';
                if (m.profile_picture) {
                    avatarContent = `<img class="inline-block h-9 w-9 rounded-full ring-2 ring-white object-cover bg-white pointer-events-none" src="profile_image.php?hash=${encodeURIComponent(m.profile_picture)}&res=low" alt="${fullName}"/>`;
                } else {
                    const initial = m.voornaam.charAt(0).toUpperCase();
                    avatarContent = `<div class="inline-flex items-center justify-center h-9 w-9 rounded-full ring-2 ring-white bg-pink-500 text-white font-bold text-xs pointer-events-none">${initial}</div>`;
                }
                const safeName = fullName.replace(/'/g, "\\'");
                html += `<div class="inline-block flex-shrink-0 cursor-pointer" onmouseenter="showAvatarTooltip(event, this, '${safeName}')" onmouseleave="hideAvatarTooltip()" onclick="showAvatarTooltip(event, this, '${safeName}')">${avatarContent}</div>`;
            });
            container.innerHTML = html;
        }
    }

    if (btn) {
        if (isInTeam) {
            btn.className = "text-sm font-bold bg-red-100 text-red-700 hover:bg-red-200 px-4 py-2 rounded transition shadow-sm whitespace-nowrap";
            btn.innerHTML = `<i class="fas fa-times mr-1"></i> Stop hiermee`;
        } else {
            btn.className = "text-sm font-bold bg-blue-100 text-blue-700 hover:bg-blue-200 px-4 py-2 rounded transition shadow-sm whitespace-nowrap";
            btn.innerHTML = `<i class="fas fa-hand-paper mr-1"></i> Ga hiermee aan de slag`;
        }
    }
}

/**
 * Opens modal to mark photo assignment as submitted.
 * @param {number} id
 * @param {string} title
 */
function openSubmitModal(id, title) {
    currentSubmitTargetId = id;
    const titleEl = document.getElementById('submit-modal-title');
    if (titleEl) titleEl.innerText = title;

    const modal = document.getElementById('modal-mark-submitted');
    if (modal) modal.classList.remove('hidden');
}

/**
 * Closes submission modal.
 */
function closeSubmitModal() {
    const modal = document.getElementById('modal-mark-submitted');
    if (modal) modal.classList.add('hidden');
    currentSubmitTargetId = null;
}

/**
 * Executes submission marker.
 */
async function confirmMarkSubmitted() {
    if (!currentSubmitTargetId) return;

    const btn = document.getElementById('btn-confirm-submit');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = `<i class="fas fa-spinner fa-spin mr-1"></i> Opslaan...`;
    }

    try {
        const formData = new FormData();
        formData.append('action', 'mark_submitted');
        formData.append('id', currentSubmitTargetId);

        const resp = await fetch('fotoopdrachten_helper.php', {
            method: 'POST',
            body: formData
        });

        const data = await resp.json();
        if (data.status === 'success') {
            closeSubmitModal();
            window.location.reload();
        } else {
            alert(data.message || 'Fout bij opslaan.');
        }
    } catch (err) {
        console.error('Submit error:', err);
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = `Bevestig Inzending`;
        }
    }
}
