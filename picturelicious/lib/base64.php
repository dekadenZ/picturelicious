<?php

class Base64
{
  const LAX = 1;
  const URI = 2;
  const FIXED = 4;


  public static function encode( $str, $flags = 0 )
  {
    $str_encoded = base64_encode($str);

    if ($flags & self::FIXED)
      $str_encoded = rtrim($str_encoded, '=');

    if ($flags & self::URI)
      $str_encoded = strtr($str_encoded, '+/', '-_');

    return $str_encoded;
  }


  public static function decode( $str_encoded, $flags = 0, $fixed_width = 0 )
  {
    if ($flags & self::URI)
      $str_encoded = strtr($str_encoded, '-_', '+/');

    $str = base64_decode($str_encoded, !($flags & self::LAX));
    if ($str === false)
      return false;

    if ($fixed_width > 0)
      $str = substr($str, 0, $fixed_width);

    return $str;
  }

}

?>
