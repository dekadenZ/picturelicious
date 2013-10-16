<?php

if ($user) {
  $uploadErrors = array();
  if ($user->isSpamLocked()) {
    $uploadErrors[] = 'No more than 10 images in 2 hours!';
  }
  else if (!empty($_POST)) {
    require_once('lib/imageuploader.php');
    if((
        !empty($_POST['url']) &&
        ImageUploader::copyFromUrl($_POST['url'], $_POST['tags'], false, $uploadErrors)
      ) || (
        !empty($_FILES['image']['name']) &&
        ImageUploader::process($_FILES['image']['name'], $_FILES['image']['tmp_name'], $_POST['tags'], true, $uploadErrors)
    )) {
      $cache->clear();
      $user->logUpload();
      http_redirect(Config::$absolutePath);
      exit(0);
    }
  }
  include(Config::$templates . 'upload.tpl.php');
} else {
  http_redirect(Config::$absolutePath . 'login');
}

?>
