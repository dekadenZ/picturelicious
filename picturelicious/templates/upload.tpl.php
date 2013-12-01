<?php
include(Config::$templates.'header.tpl.php');
require_once('lib/string.php');

?>
<form action="<?php echo Config::$absolutePath; ?>upload" enctype="multipart/form-data" method="POST">
  <fieldset>
    <legend>Upload / Post</legend>

    <p>
      Images must be no larger than
      <strong><?php echo si_size(Config::$images['maxPixels'], 'Pixel', 3); ?></strong>
      <?php printf('(≈%1$d⨯%1$d)', (int) sqrt(Config::$images['maxPixels'])); ?>
      and must not exceed
      <strong><?php echo si_size(Config::$images['maxDownload'], 'Byte', 2, 'guess'); ?></strong>.
      If the specific image file is already in our database, the upload will fail. Please follow our rules:
    </p>

    <ol>
      <li> Absolutely NO pictures of children (less than 18 yrs.) or animal porn.</li>
      <li> NO pictures of racist propaganda.</li>
      <li> NO snuff pictures!</li>
      <li> NO HARDCORE porn <a href="https://en.wikipedia.org/wiki/Hardcore_pornography">contents</a>!!</li>
      <li> NO crappy low quality pics!</li>
    </ol>

    <p>You can either upload an image directly from your computer, or specify an URL to copy the image from.</p>

    <?php if( !empty( $uploadErrors ) ) { ?>
      <div class="warn">
        There was an issue uploading your image:
        <ul>
        <?php foreach( $uploadErrors as $msg ) { ?>
          <li class="error"><?php echo htmlspecialchars($msg); ?></li>
        <?php }/*foreach*/?>
        </ul>
      </div>
    <?php }/*if*/?>

    <dl class="form">
      <dt>File:</dt>
      <dd>
        <input type="file" name="image" style="color: #000; background-color: #fff;"/>
      </dd>

      <dt>or URL:</dt>
      <dd>
        <input type="text" name="url" value="<?php echo htmlspecialchars(@$_POST['url']); ?>"/>
      </dd>

      <dt>Tags:</dt>
      <dd>
        <input type="text" name="tags" value="<?php echo htmlspecialchars(@$_POST['tags']); ?>"/>
      </dd>

      <dt>&nbsp;</dt>
      <dd>
        <input type="submit" name="upload" value="Upload" class="button"/>
      </dd>
    </dl>
  </fieldset>
</form>

<?php include( Config::$templates.'footer.tpl.php' ); ?>
