<?php
// Footer component
require_once(__DIR__ . '/helpers.php');
?>
<div id="site-footer-wrapper" class="mt-auto w-full flex-shrink-0">
  <footer class="py-4 px-6 bg-white border-t border-gray-200 theme-card text-center text-sm text-gray-500 theme-text opacity-80">
    <p>
      <a href="https://nielsmaarleveld.nl" class="font-medium hover:underline theme-primary">Niels Maarleveld</a> - &copy; <?php echo date("Y");?> 
      <?php
      if (function_exists('getGitBuildInfo')) {
          $build = getGitBuildInfo();
          if ($build['hash'] !== 'unknown') {
              $githubUrl = "https://github.com/thehairyvikingniels/Joti/commit/" . $build['hash'];
              
              echo ' | Build: <a href="' . htmlspecialchars($githubUrl) . '" target="_blank" rel="noopener" class="font-medium hover:underline theme-primary">' . htmlspecialchars($build['hash']) . '</a>';
              echo ' (' . htmlspecialchars($build['date']) . ')';
          } else {
              echo ' | Build: unknown';
          }
      }
      
      if (isset($_SESSION['kiosk_id']) && empty($_SESSION['id']) && !empty($_SESSION['kiosk_naam'])) {
          echo ' | Kiosk: ' . htmlspecialchars($_SESSION['kiosk_naam']);
      }
      ?>
    </p>
  </footer>
</div>
