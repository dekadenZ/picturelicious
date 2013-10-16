<?php

if ($user) {
  include(Config::$templates . 'quicktags.tpl.php');
} else {
  http_redirect(Config::$absolutePath . 'login');
}

?>
