<?php
require_once( 'lib/config.php' );
require_once( 'lib/db.php' );
require_once('lib/base64.php');
require_once('lib/password.php');
require_once('lib/http.php');
require_once('lib/string.php');


/*
 * An object of this class represents a user on the site. All user
 * related tasks (registration, login, profile etc.) are handled here.
 *
 * A user object is created and attempted to login on each page load.
 */
class User
{
  public $name;
  public $id;
  public $admin;
  public $website;
  public $email;
  public $avatar;

  // aggregated user info
  public $score;
  public $imageCount, $commentCount, $tagCount;

  private $validationString;
  private $passwordHash;

  // set whenever this object differs from it's database representation
  private $modified;


  public function __construct( $uniqueIdentifier = null, $flags = 0 )
  {
    if (!is_null($uniqueIdentifier)) {
      if (is_integer($uniqueIdentifier)) {
        $what = 'id';
      } else if (is_string($uniqueIdentifier)) {
        $what = 'name';
      } else {
        throw new Exception('Unsupported type ' . gettype($uniqueIdentifier) . ' for $uniqueIdentifier');
      }

      assert(isset($what));
      $this->fetchBy(array($what => $uniqueIdentifier), $flags);
    }
  }


  public function __set( $prop, $value )
  {
    switch ($prop) {
      case 'passwordHash':
      case 'validationString':
        $this->{$prop} = $value;
        break;

      default:
        throw new Exception("Unknown or inaccessible property $prop on object of class ".__CLASS__);
    }
  }


  const PROPERTIES = 'name|id|admin|website|email|avatar';

  public function __from_array( $a )
  {
    foreach (explode('|', self::PROPERTIES) as $prop) {
      if (isset($a[$prop]))
        $this->{$prop} = $a[$prop];
    }
  }

  public function &__to_array( &$a = array() )
  {
    foreach (explode('|', self::PROPERTIES) as $prop) {
      if (!is_null($this->{$prop}))
        $a[$prop] = $this->{$prop};
    }
    return $a;
  }


  private function __to_array_all()
  {
    $a = array();
    foreach ($this as $prop => $value) {
      if (!is_null($value))
        $a[$prop] = $value;
    }
    return $a;
  }


  public function reset()
  {
    foreach ($this as &$value)
      $value = null;
  }


  public function validate( $id )
  {
    $validationString =
      Base64::decode($id, Base64::URI, self::VALIDATIONSTRING_BYTES);

    if ($validationString === false)
      return false;

    $rv = DB::getRow(
      'CALL pl_validate_user_change(?, TRUE)',
      array($validationString),
      array(PDO::FETCH_INTO, $this));
    if ($rv === false)
      return false;
    unset($rv);

    session_name( Config::$sessionCookie );
    session_start();
    $_SESSION['user'] = $this;

    if( Config::$vbbIntegration['enabled'] ) {
      global $vbulletin;
      $forum = new ForumOps($vbulletin);
      $forum->login(array('username' => $this->name));
    }

    return true;
  }


  public static function getInstance()
  {
    return self::login();
  }


  public static function login()
  {
    // attempt to login via post, session or remember cookie
    session_name( Config::$sessionCookie );

    if (isset($_POST['login']))
      return self::login_password();

    $u = self::login_session();
    if ($u !== false)
      return $u;

    return self::login_remember();
  }


  private static function login_password()
  {
    if (empty($_POST['name']) || empty($_POST['pass']))
      return false;

    $name = $_POST['name'];
    $password = $_POST['pass'];

    $u = new User;
    if ($u->fetchBy(array('name' => $name, 'valid' => true)) &&
      $u->check_password($password, true)
    ) {
      if (filter_input(INPUT_POST, 'remember', FILTER_VALIDATE_BOOLEAN))
        $u->update_remember();

      session_start();
      $_SESSION['user'] = $u;

      if( Config::$vbbIntegration['enabled'] ) {
        global $vbulletin;
        $forum = new ForumOps($vbulletin);
        $forum->login(array('username' => $u->name));
      }
    }
    else {
      $u = false;
    }

    return $u;
  }


  private static function login_session()
  {
    if (!empty($_COOKIE[Config::$sessionCookie])) {
      // session running
      session_start();
      if (isset($_SESSION['user'])) {
        return $_SESSION['user'];
      } else {
        setcookie(Config::$sessionCookie, false, 1, Config::$absolutePath);
      }
    }
    return false;
  }


  private static function login_remember()
  {
    if (empty($_COOKIE[Config::$rememberCookie]))
      return false;

    $validationString = Base64::decode($_COOKIE[Config::$rememberCookie],
        Base64::URI, self::VALIDATIONSTRING_BYTES);

    if ($validationString === false) {
      setcookie(Config::$sessionCookie, false, 1, Config::$absolutePath);
      return false;
    }

    // remember cookie found
    $u = new User;
    if ($u->fetchBy(array('remember' => $validationString))) {
      // refresh for another year
      $u->validationString = $validationString;
      $u->update_remember_cookie($_COOKIE[Config::$rememberCookie]);

      session_start();
      $_SESSION['user'] = $u;

      if( Config::$vbbIntegration['enabled'] ) {
        global $vbulletin;
        $forum = new ForumOps($vbulletin);
        $forum->login(array('username' => $u->name));
      }
    }

    return $u;
  }


  private function check_password( $password, $fetch = false )
  {
    if (empty($password))
      return false;

    if (empty($this->passwordHash)) {
      if ($fetch && !is_null($this->id)) {
          try {
            $r = DB::query_nofetch(
              'SELECT `pass` FROM ' . DB::escape_identifier(TABLE_USERS) .
              'WHERE `id` = ?',
              $this->id);
            $this->passwordHash = $r->fetchColumn();
          } catch (Exception $e) {
          }

          // finally
          $r->closeCursor();
          unset($r);

          if (isset($e))
            throw $e;
      }
      else {
        return null;
      }
    }

    require_once('lib/password.php');

    if (!empty($this->passwordHash) &&
      ($this->passwordHash[0] === '$') ?
        password_verify($password, $this->passwordHash) :
        (md5($password) === $this->passwordHash))
    {
      if (password_needs_rehash($this->passwordHash, PASSWORD_DEFAULT)) {
        $this->passwordHash = self::password_hash($password);
        $r = DB::query_nofetch(
          'UPDATE ' . DB::escape_identifier(TABLE_USERS) .
          'SET `pass` = ? WHERE `id` = ?',
          $this->passwordHash, $this->id);
        assert($r && $r->rowCount() === 1);
      }
      return true;
    }
    return false;
  }


  private static function password_hash( $password )
  {
    return password_hash($password, PASSWORD_DEFAULT);
  }


  const VALIDATIONSTRING_BYTES = 16;

  private function makeValidationString()
  {
    $this->validationString =
      mcrypt_create_iv(self::VALIDATIONSTRING_BYTES, MCRYPT_DEV_URANDOM);
  }

  private function update_remember()
  {
    $this->makeValidationString();
    $this->update_remember_db();
    $this->update_remember_cookie();
  }

  private function update_remember_db()
  {
    $r = DB::query_nofetch(
      'UPDATE ' . DB::escape_identifier(TABLE_USERS) .
      'SET `remember` = ? WHERE `id` = ?',
      $this->validationString, $this->id);
    assert($r->rowCount() === 1);
  }

  private function update_remember_cookie( $remember = null )
  {
    if (is_null($remember))
      $remember = Base64::encode($this->validationString,
        Base64::URI | Base64::FIXED);

    setcookie(Config::$rememberCookie, $remember,
      time() + 3600 * 24 * 365, Config::$absolutePath);
  }


  private function filter_password( &$messages )
  {
    $pass = @$_POST['cpass'];
    if (!empty($pass)) {
      if (strlen($pass) < 6 ) {
        $messages['passToShort'] = true;
      } else if ($pass != @$_POST['cpass2'] ) {
        $messages['passNotEqual'] = true;
      } else {
        return $pass;
      }
      return false;
    }
    return null;
  }

  private function filter_email( &$messages )
  {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    if ((empty($email) && empty($this->email)) || $email === false) {
      $email = false;
      $messages['wrongEmail'] = true;
    } else if ($email == $this->email) {
      $email = null;
    }
    return $email;
  }

  private function filter_name ( &$messages )
  {
    $name =
      filter_input(INPUT_POST, 'name', FILTER_VALIDATE_REGEXP,
        array('options' => array('regexp' => '/^[\w\|]{2,20}$/')));
    if ($name === false)
      $messages['nameInvalid'] = true;
    return $name;
  }


  public function profile( $avatarLocalFile, &$messages )
  {
    list($update, $update_restricted) =
        $this->profile_validate($avatarLocalFile, $messages);

    return (bool)(
      $this->profile_commit($update, $messages) |
      $this->profile_commit_restricted($update_restricted, $messages));
  }


  private function profile_commit( $update, &$messages )
  {
    if (empty($update))
      return false;

    $rv =
      DB::updateRow(TABLE_USERS, array('id' => $this->id),
        array_map(function($value) {
            return $value !== '' ? $value : null;
          },
          $update));
    assert($rv === 1);
    $this->__from_array($update);
    return true;
  }


  private function profile_commit_restricted( $update, &$messages )
  {
    if (empty($update) ||
      !array_reduce($update, 'logical_or', false))
    {
      return false;
    }

    if ($update['pass']) {
      $update['pass'] =
        self::password_hash($update['pass']);
    }

    $this->makeValidationString();
    $update['token'] = $this->validationString;
    $update['user'] = $this->id;
    $update['time'] = time();

    $rv = DB::insertRow('pl_user_validation_requests', $update, true);
    assert(in_array($rv->rowCount(), array(1, 2), true));
    unset($rv);
    $this->modified = false;

    $changes = array();
    if ($update['email'])
      $changes[] = 'e-mail address: ' . $update['email'];
    if ($update['pass'])
      $changes[] = 'new password';

    if (!$this->send_validation_request(
      file_get_contents(Config::$templates . 'validationmail.txt'),
      array(
        '%changes%' => ' - ' . join("\n - ", $changes),
        '%changetime%' => date(DATE_RFC822, $update['time'])))
    ) {
      http_status(500, 'Internal Server Error');
      die();
    }

    $messages['confirmationEmailSent'] = true;
    return true;
  }


  private function profile_validate( $avatarLocalFile, &$_messages )
  {
    $messages = array();

    $update = array('website' => $this->profile_validate_website());

    $update_restricted =
      $this->profile_validate_restricted(@$_POST['pass'], $messages);
    if (!empty($messages))
      goto fail;

    $update['avatar'] =
      $this->profile_validate_avatar($avatarLocalFile, $messages);
    if (!empty($messages))
      goto fail;

    $update =
      array_filter($update, function($value) {
          return !is_null($value) && $value !== false;
        });

    assert(!in_array(false, $update_restricted, true));
    return array($update, $update_restricted);

  fail:
    $_messages = array_merge($_messages, $messages);
    return false;
  }


  private function profile_validate_website()
  {
    $website = trim(@$_POST['website']);
    if ($website != $this->website) {
      $this->website = $website;
      $this->modified = true;
    } else if (!$this->modified) {
      $website = null;
    }
    return $website;
  }


  private function profile_validate_restricted( $password, &$messages )
  {
    $update = array(
        'email' => $this->filter_email($messages),
        'pass' => $this->filter_password($messages)
      );

    if (array_reduce($update, 'logical_or', false)) {
      if (!$this->modified && $this->check_password($update['pass'])) {
        $update['pass'] = null;
      } else if (!$this->check_password($password)) {
        $update['pass'] = false;
        $messages['wrongLogin'] = true;
      }
    }

    return $update;
  }


  private function profile_validate_avatar( $localFile, &$messages )
  {
    if (!empty($localFile)) {
      $name = sha1_file($localFile);
      if ($name !== false) {
        $dirprefix = Config::$images['avatarsPath'] . substr($name, 0, 2);
        $name = "$dirprefix/$name.jpg";

        if (!is_file($name)) {
          require_once('lib/images.php');
          if ((!is_dir($dirprefix) && !mkdir($dirprefix, 0755, true)) ||
            !Image::createThumb($localFile, $name, 40, 40))
          {
            $name = false;
          }
        }
      }

      if ($name === false)
        $messages['avatarFailed'] = true;

      return $name;
    }
    return null;
  }


  public function logout()
  {
    session_unset();
    session_destroy();
    $_SESSION = array();

    $this->validationString = null;
    setcookie(Config::$rememberCookie, false, 1, Config::$absolutePath);
    setcookie(Config::$sessionCookie, false, 1, Config::$absolutePath);
    $this->update_remember_db();

    if( Config::$vbbIntegration['enabled'] ) {
      global $vbulletin;
      $forum = new ForumOps($vbulletin);
      $forum->logout();
    }
  }


  public function register( &$messages )
  {
    $password = null;
    $r = $this->register_validate($password, $messages);
    if ($r === false)
      return false;

    $this->passwordHash = self::password_hash($password);
    $this->makeValidationString();

    $rv = DB::getRow(
      'CALL pl_register_user(?, ?, ?, ?, ?)',
      array($this->name, $this->email,
        $this->passwordHash, $this->validationString,
        time() - 2 * 3600));
    assert($rv !== false);

    if (!$rv['success']) {
      unset($rv['success']);
      foreach ($rv as $what => $value) {
        if ($value)
          $messages[$what] = true;
      }
      return false;
    }

    $this->register_forum($password);
    $this->send_validation_request(
      file_get_contents(Config::$templates.'registrationmail.txt'));

    return true;
  }


  private function register_validate( &$password, &$messages )
  {
    return ($this->name = $this->filter_name($messages)) &&
      ($this->email = $this->filter_email($messages)) &&
      ($password = $this->filter_password($messages));
  }


  private function register_forum( $password )
  {
    if( Config::$vbbIntegration['enabled'] ) {
      global $vbulletin;
      $forum = new ForumOps($vbulletin);
      $rv = $forum->register_newuser(
        array(
          'name' => $this->name,
          'email' => $this->email,
          'pass' => $password),
        false);
      echo $rv;
    }
  }


  const EMAIL_ADDITIONAL_HEADERS =
    "Content-Type: text/plain; charset=\"utf-8\"\r\nContent-Transfer-Encoding: quoted-printable";

  private function send_validation_request( $template,
    $substitutions = array() )
  {
    assert(!empty($template));

    $mail = array();
    preg_match(
      '/From: (?<from>.*?)(?<newline>\r?\n)Subject: (?<subject>.*?)\r?\n\r?\n(?<text>.*)/sim',
      $template, $mail);

    $substitutions = array_merge(
      array(
        '%siteName%' => Config::$siteName,
        '%userName%' => $this->name,
        '%frontendPath%' => Config::$frontendPath,
        '%validationURI%' => 'validate/' .
            Base64::encode($this->validationString, Base64::URI | Base64::FIXED)),
      $substitutions);

    if ($mail['newline'] !== CRLF)
      $substitutions[ $mail['newline'] ] = CRLF;

    $search = array_keys($substitutions);
    $replace = array_values($substitutions);
    $mail = array(
      mb_encode_mimeheader_utf8_q("{$this->name} <{$this->email}>"),
      str_replace($search, array_map('mb_encode_mimeheader_utf8_q', $replace),
        $mail['subject']),
      quoted_printable_encode(str_replace($search, $replace, $mail['text'])),
      'From: ' . $mail['from'] . CRLF . self::EMAIL_ADDITIONAL_HEADERS);

    if (!Config::is_debug()) {
      $send_func = 'mail';
    } else {
      $mail = array_map('htmlspecialchars', $mail);
      $mail[] = CRLF;
      array_unshift($mail,
        '<pre>To: %1$s%5$s%4$s%5$sSubject: %2$s%5$s%5$s%3$s</pre>');
      $send_func = 'printf';
    }

    return call_user_func_array($send_func, $mail);
  }


  private function fetchBy( $filter, $flags = 0 )
  {
    if (!is_array($filter)) {
      $filter = array($filter => $this->{$filter});
    } else {
      assert(!empty($filter));
    }

    $r = DB::getRow(
      DB::buildQueryString(__CLASS__, array_keys($filter), $flags),
      array_values($filter),
      array(PDO::FETCH_INTO, $this));

    $this->__fix_types_internal();

    return $r !== false;
  }


  private function __fix_types_internal()
  {
    foreach (array(
        'score' => 'float',
        'imageCount' => 'int')
      as $prop => $type)
    {
      if (is_string($this->{$prop}) && !settype($this->{$prop}, $type))
        throw new Exception("Could not convert '{$this->{$prop}}' to $type");
    }
  }

  public static function fix_types( $user )
  {
    $user->__fix_types_internal();
  }


  const
    FETCH_VERBATIM_FILTER = 1,
    FETCH_INVALID = 2,
    FETCH_SCORE = 4,
    FETCH_ACCESS_TOKENS = 8;

  public static function buildQuery( $filter = null, $flags = 0 )
  {
    if (!($flags & self::FETCH_VERBATIM_FILTER) && !empty($filter))
      $filter = array_map(
        function($c) { return 'u.' . DB::escape_identifier($c); },
        $filter);

    $columns = 'u.`id`, u.`name`, u.`admin`, u.`website`, u.`email`, u.`avatar`';
    $where_clause =
      empty($filter) ? 'TRUE' : (join('=? AND ', $filter) . '=?');
    $tables = DB::escape_identifier(TABLE_USERS) . ' AS u';

    if ($flags & self::FETCH_ACCESS_TOKENS)
      $columns .= ' u.`pass` AS `passwordHash, u.`remember` AS validationString';

    if ($flags & self::FETCH_INVALID) {
      $columns .= ' u.`valid`';
    } else {
      $where_clause .= ' AND u.`valid`';
    }

    if ($flags & self::FETCH_SCORE) {
      $columns .= ',
        i.`count` AS `imageCount`,
        c.`count` AS `commentCount`,
        t.`count` AS `tagCount`,
        IFNULL(l.`score`, 0) + IFNULL(i.`score`, 0) + IFNULL(iv.`score`, 0) + IFNULL(ir.`score`, 0) + IFNULL(c.`score`, 0) + IFNULL(t.`score`, 0) AS `score`';
      $tables .=
      ' LEFT OUTER JOIN `pl_users_legacy` AS l ON u.`id` = l.`user`
        LEFT OUTER JOIN `plv_score_user_images` AS i ON u.`id` = i.`user`
        LEFT OUTER JOIN `plv_score_user_imagevotes` AS iv ON u.`id` = iv.`user`
        LEFT OUTER JOIN `plv_score_user_imageratings` AS ir ON u.`id` = ir.`user`
        LEFT OUTER JOIN `plv_score_user_comments` AS c ON u.`id` = c.`user`
        LEFT OUTER JOIN `plv_score_user_tags` AS t ON u.`id` = t.`user`';
    }

    //var_dump($columns, $tables, $where_clause);
    return array($columns, $tables, $where_clause);
  }


  public function getAvatar()
  {
    return empty($this->avatar) ?
      Config::$images['avatarsPath'] . 'default.png' :
      $this->avatar;
  }

}


function mb_encode_mimeheader_utf8_q( $str )
{
  return mb_encode_mimeheader($str, 'UTF-8', 'Q');
}


// PHP 5.3 doesn't have this
function boolval( $value )
{
  return (bool) $value;
}


function logical_or( $a, $b )
{
  return $a || $b;
}

?>
