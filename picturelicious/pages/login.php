<?php

if (Config::$vbbIntegration['enabled']) {
  require_once( 'lib/class.forumops.php' );
}

if (!$user && !empty($r[1])) {
  $user = new User;
  if ($user->validate($r[1])) {
    http_redirect(Config::$absolutePath);
  } else {
    http_redirect(Config::$absolutePath.'login');
  }
  exit(0);
}
if ($user) {
  http_redirect(Config::$absolutePath);
  exit();
}

if (isset($_POST['login'])) {
  $messages['wrongLogin'] = true;
}

include( Config::$templates.'login.tpl.php' );

?>
