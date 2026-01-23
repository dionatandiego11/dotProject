<?php
/**
 * Session Handling Functions
 *
 * @package dotProject
 * @license GPL version 2 or later
 */

declare(strict_types=1);

if (!defined('DP_BASE_DIR')) {
	die('You should not access this file directly.');
}

require_once DP_BASE_DIR . '/includes/main_functions.php';
require_once DP_BASE_DIR . '/includes/db_adodb.php';
require_once DP_BASE_DIR . '/includes/db_connect.php';
require_once DP_BASE_DIR . '/classes/query.class.php';
require_once DP_BASE_DIR . '/classes/ui.class.php';
require_once DP_BASE_DIR . '/classes/event_queue.class.php';

function dPsessionOpen(string $save_path, string $session_name): bool
{
	return true;
}

function dPsessionClose(): bool
{
	return true;
}

function dPsessionRead(string $id): string
{
	$q = new DBQuery();
	$q->addTable('sessions');
	$q->addQuery('session_data');
	$q->addQuery('UNIX_TIMESTAMP() - UNIX_TIMESTAMP(session_created) as session_lifespan');
	$q->addQuery('UNIX_TIMESTAMP() - UNIX_TIMESTAMP(session_updated) as session_idle');
	$q->addWhere("session_id = '$id'");
	$qid = $q->exec();

	if (empty($qid) || $qid->EOF) {
		dprint(__FILE__, __LINE__, 11, "Failed to retrieve session $id");
		$data = '';
	} else {
		$max = dPsessionConvertTime('max_lifetime');
		$idle = dPsessionConvertTime('idle_time');
		dprint(
			__FILE__,
			__LINE__,
			11,
			"Found session $id, max=$max/" . $qid->fields['session_lifespan']
			. ", idle=$idle/" . $qid->fields['session_idle']
		);

		if ($max < $qid->fields['session_lifespan'] || $idle < $qid->fields['session_idle']) {
			dprint(__FILE__, __LINE__, 11, "session $id expired");
			dPsessionDestroy($id);
			$data = '';
		} else {
			$data = $qid->fields['session_data'];
		}
	}
	$q->clear();
	return $data;
}

function dPsessionWrite(string $id, string $data): bool
{
	global $AppUI;

	$q = new DBQuery();
	$q->addQuery('count(*) as row_count');
	$q->addTable('sessions');
	$q->addWhere("session_id = '$id'");

	$qid = $q->exec();
	$rowCount = ($qid->fields['row_count'] ?? 0) ?: ($qid->fields[0] ?? 0);

	if ($qid && $rowCount > 0) {
		dprint(__FILE__, __LINE__, 11, "Updating session $id");
		$q->query = null;
		$q->addUpdate('session_data', $data);
		if (isset($AppUI)) {
			$q->addUpdate('session_user', (string) (int) $AppUI->last_insert_id);
		}
	} else {
		dprint(__FILE__, __LINE__, 11, "Creating new session $id");
		$q->query = null;
		$q->where = null;
		$q->addInsert('session_id', $id);
		$q->addInsert('session_data', $data);
		$q->addInsert('session_created', date('Y-m-d H:i:s'));
	}
	$q->exec();
	$q->clear();
	return true;
}

function dPsessionDestroy(string $id, int $user_access_log_id = 0): bool
{
	global $AppUI;

	$q = new DBQuery();
	$q->addTable('sessions');
	$q->addQuery('session_user');
	$q->addWhere("session_id='" . $id . "'");
	$sql2 = $q->prepare(true);

	dprint(__FILE__, __LINE__, 11, "Killing session $id");
	$q->addTable('user_access_log');
	$q->addUpdate('date_time_out', date('Y-m-d H:i:s'));
	$q->addWhere('user_access_log_id = '
		. ($user_access_log_id ?: '(' . $sql2 . ')'));
	$q->exec();
	$q->clear();

	$q->setDelete('sessions');
	$q->addWhere("session_id = '$id'");
	$q->exec();
	$q->clear();

	return true;
}

function dPsessionGC(int $maxlifetime): bool
{
	global $AppUI;

	dprint(__FILE__, __LINE__, 11, 'Session Garbage collection running');
	$max = dPsessionConvertTime('max_lifetime');
	$idle = dPsessionConvertTime('idle_time');

	$where = 'UNIX_TIMESTAMP() - UNIX_TIMESTAMP(session_updated) > ' . $idle
		. ' OR UNIX_TIMESTAMP() - UNIX_TIMESTAMP(session_created) > ' . $max;

	$q = new DBQuery();
	$q->addTable('sessions');
	$q->addQuery('session_user');
	$q->addWhere($where);
	$sql2 = $q->prepare(true);

	$q->addTable('user_access_log');
	$q->addUpdate('date_time_out', date('Y-m-d H:i:s'));
	$q->addWhere('user_access_log_id IN (' . $sql2 . ')');
	$q->exec();
	$q->clear();

	$q->setDelete('sessions');
	$q->addWhere($where);
	$q->exec();
	$q->clear();

	if (dPgetConfig('session_gc_scan_queue')) {
		if (!isset($AppUI)) {
			$AppUI = new CAppUI();
			$queue = EventQueue::getInstance();
			$queue->scan();
		}
	}
	return true;
}

function dPsessionConvertTime(string $key): int
{
	$key = 'session_' . $key;

	if (dPgetConfig($key) === null) {
		return 86400;
	}

	$numpart = (int) dPgetConfig($key);
	$modifier = mb_substr((string) dPgetConfig($key), -1);

	if (!is_numeric($modifier)) {
		$numpart = match ($modifier) {
			'h' => $numpart * 3600,
			'd' => $numpart * 86400,
			'm' => $numpart * 86400 * 30,
			'y' => $numpart * 86400 * 365,
			default => $numpart
		};
	}
	return $numpart;
}

/**
 * Start a session with dotProject's configuration.
 *
 * @param string|array<int, string> $start_vars
 */
function dpSessionStart(string|array $start_vars = 'AppUI'): void
{
	if (session_status() === PHP_SESSION_NONE) {
		session_name('dotproject');
	}

	if ((int) ini_get('session.auto_start') > 0) {
		session_write_close();
	}

	if (dPgetConfig('session_handling') === 'app') {
		register_shutdown_function('session_write_close');

		if (session_status() === PHP_SESSION_NONE) {
			session_set_save_handler(
				'dPsessionOpen',
				'dPsessionClose',
				'dPsessionRead',
				'dPsessionWrite',
				'dPsessionDestroy',
				'dPsessionGC'
			);
		}
		$max_time = dPsessionConvertTime('max_lifetime');
	} else {
		$max_time = 0;
	}

	$dP_base_url = dPgetConfig('base_url');
	if (empty($dP_base_url)) {
		$dP_base_url = safe_get_env('HTTP_HOST');
		dprint(__FILE__, __LINE__, 2, "dPgetConfig returned empty dP_base_url, we'll improvise with <$dP_base_url>");
	}

	preg_match('_^(https?://)([^/:]+)(:[0-9]+)?(/.*)?$_i', $dP_base_url, $url_parts);
	$cookie_dir = $url_parts[4] ?? '/';

	if (!str_starts_with($cookie_dir, '/')) {
		$cookie_dir = '/' . $cookie_dir;
	}
	if (!str_ends_with($cookie_dir, '/')) {
		$cookie_dir .= '/';
	}

	$domain = $url_parts[2] ?? '';
	$secure = ($url_parts[1] ?? '') === 'https://';

	// Fix for localhost - empty domain works better for local development
	if ($domain === 'localhost' || $domain === '127.0.0.1') {
		$domain = '';
	}

	if (!session_set_cookie_params($max_time, $cookie_dir, $domain, $secure, true)) {
		dprint(__FILE__, __LINE__, 2, "[WARN] Failed to set cookie parameters on session!");
	} else {
		dprint(__FILE__, __LINE__, 8, "[INFO] Cookie parameters set: Max Time: $max_time, Cookie Dir: $cookie_dir, Domain: $domain, Secure: $secure .");
	}

	session_start();

	// Only register globals if session is empty (new session) or if explicitly needed
	if (is_array($start_vars)) {
		foreach ($start_vars as $var) {
			if (!isset($_SESSION[$var]) && isset($GLOBALS[$var])) {
				$_SESSION[$var] = $GLOBALS[$var];
			}
		}
	} elseif (!empty($start_vars)) {
		if (!isset($_SESSION[$start_vars]) && isset($GLOBALS[$start_vars])) {
			$_SESSION[$start_vars] = $GLOBALS[$start_vars];
		}
	}

	dprint(__FILE__, __LINE__, 8, "[DEBUG]: SESSION: " . print_r($_SESSION, true));
}
