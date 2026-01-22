<?php /* STYLE/MODERN_HYBRID - Header */
if (!defined('DP_BASE_DIR')) {
    die('You should not access this file directly');
}
$dialog = (int) dPgetParam($_GET, 'dialog', 0);
if ($dialog)
    $page_title = '';
else
    $page_title = ($dPconfig['page_title'] == 'dotProject') ? $dPconfig['page_title'] . '&nbsp;' . $AppUI->getVersion() : $dPconfig['page_title'];
?>
<!DOCTYPE html>
<html lang="<?php echo $AppUI->user_locale ?? 'en'; ?>" class="h-full bg-gray-100">

<head>
    <meta charset="<?php echo isset($locale_char_set) ? $locale_char_set : 'UTF-8'; ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>

    <!-- Google Fonts -->
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

    <!-- Alpine.js for interactivity -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Theme Custom CSS -->
    <link rel="stylesheet" href="./style/<?php echo $uistyle; ?>/css/main.css" media="all" />
    <link rel="shortcut icon" href="./style/<?php echo $uistyle; ?>/images/favicon.ico" type="image/ico" />

    <!-- Theme JavaScript -->
    <script src="./style/<?php echo $uistyle; ?>/js/app.js" defer></script>

    <?php @$AppUI->loadJS(); ?>
</head>

<body class="h-full" x-data="{ sidebarOpen: false }">

    <?php if (empty($dialog)) {
        $nav = $AppUI->getMenuModules() ?? array();
        ?>

        <div class="min-h-full">

            <!-- Mobile Sidebar (Off-canvas) -->
            <div x-show="sidebarOpen" class="relative z-40 lg:hidden" role="dialog" aria-modal="true">
                <div x-show="sidebarOpen" x-transition:enter="transition-opacity ease-linear duration-300"
                    x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                    x-transition:leave="transition-opacity ease-linear duration-300" x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-600 bg-opacity-75"
                    @click="sidebarOpen = false"></div>

                <div class="fixed inset-0 flex z-40">
                    <div x-show="sidebarOpen" x-transition:enter="transition ease-in-out duration-300 transform"
                        x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
                        x-transition:leave="transition ease-in-out duration-300 transform"
                        x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
                        class="relative flex-1 flex flex-col max-w-xs w-full bg-white pt-5 pb-4">
                        <div class="flex-shrink-0 flex items-center px-4">
                            <span class="font-bold text-xl text-primary-600">dotProject</span>
                        </div>
                        <div class="mt-5 flex-1 h-0 overflow-y-auto">
                            <nav class="px-2 space-y-1">
                                <?php foreach ($nav as $module) {
                                    if ($module['mod_directory'] == 'contacts')
                                        continue;
                                    if (getPermission($module['mod_directory'], 'access')) {
                                        $active = (isset($_GET['m']) && $_GET['m'] == $module['mod_directory']) ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900';
                                        ?>
                                        <a href="?m=<?php echo $module['mod_directory']; ?>"
                                            class="<?php echo $active; ?> group flex items-center px-2 py-2 text-base font-medium rounded-md">
                                            <?php echo $AppUI->_($module['mod_ui_name']); ?>
                                        </a>
                                    <?php }
                                } ?>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Static Sidebar for Desktop -->
            <div class="hidden md:flex md:w-64 md:flex-col md:fixed md:inset-y-0"
                style="display: flex !important; width: 16rem; flex-direction: column; position: fixed; top: 0; bottom: 0;">
                <div class="flex flex-col flex-grow border-r border-gray-200 pt-5 bg-white overflow-y-auto">
                    <div class="flex items-center flex-shrink-0 px-4 mb-4">
                        <div
                            class="h-8 w-8 bg-primary-600 rounded-lg flex items-center justify-center text-white font-bold mr-3">
                            DP</div>
                        <span class="font-bold text-xl text-gray-900">dotProject</span>
                    </div>
                    <div class="mt-5 flex-grow flex flex-col">
                        <nav class="flex-1 px-2 pb-4 space-y-1">
                            <!-- New Item Button -->
                            <div class="mb-6 px-2">
                                <form name="frm_new" method="get" action="./index.php" style="margin:0">
                                    <input type="hidden" name="a" value="addedit" />
                                    <?php
                                    if (!empty($company_id))
                                        echo '<input type="hidden" name="company_id" value="' . $company_id . '" />';
                                    if (!empty($task_id))
                                        echo '<input type="hidden" name="task_parent" value="' . $task_id . '" />';
                                    if (!empty($file_id))
                                        echo '<input type="hidden" name="file_id" value="' . $file_id . '" />';

                                    $newItemPermCheck = array('companies' => 'Company', 'calendar' => 'Event', 'files' => 'File', 'projects' => 'Project');
                                    $newItem = array(0 => '+ ' . $AppUI->_('New Item'));
                                    foreach ($newItemPermCheck as $mod_check => $mod_check_title) {
                                        if (getPermission($mod_check, 'add'))
                                            $newItem[$mod_check] = $mod_check_title;
                                    }
                                    ?>
                                    <select name="m"
                                        class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-md bg-primary-50 text-primary-700 font-medium cursor-pointer hover:bg-primary-100 transition-colors"
                                        onChange="if(this.value) this.form.submit();">
                                        <?php foreach ($newItem as $k => $v) {
                                            echo "<option value=\"$k\">$v</option>";
                                        } ?>
                                    </select>
                                </form>
                            </div>

                            <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider pl-4 mt-6 mb-2">Main
                                Menu</div>

                            <?php foreach ($nav as $module) {
                                if ($module['mod_directory'] == 'contacts')
                                    continue;
                                if (getPermission($module['mod_directory'], 'access')) {
                                    $active = (isset($_GET['m']) && $_GET['m'] == $module['mod_directory']) ? 'bg-primary-50 text-primary-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900';
                                    ?>
                                    <a href="?m=<?php echo $module['mod_directory']; ?>"
                                        class="<?php echo $active; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                                        <?php echo $AppUI->_($module['mod_ui_name']); ?>
                                    </a>
                                <?php }
                            } ?>
                        </nav>
                    </div>
                </div>
            </div>

            <!-- Main Column -->
            <div class="md:pl-64 flex flex-col flex-1" style="padding-left: 16rem;">

                <!-- Topbar -->
                <div class="sticky top-0 z-10 flex-shrink-0 flex h-16 bg-white shadow-sm border-b border-gray-200">
                    <button type="button"
                        class="px-4 border-r border-gray-200 text-gray-500 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary-500 lg:hidden"
                        @click="sidebarOpen = true">
                        <span class="sr-only">Open sidebar</span>
                        <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h7" />
                        </svg>
                    </button>
                    <div class="flex-1 px-4 flex justify-between">
                        <div class="flex-1 flex items-center">
                            <h1 class="text-lg font-semibold text-gray-800 truncate">
                                <?php echo $page_title; ?>
                            </h1>
                        </div>
                        <div class="ml-4 flex items-center md:ml-6">
                            <!-- User Dropdown (simple) -->
                            <div class="relative flex items-center text-sm text-gray-500">
                                <div
                                    class="h-8 w-8 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 font-bold mr-2">
                                    <?php echo strtoupper(substr($AppUI->user_first_name ?? 'U', 0, 1)); ?>
                                </div>
                                <span class="hidden md:inline font-medium text-gray-700 mr-4">
                                    <?php echo $AppUI->user_first_name . ' ' . $AppUI->user_last_name; ?>
                                </span>
                                <a href="?logout=-1" class="text-red-600 hover:text-red-800 font-medium">Logout</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Page Content -->
                <main class="flex-1">
                    <div class="py-6">
                        <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                            <?php
                            $msg = $AppUI->getMsg();
                            if (!empty($msg)) { ?>
                                <div class="mb-4 rounded-md bg-blue-50 p-4">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <svg class="h-5 w-5 text-blue-400" xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd"
                                                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                        <div class="ml-3 flex-1 md:flex md:justify-between">
                                            <p class="text-sm text-blue-700"><?php echo $msg; ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>

                            <!-- Legacy Content Wrapper -->
                            <div class="bg-white shadow rounded-lg p-6 overflow-x-auto">
                            <?php } else { ?>
                                <!-- Dialog Mode Header -->

                                <body class="bg-white p-6">
                                    <div class="mb-4">
                                        <?php
                                        $msg = $AppUI->getMsg();
                                        if (!empty($msg))
                                            echo '<div class="text-red-600 font-medium p-2 bg-red-50 rounded">' . $msg . '</div>';
                                        ?>
                                    </div>
                                <?php } ?>