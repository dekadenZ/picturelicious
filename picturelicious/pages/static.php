<?php

preg_match('/(\w+)/', @$r[1], $m);
$m = !empty($m[1]) ? "static/{$m[1]}.html.php" : null;
if (!is_null($m) && file_exists($m) {
  include(Config::$templates . 'header.tpl.php');
  include($m);
  include(Config::$templates . 'footer.tpl.php');
} else {
  notfound();
}

?>
