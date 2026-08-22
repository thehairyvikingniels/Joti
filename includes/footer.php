<!-- Footer -->
<div id="site-footer-wrapper" class="mt-auto flex-shrink-0">
  <footer class="p-6 bg-white border-t border-gray-200 mt-6 theme-card">
    <div class="text-center text-sm text-gray-500 theme-text opacity-80">
      <p>
        <a href="https://nielsmaarleveld.nl" class="font-medium hover:underline theme-primary">Niels Maarleveld</a> - &copy; <?php echo date("Y");?> 
// Renders the site footer with author copyright, Git commit build metadata, and active kiosk details.
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
                
                echo ' | Build: <a href="' . htmlspecialchars($githubUrl) . '" target="_blank" rel="noopener" class="font-medium hover:underline theme-primary">' . htmlspecialchars($build['hash']) . '</a>';
                echo ' (' . htmlspecialchars($build['date']) . ')';
            } else {
                echo ' | Build: unknown';
            }
        }
        
        if (isset($_SESSION['kiosk_id']) && !empty($_SESSION['kiosk_naam'])) {
            echo ' | Kiosk: ' . htmlspecialchars($_SESSION['kiosk_naam']);
        }
        ?>
      </p>
    </div>
  </footer>
</div>
