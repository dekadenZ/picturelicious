<?php

if (Config::$vbbIntegration['enabled']) {
  require_once( 'lib/class.forumops.php' );
}

if ($user) {
  http_redirect(Config::$absolutePath);
  exit();
}

$user = new User;
if (isset($_POST['register']) && $user->register($messages)) {
  include( Config::$templates.'registered.tpl.php' );
} else {
  include( Config::$templates.'register.tpl.php' );
}

?>
