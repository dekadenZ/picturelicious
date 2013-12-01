<?php
{
  assert_options(ASSERT_BAIL, 1);

  $include_path = explode(PATH_SEPARATOR, get_include_path());
  $p = array_search('.', $include_path, true);
  if ($p !== false)
    unset($include_path[$p]);
  array_unshift($include_path, dirname(__FILE__) . '/picturelicious');
  set_include_path(implode(PATH_SEPARATOR, $include_path));
  unset($p, $include_path);

  defined('STDERR') || define('STDERR', fopen('php://stderr', 'w'));
}
?>
