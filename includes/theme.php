<?php
// includes/theme.php

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

function getThemeConfig($theme) {
    $config = [];
    
    switch ($theme) {
        case 'dark':
            $config['bg'] = '#111827';
            $config['text'] = '#F3F4F6';
            $config['sidebar_bg'] = '#000000';
            $config['sidebar_text'] = '#D1D5DB';
            $config['sidebar_active'] = '#1F2937';
            $config['card_bg'] = '#1F2937';
            $config['card_border'] = '#374151';
            $config['primary'] = '#3B82F6';
            $config['font'] = "'Inter', sans-serif";
            break;
        case 'rose-gold':
            $config['bg'] = '#FFF5F7';
            $config['text'] = '#702459';
            $config['sidebar_bg'] = '#FFE4E6';
            $config['sidebar_text'] = '#831843';
            $config['sidebar_active'] = '#FCC2D7';
            $config['card_bg'] = '#FFFFFF';
            $config['card_border'] = '#FBCFE8';
            $config['primary'] = '#D53F8C';
            $config['font'] = "'Quicksand', sans-serif";
            break;
        case 'cyber':
            $config['bg'] = '#000000';
            $config['text'] = '#22C55E';
            $config['sidebar_bg'] = '#0A0A0A';
            $config['sidebar_text'] = '#16A34A';
            $config['sidebar_active'] = '#14532D';
            $config['card_bg'] = '#050505';
            $config['card_border'] = '#22C55E';
            $config['primary'] = '#4ADE80';
            $config['font'] = "'JetBrains Mono', monospace";
            break;
        case 'nature':
            $config['bg'] = '#F0FDF4';
            $config['text'] = '#14532D';
            $config['sidebar_bg'] = '#14532D';
            $config['sidebar_text'] = '#DCFCE7';
            $config['sidebar_active'] = '#166534';
            $config['card_bg'] = '#FFFFFF';
            $config['card_border'] = '#BBF7D0';
            $config['primary'] = '#16A34A';
            $config['font'] = "'Merriweather', serif";
            break;
        case 'coral':
            $config['bg'] = '#FFF7ED';
            $config['text'] = '#7C2D12';
            $config['sidebar_bg'] = '#9A3412';
            $config['sidebar_text'] = '#FFEDD5';
            $config['sidebar_active'] = '#7C2D12';
            $config['card_bg'] = '#FFFFFF';
            $config['card_border'] = '#FED7AA';
            $config['primary'] = '#EA580C';
            $config['font'] = "'Outfit', sans-serif";
            break;
        case 'light':
        default:
            $config['bg'] = '#F3F4F6';
            $config['text'] = '#111827';
            $config['sidebar_bg'] = '#1F2937';
            $config['sidebar_text'] = '#E5E7EB';
            $config['sidebar_active'] = '#374151';
            $config['card_bg'] = '#FFFFFF';
            $config['card_border'] = '#E5E7EB';
            $config['primary'] = '#3B82F6';
            $config['font'] = "'Inter', sans-serif";
            break;
    }
    return $config;
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
    }
    .theme-card-header {
        background-color: rgba(0,0,0,0.05);
        border-bottom-color: var(--theme-card-border);
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
