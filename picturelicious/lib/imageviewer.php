<?php
require_once( 'lib/imagecatalog.php' );
require_once('lib/image.php');

/**
 * ImageViewer loads a single image and its comments specified by a keyword
 */
class ImageViewer extends ImageCatalog
{
  protected $position = 0;
  protected $keyword = null;

  public $image = null;
  public $stream = array();
  public $comments = null;


  public function setCurrent( $keyword ) {
    $this->keyword = $keyword;
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
      array($this->keyword, $this->user ? $this->user->id : null),
      array(PDO::FETCH_CLASS, 'Image'));

    $img = $r->fetch();
    if ($img === false)
      return false;

    if ($img->keyword !== $this->keyword) {
      $this->stream['prev'] = $img->keyword;
      $img = $r->fetch();
    }

    if ($this->user)
      $img->uploader = $this->user;
    $this->image = $img;
    $img = $r->fetch();

    if ($img !== false) {
      $this->stream['next'] = $img->keyword;
    }

    $r->nextRowset();
    $r->closeCursor();
    unset($r);

    return true;
  }


  public function loadComments()
  {
    $this->comments = DB::query(
      'SELECT
        c.id, c.content, u.name, u.avatar,
        UNIX_TIMESTAMP(c.created) AS created
      FROM '.TABLE_COMMENTS.' c
      LEFT JOIN '.TABLE_USERS.' u
        ON u.id = c.userId
      WHERE c.imageId = :1
      ORDER BY created',
      $this->image->id
    );

    foreach ($this->comments as &$comment) {
      $comment['content'] = nl2br(preg_replace(
        '#(?<!\w)(((http|https|ftp)://)|(www\.))([^\s<>]+)#i',
        "<a href=\"$3://$4$5\">$4$5</a>",
        htmlspecialchars($comments['content'])));
    }
  }
}

?>
