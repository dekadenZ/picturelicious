<?php

if ($user) {
  if (Config::$vbbIntegration['enabled']) {
    require_once('lib/class.forumops.php');
  }
  $user->logout();
}
http_redirect(Config::$absolutePath);
exit();

?>
