// js/sidebar.js — Handles sidebar accordion category folding, state persistence, and active category tracking

(function () {
    const STORAGE_KEY = 'jotify_sidebar_accordion';

    function getCollapsedState() {
        try {
            const stored = localStorage.getItem(STORAGE_KEY);
            return stored ? JSON.parse(stored) : {};
        } catch (e) {
            return {};
        }
    }

    function saveCollapsedState(state) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        } catch (e) {
            // LocalStorage might be disabled in private browsing
        }
    }

    window.toggleSidebarCategory = function (categoryId) {
        const content = document.getElementById('nav-group-' + categoryId);
        const chevron = document.getElementById('nav-chevron-' + categoryId);
        if (!content) return;

        const isCurrentlyHidden = content.classList.contains('hidden');
        const state = getCollapsedState();

        if (isCurrentlyHidden) {
            // Expand
            content.classList.remove('hidden');
            if (chevron) {
                chevron.classList.remove('-rotate-90');
                chevron.classList.add('rotate-0');
            }
            delete state[categoryId];
        } else {
            // Collapse
            content.classList.add('hidden');
            if (chevron) {
                chevron.classList.remove('rotate-0');
                chevron.classList.add('-rotate-90');
            }
            state[categoryId] = true;
        }

        saveCollapsedState(state);
    };

    function initSidebarAccordion() {
        const state = getCollapsedState();
        const categorySections = document.querySelectorAll('[data-sidebar-category]');

        categorySections.forEach(section => {
            const categoryId = section.getAttribute('data-sidebar-category');
            const content = document.getElementById('nav-group-' + categoryId);
            const chevron = document.getElementById('nav-chevron-' + categoryId);
            if (!content) return;

            // Check if this category contains the currently active page link
            const hasActivePage = content.querySelector('.theme-sidebar-active') !== null;

            if (hasActivePage) {
                // Always expand the active category
                content.classList.remove('hidden');
                if (chevron) {
                    chevron.classList.remove('-rotate-90');
                    chevron.classList.add('rotate-0');
                }
                if (state[categoryId]) {
                    delete state[categoryId];
                    saveCollapsedState(state);
                }
            } else if (state[categoryId] === true) {
                // User previously collapsed this category
                content.classList.add('hidden');
                if (chevron) {
                    chevron.classList.remove('rotate-0');
                    chevron.classList.add('-rotate-90');
                }
            } else {
                // Default: expanded
                content.classList.remove('hidden');
                if (chevron) {
                    chevron.classList.remove('-rotate-90');
                    chevron.classList.add('rotate-0');
                }
            }
        });
    }

    function adjustSidebarUserGreeting() {
        const greetingEl = document.getElementById('sidebar-user-greeting');
        const prefixEl = document.getElementById('sidebar-welcome-prefix');
        if (!greetingEl || !prefixEl) return;

        // Reset prefix visibility to measure natural width
        prefixEl.style.display = '';

        // If overflowing with "Welkom, ", hide the prefix so only the first name is displayed
        if (greetingEl.scrollWidth > greetingEl.clientWidth) {
            prefixEl.style.display = 'none';
        }
    }

    function init() {
        initSidebarAccordion();
        adjustSidebarUserGreeting();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(adjustSidebarUserGreeting);
    }
    window.addEventListener('resize', adjustSidebarUserGreeting);
})();
