<?php
// includes/theme.php

// Default theme is light
$theme = 'light';

// Check if user is logged in and has a theme set in session
if (isset($_SESSION['theme'])) {
    $theme = $_SESSION['theme'];
}

// Ensure theme is valid
$valid_themes = ['light', 'dark', 'pink', 'cyber', 'nature', 'red-orange'];
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
            $config['font'] = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";
            break;
        case 'pink':
            $config['bg'] = '#FDF2F8';
            $config['text'] = '#831843';
            $config['sidebar_bg'] = '#FCE7F3';
            $config['sidebar_text'] = '#9D174D';
            $config['sidebar_active'] = '#FBCFE8';
            $config['card_bg'] = '#FFFFFF';
            $config['card_border'] = '#FBCFE8';
            $config['primary'] = '#EC4899';
            $config['font'] = "'Nunito', 'Segoe UI', Tahoma, sans-serif";
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
            $config['font'] = "'Courier New', Courier, monospace";
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
            $config['font'] = "'Georgia', serif";
            break;
        case 'red-orange':
            $config['bg'] = '#FFF7ED';
            $config['text'] = '#7C2D12';
            $config['sidebar_bg'] = '#9A3412';
            $config['sidebar_text'] = '#FFEDD5';
            $config['sidebar_active'] = '#7C2D12';
            $config['card_bg'] = '#FFFFFF';
            $config['card_border'] = '#FED7AA';
            $config['primary'] = '#EA580C';
            $config['font'] = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";
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
            $config['font'] = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";
            break;
    }
    return $config;
}

$themeConfig = getThemeConfig($theme);
?>
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
