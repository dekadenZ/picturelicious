<?php
require_once( 'lib/config.php' );

umask(~Config::$defaultChmod);


/**
 * Various functions regarding file handling
 */
class Filesystem
{

  /**
   * Calls unlink() but has no resource parameter.
   */
  public static function unlink( $path )
  {
    return unlink($path);
  }


  public static function rmdirr( $dir, $clearOnly = false ) {
    $dh = opendir( $dir );
    while ( $file = readdir( $dh ) ) {
      if( $file != '.' && $file != '..' ) {
        $fullpath = $dir.'/'.$file;
        if( !is_dir( $fullpath ) ) {
          @unlink( $fullpath );
        }
        else {
          self::rmdirr( $fullpath );
        }
      }
    }
    closedir( $dh );

    return $clearOnly || @rmdir($dir);
  }


  /**
   * Just a shallow wrapper around PHP's mkdir().
   */
  public static function mkdirr( $path, $mode = 0777 ) {
    return is_dir($path) || mkdir($path, $mode, true);
  }


  public static function dir_prefix( $str, $depth, $chars_per_level )
  {
    assert($depth >= 0);
    assert($chars_per_level > 0);
    assert(strlen($str) >= $depth * $chars_per_level);

    $dir = '';
    for ($i = 0; $i < $depth; ++$i)
      $dir .= substr($str, $i * $chars_per_level, $chars_per_level) . '/';

    return $dir;
  }


  public static function mkdir_prefix( $root, $str, $depth, $chars_per_level,
    $mode = 0777 )
  {
    $dir = self::dir_prefix($str, $depth, $chars_per_level);

    if (!empty($root)) {
      if ($root[strlen($root) - 1] !== '/')
        $root .= '/';

      $dir = $root . $dir;
    }

    return (empty($dir) || self::mkdirr($dir, $mode)) ? $dir . $str : false;
  }


  public static function foreach_file( $callback, $path = '.',
    $recursive = false )
  {
    if (!is_array($path))
      return self::foreach_file_internal($callback, $path, $recursive);

    foreach ($path as $p) {
      if (!self::foreach_file_internal($callback, $p, $recursive))
        return false;
    }
    return true;
  }


  private static function foreach_file_internal( $callback, $path, $recursive )
  {
    if (!$recursive || !is_dir($path))
      return $callback($path) !== false;

    $hDir = opendir($path);
    if ($hDir === false)
      return false;
    $path .= '/';
    while (($entry = readdir($hDir)) !== false) {
      if ($entry === '.' || $entry === '..')
        continue;

      if (!self::foreach_file_internal($callback, $path . $entry, true))
        break;
    }
    closedir($hDir);
    return $entry === false;
  }


  public static function download( $url, $target, $maxSize = 2097152,
    $referer = false )
  {

    if( $referer ) {
      $opts = array(
        'http'=>array(
          'method'=>"GET",
          'header'=>"Referer: $referer\r\n"
        )
      );
      $context = stream_context_create($opts);
      $fp = fopen( $url, 'r', false, $context );
    } else {
      $fp = @fopen( $url, 'r' );
    }
    if( !$fp ) {
      return false;
    }

    while ( !feof( $fp ) ) {
      $chunk = fread( $fp, 8192 );
      $bytesRead += strlen( $chunk );
      if( $bytesRead > $maxSize) {
        return false;
      }
      $contents .= $chunk;
    }
    fclose( $fp );

    file_put_contents( $target, $contents );
    return true;
  }
}

?>
