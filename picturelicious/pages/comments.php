<?php
require_once('lib/comment.php');


$newComments =
  Comment::fetchAll(array(), array(
    'flags' => Comment::FETCH_IMAGE,
    'order' => array('-edited'),
    'limit' => 50));

include(Config::$templates . 'comments.tpl.php');

?>
