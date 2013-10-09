<?php 

$newComments = DB::query( 
  'SELECT 
    c.id, c.content, u.name, u.avatar,
    UNIX_TIMESTAMP(c.created) AS created,
    i.keyword FROM '.TABLE_COMMENTS.' c
  LEFT JOIN '.TABLE_USERS.' u
    ON u.id = c.userId
  LEFT JOIN '.TABLE_IMAGES.' i
    ON i.id = c.imageId
  ORDER BY c.created DESC LIMIT 100'
);
include( Config::$templates.'header.tpl.php' );
require_once( 'lib/time.php' );
?>
<h2>Newest comments:</h2>
<div style="width: 700px;">
  <?php foreach( $newComments as $c ) {?>
    <div class="comment">
      <div class="commentHead">
        <img class="avatarSmall" width="16" height="16" src="<?php echo Config::$absolutePath.$c['avatar']; ?>"/>
        <a href="<?php echo Config::$absolutePath.'user/'.$c['name']; ?>"><?php echo $c['name']; ?></a>
        <?php echo time_diff_human($c['created']); ?>
        [image:<a href="<?php echo Config::$absolutePath.'all/view/'.$c['keyword']; ?>"><?php echo $c['keyword']; ?></a>]
        <?php if($user->admin) { ?>
          <div style="float:right;" id="del">
            <a href="#" onclick="return delComment(<?php echo $c['id']; ?>, this)">[x]</a>
          </div>
        <?php } ?>
      </div>
      <?php echo nl2br(htmlspecialchars($c['content'])); ?>
    </div>
  <?php } ?>
</div>


<?php 
include( Config::$templates.'footer.tpl.php' );
?>
