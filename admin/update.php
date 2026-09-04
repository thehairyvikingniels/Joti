<?php
// 301 Permanent Redirect from old /admin/update to /admin/system
header('Location: system', true, 301);
exit();
