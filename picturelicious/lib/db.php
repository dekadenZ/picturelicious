<?php
require_once( 'lib/config.php' );

/*
 * This class handles all database IO via the PDO API. To prevent SQL injection
 * attacks all variables should be passed as additional parameters. The query
 * is then transformed to a prepared statement.

 * Example:
 *   $res = DB::query(
 *     'SELECT * FROM `images` WHERE `user` = ? AND `tags` LIKE ?',
 *     $user, $_GET['q']);
 *
 * or:
 *   $res = DB::query(
 *     'SELECT * FROM `images` WHERE `user` = :user AND `tags` LIKE :search',
 *     array('user' => $user, 'search' => $_GET['q']));
 *
 * query() and getRow() return a 2-dimensional array instead of a result resource.
 */
class DB {
  public static $link = null;
  public static $result = null;
  public static $sql;
  public static $numQueries = 0;

  private static function connect() {
    if (!is_null(self::$link))
      return self::$link;

    $db = Config::$db;
    try {
      self::$link = new PDO($db['datasource'], $db['user'], $db['password'],
        array(
          PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\'',
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
          PDO::ATTR_EMULATE_PREPARES => false
        ));
    } catch (PDOException $e) {
      die('Couldn\'t establish PDO database connection: ' . $e->getMessage() . "\nconnection string: " . $db['datasource']);
    }
  }

  public static function foundRows() {
    $r = self::query_nofetch('SELECT FOUND_ROWS()');
    try {
      $rv = $r->fetchColumn();
    } catch (Exception $ex) {
    }

    // finally
    $r->closeCursor();

    if (isset($ex))
      throw $ex;

    return $rv;
  }

  public static function numRows() {
    /* This works only for some versions of MySQL (and MariaDB). Visit
     * http://www.php.net/manual/en/pdostatement.rowcount.php for details.
     */
    return self::$result->rowCount();
  }

  public static function affectedRows() {
    return self::$result->rowCount();
  }

  public static function insertId() {
    return self::$link->lastInsertId();
  }

  public static function query_nofetch( $q, $params = array(),
    $fetch_mode = null )
  {
    if (!is_array($params)) {
      $params = array_slice( func_get_args(), 1 );
      $fetch_mode = null;
    } else {
      assert(is_null($fetch_mode) || is_array($fetch_mode));
    }

    self::connect();

    try {
      if (self::$result)
        self::$result->closeCursor();

      if (empty($params) && empty($fetch_mode)) {
        self::$result = $r = self::$link->query($q);
      } else {
        self::$result = $r = self::$link->prepare($q);

        if (!empty($fetch_mode)) {
          call_user_func_array(array($r, 'setFetchMode'), $fetch_mode);
        }

        /*
        foreach ($params as $k => $v) {
          $type = (is_null($v) ? PDO::PARAM_NULL :
            (is_int($v) ? PDO::PARAM_INT :
            (is_bool($v) ? PDO::PARAM_BOOL :
              PDO::PARAM_STRING)));
          $r->bindParam(is_int($k) ? $k + 1 : ":$k", $v, $type);
        }
        */

        $r->execute($params);
      }
      return $r;
    } catch (PDOException $e) {
      array_push($e->errorInfo, $q, $params);
      throw $e;
    }
  }

  public static function query( $q, $params = array(),
    $fetch_mode = array(PDO::FETCH_ASSOC) )
  {
    $r = call_user_func_array(__CLASS__.'::query_nofetch', func_get_args());
    try {
      $rv = $r->fetchAll();
    } catch (Exception $ex) {
    }

    // finally
    $r->closeCursor();

    if (isset($ex))
      throw $ex;

    return $rv;
  }

  public static function getRow( $q, $params = array(),
    $fetch_mode = array(PDO::FETCH_ASSOC) )
  {
    $r = call_user_func_array(__CLASS__.'::query_nofetch', func_get_args());
    try {
      $rv = $r->fetch();
    } catch (Exception $ex) {
    }

    // finally
    $r->closeCursor();

    if (isset($ex))
      throw $ex;

    return $rv;
  }

  public static function updateRow( $table, $idFields, $updateFields )
  {
    assert(is_array($idFields) && !empty($idFields) && !isset($idFields[0]));
    assert(is_array($updateFields) && !empty($updateFields) && !isset($updateFields[0]));

    $escape_identifier = __CLASS__.'::escape_identifier';
    $r = self::query_nofetch(
      'UPDATE ' . escape_identifier($table) . '
      SET ' . join('=?, ', array_map($escape_identifier, array_keys($updateFields))) . '=?
      WHERE ' . join('=?, ', array_map($escape_identifier, array_keys($idFields))) . '=?',
      array_merge(array_values($updateFields), array_values($idFields)));

    return $r->rowCount();
  }

  public static function insertRow( $table, $insertFields )
  {
    assert(is_array($insertFields) && !empty($insertFields));

    $q = 'INSERT INTO ' . escape_identifier($table);
    if (!isset($insertFields[0])) {
      $q .= ' (' . join(', ', array_map(__CLASS__.'::escape_identifier', array_keys($insertFields))) . ')';
    }
    $q .= ' VALUES (' . join(', ', array_fill(0, count($insertFields), '?')) . ')';

    return self::query_nofetch($q, array_values($insertFields));
  }

  public static function escape_identifier( $name )
  {
    return '`' . str_replace('`', '``', $name) . '`';
  }
}

?>
