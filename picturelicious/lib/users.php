<?php
require_once( 'lib/config.php' );
require_once( 'lib/db.php' );
require_once( 'lib/base64.php' );

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

  private $validationString = null;
  private $passwordHash = null;


  public function __construct() {
    $this->reset();
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


  const PROPERTIES = 'name|id|admin|website|email';

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


  public function reset()
  {
    $this->name = null;
    $this->id = null;
    $this->admin = null;
    $this->website = null;
    $this->email = null;
    $this->validationString = null;
    $this->passwordHash = null;
  }


  public function validate( $id ) {
    $u = DB::getRow(
      'SELECT id, name, admin, website FROM '.TABLE_USERS.' WHERE valid = 0 AND remember = :1',
      $id
    );

    if( empty($u) ) {
      return false;
    }

    DB::updateRow( TABLE_USERS, array('id' => $u['id']), array( 'valid' => 1) );

    session_name( Config::$sessionCookie );
    session_start();
    $_SESSION['id'] = $this->id = $u['id'];
    $_SESSION['name'] = $this->name = $u['name'];
    $_SESSION['admin'] = $this->admin = $u['admin'];
    $_SESSION['website'] = $this->website = $u['website'];

    setcookie( Config::$rememberCookie, $id, time() + 3600 * 24 * 365, Config::$absolutePath );

    if( Config::$vbbIntegration['enabled'] ) {
      global $vbulletin;
      $forum = new ForumOps($vbulletin);
      $forum->login(array('username' => $u['name']));
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

    $r = DB::query_nofetch(
      'SELECT `name`, `id`, `admin`, `website`, `email`, `pass` AS `passwordHash`
       FROM ' . DB::escape_identifier(TABLE_USERS) .
      'WHERE `name` = ? AND `valid`',
      array($name),
      array(PDO::FETCH_INTO, new User));
    $u = $r->fetch();
    $r->closeCursor();
    unset($r);


    if ($u !== false && $u->check_password($password)) {
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
    $r = DB::query_nofetch(
      'SELECT `id`, `name`, `admin`, `website`, `email`
       FROM ' . DB::escape_identifier(TABLE_USERS) .
      'WHERE `remember` = ?',
      array($validationString),
      array(PDO::FETCH_INTO, new User));
    $u = $r->fetch();
    $r->closeCursor();
    unset($r);

    if ($u !== false) {
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


  private function check_password( $password )
  {
    require_once('lib/password.php');

    if (($this->passwordHash[0] === '$') ?
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

  private function update_remember()
  {
    $this->validationString =
      mcrypt_create_iv(self::VALIDATIONSTRING_BYTES, MCRYPT_DEV_URANDOM);
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


  public function profile( $localFile, &$messages ) {
    $upd = array( 'website' => $_POST['website'] );

    $_SESSION['website'] = $_POST['website'];

    if( Config::$vbbIntegration['enabled'] ) {
      global $vbulletin;
      $forum = new ForumOps($vbulletin);
    }

    $p = trim($_POST['cpass']);
    if( !empty( $p ) ) {
      if( strlen($_POST['cpass']) < 6 ) {
        $messages['passToShort'] = true;
      }
      else if( $_POST['cpass'] != $_POST['cpass2'] ) {
        $messages['passNotEqual'] = true;
      }
      else {
        $upd['pass'] = md5($_POST['cpass2']);
        if( Config::$vbbIntegration['enabled'] ) {
          $forum->set_pass( $_SESSION['name'], $_POST['cpass2']);
        }
      }
    }

    if( !empty( $localFile ) ) {
      require_once( 'lib/images.php' );
      $name = Config::$images['avatarsPath'].uniqid().'.jpg';
      if( Image::createThumb( $localFile, $name, 40,40 ) ) {
        $upd['avatar'] = $name;
      } else {
        $messages['avatarFailed'] = true;
      }
    }

    if( empty($this->email) &&
      preg_match('/^[\.\w\-\+]{1,}@[\.\w\-]{2,}\.[\w]{2,}$/', $_POST['email'])
    ) {
      if( Config::$vbbIntegration['enabled'] ) {
        $forum->set_email( $_SESSION['name'], $_POST['email']);
      }
      $upd['email'] = $_POST['email'];
    }

    DB::updateRow( TABLE_USERS, array( 'id' => $this->id ), $upd );
  }


  public function logout() {
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


  public function register( &$messages ) {

    DB::query( 'DELETE FROM '.TABLE_USERS.' WHERE valid = 0 AND registered < NOW() - INTERVAL 30 MINUTE' );

    if( !preg_match('/^\w{2,20}$/', $_POST['name']) ) {
      $messages['nameInvalid'] = true;
    } else {
      $u = DB::getRow( 'SELECT * FROM '.TABLE_USERS.' WHERE name = :1', $_POST['name'] );
      if( !empty($u) ) {
        $messages['nameInUse'] = true;
      }
    }

    if( strlen($_POST['pass']) < 6 ) {
      $messages['passToShort'] = true;
    }
    else if( $_POST['pass'] != $_POST['pass2'] ) {
      $messages['passNotEqual'] = true;
    }

    if( !preg_match( '/^[\.\w\-\+]{1,}@[\.\w\-]{2,}\.[\w]{2,}$/', $_POST['email'] ) ) {
      $messages['wrongEmail'] = true;
    } else {
      $u = DB::getRow( 'SELECT * FROM '.TABLE_USERS.' WHERE email = :1', $_POST['email'] );
      if( !empty($u) ) {
        $messages['emailInUse'] = true;
      }
    }


    if( !empty($messages) ) {
      return false;
    }


    $this->validationString = md5(uniqid(rand()));

    DB::insertRow( TABLE_USERS, array(
      'registered' => date('Y-m-d H:i:s'),
      'name' => $_POST['name'],
      'pass' => md5($_POST['pass']),
      'valid' => 0,
      'score' => 0,
      'images' => 0,
      'website' => '',
      'avatar' => Config::$images['avatarsPath'].'default.png',
      'remember' => $this->validationString,
      'admin' => 0,
      'email' => $_POST['email']
    ));

    if( Config::$vbbIntegration['enabled'] ) {
      global $vbulletin;
      $forum = new ForumOps($vbulletin);
      $user['name'] = $_POST['name'];
      $user['pass'] = $_POST['pass'];
      $user['email'] = $_POST['email'];
      echo $forum->register_newuser($user, false);
    }

    $mail = file_get_contents( Config::$templates.'registrationmail.txt' );
    preg_match( '/From: (?<from>.*?)\r?\nSubject: (?<subject>.*?)\r?\n\r?\n(?<text>.*)/sim', $mail, $mail );
    $mail['text'] = str_replace( '%validationString%', $this->validationString, $mail['text'] );
    $mail['text'] = str_replace( '%siteName%', Config::$siteName, $mail['text'] );
    $mail['text'] = str_replace( '%frontendPath%', Config::$frontendPath, $mail['text'] );
    $mail['text'] = str_replace( '%userName%', $_POST['name'], $mail['text'] );

    $mail['subject'] = str_replace( '%siteName%', Config::$siteName, $mail['subject'] );
    $mail['subject'] = str_replace( '%userName%', $_POST['name'], $mail['subject'] );

    mail( $_POST['email'], $mail['subject'], $mail['text'], 'From: '.$mail['from']."\n".'Content-Type: text/plain; charset="utf-8"' );
    return true;
  }
}

?>
