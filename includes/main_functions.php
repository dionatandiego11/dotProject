<?php
/**
 * Global General Purpose Functions
 *
 * @package dotProject
 * @license GPL version 2 or later
 */

declare(strict_types=1);

if (!defined('DP_BASE_DIR')) {
	die('You should not access this file directly.');
}

require_once DP_BASE_DIR . '/lib/htmlpurifier-standalone/HTMLPurifier.standalone.php';

// Use PHP_EOL for cross-platform compatibility
$CR = PHP_EOL;
define('SECONDS_PER_DAY', 60 * 60 * 24);

/**
 * Returns the best color based on a background color
 */
function bestColor(string $bg, string $lt = '#ffffff', string $dk = '#000000'): string
{
	if (empty($bg)) {
		$bg = $lt;
	}
	$bg = str_replace('#', '', $bg);
	$dk = str_replace('#', '', $dk);
	$base = new CSS_Color($bg);
	return '#' . $base->calcFG($bg, $dk);
}

/**
 * Returns a select box based on a key/value array where selected is based on key
 *
 * @param array<int|string, mixed> $arr
 */
function arraySelect(
	array $arr,
	string $select_name,
	string $select_attribs,
	int|string|null $selected,
	bool $translate = false
): string {
	global $AppUI;

	if (empty($arr)) {
		dprint(__FILE__, __LINE__, 2, '[INFO]: ' . __FUNCTION__ . ' called with empty array');
		return '';
	}

	if (!is_array($arr)) {
		dprint(__FILE__, __LINE__, 2, '[INFO]: ' . __FUNCTION__ . ' called with no array');
		return '';
	}

	reset($arr);
	$s = PHP_EOL . '<select name="' . $select_name . '" ' . $select_attribs . '>';
	$did_selected = 0;

	foreach ($arr as $k => $v) {
		if ($translate) {
			$v = $AppUI->_($v) ?? $v;
			$v = str_replace('&#369;', 'ű', $v);
			$v = str_replace('&#337;', 'ő', $v);
		} else {
			$v = $AppUI->___($v);
		}
		$s .= PHP_EOL . "\t" . '<option value="' . $AppUI->___((string) $k) . '"'
			. (($k == $selected && !$did_selected) ? ' selected="selected"' : '') . '>'
			. $v . '</option>';
		if ($k == $selected) {
			$did_selected = 1;
		}
	}
	$s .= PHP_EOL . '</select>' . PHP_EOL;
	return $s;
}

/**
 * Returns a select box based on a key/value array with tree structure
 *
 * @param array<int, array<int, mixed>> $arr
 */
function arraySelectTree(
	array &$arr,
	string $select_name,
	string $select_attribs,
	int|string|null $selected,
	bool $translate = false
): string {
	reset($arr);

	$children = [];
	foreach ($arr as $v) {
		$id = $v[0];
		$pt = $v[2];
		$list = $children[$pt] ?? [];
		$list[] = $v;
		$children[$pt] = $list;
	}
	$list = tree_recurse($arr[0][2], '', [], $children);
	return arraySelect($list, $select_name, $select_attribs, $selected, $translate);
}

/**
 * @param array<int|string, string> $list
 * @param array<int|string, array<int, array<int, mixed>>> $children
 * @return array<int|string, string>
 */
function tree_recurse(int|string $id, string $indent, array $list, array $children): array
{
	if (!empty($children[$id])) {
		foreach ($children[$id] as $v) {
			$id = $v[0];
			$txt = $v[1];
			$list[$id] = $indent . ' ' . $txt;
			$list = tree_recurse($id, "$indent--", $list, $children);
		}
	}
	return $list;
}

/**
 * Provide Projects Selectbox sorted by Companies
 */
function projectSelectWithOptGroup(
	int $user_id,
	string $select_name,
	string $select_attribs,
	int $selected,
	?int $excludeProjWithId = null
): string {
	global $AppUI;

	$q = new DBQuery();
	$q->addTable('projects');
	$q->addQuery('project_id, co.company_name, project_name');

	if (!empty($excludeProjWithId)) {
		$q->addWhere('project_id != ' . $excludeProjWithId);
	}

	$proj = new CProject();
	$proj->setAllowedSQL($user_id, $q);
	$q->addOrder('co.company_name, project_name');
	$projects = $q->loadList();

	$s = PHP_EOL . '<select name="' . $select_name . '"'
		. ($select_attribs ? ' ' . $select_attribs : '') . '>';
	$s .= "\n\t" . '<option value="0"' . ($selected === 0 ? ' selected="selected"' : '') . ' >'
		. $AppUI->_('None') . '</option>';

	$current_company = '';
	foreach ($projects as $p) {
		if ($p['company_name'] !== $current_company) {
			$current_company = $AppUI->___($p['company_name']);
			$s .= PHP_EOL . '<optgroup label="' . $current_company . '" >' . $current_company . '</optgroup>';
		}
		$s .= "\n\t" . '<option value="' . $p['project_id'] . '"'
			. ($selected == $p['project_id'] ? ' selected="selected"' : '')
			. '>&nbsp;&nbsp;&nbsp;' . $AppUI->___($p['project_name']) . '</option>';
	}
	$s .= "\n</select>" . PHP_EOL;
	return $s;
}

/**
 * Merges arrays maintaining/overwriting shared numeric indices
 *
 * @param array<int|string, mixed> $a1
 * @param array<int|string, mixed> $a2
 * @return array<int|string, mixed>
 */
function arrayMerge(array $a1, array $a2): array
{
	foreach ($a2 as $k => $v) {
		$a1[$k] = $v;
	}
	return $a1;
}

/**
 * Show a colon separated list of bread crumbs
 *
 * @param array<string, string> $arr
 */
function breadCrumbs(array &$arr): string
{
	global $AppUI;
	$crumbs = [];
	foreach ($arr as $k => $v) {
		$crumbs[] = '<a href="' . $AppUI->___($k) . '">' . $AppUI->_($v) . '</a>';
	}
	return implode(' <strong>:</strong> ', $crumbs);
}

/**
 * Generate link for context help
 */
function dPcontextHelp(string $title, string $link = ''): string
{
	global $AppUI;
	return '<a href="#' . $AppUI->___($link) . '" onClick="'
		. "javascript:window.open('?m=help&dialog=1&hid='" . $link . "', 'contexthelp', "
		. "'width=400, height=400, left=50, top=50, scrollbars=yes, resizable=yes')" . '">'
		. $AppUI->_($title) . '</a>';
}

/**
 * Retrieves a configuration setting.
 */
function dPgetConfig(string $key, mixed $default = null): mixed
{
	global $dPconfig;
	return $dPconfig[$key] ?? $default;
}

function dPgetUsername(string $user): string
{
	$q = new DBQuery();
	$q->addTable('users');
	$q->addQuery('contact_first_name, contact_last_name');
	$q->addJoin('contacts', 'con', 'contact_id = user_contact');
	$q->addWhere("user_username LIKE '" . $user . "'");
	$r = $q->loadList();
	return ($r[0]['contact_first_name'] ?? '') . ' ' . ($r[0]['contact_last_name'] ?? '');
}

function dPgetUsernameFromID(int $user): string
{
	$q = new DBQuery();
	$q->addTable('users');
	$q->addQuery('contact_first_name, contact_last_name');
	$q->addJoin('contacts', 'con', 'contact_id = user_contact');
	$q->addWhere('user_id = ' . $user);
	$r = $q->loadList();
	return ($r[0]['contact_first_name'] ?? '') . ' ' . ($r[0]['contact_last_name'] ?? '');
}

/**
 * @return array<int, string>
 */
function dPgetUsers(): array
{
	global $AppUI;
	$q = new DBQuery();
	$q->addTable('users');
	$q->addQuery('user_id, concat_ws(" ", contact_first_name, contact_last_name) as name');
	$q->addJoin('contacts', 'con', 'contact_id = user_contact');
	$q->addOrder('contact_last_name,contact_first_name');
	return arrayMerge([0 => $AppUI->_('All Users')], $q->loadHashList() ?? []);
}

/**
 * Displays the configuration array of a module
 *
 * @param array<string, mixed> $config
 */
function dPshowModuleConfig(array $config): string
{
	global $AppUI;
	$s = '<table cellspacing="2" cellpadding="2" border="0" class="std" width="50%">';
	$s .= '<tr><th colspan="2">' . $AppUI->_('Module Configuration') . '</th></tr>';
	foreach ($config as $k => $v) {
		$s .= '<tr><td width="50%">' . $AppUI->_($k) . '</td><td width="50%" class="hilite">'
			. $AppUI->_($v) . '</td></tr>';
	}
	$s .= '</table>';
	return $s;
}

/**
 * Function to recursively find an image in a number of places
 */
function dPfindImage(string $name, ?string $module = null): string
{
	global $uistyle;

	$locations_to_search = [
		'/style/' . $uistyle . '/images/',
		'module-image' => '/modules/' . $module . '/images/',
		'module-icon' => '/modules/' . $module . '/images/icons/',
		'/images/icons/',
		'/images/obj/'
	];

	foreach ($locations_to_search as $exception => $folder) {
		if (
			($module || ($exception !== 'module-image' && $exception !== 'module-icon'))
			&& file_exists(DP_BASE_DIR . $folder . $name)
		) {
			return DP_BASE_URL . $folder . $name;
		}
	}
	return './images/' . $name;
}

/**
 * Show image tag
 */
function dPshowImage(string $src, string $wid = '', string $hgt = '', string $alt = '', string $title = ''): string
{
	global $AppUI;

	if (empty($src)) {
		return '';
	}

	return '<img src="' . $AppUI->___($src) . '" '
		. ($wid ? ' width="' . $AppUI->___($wid) . '"' : '')
		. ($hgt ? ' height="' . $AppUI->___($hgt) . '"' : '')
		. ' alt="' . ($alt ? $AppUI->_($alt) : $AppUI->___($src)) . '"'
		. ($title ? ' title="' . $AppUI->_($title) . '"' : '') . ' border="0" />';
}

/**
 * Function to return a default value if a variable is not set
 */
function defVal(mixed $var, mixed $def): mixed
{
	return $var ?? $def;
}

/**
 * Utility function to return a value from a named array or a specified default
 *
 * @param array<string, mixed> $arr
 */
function dPgetParam(array $arr, string $name, mixed $def = null): mixed
{
	if (isset($arr[$name])) {
		return defVal($arr[$name], $def);
	}
	dprint(__FILE__, __LINE__, 8, __FUNCTION__ . ": '" . $name . "' is not a defined value");
	return null;
}

/**
 * Alternative to protect from XSS attacks.
 *
 * @param array<string, mixed> $arr
 * @return mixed
 */
function dPgetCleanParam(array $arr, string $name, mixed $def = null): mixed
{
	if (!isset($arr[$name])) {
		return $def;
	}

	if (is_array($arr[$name])) {
		$val = [];
		foreach (array_keys($arr[$name]) as $key) {
			$val[$key] = dPgetCleanParam($arr[$name], (string) $key, $def);
		}
		return $val;
	}

	$val = defVal($arr[$name], $def);

	if (empty($val)) {
		return $val;
	}

	return dPsanitiseHTML($val);
}

/**
 * Encapsulation of HTML Purifier code to filter out XSS
 *
 * @param array<int, string>|null $allowedTags
 */
function dPsanitiseHTML(string $text, ?array $allowedTags = null): string
{
	if ($allowedTags === null) {
		$allowedTags = dPgetConfig('filter_allowed_tags', [
			'a',
			'em',
			'strong',
			'cite',
			'code',
			'ul',
			'ol',
			'li',
			'dl',
			'dt',
			'dd',
			'table',
			'tr',
			'td',
			'tbody',
			'thead',
			'br',
			'b',
			'i',
			'h1',
			'h2',
			's',
			'u',
			'p',
			'q',
			'blockquote',
			'sub',
			'sup',
			'abbr',
			'kbd',
			'var',
			'samp',
			'hr',
			'address',
			'caption',
			'del',
			'ins',
			'dfn',
			'small'
		]);
	}

	$config = HTMLPurifier_Config::createDefault();
	$purifiedAllowedTags = implode(',', $allowedTags);
	$config->set('HTML.Allowed', $purifiedAllowedTags);
	$purifier = new HTMLPurifier($config);
	return $purifier->purify($text);
}

/**
 * @param array<string, mixed> $arr
 */
function dPgetEmailParam(array $arr, string $name, ?string $def = null): ?string
{
	$val = $arr[$name] ?? $def;
	if ($val === null) {
		return null;
	}
	preg_match('/(([\\w\\s]+)?<)?(\\w+)@([\\w\\d\\.]+)>?/', $val, $matched);
	if (empty($matched[3]) || empty($matched[4])) {
		return $def;
	}
	$result = filter_xss($matched[3]) . '@' . filter_xss($matched[4]);
	if (!empty($matched[2])) {
		$result = filter_xss($matched[2]) . ' <' . $result . '>';
	}
	return $result;
}

function dPlink(string $title, string $href): string
{
	return dPsanitiseHTML('<a href="' . $href . '">' . $title . '</a>');
}

/**
 * Add history entries for tracking changes
 */
function addHistory(
	string $table,
	int|string $id,
	string $action = 'modify',
	string $description = '',
	int $project_id = 0
): void {
	global $AppUI;

	if (!dPgetConfig('log_changes')) {
		return;
	}

	$description = str_replace("'", "\\'", $description);

	$q = new DBQuery();
	$q->addTable('modules');
	$q->addWhere("mod_name = 'History' AND mod_active = 1");
	$qid = $q->exec();

	if (!$qid || db_num_rows($qid) === 0) {
		$AppUI->setMsg(
			'History module is not loaded, but your config file has requested that'
			. ' changes be logged. You must either change the config file or install'
			. ' and activate the history module to log changes.',
			UI_MSG_ALERT
		);
		$q->clear();
		return;
	}

	$q->clear();
	$q->addTable('history');
	$q->addInsert('history_action', $action);
	$q->addInsert('history_item', (string) $id);
	$q->addInsert('history_description', $description);
	$q->addInsert('history_user', (string) $AppUI->user_id);
	$q->addInsert('history_date', 'now()', false, true);
	$q->addInsert('history_project', (string) $project_id);
	$q->addInsert('history_table', $table);
	$q->exec();
	echo db_error();
	$q->clear();
}

/**
 * Looks up a value from the SYSVALS table
 *
 * @return array<int|string, string>
 */
function dPgetSysVal(string $title): array
{
	$q = new DBQuery();
	$q->addTable('sysvals');
	$q->leftJoin('syskeys', 'sk', 'syskey_id = sysval_key_id');
	$q->addQuery('syskey_type, syskey_sep1, syskey_sep2, sysval_value');
	$q->addWhere("sysval_title = '$title'");
	$q->exec();
	$row = $q->fetchRow();
	$q->clear();

	if (!$row) {
		return [];
	}

	$sep1 = $row['syskey_sep1'] ?? "\n";
	$sep2 = $row['syskey_sep2'] ?? '';

	if (!isset($sep1) || empty($sep1) || $sep1 === "\\n") {
		$sep1 = "\n";
	} elseif ($sep1 === "\\r") {
		$sep1 = "\r";
	}

	$temp = explode($sep1, $row['sysval_value']);
	$arr = [];

	foreach ($temp as $item) {
		if ($item) {
			$sep2 = empty($sep2) ? PHP_EOL : $sep2;
			$temp2 = explode($sep2, $item);
			$arr[trim($temp2[0])] = trim($temp2[1] ?? $temp2[0]);
		}
	}
	return $arr;
}

function dPuserHasRole(string $name): mixed
{
	global $AppUI;
	$uid = (int) $AppUI->user_id;

	$q = new DBQuery();
	$q->addTable('roles', 'r');
	$q->innerJoin('user_roles', 'ur', 'ur.role_id=r.role_id');
	$q->addQuery('r.role_id');
	$q->addWhere("ur.user_id=" . $uid . " AND r.role_name='" . $name . "'");
	return $q->loadResult();
}

/**
 * Format duration in days and hours
 */
function dPformatDuration(int|float $x): string
{
	global $AppUI;

	$dailyHours = dPgetConfig('daily_working_hours') ?: 8;
	$dur_day = (int) floor($x / $dailyHours);
	$dur_hour = (int) ($x % $dailyHours);
	$str = '';

	if ($dur_day) {
		$str .= $dur_day . ' ' . $AppUI->_('day' . (abs($dur_day) === 1 ? '' : 's')) . ' ';
	}
	if ($dur_hour) {
		$str .= $dur_hour . ' ' . $AppUI->_('hour' . (abs($dur_hour) === 1 ? '' : 's')) . ' ';
	}
	if ($str === '') {
		$str = $AppUI->_('n/a');
	}

	return $str;
}

function dPsetMicroTime(): void
{
	global $microTimeSet;
	[$usec, $sec] = explode(' ', microtime());
	$microTimeSet = (float) $usec + (float) $sec;
}

function dPgetMicroDiff(): string
{
	global $microTimeSet;
	$mt = $microTimeSet;
	dPsetMicroTime();
	return sprintf('%.3f', $microTimeSet - $mt);
}

define('DP_FORM_DESLASH', 1);
define('DP_FORM_URI', 2);
define('DP_FORM_JSVARS', 4);

/**
 * Make text safe to output into double-quote enclosed attributes of an HTML tag
 */
function dPformSafe(object|array|string|null $txt, int $flag_bits = 0): object|array|string
{
	global $AppUI, $locale_char_set;

	if ($txt === null) {
		return '';
	}

	$locale_char_set ??= 'utf-8';

	$deslash = $flag_bits & DP_FORM_DESLASH;
	$isURI = $flag_bits & DP_FORM_URI;
	$isJSVars = $flag_bits & DP_FORM_JSVARS;

	if (is_object($txt) || is_array($txt)) {
		$txt_arr = is_object($txt) ? get_object_vars($txt) : $txt;
		foreach ($txt_arr as $k => $v) {
			$value = $deslash ? $AppUI->___((string) $v, UI_OUTPUT_RAW) : $v;
			$value = $isURI ? $AppUI->___((string) $value, UI_OUTPUT_URI) : $value;

			if (!$isURI) {
				$value = $isJSVars ? $AppUI->___((string) $value, UI_OUTPUT_JS) : $value;
				$value = $deslash ? htmlspecialchars((string) $value) : $AppUI->___((string) $value, UI_OUTPUT_FORM);
			}

			if (is_object($txt)) {
				$txt->$k = $value;
			} else {
				$txt[$k] = $value;
			}
		}
	} else {
		$txt = $deslash ? $AppUI->___($txt, UI_OUTPUT_RAW) : $txt;
		$txt = $isURI ? $AppUI->___($txt, UI_OUTPUT_URI) : $txt;

		if (!$isURI) {
			$txt = $isJSVars ? $AppUI->___($txt, UI_OUTPUT_JS) : $txt;
			$txt = $deslash ? htmlspecialchars($txt) : $AppUI->___($txt, UI_OUTPUT_FORM);
		}
	}
	return $txt;
}

function convert2days(int|float $durn, int $units): float
{
	$dailyHours = dPgetConfig('daily_working_hours') ?: 8;
	return match ($units) {
		0, 1 => $durn / $dailyHours,
		24 => (float) $durn,
		default => (float) $durn
	};
}

function formatTime(int $uts): string
{
	global $AppUI;
	$date = new CDate();
	$date->setDate($uts, DATE_FORMAT_UNIXTIME);
	return $date->format($AppUI->getPref('SHDATEFORMAT'));
}

/**
 * Format currency using NumberFormatter (PHP 8+ compatible)
 */
function formatCurrency(float|int $number, string $format = ''): string
{
	global $AppUI, $locale_char_set;

	if (empty($format)) {
		$format = $AppUI->getPref('SHCURRFORMAT') ?? 'en_US';
	}

	// Use NumberFormatter for PHP 8+ compatibility
	if (class_exists('NumberFormatter')) {
		$formatter = new NumberFormatter($format, NumberFormatter::CURRENCY);
		$currencyCode = $formatter->getTextAttribute(NumberFormatter::CURRENCY_CODE);
		return $formatter->formatCurrency($number, $currencyCode ?: 'USD');
	}

	// Fallback using localeconv
	$locale_char_set ??= 'utf-8';

	if (
		($locale_char_set !== 'utf-8' || setlocale(LC_MONETARY, $format . '.UTF8') !== false)
		&& setlocale(LC_MONETARY, $format) !== false
	) {
		setlocale(LC_MONETARY, '');
	}

	$mondat = localeconv();
	$frac_digits = isset($mondat['int_frac_digits']) && $mondat['int_frac_digits'] <= 100
		? $mondat['int_frac_digits']
		: 2;
	$curr_symbol = is_string($mondat['int_curr_symbol'] ?? '')
		? $mondat['int_curr_symbol']
		: '';
	$dec_point = $mondat['mon_decimal_point'] ?? '.';
	$thousands = $mondat['mon_thousands_sep'] ?? ',';

	return $curr_symbol . ' ' . number_format(abs($number), $frac_digits, $dec_point, $thousands);
}

/**
 * Backtracing formatter for error logging
 *
 * @param array<int, array<string, mixed>> $bt
 */
function format_backtrace(array $bt, ?string $file, int $line, string $msg): void
{
	echo '<div class="backtrace"><pre>' . PHP_EOL;
	echo 'ERROR: ' . dPrefix($file, $line) . $msg . PHP_EOL;

	if (!empty($bt) && is_array($bt)) {
		echo 'Backtrace:' . PHP_EOL;
		foreach ($bt as $level => $frame) {
			echo "$level {$frame['file']}:{$frame['line']} {$frame['function']}(";
			$in = false;
			foreach ($frame['args'] ?? [] as $arg) {
				echo ($in ? ',' : '') . var_export($arg, true);
				$in = true;
			}
			echo ')' . PHP_EOL;
		}
	} else {
		echo '(Backtrace requested but not found)' . PHP_EOL;
	}
	echo '</pre></div>' . PHP_EOL;
}

/**
 * General-purpose logging function
 */
function dprint(?string $file = null, int $line = 0, int $level = 0, string $msg = ''): void
{
	global $baseDir;

	$max_level = (int) dPgetConfig('debug', 0);
	$display_debug = (int) dPgetConfig('display_debug', 0);
	$error_log_file = dPgetConfig('error_log_file', '');

	if (!empty($baseDir)) {
		$file = str_replace($baseDir, '', $file ?? '');
	} elseif (!empty($_SERVER['DOCUMENT_ROOT'])) {
		$file = str_replace($_SERVER['DOCUMENT_ROOT'], '', $file ?? '');
	}

	$prefix = dPrefix($file, $line);

	if ($level <= $max_level) {
		if (empty($error_log_file)) {
			error_log($prefix . $msg . PHP_EOL);
		} else {
			error_log($prefix . $msg . PHP_EOL, 3, $error_log_file);
		}

		if ($display_debug) {
			echo $prefix . $msg . ' <br />' . PHP_EOL;
		}

		if ($level === 0 && $max_level > 0 && $display_debug) {
			format_backtrace(debug_backtrace(), $file, $line, $msg);
		}
	}
}

/**
 * Create a prefix for writing out a line on the logs.
 */
function dPrefix(?string $file = null, int $line = 0): string
{
	if (!empty($file)) {
		return $file . '(' . $line . '): ';
	}
	return 'Line ' . $line . ': ';
}

/**
 * Function to wrap the ADODB debug print
 */
function db_dprint(string $msg, bool $newline): void
{
	dprint('adodb', 0, 12, $msg);
}

/**
 * Return a list of modules that are associated with tabs for this page.
 *
 * @return array<int, string>
 */
function findTabModules(string $module, ?string $file = null): array
{
	$modlist = [];

	if (empty($_SESSION['all_tabs']) || empty($_SESSION['all_tabs'][$module])) {
		return $modlist;
	}

	if ($file) {
		if (
			!empty($_SESSION['all_tabs'][$module][$file])
			&& is_array($_SESSION['all_tabs'][$module][$file])
		) {
			$tabs_array = $_SESSION['all_tabs'][$module][$file];
		} else {
			return $modlist;
		}
	} else {
		$tabs_array = $_SESSION['all_tabs'][$module] ?? [];
	}

	foreach ($tabs_array as $tab) {
		if (!empty($tab['module'])) {
			$modlist[] = $tab['module'];
		}
	}

	return array_unique($modlist);
}

/**
 * Show a formatted structure
 */
function showFVar(mixed &$var, string $title = ''): void
{
	echo '<h1>' . $title . '</h1><pre class="lang-php">' . print_r($var, true) . '</pre>';
}

/**
 * @return array<int, array<string, string>>|null
 */
function getUsersArray(): ?array
{
	$q = new DBQuery();
	$q->addTable('users');
	$q->addQuery('user_id, user_username, contact_first_name, contact_last_name');
	$q->addJoin('contacts', 'con', 'contact_id = user_contact');
	$q->addOrder('contact_first_name, contact_last_name');
	return $q->loadHashList('user_id');
}

function getUsersCombo(int $default_user_id = 0, string $first_option = 'All users'): string
{
	global $AppUI;

	$parsed = '<select name="user_id" class="text">';
	if ($first_option !== '') {
		$parsed .= '<option value="0" '
			. (!$default_user_id ? 'selected="selected"' : '') . '>'
			. $AppUI->_($first_option) . '</option>';
	}

	foreach (getUsersArray() ?? [] as $user_id => $user) {
		$selected = $user_id == $default_user_id ? ' selected="selected"' : '';
		$parsed .= '<option value="' . $user_id . '"' . $selected . '>'
			. $user['contact_first_name'] . ' ' . $user['contact_last_name'] . '</option>';
	}
	$parsed .= '</select>';
	return $parsed;
}

/**
 * Show navigation bar for paginated results
 */
function shownavbar(
	int $xpg_totalrecs,
	int $xpg_pagesize,
	int $xpg_total_pages,
	int $page,
	mixed $folder = false
): void {
	global $AppUI, $tab, $m, $a;

	$NUM_PAGES_TO_DISPLAY = 15;
	$RANGE_LIMITS = (int) floor($NUM_PAGES_TO_DISPLAY / 2);

	$xpg_prev_page = $xpg_next_page = 1;

	echo "\t" . '<table width="100%" cellspacing="0" cellpadding="0" border="0"><tr>' . PHP_EOL;

	if ($xpg_totalrecs > $xpg_pagesize) {
		$xpg_prev_page = $page - 1;
		$xpg_next_page = $page + 1;

		echo '<td align="left" width="15%">' . PHP_EOL;
		if ($xpg_prev_page > 0) {
			echo '<a href="./index.php?m=' . $m
				. ($a ? '&amp;a=' . $a : '') . ($tab ? '&amp;tab=' . $tab : '')
				. ($folder ? '&amp;folder=' . $folder : '') . '&amp;page=1">'
				. '<img src="images/navfirst.gif" border="0" Alt="'
				. $AppUI->_('First Page') . '"></a>' . PHP_EOL;
			echo '&nbsp;&nbsp;' . PHP_EOL;
			echo '<a href="./index.php?m=' . $m
				. ($a ? '&amp;a=' . $a : '') . ($tab ? '&amp;tab=' . $tab : '')
				. ($folder ? '&amp;folder=' . $folder : '') . '&amp;page=' . $xpg_prev_page . '">'
				. '<img src="images/navleft.gif" border="0" Alt="'
				. $AppUI->_('Previous Page') . ': ' . $xpg_prev_page . '"></a>' . PHP_EOL;
		} else {
			echo '&nbsp;' . PHP_EOL;
		}
		echo '</td>' . PHP_EOL;

		echo '<td align="center" width="70%">' . PHP_EOL;
		echo $xpg_totalrecs . ' ' . $AppUI->_('Result(s)')
			. ' (' . $xpg_total_pages . ' ' . $AppUI->_('Page(s)') . ')' . '<br />' . PHP_EOL;

		$start_page_range = $page > $RANGE_LIMITS ? $page - $RANGE_LIMITS : 1;
		$end_page_range = ($start_page_range - 1) + $NUM_PAGES_TO_DISPLAY;

		if ($xpg_total_pages < $end_page_range) {
			$start_page_range = ($xpg_total_pages + 1) - $NUM_PAGES_TO_DISPLAY;
			$start_page_range = $start_page_range > 0 ? $start_page_range : 1;
			$end_page_range = $xpg_total_pages;
		}

		echo $start_page_range <= $end_page_range ? ' [ ' : '';
		for ($n = $start_page_range; $n <= $end_page_range; $n++) {
			echo $n === $page
				? '<b>'
				: '<a href="./index.php?m=' . $m
				. ($a ? '&amp;a=' . $a : '')
				. ($tab ? '&amp;tab=' . $tab : '')
				. ($folder ? '&amp;folder=' . $folder : '') . '&amp;page=' . $n . '">';
			echo $n;
			echo $n === $page ? '</b>' : '</a>';
			echo $n < $end_page_range ? ' | ' : ' ]' . PHP_EOL;
		}

		echo '</td>' . PHP_EOL;

		echo '<td align="left" width="15%">' . PHP_EOL;
		if ($xpg_next_page <= $xpg_total_pages) {
			echo '<a href="./index.php?m=' . $m
				. ($a ? '&amp;a=' . $a : '') . ($tab ? '&amp;tab=' . $tab : '')
				. ($folder ? '&amp;folder=' . $folder : '') . '&amp;page=' . $xpg_next_page . '">'
				. '<img src="images/navright.gif" border="0" Alt="'
				. $AppUI->_('Next Page') . ': ' . $xpg_next_page . '"></a>' . PHP_EOL;
			echo '&nbsp;&nbsp;' . PHP_EOL;
			echo '<a href="./index.php?m=' . $m
				. ($a ? '&amp;a=' . $a : '') . ($tab ? '&amp;tab=' . $tab : '')
				. ($folder ? '&amp;folder=' . $folder : '') . '&amp;page=' . $xpg_total_pages . '">'
				. '<img src="images/navlast.gif" border="0" Alt="'
				. $AppUI->_('Last Page') . '"></a>' . PHP_EOL;
		} else {
			echo '&nbsp;' . PHP_EOL;
		}
		echo '</td>' . PHP_EOL;
	} else {
		echo '<td align="center">'
			. ($xpg_totalrecs
				? $xpg_totalrecs . ' ' . $AppUI->_('Result(s)')
				: $AppUI->_('No Result(s)'))
			. '</td>' . PHP_EOL;
	}
	echo '</tr></table>';
}

/**
 * Signum function
 */
function dPsgn(int|float $x): int
{
	return $x <=> 0;
}

/**
 * Create the Required Fields JavaScript Code
 *
 * @param array<string, string> $requiredFields
 */
function dPrequiredFields(array $requiredFields): string
{
	global $AppUI;
	$buffer = 'var foc=false;' . PHP_EOL;

	if (!empty($requiredFields)) {
		foreach ($requiredFields as $rf => $comparator) {
			$buffer .= 'if (' . $rf . html_entity_decode($comparator, ENT_QUOTES) . ') {' . PHP_EOL;
			$buffer .= "\t" . 'msg += "\\n' . $AppUI->_('required_field_' . $rf, UI_OUTPUT_JS) . '";' . PHP_EOL;
			$r = mb_strstr($rf, '.');
			$buffer .= "\t" . 'if ((foc==false) && (navigator.userAgent.indexOf(\'MSIE\')== -1)) {' . PHP_EOL;
			$buffer .= "\t\t" . 'f.' . mb_substr($r, 1, mb_strpos($r, '.', 1) - 1) . '.focus();' . PHP_EOL;
			$buffer .= "\t\t" . 'foc=true;' . PHP_EOL;
			$buffer .= "\t}" . PHP_EOL;
			$buffer .= '}' . PHP_EOL;
		}
	}
	return $buffer;
}

/**
 * Return the number of bytes represented by a PHP.INI value
 */
function dPgetBytes(string $str): int
{
	$val = (int) $str;
	if (preg_match('/^([0-9]+)([kmg])?$/i', $str, $match)) {
		if (!empty($match[2])) {
			$val = match (mb_strtolower($match[2])) {
				'k' => (int) $match[1] * 1024,
				'm' => (int) $match[1] * 1024 * 1024,
				'g' => (int) $match[1] * 1024 * 1024 * 1024,
				default => (int) $match[1]
			};
		}
	}
	return $val;
}

/**
 * Check for a memory limit
 */
function dPcheckMem(int $min = 0, bool $revert = false): bool
{
	$want = dPgetBytes($GLOBALS['dPconfig']['reset_memory_limit'] ?? '128M');
	$have = ini_get('memory_limit') ?: '128M';
	ini_set('memory_limit', $GLOBALS['dPconfig']['reset_memory_limit'] ?? '128M');
	$now = dPgetBytes(ini_get('memory_limit') ?: '128M');

	if ($revert) {
		ini_set('memory_limit', $have);
	}

	return !($now < $want || $now < $min);
}

/**
 * Check if string seems to be UTF-8 encoded
 */
function seems_utf8(string $Str): bool
{
	for ($i = 0, $len = mb_strlen($Str); $i < $len; $i++) {
		$ord = ord($Str[$i]);
		if ($ord < 0x80) {
			continue;
		}
		if (($ord & 0xE0) === 0xC0) {
			$n = 1;
		} elseif (($ord & 0xF0) === 0xE0) {
			$n = 2;
		} elseif (($ord & 0xF8) === 0xF0) {
			$n = 3;
		} elseif (($ord & 0xFC) === 0xF8) {
			$n = 4;
		} elseif (($ord & 0xFE) === 0xFC) {
			$n = 5;
		} else {
			return false;
		}
		for ($j = 0; $j < $n; $j++) {
			if ((++$i === $len) || ((ord($Str[$i]) & 0xC0) !== 0x80)) {
				return false;
			}
		}
	}
	return true;
}

/**
 * Safe UTF-8 decoder
 */
function safe_utf8_decode(string $string): string
{
	if (seems_utf8($string)) {
		return mb_convert_encoding($string, 'ISO-8859-1', 'UTF-8');
	}
	return $string;
}
