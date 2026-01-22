<?php /* STYLE/MODERN_HYBRID - Login Page */
if (!defined('DP_BASE_DIR')) {
    die('You should not access this file directly');
}
?>
<!DOCTYPE html>
<html lang="<?php echo $AppUI->user_locale ?? 'en'; ?>" class="h-full bg-gray-50">
<head>
    <meta charset="<?php echo isset($locale_char_set) ? $locale_char_set : 'UTF-8'; ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $dPconfig['company_name']; ?> :: Sign In</title>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS (CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                        }
                    }
                }
            }
        }
    </script>

    <!-- Custom Theme Overrides -->
    <link rel="stylesheet" href="./style/<?php echo $uistyle; ?>/css/main.css" media="all" />
    <link rel="shortcut icon" href="./style/<?php echo $uistyle; ?>/images/favicon.ico" type="image/ico" />
</head>
<body class="h-full font-sans antialiased text-gray-900 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">

    <div class="max-w-md w-full space-y-8 bg-white p-10 rounded-2xl shadow-xl">
        <div class="text-center">
            <!-- Brand Logo/Icon -->
            <div class="mx-auto h-16 w-16 bg-gradient-to-br from-primary-500 to-primary-700 rounded-xl flex items-center justify-center shadow-lg mb-6">
                <svg class="h-10 w-10 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
            </div>
            
            <h2 class="mt-2 text-3xl font-extrabold text-gray-900 tracking-tight">
                <?php echo dPgetConfig('company_name'); ?>
            </h2>
            <p class="mt-2 text-sm text-gray-500">
                Project Management System
            </p>
        </div>

        <form class="mt-8 space-y-6" method="post" action="<?php echo $loginFromPage; ?>" name="loginform">
            <input type="hidden" name="login" value="login" />
            <input type="hidden" name="lostpass" value="0" />
            <input type="hidden" name="redirect" value="<?php echo $redirect; ?>" />

            <div class="rounded-md shadow-sm -space-y-px">
                <div>
                    <label for="username" class="sr-only"><?php echo $AppUI->_('Username'); ?></label>
                    <input id="username" name="username" type="text" autocomplete="username" required 
                        class="appearance-none rounded-none relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-primary-500 focus:border-primary-500 focus:z-10 sm:text-sm" 
                        placeholder="<?php echo $AppUI->_('Username'); ?>">
                </div>
                <div>
                    <label for="password" class="sr-only"><?php echo $AppUI->_('Password'); ?></label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required 
                        class="appearance-none rounded-none relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-primary-500 focus:border-primary-500 focus:z-10 sm:text-sm" 
                        placeholder="<?php echo $AppUI->_('Password'); ?>">
                </div>
            </div>

            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <input id="remember-me" name="remember-me" type="checkbox" class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded">
                    <label for="remember-me" class="ml-2 block text-sm text-gray-900">
                        Remember me
                    </label>
                </div>

                <div class="text-sm">
                    <a href="#" onclick="f=document.loginform;f.lostpass.value=1;f.submit();" class="font-medium text-primary-600 hover:text-primary-500 transition-colors">
                        <?php echo $AppUI->_('Forgot your password?'); ?>
                    </a>
                </div>
            </div>

            <div>
                <button type="submit" class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-semibold rounded-md text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all shadow-md hover:shadow-lg">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <svg class="h-5 w-5 text-primary-500 group-hover:text-primary-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                        </svg>
                    </span>
                    <?php echo $AppUI->_('Sign in'); ?>
                </button>
            </div>
        </form>

        <?php 
        $msg = @$AppUI->getMsg();
        if (!empty($msg)) { ?>
             <div class="rounded-md bg-red-50 p-4 animate-bounce">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">
                            Authentication Error
                        </h3>
                        <div class="mt-2 text-sm text-red-700">
                            <p><?php echo $msg; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        <?php } ?>
        
        <p class="text-center text-xs text-gray-400">
             &copy; <?php echo date('Y'); ?> <?php echo dPgetConfig('company_name'); ?>. All/Some rights reserved. <br/>
             dotProject v<?php echo @$AppUI->getVersion(); ?>
        </p>
    </div>

</body>
</html>
