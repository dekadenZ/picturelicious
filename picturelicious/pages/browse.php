<?php

switch ($r[0])
{
  case 'all':
  case 'search':
  case 'user':
    if ( //----------------------------------------------------- view
      @$r[ ($r[0] === 'all') ? 1 : 2 ] === 'view'
    ) {
      require_once('lib/imageviewer.php');
      $iv = new ImageViewer();

      switch ($r[0]) {
        case 'search': // /search/term/view/2007/09/hans
          $iv->setSearch(@$r[1]);
          $offset = 3;
          $length = null;
          break;

        case 'user': // /user/name/view/2007/09/hans
          $iv->setUser(@$r[1]);
          $offset = 3;
          $length = 3;
          break;

        default: // /all/view/2007/09/hans
          $offset = 2;
          $length = 3;
          break;
      }

      $iv->setCurrent(join('/', array_slice($r, $offset, $length)));
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
          //$iv->loadComments(); // TODO
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

    $all = !in_array($r[0], array('search', 'user'));
    $pageIndex = 2 - intval($all);

    if (@$r[$pageIndex] === 'page')
      $ib->setPage(@$r[$pageIndex+1]);

    if (!$all) {
      $set = 'set' . ucfirst($r[0]);
      $ib->$set(@$r[1]);
    }

    $ib->load();
    if (!empty($ib->thumbs)) {
      require_once('lib/gridview.php');
      $gv = new GridView(Config::$gridView['gridWidth']);
      $ib->thumbs = $gv->solve($ib->thumbs);

      $cache->capture();
      include(Config::$templates . 'browse.tpl.php');
    } else {
      notfound();
    }
    break;
}

?>
