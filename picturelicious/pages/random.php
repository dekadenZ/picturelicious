<?php

$count = max(1, min(Config::$maxRandomThumbs, intval(@$r[1])));
$size = '';
list($twidth, $theight) = sscanf(@$r[2], '%ux%u');
foreach (Config::$gridView['classes'] as $c) {
  if (@$r[2] === $c['dir']) {
    $size = $r[2];
    break;
  }
  if (empty($size)) {
    $size = $c['dir'];
  }
}

require_once('lib/imagebrowser.php');
$ib = new ImageBrowser($count);
$ib->loadRandom( Config::$minRandomScore, $size);

$cache->forceEnable();
$cache->capture();
include(Config::$templates.'random.js.php');

?>
