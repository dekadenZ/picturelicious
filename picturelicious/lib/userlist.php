<?php
require_once( 'lib/config.php' );
require_once( 'lib/db.php' );
require_once('lib/user.php');


/**
 * The UserList class loads a number of users from the database, specified by a
 * page.
 */
class UserList
{
  protected $usersPerPage;

  public $users, $totalResults;
  public $pages;


  public function __construct( $usersPerPage ) {
    $this->usersPerPage = $usersPerPage;
    $this->pages = new stdclass;
  }


  public function setPage( $page ) {
    $this->pages->current = max(intval($page), 1);
  }


  public function load()
  {
    $q = User::buildQuery(null, User::FETCH_SCORE);
    $this->users = DB::query(
      "SELECT SQL_CALC_FOUND_ROWS $q[0] FROM $q[1] WHERE $q[2] GROUP BY u.`id` ORDER BY `score` DESC LIMIT ?, ?",
      array(
        ($this->pages->current - 1) * $this->usersPerPage,
        $this->usersPerPage),
      array(PDO::FETCH_CLASS, 'User'));

    array_walk($this->users, 'User::fix_types');
    $this->totalResults = DB::foundRows();
    $this->pages->total = ceil($this->totalResults / $this->usersPerPage);

    // compute previoues, current and next page
    if ($this->totalResults) {
      $this->pages->prev =
        ($this->pages->current > 1) ? $this->pages->current - 1 : null;
      $this->pages->next =
        ($this->pages->current < $this->pages->total) ? $this->pages->current + 1 : null;
    }
  }

}

?>
