#!/usr/bin/env php
<?php
include_once(dirname(__FILE__) . '/set-include-path.php');
require_once('lib/config.php');
require_once('lib/thumbnail.php');
require_once('lib/filesystem.php');
require_once('lib/string.php');


function array_last($arr) { return end($arr); }
function array_get($arr, $key) { return $arr[$key]; }

define('IMAGE_PATH', rtrim(Config::$images['imagePath'], '/'));
define('THUMB_PATH', Config::$images['thumbPath']);
define('THUMB_LASTDIR', THUMB_PATH . array_get(array_last(Config::$gridView['classes']), 'dir') . '/');


function create_thumbnails($path)
{
  if (starts_with($path, './'))
    $path = substr($path, 2);

  assert(starts_with($path, IMAGE_PATH));

  $suffix = substr($path, strlen(IMAGE_PATH) + 1,
    strrpos($path, '.') - strlen(IMAGE_PATH) - 1);
  printf("%s -> %s*/%s.jpg\n", $path, THUMB_PATH, $suffix);

  $stat = @stat(THUMB_LASTDIR . $suffix . '.jpg');
  if (!$stat || !$stat['size']) {
    try {
      $t = new Thumbnail($path);
      $t->writeThumbnails(Config::$gridView, $suffix, THUMB_PATH);
      $t->destroy();
    }
    catch (ImagickException $ex) {
      fprintf(STDERR, "Warning: %s: %s\n", $path, $ex->getMessage());
    }
  }
}


Imagick::setResourceLimit(Imagick::RESOURCETYPE_FILE, 255);
Imagick::setResourceLimit(Imagick::RESOURCETYPE_AREA, 256);
Imagick::setResourceLimit(Imagick::RESOURCETYPE_MEMORY, 256);
Imagick::setResourceLimit(Imagick::RESOURCETYPE_MAP, 1024);

$rv = Filesystem::foreach_file('create_thumbnails',
  (count($argv) <= 1) ? IMAGE_PATH : array_slice($argv, 1),
  count($argv) <= 1);

exit($rv ? 0 : 1);

?>
