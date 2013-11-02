<?php
include( Config::$templates.'header.tpl.php' );
require_once( 'lib/time.php' );
?>
<h2>Newest comments:</h2>
<div style="width: 700px;">
  <?php foreach( $newComments as $c ) { ?>
    <div class="comment">
      <div class="commentHead">
        <img class="avatarSmall" width="16" height="16" src="<?php echo Config::$absolutePath, $c->author->getAvatar(); ?>"/>
        <a href="<?php echo Config::$absolutePath, 'user/', $c->author->name; ?>"><?php echo $c->author->name; ?></a>
        <?php echo time_diff_human($c->edited); ?>
        [image:<a href="<?php echo Config::$absolutePath, 'all/view/', $c->image->getLink(); ?>"><?php echo $c->image->keyword; ?></a>]
        <?php if($user->admin) { ?>
          <div style="float:right;" id="del">
            <a href="#" onclick="return delComment(<?php echo $c->id; ?>, this)">[x]</a>
          </div>
        <?php } ?>
      </div>
      <div class="commentBody"><?php echo $c->getContent(true); ?></div>
    </div>
  <?php } ?>
</div>


<?php
include( Config::$templates.'footer.tpl.php' );
?>
