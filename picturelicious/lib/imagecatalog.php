<?php
require_once( 'lib/config.php' );
require_once( 'lib/db.php' );
require_once('lib/users.php');

/**
 * ImageCatalog is the abstract base class for ImageBrowser and ImageViewer. It
 * provides the basic functionality to help build queries to get the images out
 * of the database.
 */
class ImageCatalog
{
  public $user = null;
  public $searchTerm = null;
  public $searchColor = null;
  public $totalResults = 0;

  public $basePath = 'all/';

  public function setUser( $name )
  {
    $user = new User($name, User::FETCH_SCORE);
    if (is_null($user->id))
      return false;

    $this->user = $user;
    $this->basePath = "user/{$user->name}/";
    return true;
  }

  public function setSearch( $term ) {

    $this->basePath = 'search/'.htmlspecialchars($term).'/';

    if( preg_match( '/^\s*0x([0-9a-f]{6})\s*$/i', $term, $m ) ) {
      $c = str_split( $m[1], 2 );
      $this->searchColor = array(
        'r' => hexdec($c[0]),
        'g' => hexdec($c[1]),
        'b' => hexdec($c[2]),
      );
    }
    else if( !empty($term) ) {
      $this->searchTerm = $term;
      $ftq = preg_replace( '/\s+/',' +', $this->searchTerm );
      $this->seachCondition .= " AND (
        i.image LIKE ".DB::quote('%'.$this->searchTerm.'%')."
        OR MATCH( i.tags ) AGAINST ( ".DB::quote($ftq)." IN BOOLEAN MODE )
      )";
    }
  }



  public function load() {
  }
}

?>
