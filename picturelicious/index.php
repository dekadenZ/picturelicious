<?php
/*
  Picturelicious
  (c) 2008 Dominic Szablewski
*/

header("Content-type: text/html; charset=UTF-8");
require_once( 'lib/config.php' );
require_once('lib/http.php');

// HTTP 404
function notfound()
{
  global $cache, $user;
  HTTPStatusCodes::set(HTTPStatusCodes::NOT_FOUND);
  include(Config::$templates . '404.tpl.php');
  $cache->disable();
}

// No JS - redirect to GET search url
if( isset($_POST['q']) ) {
  http_redirect(Config::$absolutePath.'search/'.$_POST['q']);
  exit;
}


require_once( 'lib/cache.php' );
$cache = new Cache( Config::$cache['path'], !empty($_GET['s']) ? $_GET['s'] : 'index' );
if( !Config::$cache['enabled'] ) {
  $cache->disable();
}

// no session or remember cookie -> get the page from cache
if(
  empty($_COOKIE[Config::$sessionCookie]) &&
  empty($_COOKIE[Config::$rememberCookie])
) {
  $cache->lookup();
}


require_once( 'lib/users.php' );
require_once( 'lib/db.php' );

// If the User logged in with POST or a remember cookie, we need forumops.php
// to log him in the forum, too.
if(
  isset($_POST['login']) ||
  (
    empty($_COOKIE[Config::$sessionCookie]) &&
    !empty($_COOKIE[Config::$rememberCookie])
  )
) {
  if( Config::$vbbIntegration['enabled'] ) { require_once( 'lib/class.forumops.php' ); }
}
$user = User::getInstance();

if (!$user) {
  // Don't cache pages for logged-in users
  $cache->disable();
}

$messages = array();
$query = !empty($_GET['s']) ? $_GET['s'] : '';
$r = explode( '/', $query );


switch ($r[0])
{
  case 'login':
  case 'validate':
  case 'static':
  case 'logout':
  case 'register';
  case 'random':
  case 'upload':
  case 'profile':
  case 'users':
  case 'quicktags':
  case 'comments':
    include("pages/{$r[0]}.php");
    break;

  default:
    include('pages/browse.php');
    break;
}

$cache->write();


?>
