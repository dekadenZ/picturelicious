<?php
require_once('lib/users.php');


class Image
{
  public $id;
  public $hash;
  public $keyword;
  public $width;
  public $height;
  public $uploader;
  public $uploadtime;

  public $votecount;
  public $rating;
  public $favorited_count;
  public $comments;

  private $tags, $path, $thumbnail;

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
      if (!is_null($this->{$prop}))
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
    if (strpos($prop, '->') !== false || strpos($prop, '[') !== false) {
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
    $this->tags = is_array($value) ? $value : explode("\0", $value);
  }


  private function fetchBy( $filter, $flags = 0 )
  {
    if (is_array($filter)) {
      assert(!empty($filter));
    } else if (is_integer($filter)) {
      $filter = array('id' => $filter);
    } else if (is_string($arg)) {
      $filter = array('keyword' => $filter);
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
      $where_clause .= ' AND t.`delete_reason` = \'\'';
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

?>
