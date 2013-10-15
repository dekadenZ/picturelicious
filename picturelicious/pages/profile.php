<?php

if ($user) {
  if (Config::$vbbIntegration['enabled']) {
    require_once('lib/class.forumops.php');
  }

  $messages = array();
  if (!empty($_POST)) {
    $user->profile($_FILES['avatar']['tmp_name'], $messages);
  }

  include(Config::$templates . 'profile.tpl.php');
}
else {
  http_redirect(Config::$absolutePath . 'login');
}

?>
