<?php /* STYLE/DEFAULT $Id$ */
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
<html lang="en">

<head>
	<meta charset="<?php echo isset($locale_char_set) ? $locale_char_set : 'UTF-8'; ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="Description" content="dotProject Default Style" />
	<meta name="Version" content="<?php echo @$AppUI->getVersion() ?? 'unknown'; ?>" />
	<title><?php echo @dPgetConfig('page_title'); ?></title>
	<link rel="stylesheet" href="./style/<?php echo $uistyle; ?>/css/main.css" media="all" />
	<link rel="shortcut icon" href="./style/<?php echo $uistyle; ?>/images/favicon.ico" type="image/ico" />
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
	<?php @$AppUI->loadJS(); ?>
	<style>
		/* Inline overrides for header modernization */
		.app-header {
			background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
			color: white;
			padding: var(--spacing-sm) var(--spacing-md);
			display: flex;
			justify-content: space-between;
			align-items: center;
			box-shadow: var(--shadow-sm);
		}

		.app-brand a {
			color: white;
			font-size: 1.25rem;
			font-weight: 600;
			text-decoration: none;
		}

		.app-search form {
			display: flex;
			gap: 5px;
		}

		.app-nav-bar {
			background: white;
			border-bottom: 1px solid var(--border-color);
			padding: 0 var(--spacing-md);
			display: flex;
			justify-content: space-between;
			align-items: center;
			height: 50px;
		}

		.nav-modules {
			display: flex;
			gap: 15px;
			overflow-x: auto;
		}

		.nav-modules a {
			color: var(--text-muted);
			text-decoration: none;
			font-weight: 500;
			font-size: 0.9rem;
			padding: 14px 0;
			border-bottom: 2px solid transparent;
			transition: all 0.2s;
			white-space: nowrap;
		}

		.nav-modules a:hover {
			color: var(--primary-color);
			border-bottom-color: var(--primary-color);
		}

		.nav-user {
			display: flex;
			align-items: center;
			gap: 15px;
			font-size: 0.85rem;
		}

		.nav-user a {
			color: var(--text-main);
			text-decoration: none;
		}

		.nav-user a:hover {
			color: var(--primary-color);
		}

		/* Hide legacy elements via class if they leak through */
		table.banner,
		td.nav {
			display: none;
		}
	</style>
</head>

<body onload="this.focus();">

	<header class="app-header" style="position: fixed; top: 0; left: 0; right: 0; z-index: 101;">
		<div class="app-brand">
			<strong><?php echo "<a href='" . $dPconfig['base_url'] . "'>" . $page_title . "</a>"; ?></strong>
		</div>

		<div class="app-actions">
			<div class="nav-user-bar" style="background: transparent; border: none;">
				<div class="nav-user">
					<span
						class="text-white"><?php echo $AppUI->_('Welcome') . ' <strong>' . $AppUI->user_first_name . '</strong>'; ?></span>
					<span class="divider">|</span>
					<a href="?logout=-1" class="text-white"
						style="font-weight: 500"><?php echo $AppUI->_('Logout'); ?></a>
				</div>
			</div>
		</div>
	</header>

	<?php if (empty($dialog)) {
		$nav = $AppUI->getMenuModules() ?? array();
		?>

		<div class="app-layout" style="padding-top: 60px;">
			<aside class="app-sidebar">
				<div class="sidebar-nav">
					<?php
					// New Item Button in Sidebar
					?>
					<div style="padding: 0 20px 20px 20px;">
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
							$newItem = array(0 => '+ New Item');
							foreach ($newItemPermCheck as $mod_check => $mod_check_title) {
								if (getPermission($mod_check, 'add'))
									$newItem[$mod_check] = $mod_check_title;
							}
							echo arraySelect($newItem, 'm', 'class="form-control" style="background: var(--primary-color); color: white; border: none;" onChange="if(this.value) this.form.submit();"', '', true);
							?>
						</form>
					</div>

					<?php
				// Icons mapping
				$icons = [
					'companies' => '<svg xmlns="http://www.w3.org/2000/svg" class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>',
					'projects' => '<svg xmlns="http://www.w3.org/2000/svg" class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>',
					'tasks' => '<svg xmlns="http://www.w3.org/2000/svg" class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
					'calendar' => '<svg xmlns="http://www.w3.org/2000/svg" class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>',
					'files' => '<svg xmlns="http://www.w3.org/2000/svg" class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>',
					'contacts' => '<svg xmlns="http://www.w3.org/2000/svg" class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>',
					'forums' => '<svg xmlns="http://www.w3.org/2000/svg" class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z" /></svg>',
					'ticketsmith' => '<svg xmlns="http://www.w3.org/2000/svg" class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" /></svg>',
					'admin' => '<svg xmlns="http://www.w3.org/2000/svg" class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>',
					'system' => '<svg xmlns="http://www.w3.org/2000/svg" class="nav-icon" fill="none" viewBox="0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" /></svg>',
					'default' => '<svg xmlns="http://www.w3.org/2000/svg" class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>'
				];

				foreach ($nav as $module) {
					if (getPermission($module['mod_directory'], 'access')) {
						$active = (isset($_GET['m']) && $_GET['m'] == $module['mod_directory']) ? 'active' : '';
						$icon = $icons[$module['mod_directory']] ?? $icons['default'];
						
						echo '<a href="?m='.$module['mod_directory'].'" class="'.$active.'">';
						echo $icon; 
						echo $AppUI->_($module['mod_ui_name']);
						echo '</a>';
					}
				}
				?>
				
				<div style="margin-top: 20px; border-top: 1px solid var(--border-color); padding-top: 10px;">
					<small style="padding-left: 24px; color: var(--text-muted); font-size: 0.7rem; font-weight: 600; text-transform: uppercase;">Shortcuts</small>
				</div>
				
				<?php if (getPermission('calendar', 'access')) { 
					$now = new CDate(); 
				?>
				<a href="./index.php?m=tasks&amp;a=todo" class="<?php echo ($_GET['a']=='todo')?'active':'';?>">
					<span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg></span> <?php echo $AppUI->_('Todo');?>
				</a>
				<a href="./index.php?m=calendar&amp;a=day_view&amp;date=<?php echo $now->format(FMT_TIMESTAMP_DATE);?>" class="<?php echo ($_GET['a']=='day_view')?'active':'';?>">
					<span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg></span> <?php echo $AppUI->_('Today');?>
				</a>
					<?php } ?>
				</div>
			</aside>

			<main class="app-main">
				<!-- Breadcrumbs or Page Title could go here -->
				<div class="app-content-wrapper">
					<?php echo $AppUI->getMsg() ?? ''; ?>
				<?php } else { ?>
					<div class="app-layout-dialog" style="padding-top: 60px; padding: 20px;">
						<div class="app-content-wrapper">
							<?php echo $AppUI->getMsg() ?? ''; ?>
						<?php } ?>