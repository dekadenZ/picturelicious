<?php

require_once('lib/userlist.php');
$ul = new UserList(Config::$usersPerPage);
$ul->setPage(@$r[2]);
$ul->load();

$cache->capture();
include(Config::$templates . 'userlist.tpl.php');

?>
