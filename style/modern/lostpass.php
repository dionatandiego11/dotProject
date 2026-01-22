<?php /* STYLE/MODERN - Lost Password Page */
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
        <?php echo $dPconfig['company_name']; ?> ::
        <?php echo $AppUI->_('Password Recovery'); ?>
    </title>
    <meta http-equiv="Pragma" content="no-cache" />

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
                <div style="
                    width: 72px;
                    height: 72px;
                    margin: 0 auto 1.5rem;
                    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
                    border-radius: 16px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    box-shadow: 0 8px 16px rgba(245, 158, 11, 0.3);
                ">
                    <svg width="40" height="40" fill="white" viewBox="0 0 24 24">
                        <path
                            d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                    </svg>
                </div>

                <h1>
                    <?php echo $AppUI->_('Password Recovery'); ?>
                </h1>
                <p>
                    <?php echo $AppUI->_('Enter your username or email'); ?>
                </p>
            </div>

            <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" name="lostpassform" class="login-form">
                <input type="hidden" name="lostpass" value="1" />
                <input type="hidden" name="sendpass" value="1" />

                <div class="form-group">
                    <label for="lostpassname">
                        <?php echo $AppUI->_('Username or Email'); ?>
                    </label>
                    <input type="text" id="lostpassname" name="lostpassname" class="form-control"
                        placeholder="<?php echo $AppUI->_('Enter your username or email'); ?>" required autofocus />
                </div>

                <div class="form-actions" style="margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary btn-block btn-lg"
                        style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); border-color: #f59e0b;">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            style="margin-right: 0.5rem;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <?php echo $AppUI->_('Send Recovery Email'); ?>
                    </button>
                </div>

                <div class="login-footer">
                    <a href="./index.php" class="text-sm">
                        &larr;
                        <?php echo $AppUI->_('Back to Login'); ?>
                    </a>
                </div>
            </form>

            <?php if (@$AppUI->getVersion()) { ?>
                <div class="version-info">
                    dotProject v
                    <?php echo @$AppUI->getVersion(); ?>
                </div>
            <?php } ?>
        </div>
    </div>

</body>

</html>