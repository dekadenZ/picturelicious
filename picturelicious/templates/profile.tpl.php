<?php include( Config::$templates.'header.tpl.php' ); ?>

<form action="<?php echo Config::$absolutePath; ?>profile" enctype="multipart/form-data" method="post">
  <fieldset>
    <legend>Change profile</legend>

    <?php if( isset($messages['wrongLogin']) ) { ?>
      <div class="warn">You need to enter your current password to perform these changes.</div>
    <?php } ?>

    <?php if( isset($messages['passToShort']) ) { ?>
      <div class="warn">Your password must be at least 6 characters long!</div>
    <?php } ?>

    <?php if( isset($messages['passNotEqual']) ) { ?>
      <div class="warn">Your both passwords are not equal!</div>
    <?php } ?>

    <?php if( isset($messages['avatarFailed']) ) { ?>
      <div class="warn">Your avatar image could not be processed!</div>
    <?php } ?>

    <?php if( isset($messages['confirmationEmailSent']) ) { ?>
      <div class="success">We sent you an e-mail to
        <?php echo htmlspecialchars($user->email) ?>
        with a link to confirm your changes.</div>
    <?php } ?>

    <dl class="form">
      <dt>Current password:</dt>
      <dd>
        <input type="password" name="pass" /> (leave empty, if you don't want to change your password or e-mail address)
      </dd>

      <dt>New password:</dt>
      <dd>
        <input type="password" name="cpass" /> (leave empty, if you don't want to change it)
      </dd>

      <dt>Repeat new password:</dt>
      <dd>
        <input type="password" name="cpass2" />
      </dd>

      <?php if( empty($user->email) ) { ?>
        <dt>E-mail:</dt>
        <dd>
          <input type="text" name="email" />
        </dd>
      <?php } ?>

      <dt>Website:</dt>
      <dd>
        <input type="text" name="website" value="<?php echo htmlspecialchars( $user->website ); ?>"/>
      </dd>

      <dt>Avatar:</dt>
      <dd>
        <input type="file" name="avatar" style="color: #000; background-color: #fff;"/>
      </dd>
      <dt>Current avatar:</dt>
      <dd><img src="<?php echo $user->getAvatar(); ?>"/></dd>

      <dt/>
      <dd>
        <input type="submit" name="save" class="button" value="Save" />
      </dd>
    </dl>
  </fieldset>
</form>

<?php include( Config::$templates.'footer.tpl.php' ); ?>
