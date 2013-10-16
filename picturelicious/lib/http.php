<?php

function http_status( $code, $name = null )
{
  $h = $_SERVER['SERVER_PROTOCOL'] . ' ' . $code;
  if (!empty($name))
    $h .= ' ' . $name;
  header($h);
}


function http_redirect( $destination, $http_responce_code = 302 )
{
  http_status($http_responce_code, 'Found');
  header("Location: $destination");
}

?>
