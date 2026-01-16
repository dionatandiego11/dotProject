<?php /* STYLE/DEFAULT $Id$ */
if (!defined('DP_BASE_DIR')) {
	die('You should not access this file directly');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="<?php echo isset($locale_char_set) ? $locale_char_set : 'UTF-8'; ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo $dPconfig['company_name']; ?> :: dotProject Login</title>
	<meta http-equiv="Pragma" content="no-cache" />
	<meta name="Version" content="<?php echo @$AppUI->getVersion(); ?>" />
	<link rel="stylesheet" href="./style/<?php echo $uistyle; ?>/css/main.css" media="all" />
	<link rel="shortcut icon" href="./style/<?php echo $uistyle; ?>/images/favicon.ico" type="image/ico" />
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
</head>

<body class="login-page">

	<div class="login-container">
		<div class="login-card">
			<div class="login-header">
				<img src="./style/default/images/dp_icon.gif" alt="dotProject logo" class="login-logo" />
				<h1><?php echo dPgetConfig('company_name'); ?></h1>
				<p>Project Management</p>
			</div>

			<form method="post" action="<?php echo $loginFromPage; ?>" name="loginform" class="login-form">
				<input type="hidden" name="login" value="<?php echo time(); ?>" />
				<input type="hidden" name="lostpass" value="0" />
				<input type="hidden" name="redirect" value="<?php echo $redirect; ?>" />

				<div class="form-group">
					<label for="username"><?php echo $AppUI->_('Username'); ?></label>
					<input type="text" id="username" name="username" class="form-control" autocomplete="username"
						required autofocus />
				</div>

				<div class="form-group">
					<label for="password"><?php echo $AppUI->_('Password'); ?></label>
					<input type="password" id="password" name="password" class="form-control"
						autocomplete="current-password" required />
				</div>

				<div class="form-actions">
					<button type="submit" name="login" class="btn btn-primary btn-block">
						<?php echo $AppUI->_('login'); ?>
					</button>
				</div>

				<div class="login-footer">
					<a href="#" onclick="f=document.loginform;f.lostpass.value=1;f.submit();" class="text-sm">
						<?php echo $AppUI->_('forgotPassword'); ?>
					</a>
				</div>
			</form>

			<?php if (@$AppUI->getVersion()) { ?>
				<div class="version-info">
					v<?php echo @$AppUI->getVersion(); ?>
				</div>
			<?php } ?>

			<div class="login-messages">
				<?php echo '<span class="error">' . $AppUI->getMsg() . '</span>'; ?>
				<?php echo phpversion() < '4.1' ? '<br /><span class="warning">WARNING: PHP too old</span>' : ''; ?>
			</div>
		</div>

		<p class="cookie-notice"><?php echo "* " . $AppUI->_("You must have cookies enabled in your browser"); ?></p>
	</div>

</body>

</html>