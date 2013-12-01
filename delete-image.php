#!/usr/bin/env php
<?php
include_once(dirname(__FILE__) . '/set-include-path.php');
require_once('lib/config.php');
require_once('lib/image.php');
require_once('lib/string.php');


$count = 0;
$img = new Image;

for ($i = 1; $i < count($argv); ++$i) {
  $img->hash = hex2bin($argv[$i]);
  if ($img->hash !== false && strlen($img->hash) === 20) {
    printf('Deleting %s... ', strtolower($argv[$i]));
    $success = $img->delete(true, true);
    $count += $success;
    printf("%s\n", $success ? 'success' : 'failure!');
  } else {
    fprintf("Warning: '%s' doesn't seem to be a hexadecimal SHA1 hash -- skipping.\n", $argv[$i]);
  }
}

if ($count)
  printf("\n%d images deleted\n", $count);

exit($count ? 0 : 1);

?>
