<?php
require_once( 'lib/imagecatalog.php' );
require_once('lib/image.php');
require_once('lib/comment.php');
require_once('lib/string.php');


/**
 * ImageViewer loads a single image and its comments specified by a hash
 */
class ImageViewer extends ImageCatalog
{
  protected $position = 0;
  protected $hash = null;

  public $image = null;
  public $stream = array();


  public function setCurrent( $hash ) {
    $this->hash = $hash;
  }

  public function setCurrentHex( $hexhash ) {
    $this->setCurrent(hex2bin($hexhash));
  }

  public function addComment( $userId, $comment ) {
    if( $this->image['id'] && $userId && !empty($comment) ) {
      DB::insertRow( TABLE_COMMENTS, array(
        'imageId' => $this->image['id'],
        'userId' => $userId,
        'created' => date( 'Y.m.d H:i:s' ),
        'content' => $comment
      ));
      return true;
    } else {
      return false;
    }
  }


  public function load()
  {
    $r = DB::query_nofetch(
      'CALL pl_find_image_prev_next(?, ?)',
      array($this->hash, $this->user ? $this->user->id : null),
      array(PDO::FETCH_CLASS, 'Image', array(array('tags'))));

    $img = $r->fetch();
    if ($img === false)
      return false;

    if ($img->hash !== $this->hash) {
      $this->stream['prev'] = $img;
      $img = $r->fetch();
    }

    $this->image = $img;
    $img = $r->fetch();

    if ($img !== false) {
      $this->stream['next'] = $img;
    }

    $r->nextRowset();
    $r->closeCursor();
    unset($r);

    $this->image->uploader =
      !is_null($this->user) ? $this->user :
      ((isset($_SESSION) && isset($_SESSION['user'])
          && $_SESSION['user']->id === $this->image->uploader)
      ?
        $_SESSION['user']->updateScore() :
        new User($this->image->uploader, User::FETCH_SCORE));

    assert(!is_null($this->image->uploader->score));

    return true;
  }

}

?>
