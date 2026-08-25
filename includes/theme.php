<?php
// includes/theme.php

require_once(__DIR__ . '/helpers.php');

// Default theme is light
$theme = 'light';

// Check if user is logged in and has a theme set in session
if (isset($_SESSION['theme'])) {
    $theme = $_SESSION['theme'];
}

// Ensure theme is valid
$valid_themes = ['light', 'dark', 'rose-gold', 'cyber', 'nature', 'coral'];
if (!in_array($theme, $valid_themes)) {
    $theme = 'light';
}

$themeConfig = getThemeConfig($theme);
?>
<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;700&family=Merriweather:wght@400;700&family=Outfit:wght@400;500;600;700&family=Quicksand:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    :root {
        --theme-bg: <?php echo $themeConfig['bg']; ?>;
        --theme-text: <?php echo $themeConfig['text']; ?>;
        --theme-sidebar-bg: <?php echo $themeConfig['sidebar_bg']; ?>;
        --theme-sidebar-text: <?php echo $themeConfig['sidebar_text']; ?>;
        --theme-sidebar-active: <?php echo $themeConfig['sidebar_active']; ?>;
        --theme-card-bg: <?php echo $themeConfig['card_bg']; ?>;
        --theme-card-border: <?php echo $themeConfig['card_border']; ?>;
        --theme-primary: <?php echo $themeConfig['primary']; ?>;
    }
    body {
        font-family: <?php echo $themeConfig['font']; ?>;
        background-color: var(--theme-bg);
        color: var(--theme-text);
    }
    .theme-sidebar {
        background-color: var(--theme-sidebar-bg);
        color: var(--theme-sidebar-text);
    }
    .theme-sidebar-active {
        background-color: var(--theme-sidebar-active);
    }
    .theme-card {
        background-color: var(--theme-card-bg);
        border-color: var(--theme-card-border);
        color: var(--theme-text);
        border-radius: 0.75rem;
    }
    .theme-card-header {
        background-color: var(--theme-sidebar-active);
        border-bottom-color: var(--theme-card-border);
        color: #ffffff;
    }
    .theme-primary {
        color: var(--theme-primary);
    }
    .theme-bg-primary {
        background-color: var(--theme-primary);
    }
    .theme-border-primary {
        border-color: var(--theme-primary);
    }
    .hover-theme-border-primary:hover {
        border-color: var(--theme-primary);
    }
    
    /* Utility for overriding Tailwind bg-white in dark mode */
    .theme-override-bg {
        background-color: var(--theme-card-bg) !important;
    }
    .theme-override-text {
        color: var(--theme-text) !important;
    }
    .theme-input {
        background-color: var(--theme-card-bg) !important;
        color: var(--theme-text) !important;
        border: 1px solid var(--theme-card-border) !important;
        border-radius: 0.75rem !important;
    }
    .theme-input:focus {
        outline: none !important;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.5) !important;
    }
</style>
<script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    themeBg: 'var(--theme-bg)',
                    themeText: 'var(--theme-text)',
                    themeSidebarBg: 'var(--theme-sidebar-bg)',
                    themeSidebarText: 'var(--theme-sidebar-text)',
                    themeSidebarActive: 'var(--theme-sidebar-active)',
                    themeCardBg: 'var(--theme-card-bg)',
                    themeCardBorder: 'var(--theme-card-border)',
                    themePrimary: 'var(--theme-primary)',
                }
            }
        }
    }
</script>
<link rel="manifest" href="/manifest.json">
<script>window.VAPID_PUBLIC_KEY = '<?= htmlspecialchars($site_settings['VAPID_PUBLIC_KEY'] ?? '') ?>';</script>
<script src="js/push.js?v=2"></script>
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('/sw.js').catch(function(err) {
            console.warn('ServiceWorker registration failed: ', err);
        });
    });
}
</script>
<?php if (isset($_SESSION['kiosk_id'])): ?>
<script src="/includes/kiosk_controller.js?v=1"></script>
<?php endif; ?>

<script>
// Universal Modal Esc & Backdrop Click Handler
function closeModal(el) {
    if (!el) {
        document.querySelectorAll('.modal-backdrop, [id*="modal"], [id*="Modal"]').forEach(function(m) {
            if (!m.classList.contains('hidden') && (m.classList.contains('fixed') || m.style.display === 'block' || m.style.display === 'flex')) {
                m.classList.add('hidden');
                m.style.display = 'none';
            }
        });
        return;
    }
    if (typeof el === 'string') {
        const target = document.getElementById(el);
        if (target) {
            target.classList.add('hidden');
            target.style.display = 'none';
        }
        return;
    }
    const modal = el.closest('.modal-backdrop, [role="dialog"], .fixed.inset-0') || el;
    if (modal) {
        modal.classList.add('hidden');
        modal.style.display = 'none';
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-backdrop, [id*="modal"], [id*="Modal"], [role="dialog"]').forEach(function(m) {
            if (!m.classList.contains('hidden') && (m.classList.contains('fixed') || m.style.display === 'block' || m.style.display === 'flex')) {
                m.classList.add('hidden');
                m.style.display = 'none';
            }
        });
    }
});

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-backdrop') || (e.target.classList.contains('fixed') && e.target.classList.contains('inset-0') && !e.target.classList.contains('hidden'))) {
        e.target.classList.add('hidden');
        e.target.style.display = 'none';
    }
});
</script>