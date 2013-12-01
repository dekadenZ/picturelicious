<?php

if ($user) {
  if (Config::$vbbIntegration['enabled']) {
    require_once('lib/class.forumops.php');
  }

  $messages = array();
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user->profile(@$_FILES['avatar'], $messages);
  }

  include(Config::$templates . 'profile.tpl.php');
}
else {
  http_redirect(Config::$absolutePath . 'login');
}

?>
