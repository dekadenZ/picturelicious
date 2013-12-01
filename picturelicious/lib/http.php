<?php

/**
 * StatusCodes provides named constants for
 * HTTP protocol status codes. Written for the
 * Recess Framework (http://www.recessframework.com/)
 *
 * @author Kris Jordan
 * @license MIT
 * @package recess.http
 */
class HTTPStatusCodes
{
  // [Informational 1xx]
  const HTTP_CONTINUE = 100;
  const SWITCHING_PROTOCOLS = 101;

  // [Successful 2xx]
  const OK = 200;
  const CREATED = 201;
  const ACCEPTED = 202;
  const NONAUTHORITATIVE_INFORMATION = 203;
  const NO_CONTENT = 204;
  const RESET_CONTENT = 205;
  const PARTIAL_CONTENT = 206;

  // [Redirection 3xx]
  const MULTIPLE_CHOICES = 300;
  const MOVED_PERMANENTLY = 301;
  const FOUND = 302;
  const SEE_OTHER = 303;
  const NOT_MODIFIED = 304;
  const USE_PROXY = 305;
  const UNUSED= 306;
  const TEMPORARY_REDIRECT = 307;

  // [Client Error 4xx]
  const errorCodesBeginAt = 400;
  const BAD_REQUEST = 400;
  const UNAUTHORIZED = 401;
  const PAYMENT_REQUIRED = 402;
  const FORBIDDEN = 403;
  const NOT_FOUND = 404;
  const METHOD_NOT_ALLOWED = 405;
  const NOT_ACCEPTABLE = 406;
  const PROXY_AUTHENTICATION_REQUIRED = 407;
  const REQUEST_TIMEOUT = 408;
  const CONFLICT = 409;
  const GONE = 410;
  const LENGTH_REQUIRED = 411;
  const PRECONDITION_FAILED = 412;
  const REQUEST_ENTITY_TOO_LARGE = 413;
  const REQUEST_URI_TOO_LONG = 414;
  const UNSUPPORTED_MEDIA_TYPE = 415;
  const REQUESTED_RANGE_NOT_SATISFIABLE = 416;
  const EXPECTATION_FAILED = 417;

  // [Server Error 5xx]
  const INTERNAL_SERVER_ERROR = 500;
  const NOT_IMPLEMENTED = 501;
  const BAD_GATEWAY = 502;
  const SERVICE_UNAVAILABLE = 503;
  const GATEWAY_TIMEOUT = 504;
  const VERSION_NOT_SUPPORTED = 505;

  private static $messages = array(
      // [Informational 1xx]
      100=>'Continue',
      101=>'Switching Protocols',

      // [Successful 2xx]
      200=>'OK',
      201=>'Created',
      202=>'Accepted',
      203=>'Non-Authoritative Information',
      204=>'No Content',
      205=>'Reset Content',
      206=>'Partial Content',

      // [Redirection 3xx]
      300=>'Multiple Choices',
      301=>'Moved Permanently',
      302=>'Found',
      303=>'See Other',
      304=>'Not Modified',
      305=>'Use Proxy',
      307=>'Temporary Redirect',

      // [Client Error 4xx]
      400=>'Bad Request',
      401=>'Unauthorized',
      402=>'Payment Required',
      403=>'Forbidden',
      404=>'Not Found',
      405=>'Method Not Allowed',
      406=>'Not Acceptable',
      407=>'Proxy Authentication Required',
      408=>'Request Timeout',
      409=>'Conflict',
      410=>'Gone',
      411=>'Length Required',
      412=>'Precondition Failed',
      413=>'Request Entity Too Large',
      414=>'Request-URI Too Long',
      415=>'Unsupported Media Type',
      416=>'Requested Range Not Satisfiable',
      417=>'Expectation Failed',
      418=>'I\'m a teapot',

      // [Server Error 5xx]
      500=>'Internal Server Error',
      501=>'Not Implemented',
      502=>'Bad Gateway',
      503=>'Service Unavailable',
      504=>'Gateway Timeout',
      505=>'HTTP Version Not Supported'
    );


  public static function httpHeaderFor($code)
  {
    if (isset(self::$messages[$code])) {
      $msg = self::$messages[$code];
      return "{$_SERVER['SERVER_PROTOCOL']} $code $msg";
    }

    throw new RuntimeException('Unknown HTTP status code: ' . $code, self::INTERNAL_SERVER_ERROR);
  }


  public static function getMessageForCode($code)
  {
    return self::$messages[$code];
  }


  public static function isError($code)
  {
    return $code >= self::errorCodesBeginAt;
  }


  public static function canHaveBody($code)
  {
    return $code % 100 !== 1 && $code !== self::NO_CONTENT && $code !== self::NOT_MODIFIED;
  }


  public static function set($code)
  {
    header(self::httpHeaderFor($code));
  }

}


function http_redirect( $destination,
  $http_responce_code = HTTPStatusCodes::FOUND )
{
  HTTPStatusCodes::set($http_responce_code);
  header('Location: ' . $destination);
}


function is_max_post_size_exceeded()
{
  return empty($_GET) && empty($_POST) && empty($_FILES);
}

?>
