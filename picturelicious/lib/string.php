<?php

define('CRLF', "\r\n");


define('SI_PREFIXES', 'kMGTPEY');

function si_size($value, $unit = NULL, $digits = 2, $base = 1000)
{
  assert($base == 1000 || $base == 1024);

  $log_base = 0;
  if ($value != 0) {
    $log_base_max = strlen(SI_PREFIXES);
    while ($log_base <= $log_base_max && abs($value) >= $base) {
      $value /= $base;
      $log_base++;
    }

    $decimals = ($log_base || !is_int($value)) ?
      max($digits - ((int) log10(abs($value))) - 1, 0) :
      0;
  } else {
    $decimals = 0;
  }

  $rv = number_format($value, $decimals) . ' ';

  if ($log_base != 0) {
    $prefixes = SI_PREFIXES;
    $rv .= $prefixes[$log_base-1];
    if ($base == 1024)
        $rv .= 'i';
  }

  return $rv . $unit;
}


function hex2bin( $str )
{
  return pack('H*', $str);
}


function starts_with( $str, $prefix, $case_insensitive = false )
{
  return empty($prefix) || substr_compare($str, $prefix, 0, strlen($prefix), $case_insensitive) >= 0;
}


?>
