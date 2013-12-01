<?php

function array_all( $arr, $callback )
{
  foreach ($arr as $elem) {
    if (!$callback($elem))
      return false;
  }
  return true;
}

?>
