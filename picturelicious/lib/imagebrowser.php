<?php
require_once( 'lib/imagecatalog.php' );
require_once('lib/image.php');


/**
 * ImageBrowser loads a set of images specified by a search term, user and page.
 */
class ImageBrowser extends ImageCatalog {
  protected $page = 0;
  protected $thumbsPerPage = 0;

  public $thumbs = array();
  public $pages = array();

  public function __construct( $thumbsPerPage = 20 ) {
    $this->thumbsPerPage = abs(intval($thumbsPerPage));
  }

  public function setPage( $page ) {
    $page = intval($page);
    $this->page = $page > 0 ? $page - 1 : 0;
  }

  public function load() {

    if( !empty( $this->searchColor ) ) { // ----------------------------------- color search
      $this->thumbs = DB::query(
        'SELECT SQL_CALC_FOUND_ROWS
          i.logged, UNIX_TIMESTAMP(i.logged) AS loggedTS,
          i.keyword, i.thumb, i.score, i.votes,
          u.name AS userName,
          MIN( ABS(ic.r - :3) + ABS(ic.g - :4) + ABS(ic.b - :5) ) AS deviation
        FROM '.TABLE_IMAGES.' i
        LEFT JOIN '.TABLE_USERS.' u
          ON u.id = i.user
        LEFT JOIN '.TABLE_IMAGECOLORS.' ic
          ON ic.imageId = i.id
        WHERE
          ic.r BETWEEN :3 - :6 AND :3 + :6
        AND
          ic.g BETWEEN :4 - :6 AND :4 + :6
        AND
          ic.b BETWEEN :5 - :6 AND :5 + :6
        GROUP BY i.id
        ORDER BY deviation, i.id DESC
        LIMIT :1, :2',
        $this->page * $this->thumbsPerPage,
        $this->thumbsPerPage,

        $this->searchColor['r'],
        $this->searchColor['g'],
        $this->searchColor['b'],
        Config::$colorSearchDev
      );
    }
    else if( $this->searchTerm ) { // ------------------------ fulltext search
      $ftq = preg_replace( '/\s+/',' +', $this->searchTerm );
      $this->thumbs = DB::query(
        'SELECT SQL_CALC_FOUND_ROWS
          i.logged, UNIX_TIMESTAMP(i.logged) AS loggedTS,
          i.keyword, i.thumb, i.score, i.votes,
          u.name AS userName
        FROM '.TABLE_IMAGES.' i
        LEFT JOIN '.TABLE_USERS.' u
          ON u.id = i.user
        WHERE
          i.image LIKE :3
          OR MATCH( i.tags ) AGAINST ( :4 IN BOOLEAN MODE )
        ORDER BY i.id DESC
        LIMIT :1, :2',
        $this->page * $this->thumbsPerPage,
        $this->thumbsPerPage,

        '%'.$this->searchTerm.'%',
        $ftq
      );
    }
    else { // -------------------------------------------------------- user/all
      assert(is_int($this->thumbsPerPage));

      $params = array(
          'offset' => $this->page * $this->thumbsPerPage,
          'count' => $this->thumbsPerPage,
        );

      if (is_null($this->user)) {
        $where_clause = 'TRUE';
      } else {
        $where_clause = 'i.`user` = :user';
        $params['user'] = $this->user->id;
      }

      $this->thumbs = DB::query(
       'SELECT SQL_CALC_FOUND_ROWS
          i.`id`, i.`hash`, i.`keyword`,
          i.`thumb`, i.`width`, i.`height`, i.`logged`,
          COUNT(r.`rating`) + IFNULL(l.`votecount`, 0) AS `votecount`,
          (IFNULL(SUM(r.`rating`), 0) + IFNULL(l.`rating`, 0) * IFNULL(l.`votecount`, 0)) / (COUNT(r.`rating`) + IFNULL(l.`votecount`, 0))
          AS `rating`,
          COUNT(f.`user`) AS `favorited_count`,
          u.`id`, u.`name`
        FROM ' . DB::escape_identifier(TABLE_IMAGES) . ' AS i
          INNER JOIN ' . DB::escape_identifier(TABLE_USERS) . ' AS u ON u.`id` = i.`user`
          LEFT OUTER JOIN `pl_images_legacy` AS l ON i.`id` = l.`image`
          LEFT OUTER JOIN `pl_imageratings` AS r FORCE INDEX (PRIMARY) ON i.`id` = r.`image`
          LEFT OUTER JOIN `pl_favorite_images` AS f ON i.`id` = f.`image`
        WHERE (' . $where_clause . ') AND i.`delete_reason` = \'\' AND
          (r.`user` IS NULL OR i.`id` <> r.`user`)
        GROUP BY i.`id`
        ORDER BY i.`id` DESC
        LIMIT :offset, :count',

        $params,
        array(PDO::FETCH_FUNC, __CLASS__.'::__fetchImageCallback'));
    }

    $this->totalResults = DB::foundRows();
    if (!is_null($this->user))
      $this->user->imageCount = $this->totalResults;


    // compute previous, current and next page
    if( $this->totalResults > 0 ) {
      $this->pages['current'] = $this->page+1;
      $this->pages['total'] = ceil($this->totalResults / $this->thumbsPerPage);
      if( $this->page > 0 ) {
        $this->pages['prev'] = $this->page;
      }
      if ($this->pages['current'] < $this->pages['total']) {
        $this->pages['next'] = $this->page + 2;
      }
    }
  }


  public function __fetchImageCallback( $id, $hash, $keyword, $thumbnail, $width,
    $height, $uploadtime, $votecount, $rating, $favorited_count, $uploaderId,
    $uploaderName )
  {
    $i = new Image;
    foreach ($i as $prop => &$value) {
      if (isset(${$prop}))
        $value = ${$prop};
    }

    $u = new User;
    $u->id = $uploaderId;
    $u->name = $uploaderName;
    $i->setUploader($u);

    return $i;
  }


  public function loadRandom( $minScore, $thumbSize ) {
    $this->thumbs = DB::query(
      'SELECT SQL_CALC_FOUND_ROWS
        i.logged, UNIX_TIMESTAMP(i.logged) AS loggedTS,
        i.keyword, i.thumb, i.score, i.votes,
        u.name AS userName
      FROM '.TABLE_IMAGES.' i
      LEFT JOIN '.TABLE_USERS.' u
        ON u.id = i.user
      WHERE i.score >= :2
      ORDER BY RAND()
      LIMIT :1',

      $this->thumbsPerPage,
      $minScore
    );

    foreach( array_keys( $this->thumbs ) as $i ) {
      $this->thumbs[$i]['thumb'] = Config::$images['thumbPath'] .
        str_replace( '-', '/', substr( $this->thumbs[$i]['logged'], 0, 7 ) )
        .'/'. $thumbSize
        .'/'. $this->thumbs[$i]['thumb'];
    }
  }
}

?>
