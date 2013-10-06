<?php
header( 'Content-type: text/javascript; charset=utf-8' ); 
?>
<?php foreach($ib->thumbs as $t ) { ?>
  document.write('<a class="randomThumbs" href="<?php echo Config::$frontendPath; ?>all/view/<?php echo $t['keyword']; ?>"><img border="0" src="<?php echo Config::$frontendPath; ?><?php echo $t['thumb']; ?>" width="<?php echo $twidth-2; ?>" height="<?php echo $theight-2; ?>" alt="<?php echo $t['keyword']; ?>" title="<?php echo $t['userName']; ?> - <?php echo date('d. M Y H:i',$t['loggedTS']); ?>"/></a>');
<?php } ?>
