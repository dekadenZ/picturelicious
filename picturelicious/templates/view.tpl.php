<?php include( Config::$templates.'header.tpl.php' );
require_once( 'lib/time.php' );
require_once('lib/string.php');


$img = $iv->image;
$htmltags = array_map('htmlspecialchars', $img->tags);
$tagstring = join(', ', $htmltags);
$uploader = $img->getUploader();
$comments = $img->getComments();

?>
<h1>
  &raquo; Viewing
  <?php if( !empty($iv->user) ) { ?>
    user: <a href="<?php echo Config::$absolutePath, 'user/', $iv->user->name; ?>"><?php echo $iv->user->name; ?></a>
  <?php } else if( !empty($iv->channel) ) { ?>
    channel: <a href="<?php echo Config::$absolutePath, 'channel/', $iv->channel['keyword']; ?>"><?php echo $iv->channel['name']; ?></a>
  <?php } else { ?>
    "<?php echo htmlspecialchars($img->keyword) ?>" // posted
    <?php echo time_diff_human($img->uploadtime);
  } ?>
</h1>

<div class="userInfo">
  <img class="avatar" width="40" height="40" src="<?php echo Config::$absolutePath, $uploader->getAvatar(); ?>"/>
  <div class="name">
    <strong>
      <a href="<?php echo Config::$absolutePath, 'user/', $uploader->name; ?>"><?php echo $uploader->name; ?></a>
    </strong>
  </div>
  <div class="info">
    Score: <strong><?php echo si_size($uploader->score, null, 3); ?></strong> /
    Images: <strong><?php echo si_size($uploader->imageCount, null, 3); ?></strong>
    <?php if (!empty($uploader->website)) { ?>/
      Website: <strong><a href="<?php echo htmlspecialchars($uploader->website); ?>" target="_blank">
        <?php echo htmlspecialchars($uploader->website); ?>
      </a></strong>
    <?php } ?>
  </div>
  <div style="clear:both;"></div>
</div>

<div id="viewer">
  <?php if( isset($iv->stream['prev']) ) { ?>
    <a href="<?php echo Config::$absolutePath, $iv->basePath, 'view/', $iv->stream['prev']->getLink(); ?>" class="prev" id="prevBar" title="Previous">Previous</a>
  <?php } else { ?>
    <div class="noPrev" id="prevBar">Previous</div>
  <?php } ?>

  <?php if( isset($iv->stream['next']) ) { ?>
    <a href="<?php echo Config::$absolutePath, $iv->basePath, 'view/', $iv->stream['next']->getLink(); ?>" class="next" id="nextBar" title="Next">Next</a>
  <?php } else { ?>
    <div class="noNext" id="nextBar">Next</div>
  <?php } ?>

  <div id="imageContainer">
    <img id="image" onclick="swap(this, 'scaled', 'full')" class="scaled" src="<?php echo Config::$absolutePath, Config::$images['imagePath'], $img->getPath(); ?>" alt="<?php echo $tagstring; ?>"/>
  </div>

  <div class="randomThumbs">
    <script type="text/javascript" src="<?php echo Config::$absolutePath;?>random/4/128x128/"></script>
  </div>

  <div id="imageInfo">
    <div class="rating">
      <input type="hidden" value="<?php echo $img->id; ?>" id="imageId"/>
      <div class="ratingBase">
        <div class="ratingCurrent" id="currentRating" style="width: <?php echo $img->votecount > 0 ? $img->rating * 20 : 0;?>px"></div>
        <div class="ratingRate" id="userRating">
        <?php for ($i = 1; $i <= 5; $i++) { ?>
          <a href="#" onclick="return rate(<?php echo $img->id, ',', $i;?>);" onmouseout="sr('userRating',0);" onmouseover="sr('userRating',<?php echo $i;?>);"></a>
        <?php } ?>
        </div>
      </div>
      <div id="loadRating" class="load"></div>
      <span id="ratingDescription">
        <?php if ($img->votecount > 0) { ?>
          <?php echo number_format($img->rating, 1);?> after <?php echo si_size($img->votecount, null, 3);?> Vote<?php echo $img->votecount > 1 ? 's' : '' ?>
        <?php } else { ?>
          No votes yet!
        <?php } ?>
      </span>
      <div style="clear: both;"></div>
      <?php if($user->admin) { ?>
        <div style="float:right;" id="del">
          <div class="load" id="loadDelete"></div>
          <a href="#" onclick="del(<?php echo $img->id; ?>)">[x]</a>
        </div>
      <?php } ?>
    </div>

    <div class="date">
      <?php echo date('d. M Y H:i', $img->uploadtime); ?>
    </div>

    <div>
      Tags:
      <span id="tags"><?php if (empty($htmltags)) { ?>
        <em>none</em>
      <?php } else foreach ($htmltags as $tag) { ?>
        <span class="tag"><?php echo $tag; ?></span>
      <?php } ?></span>

      <?php if( $user->id ) { ?>
        <a href="#" onclick="swap($('addTag'), 'hidden', 'visible'); $('tagText').focus(); return false;">(add)</a>
        <form class="hidden" id="addTag" action="" onsubmit="return addTags(<?php echo $img->id; ?>, $('tagText'), <?php echo $user->admin ? 'true' : 'false';?>);">
          <input type="text" name="tags" id="tagText" <?php if($user->admin) {?>value="<?php echo $tagstring;?>"<?php } ?>/>
          <input type="button" name="save" value="Add Tags" class="button" onclick="addTags(<?php echo $img->id; ?>, $('tagText'), <?php echo $user->admin ? 'true' : 'false';?>);"/>
          <div id="loadTags" class="load"></div>
        </form>
      <?php } ?>
    </div>

    Post in forum: <input type="text" readonly="1" value="[URL=<?php echo Config::$frontendPath ?>][IMG]<?php echo Config::$frontendPath, Config::$images['imagePath'], $img->getLink(); ?>[/IMG][/URL]" style="width: 400px; font-size:10px" onclick="this.focus();this.select();"/>



    <div class="comments">
      <?php if (empty($comments)) { ?>
        <h3>No comments yet!</h3>
      <?php } else { ?>
        <h3><?php printf('%i comment%s:', count($comments), (count($comments) > 1) ? 's' : ''); ?></h3>
      <?php } ?>

      <?php foreach ($comments as $c) { ?>
        <div class="comment">
          <div class="commentHead">
            <img class="avatarSmall" width="16" height="16" src="<?php echo Config::$absolutePath,  $c->author->getAvatar(); ?>"/>
            <a href="<?php echo Config::$absolutePath, 'user/', $c->author->name; ?>"><?php echo $c->author->name; ?></a>
            <?php
            echo time_diff_human($c->created);
            if($user->admin) { ?>
              <div style="float:right;" id="del">
                <a href="#" onclick="return delComment(<?php echo $c->id; ?>, this)">[x]</a>
              </div>
            <?php } ?>
          </div>
          <div class="commentBody"><?php echo $c->getContent(true); ?></div>
        </div>
      <?php } ?>

      <?php if ($user->id) { ?>
        <form method="post" class="addComment" action="<?php echo Config::$absolutePath, $iv->basePath, 'view/', $img->getLink(); ?>">
          <div>
            <textarea name="content" rows="3" cols="60"></textarea>
            <input class="submit" type="submit" name="addComment" value="Submit comment"/>
          </div>
        </form>
      <?php } ?>
    </div>

  </div>
</div>

<script type="text/javascript">
  ieAdjustHeight(0);
</script>


<?php include( Config::$templates.'footer.tpl.php' ); ?>
