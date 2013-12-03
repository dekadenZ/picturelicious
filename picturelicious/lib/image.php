<?php
require_once('lib/user.php');
require_once('lib/db.php');
require_once('lib/filesystem.php');
require_once('lib/arrays.php');


class Image
{
  public $id;
  public $hash;
  public $keyword;
  public $width;
  public $height;
  public $uploader;
  public $uploadtime;
  public $source;

  public $votecount;
  public $rating;
  public $favorited_count;
  public $comments;

  private $tags, $path, $thumbnail, $link;

  public $gridData;


  public function __construct( $arg = null )
  {
    if (is_array($arg)) {
      $this->call_setters($arg);
    } else if (!is_null($arg)) {
      $this->fetchBy($arg);
    }
  }


  private function call_setters( $setters )
  {
    foreach ($setters as $prop) {
      $this->__set($prop, $this->{$prop});
    }
  }


  public function __get( $prop )
  {
    $prop = 'get' . ucfirst($prop);
    if (is_callable(array($this, $prop))) {
      return $this->$prop();
    } else {
      throw new Exception('Call to undefined method ' . __CLASS__ . "::{$prop}()");
    }
  }


  public function __set( $prop, $value )
  {
    if (preg_match('/\W/', $prop)) {
      if (@eval("\$this->{$prop} = \$value;") === false)
        throw new Exception("Invalid expression in property name \"$prop\" for class " . __CLASS__);
    } else {
      $prop = 'set' . ucfirst($prop);
      if (is_callable(array($this, $prop))) {
        $this->$prop($value);
      } else {
        throw new Exception('Call to undefined method ' . __CLASS__ . "::{$prop}()");
      }
    }
  }


  public function getUploader( $flags = 0 )
  {
    if (is_integer($this->uploader)) {
      $this->uploader = new User($this->uploader, $flags);
    } else if ($this->uploader instanceof User && ($flags & User::FETCH_SCORE)) {
      assert(!is_null($this->uploader->score)); // unimplemented
    }
    return $this->uploader;
  }


  public function getTags()
  {
    return $this->tags;
  }

  public function setTags( $value )
  {
    $this->tags =
      is_string($value) ? explode("\0", $value) :
      (is_null($value) ? array() :
       $value);

    assert(
      is_array($this->tags) &&
      array_all($this->tags,
        function($s) { return is_string($s) && strlen($s) >= 2; }));
  }


  private function fetchBy( $filter, $flags = 0 )
  {
    if (is_array($filter)) {
      assert(!empty($filter));
    } else if (is_integer($filter)) {
      $filter = array('id' => $filter);
    } else if (is_string($filter)) {
      $filter = array('hash' => $filter);
    } else {
      throw new Exception('Unsupported filter type ' . gettype($arg));
    }

    $r = DB::getRow(
      DB::buildQueryString(__CLASS__, array_keys($filter), $flags),
      array_values($filter),
      array(PDO::FETCH_INTO, $this));

    if (is_string($this->tags))
      $this->tags = explode("\0", $this->tags);

    return $r !== false;
  }


  public function getComments()
  {
    if (is_null($this->comments)) {
      $this->comments =
        Comment::fetchAll(array('image' => $this->id),
          array('flags' => Comment::FETCH_RESOLVE_PARENTS));
    }
    return $this->comments;
  }


  public function getPath()
  {
    if (is_null($this->path) && !empty($this->hash)) {
      $this->path = bin2hex($this->hash);
    }
    return $this->path;
  }


  public function getThumbnail()
  {
    if (is_null($this->thumbnail) && $this->gridData &&
      !empty($this->gridData['class']))
    {
      $path = $this->getPath();
      if (!empty($this->path)) {
        $this->thumbnail =
          Config::$absolutePath . Config::$images['thumbPath'] .
          Config::$gridView['classes'][$this->gridData['class']]['dir'] .
          '/' . $this->getPath();
      }
    }
    return $this->thumbnail;
  }


  public function getLink()
  {
    if (is_null($this->link) && !empty($this->hash)) {
      $this->link = $this->getPath();
      if (!empty($this->keyword))
        $this->link .= '/' . $this->keyword;
    }
    return $this->link;
  }


  public function upload( $fileInfo )
  {
    require_once('lib/http.php');
    assert(is_null($this->id));
    var_dump($fileInfo);

    if (empty($fileInfo) || $fileInfo['error'] === UPLOAD_ERR_NO_FILE) {
      // TODO
      throw new ImageUploadException(
        'Uploading from a URL is unimplemented.',
        HTTPStatusCodes::NOT_IMPLEMENTED);
    }

    $im = $this->upload_read_image($fileInfo);

    try
    {
      $hexhash = sha1_file($im->getImageFilename());
      assert($hexhash !== false);
      $this->hash = hex2bin($hexhash);

      if (!empty($fileInfo['name']))
        $this->keyword = self::to_keyword($fileInfo['name']);

      $this->upload_database($im, array($this, 'upload_database_callback'));
      $this->uploadtime = $_SERVER['REQUEST_TIME'];

      $currentUser = @$_SESSION['user'];
      if ($currentUser && $this->uploader->id === $currentUser->id)
        $currentUser->updateScore(true);
    }
    catch (Exception $ex) { }

    /* finally */ {
      $im->destroy();
    }

    if (isset($ex)) { // 'catch' continued
      throw $ex;
    }
  }


  private function upload_read_image( $fileInfo )
  {
    $im = self::read_image($fileInfo, true);

    $imgType = $im->getImageFormat();
    if (!isset(self::$typeExtensions[$imgType])) {
      throw new ImageUploadException(
        "Unsupported image type \"$imgType\"",
        HTTPStatusCodes::UNSUPPORTED_MEDIA_TYPE);
    }

    $this->width = $im->getImageWidth();
    $this->height = $im->getImageHeight();
    if ($this->width * $this->height > Config::$images['maxPixels']) {
      throw new ImageUploadException(
        'The image is too large (≥ ' . si_size(Config::$images['maxPixels'], 2, 'Pixel', 1000) . ').',
        HTTPStatusCodes::FORBIDDEN);
    }
    if (min($this->width, $this->height) < Config::$images['minLength']) {
      throw new ImageUploadException(
        sprintf('The image is too small (< %d px at any border).',
          Config::$images['minLength']),
        HTTPStatusCodes::FORBIDDEN);
    }

    return $im;
  }


  public static function read_image( $fileInfo, $uploadedOnly )
  {
    $error =
      !(empty($fileInfo) && is_max_post_size_exceeded()) ?
        $fileInfo['error'] : UPLOAD_ERR_INI_SIZE;
    $status = HTTPStatusCodes::INTERNAL_SERVER_ERROR;

    switch ($error) {
      case UPLOAD_ERR_OK:
        break;

      case UPLOAD_ERR_PARTIAL:
        throw new ImageUploadException(
          'Your browser uploaded only a partial file. Please try again!',
          HTTPStatusCodes::BAD_REQUEST);

      case UPLOAD_ERR_INI_SIZE:
        $status = HTTPStatusCodes::REQUEST_ENTITY_TOO_LARGE;

        if (!empty($fileInfo)) {
          $upload_max_filesize = ini_get('upload_max_filesize');
          $upload_max_filesize =
            ($upload_max_filesize == 0) ?
              'NaN Byte' :
            (ctype_digit($upload_max_filesize) ?
              si_size((int) $upload_max_filesize, 'Byte', 3, 'guess') :
              sprintf('%d.0 %siByte', (int) $upload_max_filesize,
                $upload_max_filesize[strlen($upload_max_filesize)-1]));

          throw new ImageUploadException(
            "The file is too large (> $upload_max_filesize).", $status);
        }
        // fall through

      default:
        throw new ImageUploadException('Internal error', $status,
          new RuntimeException('Unexpected file upload error', $error));
    }

    if ($uploadedOnly && !is_uploaded_file($fileInfo['tmp_name'])) {
      throw new ImageUploadException('Nice try, dipshit!', 418);
    }

    require_once('lib/thumbnail.php');
    try {
      return new Thumbnail($fileInfo['tmp_name']);
    } catch (ImagickException $ex) {
      throw new ImageUploadException(
        'The file doesn\'t contain an image.',
        HTTPStatusCodes::UNSUPPORTED_MEDIA_TYPE, $ex);
    }
  }


  private function upload_database( Thumbnail $im, $callback = null )
  {
    DB::connect()->beginTransaction();

    try {
      DB::query_nofetch(
        'INSERT INTO ' . DB::escape_identifier(TABLE_IMAGES) .
        '(`hash`, `logged`, `user`, `source`, `width`, `height`, `keyword`)
        VALUES (?, ?, ?, ?, ?, ?, ?)',
        array(
          $this->hash,
          $_SERVER['REQUEST_TIME'],
          $this->uploader->id,
          $this->source,
          $this->width, $this->height,
          $this->keyword)
        );

      $id = DB::$link->lastInsertId();
      assert(is_int($id) || (!empty($id) && ctype_digit($id)));
      $this->id = intval($id, 10);

      if (is_callable($callback, true))
        $createdFiles = call_user_func($callback, $im);

      // TODO: colours, tags

      if (!DB::$link->commit()) {
        throw new Exception('Commit conflict?', HTTPStatusCodes::CONFLICT); // TODO: Figure out what happens on a commit conflict
      }
    }
    catch (Exception $ex) {
      if (isset($createdFiles))
        array_walk($createdFiles, 'Filesystem::unlink');

      DB::$link->rollBack();

      if ($ex instanceof PDOException && $ex->getCode() == 23000) {
        // TODO: provide an actual link
        throw new ImageUploadException(
          'This image is already in our database: ' .
          Config::$frontendPath . 'all/view/' . bin2hex($this->hash),
          HTTPStatusCodes::CONFLICT, $ex);
      }

      throw $ex;
    }
  }


  private static function to_keyword( $s )
  {
    $kw = preg_replace(
      '/(?:[\p{Cc}\p{Co}\p{Cn}\p{Zl}]+|^[\p{Cc}\p{Co}\p{Cn}\pZ]+|[\p{Cc}\p{Co}\p{Cn}\pZ]+$|[\p{Cc}\p{Co}\p{Cn}\pZ]*\.(?i:gif|png|jpe?g|jpe)$)/u', '', $s);
    return empty($kw) ? null : $kw;
  }


  private function upload_database_callback( Thumbnail $im )
  {
    $this->upload_move_from_tmppath($im, $pathSuffix);

    $pathSuffix .= '.' . self::$typeExtensions['JPEG'];
    $createdFiles =
      $im->writeThumbnails(Config::$gridView, $pathSuffix,
        Config::$images['thumbPath'], true);

    $createdFiles[] = $im->getImageFilename();

    return $createdFiles;
  }


  private function upload_move_from_tmppath( Thumbnail $im, &$pathSuffix )
  {
    $filename = bin2hex($this->hash);
    $dirPrefix = Filesystem::dir_prefix($filename, 2, 2);
    assert(empty($dirPrefix) || $dirPrefix[strlen($dirPrefix)-1] === '/');
    $dir = Config::$images['imagePath'] . $dirPrefix;
    $path = $dir . $filename  . '.' . self::$typeExtensions[$im->getImageFormat()];

    if (!Filesystem::mkdirr($dir) || !rename($im->getImageFilename(), $path))
      throw new ImageUploadException('Internal error',
        HTTPStatusCodes::INTERNAL_SERVER_ERROR);

    $im->setImageFilename($path);

    $pathSuffix = $dirPrefix . $filename;
  }


  public function delete( $db = true, $files = false )
  {
    if ($files) {
      assert(!empty($this->hash));
      $hexhash = bin2hex($this->hash);
      $suffix = Filesystem::dir_prefix($hexhash, 2, 2) . $hexhash;

      $thumbPrefix = Config::$images['thumbPath'];
      $thumbSuffix = '/' . $suffix . '.' . self::$typeExtensions['JPEG'];
      foreach (Config::$gridView['classes'] as $gc) {
        $path = $thumbPrefix . $gc['dir'] . $thumbSuffix;
        if (file_exists($path))
          unlink($path);
      }

      $path = glob(Config::$images['imagePath'] . $suffix . '.*',
          GLOB_NOSORT | GLOB_NOESCAPE | GLOB_ERR);
      if (!empty($path)) {
        assert(count($path) === 1);
        $path = $path[0];
        if (file_exists($path))
          unlink($path);
      }
    }

    if ($db) {
      if (!is_null($this->id)) {
        $id = 'id';
      } else if (!is_null($this->hash)) {
        $id = 'hash';
      } else {
        assert(!is_null($this->id) || !is_null($this->hash));
      }

      $r = DB::query_nofetch(
        'DELETE FROM ' . DB::escape_identifier(TABLE_IMAGES) .
        ' WHERE ' . DB::escape_identifier($id) . '=?',
        $this->{$id});
      return (bool) $r->rowCount();
    }
  }


  private static $typeExtensions = array(
      'JPEG' => 'jpg',
      'PNG' => 'png',
      'GIF' => 'gif'
    );


  const
    FETCH_VERBATIM_FILTER = 1,
    FETCH_DELETED = 2,
    FETCH_RATING = 4,
    FETCH_TAGS = 8,
    FETCH_UPLOADER = 16;

  /*
   * I'd much rather use database views instead of puzzling out complex
   * queries in PHP. Unfortunately MySQL/MariaDB 5.5 cannot efficiently merge
   * views with a GROUP BY clause into new queries. Instead it uses temporary
   * tables, which are awfully slow.
   */
  public static function buildQuery( $filter = null, $flags = 0 )
  {
    if (!($flags & self::FETCH_VERBATIM_FILTER) && !empty($filter))
      $filter = array_map(
        function($c) { return 'i.' . DB::escape_identifier($c); },
        $filter);

    $tables = DB::escape_identifier(TABLE_IMAGES) . ' AS i';
    $where_clause =
      empty($filter) ? 'TRUE' : (join('=? AND ', $filter) . '=?');
    $columns =
      'i.`id`, i.`hash`, i.`keyword`, i.`width`, `height`, i.`logged` AS `uploadtime`';

    if ($flags & self::FETCH_DELETED) {
      $columns .= ', i.`delete_reason';
    } else {
      $where_clause .= ' AND i.`delete_reason` = \'\'';
    }

    if ($flags & self::FETCH_UPLOADER) {
      $tables .= ' INNER JOIN ' . DB::escape_identifier(TABLE_USERS) . ' AS u ON i.`user` = u.`id`';
      foreach (explode('|', User::PROPERTIES) as $c)
        $columns .= ", u.`$c` AS `uploader->$c`";
    } else {
      $columns .= ', i.`user` AS `uploader`';
    }

    if ($flags & self::FETCH_TAGS) {
      $tables .= ' LEFT OUTER JOIN `pl_tags` AS t ON i.`id` = t.`image`';
      $where_clause .= ' AND (t.`delete_reason` IS NULL OR t.`delete_reason` = \'\')';
      $columns .= ', GROUP_CONCAT(t.`tag` SEPARATOR \'\0\') AS `tags`';
    }

    if ($flags & self::FETCH_RATING) {
      $tables .=
      ' LEFT OUTER JOIN `pl_images_legacy` AS l ON i.`id` = l.`image`
        LEFT OUTER JOIN `pl_imageratings` AS r FORCE INDEX (PRIMARY) ON i.`id` = r.`image`
        LEFT OUTER JOIN `pl_favorite_images` AS f ON i.`id` = f.`image`';
      $where_clause .= ' AND (r.`user` IS NULL OR i.`user` <> r.`user`)';
      $columns .= ',
        COUNT(r.`rating`) + IFNULL(l.`votecount`, 0) AS `votecount`,
        (IFNULL(SUM(r.`rating`), 0) + IFNULL(l.`rating`, 0) * IFNULL(l.`votecount`, 0)) / (COUNT(r.`rating`) + IFNULL(l.`votecount`, 0)) AS `rating`,
        COUNT(f.`user`) AS `favorited_count`';
    }

    //var_dump($columns, $tables, $where_clause);
    return array($columns, $tables, $where_clause);
  }

}


class ImageUploadException extends RuntimeException
{ }

?>
