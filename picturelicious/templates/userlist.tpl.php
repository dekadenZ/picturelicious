<?php
require_once('lib/string.php');
include(Config::$templates . 'header.tpl.php');
?>
<h1>
  &raquo; Browsing users,
  Page: <?php echo $ul->pages->current; ?> of <?php echo $ul->pages->total; ?>
</h1>


<?php foreach($ul->users as $u) { ?>
  <div class="userInfo">
    <img class="avatar" width="40" height="40" src="<?php echo Config::$absolutePath, $u->getAvatar(); ?>"/>
    <div class="name">
      <strong>
        <a href="<?php echo Config::$absolutePath, 'user/', $u->name; ?>"><?php echo $u->name; ?></a>
      </strong>
    </div>
    <div class="info">
      Score: <strong><?php echo si_size($u->score, null, 3); ?></strong> /
      Images: <strong><?php echo si_size($u->imageCount, null, 3); ?></strong>
      <?php if (!empty($u->website)) { ?>/
        Website: <strong><a href="<?php echo htmlspecialchars($u->website); ?>" target="_blank">
          <?php echo htmlspecialchars($u->website); ?>
        </a></strong>
      <?php } ?>
    </div>
    <div style="clear:both;"></div>
  </div>
<?php } ?>

<div class="userInfo">
  <?php if ($ul->pages->prev) { ?>
    <a href="<?php echo Config::$absolutePath, 'users/page/', $ul->pages->prev; ?>" class="textPrev" title="Previous">&laquo; Previous</a>
  <?php } else { ?>
    <span class="textNoPrev">&laquo; Previous</span>
  <?php } ?>

  <?php if ($ul->pages->next) { ?>
    <a href="<?php echo Config::$absolutePath, 'users/page/', $ul->pages->next; ?>" class="textNext" title="Next">Next &raquo;</a>
  <?php } else { ?>
    <span class="textNoNext">Next &raquo;</span>
  <?php } ?>

  <div style="clear: both;"></div>
</div>

<?php include( Config::$templates.'footer.tpl.php' ); ?>
