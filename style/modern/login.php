<?php /* STYLE/MODERN - Login Page */
if (!defined('DP_BASE_DIR')) {
    die('You should not access this file directly');
}
?>
<!DOCTYPE html>
<html lang="<?php echo $AppUI->user_locale ?? 'en'; ?>">

<head>
    <meta charset="<?php echo isset($locale_char_set) ? $locale_char_set : 'UTF-8'; ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo $dPconfig['company_name']; ?> :: Login
    </title>
    <meta http-equiv="Pragma" content="no-cache" />
    <meta name="Version" content="<?php echo @$AppUI->getVersion(); ?>" />

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="./style/<?php echo $uistyle; ?>/css/main.css" media="all" />
    <link rel="shortcut icon" href="./style/<?php echo $uistyle; ?>/images/favicon.ico" type="image/ico" />
</head>

<body class="login-page">

    <div class="login-container">
        <div class="login-card animate-slideIn">
            <div class="login-header">
                <!-- Logo/Icon -->
                <div style="
                    width: 72px;
                    height: 72px;
                    margin: 0 auto 1.5rem;
                    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
                    border-radius: 16px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    box-shadow: 0 8px 16px rgba(59, 130, 246, 0.3);
                ">
                    <svg width="40" height="40" fill="white" viewBox="0 0 24 24">
                        <path
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                    </svg>
                </div>

                <h1><?php echo dPgetConfig('company_name'); ?></h1>
                <p><?php echo $AppUI->_('Projects'); ?></p>
            </div>

            <form method="post" action="<?php echo $loginFromPage; ?>" name="loginform" class="login-form">
                <input type="hidden" name="login" value="login" />
                <input type="hidden" name="lostpass" value="0" />
                <input type="hidden" name="redirect" value="<?php echo $redirect; ?>" />

                <div class="form-group">
                    <label for="username">
                        <?php echo $AppUI->_('Username'); ?>
                    </label>
                    <input type="text" id="username" name="username" class="form-control" autocomplete="username"
                        placeholder="<?php echo $AppUI->_('Enter your username'); ?>" required autofocus />
                </div>

                <div class="form-group">
                    <label for="password">
                        <?php echo $AppUI->_('Password'); ?>
                    </label>
                    <input type="password" id="password" name="password" class="form-control"
                        autocomplete="current-password" placeholder="<?php echo $AppUI->_('Enter your password'); ?>"
                        required />
                </div>

                <div class="form-actions" style="margin-top: 1.5rem;">
                    <button type="submit" name="login" class="btn btn-primary btn-block btn-lg">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            style="margin-right: 0.5rem;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                        </svg>
                        <?php echo $AppUI->_('Sign In'); ?>
                    </button>
                </div>

                <div class="login-footer">
                    <a href="#" onclick="f=document.loginform;f.lostpass.value=1;f.submit();" class="text-sm">
                        <?php echo $AppUI->_('Forgot your password?'); ?>
                    </a>
                </div>
            </form>

            <?php
            $msg = @$AppUI->getMsg();
            if (!empty($msg)) { ?>
                <div class="login-messages">
                    <div class="error" style="margin-top: 1rem;">
                        <?php echo $msg; ?>
                    </div>
                </div>
            <?php } ?>

            <?php if (@$AppUI->getVersion()) { ?>
                <div class="version-info">
                    dotProject v
                    <?php echo @$AppUI->getVersion(); ?>
                </div>
            <?php } ?>
        </div>

        <p class="cookie-notice">
            <?php echo $AppUI->_("Cookies must be enabled in your browser"); ?>
        </p>
    </div>

</body>

</html>