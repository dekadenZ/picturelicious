<?php

$all = empty($r[0]);

switch ($r[0])
{
  case 'all':
    $all = true;
  case 'search':
  case 'user':
    $actionIndex = 2 - (int) $all;
    if (@$r[$actionIndex] === 'view') {
      //----------------------------------------------------- view
      require_once('lib/imageviewer.php');
      $iv = new ImageViewer();

      /*
       * Althought this block was replaced by the equivalent subsequent block,
       * we keep it around for documentation:

      switch ($r[0]) {
        case 'search': // /search/term/view/[hash]/[keyword]
          $iv->setSearch(@$r[1]);
          break;

        case 'user': // /user/name/view/[hash]/[keyword]
          $iv->setUser(@$r[1]);
          break;

        default: // /all/view/[hash]/[keyword]
          break;
      }
       */

      if (!$all) {
        $set = 'set' . ucfirst($r[0]);
        $iv->$set(@$r[1]);
      }

      $iv->setCurrentHex(@$r[$actionIndex+1]);
      $iv->load();

      if (!is_null($iv->image)) {
        // Add comment if we have one
        if ($user && isset($_POST['addComment']) &&
          $iv->addComment($user->id, $_POST['content'])
        ) {
          $cache->clear($iv->image->keyword);
          http_redirect(
            Config::$absolutePath . $iv->basePath . 'view/' . $iv->image->keyword);
          exit();
        }
        else {
          $cache->capture();
          include(Config::$templates . 'view.tpl.php');
        }
      } else {
        notfound();
      }
      break;
    }
    // fall through

  case '': //----------------------------------------------------- browse
  case null:
    require_once('lib/imagebrowser.php');
    $ib = new ImageBrowser(Config::$images['thumbsPerPage']);

    $pageIndex = 2 - (int) $all;
    if (@$r[$pageIndex] === 'page')
      $ib->setPage(@$r[$pageIndex+1]);

    if (!$all) {
      $set = 'set' . ucfirst($r[0]);
      $ib->$set(@$r[1]);
    }

    $ib->load();
    if (!empty($ib->thumbs)) {
      require_once('lib/gridview.php');
      $gv = new GridView(Config::$gridView['width']);
      $ib->thumbs = $gv->solve($ib->thumbs);

      $cache->capture();
      include(Config::$templates . 'browse.tpl.php');
    } else {
      notfound();
    }
    break;
}

?>
