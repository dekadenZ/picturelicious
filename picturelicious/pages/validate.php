<?php
require_once('lib/http.php');


if (Config::$vbbIntegration['enabled']) {
  require_once( 'lib/class.forumops.php' );
}

if (!empty($r[1])) {
  if (!$user)
    $user = new User;

  if ($user->validate($r[1])) {
    http_redirect(Config::$absolutePath);
  } else {
    HTTPStatusCodes::set(HTTPStatusCodes::FORBIDDEN);
  }

  exit(0);
}

notfound();

?>
