<?php
require_once( 'lib/config.php' );

umask(~Config::$defaultChmod);


/**
 * Various functions regarding file handling
 */
class Filesystem
{

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

    if( !$clearOnly && @rmdir( $dir ) ) {
      return true;
    }
    else {
      return false;
    }
  }


  /**
   * Just a shallow wrapper around PHP's mkdir().
   */
  public static function mkdirr( $path, $mode = 0777 ) {
    return mkdir($path, $mode, true);
  }

  public static function download( $url, $target, $maxSize = 2097152, $referer = false ) {
    $contents = '';
    $bytesRead = 0;

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
