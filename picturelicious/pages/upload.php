<?php
require_once('lib/image.php');
require_once('lib/http.php');


if (!$user) {
  http_redirect(Config::$absolutePath . 'login');
  exit();
}

$img = new Image;
$img->uploader = $user;

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
  //$tags = preg_split('/\s*,\s*/', @$_POST['tags'], -1, PREG_SPLIT_NO_EMPTY);

  if (!empty($_POST['url']))
    $img->source = $_POST['url'];

  try {
    $img->upload(@$_FILES['image']);
    $cache->clear();
    http_redirect(Config::$absolutePath . 'all/view/' . bin2hex($img->hash));
    exit(0);
  }
  catch (ImageUploadException $ex) {
    $uploadErrors = array($ex->getMessage());
    HTTPStatusCodes::set(($ex->getCode() > 0) ? (int) $ex->getCode() : HTTPStatusCodes::INTERNAL_SERVER_ERROR);
  }
}

include(Config::$templates . 'upload.tpl.php');

?>
