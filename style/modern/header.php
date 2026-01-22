<?php /* STYLE/MODERN - Premium Theme */
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
<html lang="<?php echo $AppUI->user_locale ?? 'en'; ?>">

<head>
    <meta charset="<?php echo isset($locale_char_set) ? $locale_char_set : 'UTF-8'; ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="Description" content="dotProject Modern Theme" />
    <meta name="Version" content="<?php echo @$AppUI->getVersion() ?? 'unknown'; ?>" />
    <title>
        <?php echo @dPgetConfig('page_title'); ?>
    </title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Theme CSS -->
    <link rel="stylesheet" href="./style/<?php echo $uistyle; ?>/css/main.css" media="all" />
    <link rel="shortcut icon" href="./style/<?php echo $uistyle; ?>/images/favicon.ico" type="image/ico" />

    <?php @$AppUI->loadJS(); ?>

    <style>
        /* Page-specific styles */
        .sidebar-section-title {
            padding: 0.5rem 1.5rem;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            margin-top: 1rem;
        }

        .new-item-dropdown {
            width: 100%;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            padding: 0.625rem 1rem;
            border-radius: var(--border-radius);
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .new-item-dropdown:hover {
            box-shadow: 0 4px 8px rgba(59, 130, 246, 0.4);
            transform: translateY(-1px);
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
        }

        /* Mobile menu toggle */
        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            padding: 0.5rem;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .mobile-menu-toggle {
                display: block;
            }
        }
    </style>
</head>

<body onload="this.focus();">

    <!-- Header -->
    <header class="app-header">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <button class="mobile-menu-toggle" onclick="toggleSidebar()">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16">
                    </path>
                </svg>
            </button>
            <div class="app-brand">
                <a href="<?php echo $dPconfig['base_url']; ?>">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>
                    </svg>
                    <?php echo $page_title; ?>
                </a>
            </div>
        </div>

        <div class="app-actions">
            <div class="nav-user">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($AppUI->user_first_name ?? 'U', 0, 1)); ?>
                </div>
                <span>
                    <?php echo $AppUI->user_first_name . ' ' . $AppUI->user_last_name; ?>
                </span>
                <span class="divider">|</span>
                <a href="?logout=-1">
                    <?php echo $AppUI->_('Logout'); ?>
                </a>
            </div>
        </div>
    </header>

    <?php if (empty($dialog)) {
        $nav = $AppUI->getMenuModules() ?? array();
        ?>

        <div class="app-layout">
            <!-- Sidebar -->
            <aside class="app-sidebar" id="sidebar">
                <div class="sidebar-nav">
                    <!-- New Item Button -->
                    <div style="padding: 0 1.5rem 1rem 1.5rem;">
                        <form name="frm_new" method="get" action="./index.php" style="margin:0">
                            <input type="hidden" name="a" value="addedit" />
                            <?php
                            if (!empty($company_id))
                                echo '<input type="hidden" name="company_id" value="' . $company_id . '" />';
                            if (!empty($task_id))
                                echo '<input type="hidden" name="task_parent" value="' . $task_id . '" />';
                            if (!empty($file_id))
                                echo '<input type="hidden" name="file_id" value="' . $file_id . '" />';

                            $newItemPermCheck = array('companies' => 'Company', 'contacts' => 'Contact', 'calendar' => 'Event', 'files' => 'File', 'projects' => 'Project');
                            $newItem = array(0 => '➕ ' . $AppUI->_('New Item'));
                            foreach ($newItemPermCheck as $mod_check => $mod_check_title) {
                                if (getPermission($mod_check, 'add'))
                                    $newItem[$mod_check] = $mod_check_title;
                            }
                            echo arraySelect($newItem, 'm', 'class="new-item-dropdown" onChange="if(this.value) this.form.submit();"', '', true);
                            ?>
                        </form>
                    </div>

                    <div class="sidebar-section-title">
                        <?php echo $AppUI->_('Main Menu'); ?>
                    </div>

                    <?php
                    // Icons mapping (Heroicons style)
                    $icons = [
                        'companies' => '<svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>',
                        'projects' => '<svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>',
                        'tasks' => '<svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
                        'calendar' => '<svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>',
                        'files' => '<svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>',
                        'contacts' => '<svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>',
                        'forums' => '<svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z" /></svg>',
                        'ticketsmith' => '<svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" /></svg>',
                        'admin' => '<svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>',
                        'system' => '<svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>',
                        'default' => '<svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>'
                    ];

                    foreach ($nav as $module) {
                        if (getPermission($module['mod_directory'], 'access')) {
                            $active = (isset($_GET['m']) && $_GET['m'] == $module['mod_directory']) ? 'active' : '';
                            $icon = $icons[$module['mod_directory']] ?? $icons['default'];

                            echo '<a href="?m=' . $module['mod_directory'] . '" class="' . $active . '">';
                            echo $icon;
                            echo '<span>' . $AppUI->_($module['mod_ui_name']) . '</span>';
                            echo '</a>';
                        }
                    }
                    ?>

                    <div class="sidebar-section-title">
                        <?php echo $AppUI->_('Shortcuts'); ?>
                    </div>

                    <?php if (getPermission('calendar', 'access')) {
                        $now = new CDate();
                        ?>
                        <a href="./index.php?m=tasks&amp;a=todo"
                            class="<?php echo ($_GET['a'] ?? '') == 'todo' ? 'active' : ''; ?>">
                            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                            </svg>
                            <span>
                                <?php echo $AppUI->_('My Tasks'); ?>
                            </span>
                        </a>
                        <a href="./index.php?m=calendar&amp;a=day_view&amp;date=<?php echo $now->format(FMT_TIMESTAMP_DATE); ?>"
                            class="<?php echo ($_GET['a'] ?? '') == 'day_view' ? 'active' : ''; ?>">
                            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span>
                                <?php echo $AppUI->_('Today'); ?>
                            </span>
                        </a>
                    <?php } ?>
                </div>
            </aside>

            <!-- Main Content -->
            <main class="app-main">
                <div class="app-content-wrapper animate-fadeIn">
                    <?php
                    $msg = $AppUI->getMsg() ?? '';
                    if (!empty($msg)) {
                        echo '<div class="message" style="margin-bottom: 1rem;">' . $msg . '</div>';
                    }
                    ?>
                <?php } else { ?>
                    <div class="app-layout-dialog" style="padding: 2rem;">
                        <div class="app-content-wrapper">
                            <?php echo $AppUI->getMsg() ?? ''; ?>
                        <?php } ?>

                        <script>
                            function toggleSidebar() {
                                const sidebar = document.getElementById('sidebar');
                                sidebar.classList.toggle('open');
                            }

                            // Close sidebar when clicking outside on mobile
                            document.addEventListener('click', function (e) {
                                const sidebar = document.getElementById('sidebar');
                                const toggle = document.querySelector('.mobile-menu-toggle');

                                if (window.innerWidth <= 768 && sidebar && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
                                    sidebar.classList.remove('open');
                                }
                            });
                        </script>