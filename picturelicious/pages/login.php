<?php

if (Config::$vbbIntegration['enabled']) {
  require_once('lib/class.forumops.php');
}

if ($user) {
  http_redirect(Config::$absolutePath);
  exit();
}

if (isset($_POST['login'])) {
  $messages['wrongLogin'] = true;
}

include(Config::$templates . 'login.tpl.php');

?>
