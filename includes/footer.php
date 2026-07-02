<!-- Footer -->
<div id="site-footer-wrapper">
  <footer class="w3-container w3-padding-16 w3-dark-grey">
  <center>
    <p>
      <a href="https://nielsmaarleveld.nl">Niels Maarleveld</a> - &copy; <?php echo date("Y");?> 
      <?php
      if (function_exists('getGitBuildInfo')) {
          $build = getGitBuildInfo();
          if ($build['hash'] !== 'unknown') {
              $githubUrl = "https://github.com/thehairyvikingniels/joti/commit/" . $build['hash'];
              
              echo ' | Build: <a href="' . htmlspecialchars($githubUrl) . '" target="_blank" rel="noopener">' . htmlspecialchars($build['hash']) . '</a>';
              echo ' (' . htmlspecialchars($build['date']) . ')';
          } else {
              echo ' | Build: unknown';
          }
      }
      ?>
    </p>
  </center>
  </footer>
</div>
