<!-- Footer -->
<div id="site-footer-wrapper">
  <footer class="w3-container w3-padding-16 w3-dark-grey">
  <center>
    <p>
      <a href="https://nielsmaarleveld.nl">Niels Maarleveld</a> - &copy; <?php echo date("Y");?> 
      <?php
      if (!function_exists('getGitBuildInfo')) {
          function getGitBuildInfo() {
              $gitBasePath = __DIR__ . '/../.git'; 
              $headFile = $gitBasePath . '/HEAD';
              
              $info = ['hash' => 'unknown', 'date' => 'unknown'];

              if (!file_exists($headFile)) {
                  return $info;
              }

              $headContents = trim(file_get_contents($headFile));

              // Check if HEAD points to a branch (e.g., "ref: refs/heads/main")
              if (strpos($headContents, 'ref:') === 0) {
                  $refParts = explode(' ', $headContents);
                  $refPath = $gitBasePath . '/' . $refParts[1];
                  
                  if (file_exists($refPath)) {
                      $hash = trim(file_get_contents($refPath));
                      $info['hash'] = substr($hash, 0, 7);
                      // Get the exact time the reference file was updated by the git sync
                      $info['date'] = date('d-m-Y H:i', filemtime($refPath)); 
                  }
              } else {
                  // If it's a detached HEAD, the file contains the hash directly
                  $info['hash'] = substr($headContents, 0, 7);
                  $info['date'] = date('d-m-Y H:i', filemtime($headFile));
              }
              
              return $info;
          }
      }
      
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
