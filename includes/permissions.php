<?php /* INCLUDES $Id$ */
/*
 * Compatibility layer for handling old-style permissions checks against the
 * new PHPGACL library.
 */

if (!defined('DP_BASE_DIR')) {
	die('You should not access this file directly.');
}

// Permission flags used in the DB

define('PERM_DENY', '0');
define('PERM_EDIT', '-1');
define('PERM_READ', '1');

define('PERM_ALL', '-1');

function getReadableModule() {
	global $AppUI;
//	$perms =& $AppUI->acl();
  $perms = $AppUI->acl();  // because PHP 8 dislikes references, let's see if this works better (gwyneth 20210503)
	$dbprefix = dPgetConfig('dbprefix', '');

	static $cached_modules = null;
	static $readable_module = null;

	if ($readable_module !== null) {
		return $readable_module;
	}

	if ($cached_modules === null) {
		$sql = 'SELECT mod_directory FROM '.$dbprefix.'modules WHERE mod_active > 0 ORDER BY mod_ui_order';
		$cached_modules = db_loadColumn($sql);
	}

	foreach ($cached_modules as $mod) {
		if ($perms->checkModule($mod, 'access')) {
			$readable_module = $mod;
			return $readable_module;
		}
	}
	return null;
}

// TODO: checkFlag should be depricated as it's old and unused
/**
 * This function is used to check permissions.
 */
function checkFlag($flag, $perm_type, $old_flag) {
	if ($old_flag) {
		return (
				($flag == PERM_DENY) ||	// permission denied
				($perm_type == PERM_EDIT && $flag == PERM_READ)	// we ask for editing, but are only allowed to read
				) ? 0 : 1;
	} else {
		if ($perm_type == PERM_READ) {
			return ($flag != PERM_DENY)?1:0;
		} else {
			// => $perm_type == PERM_EDIT
			return ($flag == $perm_type)?1:0;
		}
	}
}

// TODO: isAllowed should be depricated as it's old and unused
/**
 * This function checks certain permissions for
 * a given module and optionally an item_id.
 *
 * $perm_type can be PERM_READ or PERM_EDIT
 */
function isAllowed($perm_type, $mod, $item_id = 0) {
	$invert = false;
	switch ($perm_type) {
		case PERM_READ:	$perm_type = 'view'; break;
		case PERM_EDIT:	$perm_type = 'edit'; break;
		case PERM_ALL: $perm_type = 'edit'; break;
		case PERM_DENY: $perm_type = 'view'; $invert=true; break;
	}
	$allowed = getPermission($mod, $perm_type, $item_id);
	if ($invert) {
	  return ! $allowed;
	}
	return $allowed;
}

function getPermission($mod, $perm, $item_id = 0) {
	global $AppUI;
	static $perm_cache = array();
	static $task_log_task_cache = array();
	static $task_project_cache = array();
	static $project_company_cache = array();
	$item_id = intval($item_id);
//	$perms =& $AppUI->acl();
  $perms = $AppUI->acl();  // removing call by reference to see if it helps (gwyneth 20210503)
	$dbprefix = dPgetConfig('dbprefix', '');

  if (empty($mod)) {
    dprint(__FILE__, __LINE__, 2, "[DEBUG]: " . __FUNCTION__ . "() had empty mod(ule); item_id was " . $item_id . ".");
  }
  if (empty($perm)) {
    dprint(__FILE__, __LINE__, 2, "[DEBUG]: " . __FUNCTION__ . "() had empty perm(issions); item_id was " . $item_id . ".");
  }

	$cache_key = $mod . '|' . $perm . '|' . $item_id;
	if (array_key_exists($cache_key, $perm_cache)) {
		return $perm_cache[$cache_key];
	}

	// First check if the module is readable, i.e. has view permission.
	$result = $perms->checkModuleItem($mod, $perm, $item_id);

	// We need to check if we are allowed to view in the parent module item.
	// This can be done a lot better in PHPGACL, but is here for compatibility.
	if ($item_id && $perm == 'view') {
		if ($mod == 'task_log') {
			if (array_key_exists($item_id, $task_log_task_cache)) {
				$task_id = $task_log_task_cache[$item_id];
			} else {
				$sql = ('SELECT task_log_task FROM '.$dbprefix.'task_log WHERE task_log_id =' . $item_id);
				$task_id = (int) db_loadResult($sql);
				$task_log_task_cache[$item_id] = $task_id;
			}
			$result = $result && getPermission('tasks', $perm, $task_id);
		} else if ($mod == 'tasks') {
			if (array_key_exists($item_id, $task_project_cache)) {
				$project_id = $task_project_cache[$item_id];
			} else {
				$sql = ('SELECT task_project FROM '.$dbprefix.'tasks WHERE task_id =' . $item_id);
				$project_id = (int) db_loadResult($sql);
				$task_project_cache[$item_id] = $project_id;
			}
			$result = $result && getPermission('projects', $perm, $project_id);
		} else if ($mod == 'projects') {
			if (array_key_exists($item_id, $project_company_cache)) {
				$company_id = $project_company_cache[$item_id];
			} else {
				$sql = ('SELECT project_company FROM '.$dbprefix.'projects WHERE project_id =' . $item_id);
				$company_id = (int) db_loadResult($sql);
				$project_company_cache[$item_id] = $company_id;
			}
			$result = $result && getPermission('companies', $perm, $company_id);
		}
	}
	$perm_cache[$cache_key] = $result;
	return $result;
}


// TODO: getDeny* should be deprecated as its usage is counter-intuitive and/or assuming
// Simply using getPermission function is clearer
function getDenyRead($mod, $item_id = 0) {
 	return ! getPermission($mod, 'view', $item_id);
}

function getDenyEdit($mod, $item_id = 0) {
 	return ! getPermission($mod, 'edit', $item_id);
}

/**
 * Return a join statement and a where clause filtering
 * all items which for which no explicit read permission is granted.
 */
function winnow($mod, $key, &$where, $alias = 'perm') {
	die ('The function winnow() is deprecated.  Check to see that the
	module/code has been updated to the latest permissions handling<br />');
}

?>
