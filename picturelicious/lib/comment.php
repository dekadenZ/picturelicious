<?php
require_once('lib/db.php');
require_once('lib/string.php');


class Comment
{
  public $id, $parent;
  public $image, $author, $created, $edited;
  public $content, $delete_reason;
  public $rating = array();


  public function __set( $prop, $value )
  {
    if (!$this->author && starts_with($prop, 'author->'))
      $this->author = new User;

    if (@eval("\$this->{$prop} = \$value;") === false)
      throw new Exception("Invalid expression in property name \"$prop\" for class " . __CLASS__);
  }


  const FETCH_DELETED = 1;
  const FETCH_IMAGE = 2;
  const FETCH_RESOLVE_PARENTS = 4;

  public static function fetchAll( $filter = array(), $opt = array() )
  {
    $tables =
      DB::escape_identifier(TABLE_COMMENTS) . ' AS c
        INNER JOIN ' .
      DB::escape_identifier(TABLE_USERS) . ' AS u ON c.`author` = u.`id`
        LEFT OUTER JOIN
      `pl_commentratings` AS r FORCE INDEX (PRIMARY) ON c.`id` = r.`comment`';
    $columns =
      'c.`id`, c.`parent`, c.`created`, c.`edited`, c.`content`,
        SUM(r.`rating` > 0) AS `rating[+1]`,
        SUM(r.`rating` < 0) AS `rating[-1]`,
        u.`id` AS `author->id`, u.`name` AS `author->name`, u.`avatar` AS `author->avatar`';
    $where_clause = empty($filter) ? '' :
      ('c.' . join('=? AND c.', array_map('DB::escape_identifier', array_keys($filter))) . '=? AND');
    $filter = array_values($filter);
    $flags = intval(@$opt['flags']);

    if ($flags & self::FETCH_DELETED) {
      $columns .= ', CAST(c.`delete_reason` AS UNSIGNED) AS `delete_reason`';
    } else {
      $where_clause .= ' c.`delete_reason` = \'\' AND';
    }

    if ($flags & self::FETCH_IMAGE) {
      $tables .= ' STRAIGHT_JOIN ' . DB::escape_identifier(TABLE_IMAGES) . ' AS i ON c.`image` = i.`id`';
      $columns .= ',
        i.`id` AS `image->id`,
        i.`keyword` AS `image->keyword`,
        i.`thumb` AS `image->thumbnail`';
      if ($flags & self::FETCH_DELETED)
        $where_clause .= ' i.`delete_reason` = \'\' AND';
    } else {
      $columns .= ', c.`image`';
    }

    $other =
      !isset($opt['order']) ? ' ORDER BY c.`created` ASC' :
      (empty($opt['order']) ? '' :
        (' ORDER BY c.' . join(', c.', array_map(
          function($c) {
            switch ($c[0]) {
              case '-': $o = ' DESC'; break;
              case '+': $o = ' ASC'; break;
              default: $o = '';
            }
            return DB::escape_identifier(empty($o) ? $c : substr($c, 1)) . $o;
          },
          $opt['order']))));

    if (isset($opt['limit'])) {
      $other .= ' LIMIT ?';
      $limit = $opt['limit'];
      if (is_array($limit)) {
        assert(!empty($limit));
        $other .= str_repeat(', ?', count($limit) - 1);
        $filter = array_merge($filter, $limit);
      } else {
        $filter[] = $limit;
      }
    }

    $fetchMode = array(PDO::FETCH_CLASS, __CLASS__);
    $r = DB::query_nofetch(
     "SELECT $columns FROM $tables WHERE $where_clause IFNULL(c.`author` <> r.`user`, TRUE) GROUP BY c.`id`$other",
      $filter, ($flags & self::FETCH_RESOLVE_PARENTS) ? $fetchMode : null);

    if ($flags & self::FETCH_RESOLVE_PARENTS) {
      $unresolved = 0;
      $comments = array();
      foreach ($r as $c) {
        $comments[$c->id] = $c;

        $p = $c->parent;
        if (is_int($p)) {
          if (isset($comments[$p])) {
            $c->parent = $comments[$p];
          } else {
            $unresolved++;
          }
        }
      }

      $r->closeCursor();
      unset($r);

      if ($unresolved) {
        foreach ($comments as $c) {
          $p = $c->parent;
          if (is_int($p)) {
            if (isset($comments[$p]))
              $c->parent = $comments[$p];
            if (!(--$unresolved))
              break;
          }
        }
      }
    }
    else {
      $comments = call_user_func_array(array($r, 'fetchAll'), $fetchMode);
      $r->closeCursor();
    }

    return $comments;
  }


  public function getContent( $htmlWithLinks = false )
  {
    $content = $this->content;
    if (!empty($content) && $htmlWithLinks) {
      $content = preg_replace_callback(
        '/[<>"&]+|(?<lparen>[\[(<]?)\b(?<uri>(?:(?<scheme>https?|ftp|urn|magnet|mailto|xmpp|irc|mumble|teamspeak):|www\.)[-\p{L}\p{N}\p{S}\p{Cs}+&@#\/%?=~_()|!:,.;\']*[-\p{L}\p{N}\p{S}\p{Cs}+&@#\/%=~_(|])(?<tparen>[\])>]?)/u',
        function($m) {
          if (strlen($m[0]) === 1)
            return htmlspecialchars($m[0]);

          $uri = $m['uri'];
          $lparen = $m['lparen'];
          $tparen = $m['tparen'];
          if (empty($lparen) || empty($tparen) ||
            ord($tparen) - ord($lparen) > 2)
          {
            $uri .= $tparen;
            $tparen = '';
          }

          $uri = htmlspecialchars($uri);

          $scheme = $m['scheme'];
          switch ($scheme) {
            case null:
            case '':
              $uritext = $uri;
              $uri = 'http://' . $uri;
              break;

            case 'http':
            case 'https':
            case 'ftp':
            case 'mailto':
              $uritext = substr($uri, strlen($scheme) + strspn($uri, ':/', strlen($scheme)));
              break;

            default;
              $uritext = $uri;
          }

          return "$lparen<a href=\"$uri\">$uritext</a>$tparen";
        },
        $content);
    }
    return $content;
  }

}

?>
