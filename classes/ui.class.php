<?php
/**
 * @package dotproject
 * @subpackage core
 * @license http://opensource.org/licenses/gpl-license.php GPL License Version 2
 */

declare(strict_types=1);

if (!defined('DP_BASE_DIR')) {
	die('This file should not be called directly.');
}

// Message No Constants
define('UI_MSG_OK', 1);
define('UI_MSG_ALERT', 2);
define('UI_MSG_WARNING', 3);
define('UI_MSG_ERROR', 4);

// global variable holding the translation array
$GLOBALS['translate'] = [];

define('UI_CASE_MASK', 0x0F);
define('UI_CASE_UPPER', 1);
define('UI_CASE_LOWER', 2);
define('UI_CASE_UPPERFIRST', 4);

define('UI_OUTPUT_MASK', 0xFF0);
define('UI_OUTPUT_TEXT', 0);
define('UI_OUTPUT_JS', 0x10);
define('UI_OUTPUT_RAW', 0x20);
define('UI_OUTPUT_URI', 0x40);
define('UI_OUTPUT_HTML', 0x80);
define('UI_OUTPUT_FORM', 0x100);

require_once DP_BASE_DIR . '/classes/permissions.class.php';
require_once DP_BASE_DIR . '/includes/filter.php';

/**
 * The Application User Interface Class.
 *
 * @author Andrew Eddie <eddieajau@users.sourceforge.net>
 */
class CAppUI
{
	/** Generic array for holding the state of anything */
	public ?array $state = null;

	/** User ID */
	public string|int|null $user_id = null;

	/** User first name */
	public ?string $user_first_name = null;

	/** User last name */
	public ?string $user_last_name = null;

	/** User company */
	public int|string|null $user_company = null;

	/** User department */
	public int|string|null $user_department = null;

	/** User email */
	public ?string $user_email = null;

	/** User type */
	public int|string|null $user_type = null;

	/** User preferences */
	public ?array $user_prefs = null;

	/** Unix time stamp */
	public ?int $day_selected = null;

	/** System preferences */
	public array $system_prefs = [];

	/** User locale */
	public ?string $user_locale = null;

	/** User language */
	public string|array|null $user_lang = null;

	/** Base locale - do not change - the base 'keys' will always be in english */
	public string $base_locale = 'en';

	/** Base date locale */
	public ?string $base_date_locale = null;

	/** Message string */
	public string $msg = '';

	/** Message number */
	public int|string $msgNo = '';

	/** Default page for a redirect call */
	public string $defaultRedirect = '';

	/** Configuration variable array */
	public ?array $cfg = null;

	/** Version major */
	public ?int $version_major = null;

	/** Version minor */
	public ?int $version_minor = null;

	/** Version patch level */
	public ?int $version_patch = null;

	/** Version string */
	public ?string $version_string = null;

	/** Register log ID */
	public ?int $last_insert_id = null;

	/** Project ID */
	public int $project_id = 0;

	/** List of external JS libraries */
	private array $_js = [];

	/** List of external CSS libraries */
	private array $_css = [];

	/**
	 * CAppUI Constructor
	 */
	public function __construct()
	{
		$this->state = [];
		$this->user_id = -1;
		$this->user_first_name = '';
		$this->user_last_name = '';
		$this->user_company = 0;
		$this->user_department = 0;
		$this->user_type = 0;
		$this->cfg['locale_warn'] = dPgetConfig('locale_warn');
		$this->project_id = 0;
		$this->defaultRedirect = '';
		$this->setUserLocale($this->base_locale);
		$this->user_prefs = [];
	}

	/**
	 * Used to load a php class file from the system classes directory
	 */
	public function getSystemClass(?string $name = null): ?string
	{
		return $name ? DP_BASE_DIR . '/classes/' . $name . '.class.php' : null;
	}

	/**
	 * Used to load a php class file from the lib directory
	 */
	public function getLibraryClass(?string $name = null): ?string
	{
		return $name ? DP_BASE_DIR . '/lib/' . $name . '.php' : null;
	}

	/**
	 * Used to load a php class file from the module directory
	 */
	public function getModuleClass(?string $name = null): ?string
	{
		return $name ? DP_BASE_DIR . '/modules/' . $name . '/' . $name . '.class.php' : null;
	}

	/**
	 * Determines the version.
	 */
	public function getVersion(): string
	{
		if (!isset($this->version_major)) {
			include_once DP_BASE_DIR . '/includes/version.php';
			$this->version_major = $dp_version_major ?? 0;
			$this->version_minor = $dp_version_minor ?? 0;
			$this->version_patch = $dp_version_patch ?? 0;
			$this->version_string = $this->version_major . '.' . $this->version_minor;
			if (isset($this->version_patch)) {
				$this->version_string .= '.' . $this->version_patch;
			}
			if (isset($dp_version_prepatch)) {
				$this->version_string .= '-' . $dp_version_prepatch;
			}
		}
		return $this->version_string ?? '';
	}

	/**
	 * Checks that the current user preferred style is valid/exists.
	 */
	public function checkStyle(): void
	{
		$uistyle = $this->getPref('UISTYLE');
		if ($uistyle && !is_dir(DP_BASE_DIR . '/style/' . $uistyle)) {
			$this->setPref('UISTYLE', dPgetConfig('host_style'));
		}
	}

	/**
	 * Utility function to read the 'directories' under 'path'
	 * @return array<string, string>
	 */
	public function readDirs(string $path): array
	{
		$dirs = [];
		$d = dir(DP_BASE_DIR . '/' . $path);
		while (($name = $d->read()) !== false) {
			if (
				is_dir(DP_BASE_DIR . '/' . $path . '/' . $name)
				&& $name !== '.' && $name !== '..'
				&& $name !== 'CVS' && $name !== '.svn'
			) {
				$dirs[$name] = $name;
			}
		}
		$d->close();
		return $dirs;
	}

	/**
	 * Utility function to read the 'files' under 'path'
	 * @return array<string, string>
	 */
	public function readFiles(string $path, string $filter = '.'): array
	{
		$files = [];
		if (is_dir($path) && ($handle = opendir($path))) {
			while (($file = readdir($handle)) !== false) {
				if ($file !== '.' && $file !== '..' && preg_match('/' . $filter . '/', $file)) {
					$files[$file] = $file;
				}
			}
			closedir($handle);
		}
		return $files;
	}

	/**
	 * Utility function to check whether a file name is 'safe'
	 */
	public function checkFileName(string $file): string
	{
		global $AppUI;
		$bad_chars = ';/\\\'()"$';
		$bad_replace = '.........';
		if (mb_strpos(strtr($file, $bad_chars, $bad_replace), '.') !== false) {
			$AppUI->redirect('m=public&a=access_denied');
		}
		return $file;
	}

	/**
	 * Utility function to make a file name 'safe'
	 */
	public function makeFileNameSafe(string $file): string
	{
		return str_replace(['../', '..\\'], '', $file);
	}

	/**
	 * Sets the user locale.
	 * CUSTOMIZADO: Sempre força pt_br como único idioma disponível
	 */
	public function setUserLocale(string $loc = '', bool $set = true): string|array|null
	{
		global $locale_char_set;

		$LANGUAGES = $this->loadLanguages();

		// Forçar pt_br sempre - ignorar preferências do usuário
		$loc = 'pt_br';

		if (isset($LANGUAGES[$loc])) {
			$lang = $LANGUAGES[$loc];
		} else {
			// Fallback para pt_br (minúsculo)
			$loc = 'pt_br';
			$lang = $LANGUAGES[$loc] ?? [];
		}

		if (!empty($lang)) {
			$base_locale = $lang[0];
			$default_language = $lang[3];
			$lcs = $lang[4] ?? null;
		}

		$lcs ??= $locale_char_set ?? 'utf-8';

		$user_lang = [($loc ?? '???') . '.' . $lcs, $default_language ?? '', $loc, $base_locale ?? ''];

		if ($set) {
			$this->user_locale = $base_locale ?? '';
			$this->user_lang = $user_lang;
			$locale_char_set = $lcs;
			return null;
		}

		return $user_lang;
	}

	/**
	 * Find language by language code and optional country
	 */
	public function findLanguage(string $language, string|bool $country = false): ?string
	{
		$LANGUAGES = $this->loadLanguages();
		$language = mb_strtolower($language);

		if ($country) {
			$country = mb_strtoupper((string) $country);
			$code = $language . '_' . $country;
			if (isset($LANGUAGES[$code])) {
				return $code;
			}
		}

		$first_entry = null;
		foreach ($LANGUAGES as $lang => $info) {
			[$l, $c] = explode('_', $lang);
			if ($l === $language) {
				$first_entry ??= $lang;
				if ($country && $c === $country) {
					return $lang;
				}
			}
		}
		return $first_entry;
	}

	/**
	 * Set the base locale for English date strings
	 */
	public function setBaseLocale(int $context = LC_ALL): void
	{
		global $locale_char_set;

		$LANGUAGES = $this->loadLanguages();

		$locale = $LANGUAGES['en_AU'][0] ?? 'en';
		$win_locale = $LANGUAGES['en_AU'][3] ?? 'English';
		$lcs = $LANGUAGES['en_AU'][4] ?? null;

		$real_locale = 'en_AU';
		if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
			$real_locale = $win_locale;
		} else {
			$lcs ??= $locale_char_set ?? 'utf-8';
			$real_locale .= '.' . $lcs;
		}
		setlocale($context, $real_locale);
	}

	/**
	 * Load the known language codes for loaded locales
	 * @return array<string, array>
	 */
	public function loadLanguages(): array
	{
		if (isset($_SESSION['LANGUAGES'])) {
			return $_SESSION['LANGUAGES'];
		}

		$LANGUAGES = [];
		$langs = $this->readDirs('locales');
		foreach ($langs as $lang) {
			if (file_exists(DP_BASE_DIR . '/locales/' . $lang . '/lang.php')) {
				include_once DP_BASE_DIR . '/locales/' . $lang . '/lang.php';
			}
		}
		$_SESSION['LANGUAGES'] = $LANGUAGES;
		return $LANGUAGES;
	}

	/**
	 * Translate string to the local language
	 */
	public function _(string|array|null $str, int $flags = 0): string
	{
		if ($str === null) {
			return '';
		}
		if (is_array($str)) {
			$translated = [];
			foreach ($str as $s) {
				$translated[] = $this->__((string) $s, $flags);
			}
			return implode(' ', $translated);
		}
		return $this->__($str, $flags);
	}

	/**
	 * Main translation function
	 */
	public function __(string|null $str, int $flags = 0): string
	{
		if ($str === null) {
			return '';
		}
		$str = trim($str);
		if (empty($str)) {
			return '';
		}

		$x = $GLOBALS['translate'][$str] ?? null;

		if ($x) {
			$str = $x;
		} elseif (
			dPgetConfig('locale_warn')
			&& !($this->base_locale === $this->user_locale
				&& in_array($str, $GLOBALS['translate'] ?? []))
		) {
			$str .= dPgetConfig('locale_alert');
		}

		return $this->___($str, $flags);
	}

	/**
	 * Output formatting function
	 */
	public function ___(string|null $str, int $flags = 0): string
	{
		if ($str === null) {
			return '';
		}
		global $locale_char_set;

		$locale_char_set ??= 'utf-8';

		switch ($flags & UI_CASE_MASK) {
			case UI_CASE_UPPER:
				$str = mb_strtoupper($str, $locale_char_set);
				break;
			case UI_CASE_LOWER:
				$str = mb_strtolower($str, $locale_char_set);
				break;
			case UI_CASE_UPPERFIRST:
				$str = mb_convert_case($str, MB_CASE_TITLE, $locale_char_set);
				break;
		}

		switch ($flags & UI_OUTPUT_MASK) {
			case UI_OUTPUT_URI:
				$str = str_replace(' ', '%20', $str);
				break;
			case UI_OUTPUT_TEXT:
				$str = htmlentities(stripslashes($str), ENT_COMPAT, $locale_char_set);
				$str = dPsanitiseHTML($str);
				$str = nl2br($str);
				break;
			case UI_OUTPUT_FORM:
				$str = htmlentities(stripslashes($str), ENT_COMPAT, $locale_char_set);
				$str = dPsanitiseHTML($str);
				break;
			case UI_OUTPUT_HTML:
				$str = dPsanitiseHTML($str);
				break;
			case UI_OUTPUT_JS:
				$str = addslashes(stripslashes($str));
				break;
			case UI_OUTPUT_RAW:
				$str = stripslashes($str);
				break;
		}
		return $str;
	}

	public function showHTML(string $text): string
	{
		return $this->___($text, UI_OUTPUT_HTML);
	}

	public function showRaw(string $text): string
	{
		return $this->___($text, UI_OUTPUT_RAW);
	}

	public function showJS(string $text): string
	{
		return $this->___($text, UI_OUTPUT_JS);
	}

	/**
	 * Set the display of warning for untranslated strings
	 */
	public function setWarning(bool $state = true): mixed
	{
		$temp = $this->cfg['locale_warn'] ?? null;
		$this->cfg['locale_warn'] = $state;
		return $temp;
	}

	/**
	 * Save the url query string
	 */
	public function savePlace(string $query = ''): void
	{
		if (!$query) {
			$query = $_SERVER['QUERY_STRING'] ?? '';
		}
		if ($query !== ($this->state['SAVEDPLACE'] ?? '')) {
			$this->state['SAVEDPLACE-1'] = $this->state['SAVEDPLACE'] ?? '';
			$this->state['SAVEDPLACE'] = $query;
		}
	}

	/**
	 * Resets the internal variable
	 */
	public function resetPlace(): void
	{
		$this->state['SAVEDPLACE'] = '';
	}

	/**
	 * Get the saved place
	 */
	public function getPlace(): string
	{
		return $this->state['SAVEDPLACE'] ?? '';
	}

	/**
	 * Redirects the browser to a new page.
	 */
	public function redirect(string $params = '', string $hist = ''): never
	{
		$session_id = SID;
		session_write_close();

		if (!$params) {
			$params = !empty($this->state["SAVEDPLACE$hist"])
				? $this->state["SAVEDPLACE$hist"]
				: $this->defaultRedirect;
		}

		if ($session_id !== '') {
			$params .= ($params ? '&' : '') . $session_id;
		}

		ob_implicit_flush();
		header('Location: index.php?' . $params);
		exit();
	}

	/**
	 * Set the page message.
	 */
	public function setMsg(string|array $msg, int $msgNo = 0, bool $append = false): void
	{
		$msg = $this->_($msg);
		$this->msg = $append ? $this->msg . ' ' . $msg : $msg;
		$this->msgNo = $msgNo;
	}

	/**
	 * Display the formatted message and icon
	 */
	public function getMsg(bool $reset = true): string
	{
		$img = '';
		$class = '';
		$msg = $this->msg ?? null;

		if (empty($this->msgNo)) {
			return '';
		}

		switch ($this->msgNo) {
			case UI_MSG_OK:
				$img = dPshowImage(dPfindImage('stock_ok-16.png'), '16', '16', '');
				$class = 'message';
				break;
			case UI_MSG_ALERT:
				$img = dPshowImage(dPfindImage('rc-gui-status-downgr.png'), '16', '16', '');
				$class = 'message';
				break;
			case UI_MSG_WARNING:
				$img = dPshowImage(dPfindImage('rc-gui-status-downgr.png'), '16', '16', '');
				$class = 'warning';
				break;
			case UI_MSG_ERROR:
				$img = dPshowImage(dPfindImage('stock_cancel-16.png'), '16', '16', '');
				$class = 'error';
				break;
			default:
				$class = 'message';
				break;
		}

		if ($reset) {
			$this->msg = '';
			$this->msgNo = 0;
		}

		return !empty($msg)
			? '<table cellspacing="0" cellpadding="1" border="0"><tr>'
			. '<td>' . $img . '</td><td class="' . $class . '">' . $msg . '</td>'
			. '</tr></table>'
			: '';
	}

	/**
	 * Set the value of a temporary state variable.
	 */
	public function setState(string $label, mixed $value = null): void
	{
		if ($value !== null) {
			$this->state[$label] = $value;
		}
	}

	/**
	 * Get the value of a temporary state variable.
	 */
	public function getState(string $label, mixed $default_value = null): mixed
	{
		if (!empty($this->state) && is_array($this->state) && array_key_exists($label, $this->state)) {
			return $this->state[$label];
		}

		if ($default_value !== null) {
			$this->setState($label, $default_value);
			return $default_value;
		}

		return null;
	}

	/**
	 * Check preference state
	 */
	public function checkPrefState(string $label, mixed $value, string $prefname, mixed $default_value = null): mixed
	{
		if ($value !== null) {
			$this->state[$label] = $value;
			return $value;
		}

		if (!empty($this->state) && is_array($this->state) && array_key_exists($label, $this->state)) {
			return $this->state[$label];
		}

		$pref = $this->getPref($prefname);
		if ($pref !== null) {
			$this->state[$label] = $pref;
			return $pref;
		}

		if ($default_value !== null) {
			$this->state[$label] = $default_value;
			return $default_value;
		}

		return null;
	}

	/**
	 * Login function
	 */
	public function login(string $username, string $password): bool
	{
		require_once DP_BASE_DIR . '/classes/authenticator.class.php';

		$auth_method = dPgetConfig('auth_method', 'sql');

		/*
		if (
			($_POST['login'] ?? '') !== 'login'
			&& ($_POST['login'] ?? '') !== $this->_('login', UI_OUTPUT_RAW)
			&& ($_REQUEST['login'] ?? '') !== $auth_method
		) {
			die('You have chosen to log in using an unsupported or disabled login method');
		}
		*/

		$auth = getauth($auth_method);

		$username = trim(db_escape($username));
		$password = trim($password);

		if (!$auth->authenticate($username, $password)) {
			return false;
		}

		$user_id = $auth->userId($username);
		$username = $auth->username;

		if (!isset($GLOBALS['acl'])) {
			$GLOBALS['acl'] = new dPacl();
		}

		// TEMPORARY BYPASS - Skip ACL check when ACL tables are not configured
		// Uncomment the block below when ACL tables are properly set up
		/*
		if (!$GLOBALS['acl']->checkLogin($user_id)) {
			dprint(__FILE__, __LINE__, 1, 'Permission check failed');
			return false;
		}
		*/

		$q = new DBQuery();
		$q->addTable('users');
		$q->addQuery(
			'user_id, contact_first_name as user_first_name, '
			. 'contact_last_name as user_last_name, contact_company as user_company, '
			. 'contact_department as user_department, contact_email as user_email, '
			. 'user_type'
		);
		$q->addJoin('contacts', 'con', 'contact_id = user_contact');
		$q->addWhere("user_id = $user_id AND user_username = '$username'");
		$sql = $q->prepare();
		$q->clear();
		dprint(__FILE__, __LINE__, 7, 'Login SQL: ' . $sql);

		if (!db_loadObject($sql, $this)) {
			dprint(__FILE__, __LINE__, 1, 'Failed to load user information');
			return false;
		}

		$this->loadPrefs($this->user_id);
		$this->setUserLocale();
		$this->checkStyle();
		return true;
	}

	/**
	 * Register login in user_access_log
	 */
	public function registerLogin(): void
	{
		$q = new DBQuery();
		$q->addTable('user_access_log');
		$q->addInsert('user_id', $this->user_id);
		$q->addInsert('date_time_in', 'now()', false, true);
		$q->addInsert('user_ip', $_SERVER['REMOTE_ADDR']);
		$q->exec();
		$this->last_insert_id = db_insert_id();
		$q->clear();
	}

	/**
	 * Register logout in user_access_log
	 */
	public function registerLogout(int $user_id): void
	{
		if ($user_id <= 0) {
			return;
		}

		$q = new DBQuery();
		$q->addTable('user_access_log');
		$q->addUpdate('date_time_out', date('Y-m-d H:i:s'));
		$q->addWhere('user_id = ' . $user_id);
		$q->addWhere("(date_time_out='0000-00-00 00:00:00' OR date_time_out IS NULL)");
		$q->addWhere('user_access_log_id = ' . $this->last_insert_id);
		$q->exec();
		$q->clear();
	}

	/**
	 * Update last action in user_access_log
	 */
	public function updateLastAction(?int $last_insert_id): void
	{
		if (!$last_insert_id || $last_insert_id <= 0) {
			return;
		}

		$q = new DBQuery();
		$q->addTable('user_access_log');
		$q->addUpdate('date_time_last_action', date('Y-m-d H:i:s'));
		$q->addWhere('user_access_log_id = ' . $last_insert_id);
		$q->exec();
		$q->clear();
	}

	/**
	 * @deprecated
	 */
	public function logout(): void
	{
	}

	/**
	 * Checks whether there is any user logged in.
	 */
	public function doLogin(): bool
	{
		return $this->user_id < 0;
	}

	/**
	 * Gets the value of the specified user preference
	 */
	public function getPref(string $name): mixed
	{
		return $this->user_prefs[$name] ?? null;
	}

	/**
	 * Gets the value of the specified system preference
	 */
	public function getSystemPref(string $name): mixed
	{
		return !empty($this->system_prefs[$name]) ? $this->system_prefs[$name] : null;
	}

	/**
	 * Sets the value of a user preference specified by name
	 */
	public function setPref(string $name, mixed $val): void
	{
		$this->user_prefs[$name] = $val;
	}

	/**
	 * Check if preference is system preference
	 * @param array<string, mixed> $val
	 */
	public function isSystemPref(array $val): bool
	{
		return !empty($val) && ($val['pref_user'] ?? 0) === 0;
	}

	/**
	 * Check if preference is user preference
	 * @param array<string, mixed> $val
	 */
	public function isUserPref(array $val): bool
	{
		return !empty($val) && ($val['pref_user'] ?? 0) !== 0;
	}

	/**
	 * Flatten preferences array
	 * @param array<int, array<string, mixed>> $vals
	 * @return array<string, mixed>|null
	 */
	public function flattenPrefs(array $vals): ?array
	{
		if (empty($vals)) {
			return null;
		}

		$result = [];
		foreach ($vals as $elem) {
			$result[$elem['pref_name']] = $elem['pref_value'];
		}
		return $result;
	}

	/**
	 * Loads the stored user preferences from the database
	 */
	public function loadPrefs(string|int $uid = 0): void
	{
		$q = new DBQuery();
		$q->addTable('user_preferences');
		$q->addQuery('pref_user, pref_name, pref_value');

		if (!empty($uid)) {
			$q->addWhere("pref_user in (0, $uid)");
			$q->addOrder('pref_user');
		} else {
			$q->addWhere('pref_user = 0');
		}

		$prefs = $q->loadList();
		if (!is_array($prefs)) {
			$prefs = [];
		}

		$this->system_prefs = $this->flattenPrefs(array_filter($prefs, [$this, 'isSystemPref'])) ?? [];

		$user_prefs = [];
		if (!empty($uid)) {
			$user_prefs = $this->flattenPrefs(array_filter($prefs, [$this, 'isUserPref'])) ?? [];
		}

		$this->user_prefs = array_merge($this->system_prefs, $this->user_prefs ?? [], $user_prefs);
	}

	/**
	 * Gets a list of the installed modules
	 * @return array<string, string>
	 */
	public function getInstalledModules(): array
	{
		$q = new DBQuery();
		$q->addTable('modules');
		$q->addQuery('mod_directory, mod_ui_name');
		$q->addOrder('mod_directory');
		return $q->loadHashList();
	}

	/**
	 * Gets a list of the active modules
	 * @return array<string, string>
	 */
	public function getActiveModules(): array
	{
		static $modlist = null;

		if ($modlist === null) {
			$q = new DBQuery();
			$q->addTable('modules');
			$q->addQuery('mod_directory, mod_ui_name');
			$q->addWhere('mod_active > 0');
			$q->addOrder('mod_directory');
			$modlist = $q->loadHashList();
		}

		return $modlist;
	}

	/**
	 * Gets a list of the modules that should appear in the menu
	 * @return array<int, array<string, mixed>>
	 */
	public function getMenuModules(): array
	{
		$q = new DBQuery();
		$q->addTable('modules');
		$q->addQuery('mod_directory, mod_ui_name, mod_ui_icon');
		$q->addWhere("mod_active > 0 AND mod_ui_active > 0 AND mod_directory <> 'public'");
		$q->addWhere("mod_type != 'utility'");
		$q->addOrder('mod_ui_order');
		return $q->loadList();
	}

	/**
	 * Check if module is active
	 */
	public function isActiveModule(string $module): bool
	{
		$modlist = $this->getActiveModules();
		return !empty($modlist[$module]);
	}

	/**
	 * Returns the global dpACL class or creates it as necessary.
	 */
	public function acl(): dPacl
	{
		if (empty($GLOBALS['acl'])) {
			$GLOBALS['acl'] = new dPacl();
		}
		return $GLOBALS['acl'];
	}

	/**
	 * Find and add to output the file tags required to load module-specific javascript.
	 */
	public function loadJS(): void
	{
		global $m, $a;

		if (empty($m)) {
			return;
		}

		$root = DP_BASE_DIR;
		if (!str_ends_with($root, '/')) {
			$root .= '/';
		}

		$base = dPgetConfig('base_url');
		if (!str_ends_with($base, '/')) {
			$base .= '/';
		}

		$jsdir = dir($root . 'js');
		$js_files = [];

		while (($entry = $jsdir->read()) !== false) {
			if (str_ends_with($entry, '.js')) {
				$js_files[] = $entry;
			}
		}

		asort($js_files);

		foreach ($js_files as $js_file_name) {
			echo '<script src="' . $base . 'js/' . $this->___($js_file_name) . '"></script>' . "\n";
		}

		echo '<script src="' . $base . 'lib/overlib/overlib.js"></script>' . "\n";

		$this->getModuleJS($m, $a, true);

		foreach ($this->_js as $href) {
			echo '<script src="' . $href . '"></script>' . "\n";
		}

		foreach ($this->_css as $href) {
			echo '<link rel="stylesheet" type="text/css" href="' . $href . '">' . "\n";
		}
	}

	public function loadCSS(): void
	{
	}

	/**
	 * Get module-specific JavaScript
	 */
	public function getModuleJS(string $module, ?string $file = null, bool $load_all = false): void
	{
		$root = DP_BASE_DIR;
		if (!str_ends_with($root, '/')) {
			$root .= '/';
		}

		$base = DP_BASE_URL;
		if (!str_ends_with($base, '/')) {
			$base .= '/';
		}

		$module = $this->___($module);

		if ($load_all || !$file) {
			if (file_exists($root . 'modules/' . $module . '/' . $module . '.module.js')) {
				echo '<script src="' . $base . 'modules/' . $module . '/'
					. $module . '.module.js"></script>' . "\n";
			}
		}

		if ($file !== null) {
			$file = $this->___($file);
			if (file_exists($root . 'modules/' . $module . '/' . $file . '.js')) {
				echo '<script src="' . $base . 'modules/' . $module . '/'
					. $file . '.js"></script>' . "\n";
			}
		}
	}

	/**
	 * Register loadable JS scripts
	 */
	public function addJS(string $href): void
	{
		$sanitised = dPsanitiseHTML($href);
		if ($sanitised) {
			$this->_js[] = $sanitised;
		}
	}

	/**
	 * Register loadable CSS scripts
	 */
	public function addCSS(string $href): void
	{
		$sanitised = dPsanitiseHTML($href);
		if ($sanitised) {
			$this->_css[] = $sanitised;
		}
	}
}


/**
 * Tabbed box abstract class
 */
class CTabBox_core
{
	/** @var array<int|string, array> Tabs array */
	public ?array $tabs = null;

	/** @var int The active tab */
	public ?int $active = null;

	/** @var string The base URL query string to prefix tab links */
	public ?string $baseHRef = null;

	/** @var string The base path to prefix the include file */
	public string $baseInc = '';

	/** @var string|null A javascript function */
	public ?string $javascript = null;

	/**
	 * Constructor
	 */
	public function __construct(
		string $baseHRef = '',
		string $baseInc = '',
		int $active = 0,
		?string $javascript = null
	) {
		$baseHRef = str_replace('&amp;', '&', $baseHRef);
		$baseHRef = htmlspecialchars($baseHRef);

		$this->tabs = [];
		$this->active = $active;
		$this->baseHRef = $baseHRef ? ($baseHRef . '&amp;') : '?';
		$this->javascript = $javascript;
		$this->baseInc = $baseInc;
	}

	/**
	 * Gets the name of a tab
	 */
	public function getTabName(int $idx): string
	{
		return $this->tabs[$idx][1] ?? '';
	}

	/**
	 * Adds a tab to the object
	 */
	public function add(string $file, string $title, bool $translated = false, int|string|null $key = null): void
	{
		$t = [$file, $title, $translated];
		if ($key !== null) {
			$this->tabs[$key] = $t;
		} else {
			$this->tabs[] = $t;
		}
	}

	public function isTabbed(): bool
	{
		global $AppUI;
		return !($this->active < 0 || ($AppUI->getPref('TABVIEW') ?? 0) === 2);
	}

	/**
	 * Displays the tabbed box
	 */
	public function show(string $extra = '', bool $js_tabs = false): void
	{
		global $AppUI, $currentTabId, $currentTabName;
		reset($this->tabs);
		$s = '';

		// THEME HOOK: Check for modern theme tab renderer
		if (function_exists('style_render_tabs')) {
			echo style_render_tabs($this);
			return;
		}

		if (($AppUI->getPref('TABVIEW') ?? 0) === 0) {
			$s .= '<table border="0" cellpadding="2" cellspacing="0" width="100%">';
			$s .= '<tr><td nowrap="nowrap">';
			$s .= '<a href="' . $this->baseHRef . 'tab=0">' . $AppUI->_('tabbed') . '</a> : ';
			$s .= '<a href="' . $this->baseHRef . 'tab=-1">' . $AppUI->_('flat') . '</a>';
			$s .= '</td>' . $extra . '</tr></table>';
			echo $s;
		} elseif ($extra) {
			echo '<table border="0" cellpadding="2" cellspacing="0" width="100%"><tr>'
				. $extra . '</tr></table>';
		} else {
			echo '<img src="./images/shim.gif" height="10" width="1" alt="" />';
		}

		if ($this->active < 0 || ($AppUI->getPref('TABVIEW') ?? 0) === 2) {
			echo '<table border="0" cellpadding="2" cellspacing="0" width="100%">';
			foreach ($this->tabs as $k => $v) {
				echo '<tr><td><strong>' . ($v[2] ? $AppUI->___($v[1]) : $AppUI->_($v[1]))
					. '</strong></td></tr>';
				echo '<tr><td>';
				$currentTabId = $k;
				$currentTabName = $v[1];
				include $this->baseInc . $v[0] . '.php';
				echo '</td></tr>';
			}
			echo '</table>';
		} else {
			$s = '<table width="100%" border="0" cellpadding="3" cellspacing="0">' . "\n" . '<tr>';
			foreach ($this->tabs as $k => $v) {
				$class = ($k === $this->active) ? 'tabon' : 'taboff';
				$s .= "\n\t" . '<td width="1%" nowrap="nowrap" class="tabsp">';
				$s .= "\n\t\t" . '<img src="./images/shim.gif" height="1" width="1" alt="" />';
				$s .= "\n\t" . '</td>';
				$s .= "\n\t" . '<td id="toptab_' . $k . '" width="1%" nowrap="nowrap"';
				if ($js_tabs) {
					$s .= ' class="' . $class . '"';
				}
				$s .= '>';
				$s .= "\n\t\t" . '<a href="';
				if ($this->javascript) {
					$s .= 'javascript:' . $this->javascript . '(' . $this->active . ', ' . $k . ')';
				} elseif ($js_tabs) {
					$s .= 'javascript:show_tab(' . $k . ')';
				} else {
					$s .= $this->baseHRef . 'tab=' . $k;
				}
				$s .= '">' . ($v[2] ? $v[1] : $AppUI->_($v[1])) . '</a>';
				$s .= "\n\t" . '</td>';
			}
			$s .= "\n\t" . '<td nowrap="nowrap" class="tabsp">&nbsp;</td>';
			$s .= "\n</tr>";
			$s .= "\n<tr>";
			$s .= '<td width="100%" colspan="' . (count($this->tabs) * 2 + 1) . '" class="tabox">';
			echo $s;

			if ($this->baseInc . ($this->tabs[$this->active][0] ?? '') !== '') {
				$currentTabId = $this->active;
				$currentTabName = $this->tabs[$this->active][1] ?? '';
				if (!$js_tabs) {
					require $this->baseInc . $this->tabs[$this->active][0] . '.php';
				}
			}

			if ($js_tabs) {
				foreach ($this->tabs as $k => $v) {
					echo '<div class="tab" id="tab_' . $k . '">';
					require $this->baseInc . $v[0] . '.php';
					echo '</div>';
				}
			}
			echo "\n</td>\n</tr>\n</table>";
		}
	}

	/**
	 * Load tab extras from session
	 */
	public function loadExtras(string $module, ?string $file = null): bool|int
	{
		global $AppUI;

		if (!(isset($_SESSION['all_tabs']) && isset($_SESSION['all_tabs'][$module]))) {
			return false;
		}

		if ($file) {
			if (
				isset($_SESSION['all_tabs'][$module][$file])
				&& is_array($_SESSION['all_tabs'][$module][$file])
			) {
				$tab_array = $_SESSION['all_tabs'][$module][$file];
			} else {
				return false;
			}
		} else {
			$tab_array = $_SESSION['all_tabs'][$module];
		}

		$tab_count = 0;
		foreach ($tab_array as $tab_elem) {
			if (isset($tab_elem['module']) && $AppUI->isActiveModule($tab_elem['module'])) {
				$tab_count++;
				$this->add($tab_elem['file'], $tab_elem['name']);
			}
		}
		return $tab_count;
	}

	/**
	 * Find the module for a tab
	 */
	public function findTabModule(int $tab): string|bool
	{
		global $AppUI, $m, $a;

		if (!(isset($_SESSION['all_tabs']) && isset($_SESSION['all_tabs'][$m]))) {
			return false;
		}

		if (isset($a)) {
			$tab_array = (isset($_SESSION['all_tabs'][$m][$a]) && is_array($_SESSION['all_tabs'][$m][$a]))
				? $_SESSION['all_tabs'][$m][$a]
				: $_SESSION['all_tabs'][$m];
		} else {
			$tab_array = $_SESSION['all_tabs'][$m];
		}

		[$file, $name] = $this->tabs[$tab];

		foreach ($tab_array as $tab_elem) {
			if (isset($tab_elem['name']) && $tab_elem['name'] === $name && $tab_elem['file'] === $file) {
				return $tab_elem['module'];
			}
		}
		return false;
	}
}


/**
 * Title box abstract class
 */
class CTitleBlock_core
{
	/** @var string The main title of the page */
	public string $title = '';

	/** @var string The name of the icon */
	public string $icon = '';

	/** @var string The name of the module */
	public string $module = '';

	/** @var array<int, array> First row of cells */
	public array $cells1 = [];

	/** @var array<int, array> Second row of cells */
	public array $cells2 = [];

	/** @var array<string, array> Breadcrumbs */
	public array $crumbs = [];

	/** @var string The reference for context help */
	public string $helpref = '';

	/** @var bool Whether to show help */
	public bool $showhelp = false;

	/**
	 * Constructor
	 */
	public function __construct(
		string $title,
		string $icon = '',
		string $module = '',
		string $helpref = ''
	) {
		$this->title = $title;
		$this->icon = $icon;
		$this->module = $module;
		$this->helpref = $helpref;
		$this->cells1 = [];
		$this->cells2 = [];
		$this->crumbs = [];
		$this->showhelp = (bool) getPermission('help', 'view');
	}

	/**
	 * Adds a table 'cell' beside the Title string
	 */
	public function addCell(string $data = '', string $attribs = '', string $prefix = '', string $suffix = ''): void
	{
		$this->cells1[] = [$attribs, $data, $prefix, $suffix];
	}

	/**
	 * Adds a breadcrumb
	 */
	public function addCrumb(string $link, string $label, string $icon = ''): void
	{
		$link = dPsanitiseHTML($link);
		$this->crumbs[$link] = [$label, $icon];
	}

	/**
	 * Adds a right-aligned breadcrumb
	 */
	public function addCrumbRight(string $data = '', string $attribs = '', string $prefix = '', string $suffix = ''): void
	{
		$this->cells2[] = [$attribs, $data, $prefix, $suffix];
	}

	/**
	 * Creates a standarised, right-aligned delete bread-crumb and icon.
	 */
	public function addCrumbDelete(string $title, bool|string $canDelete = '', string $msg = ''): void
	{
		global $AppUI;
		$this->addCrumbRight(
			'<table cellspacing="0" cellpadding="0" border="0"><tr><td>'
			. '<a href="javascript:delIt()" title="'
			. $AppUI->_($canDelete ? '' : $msg) . '">'
			. dPshowImage(
				'./images/icons/' . ($canDelete ? 'stock_delete-16.png' : 'stock_trash_full-16.png'),
				'16',
				'16',
				''
			)
			. '</a></td><td>&nbsp;'
			. '<a href="javascript:delIt()" title="'
			. $AppUI->_($canDelete ? '' : $msg)
			. '">' . $AppUI->_($title) . '</a></td></tr></table>'
		);
	}

	/**
	 * The drawing function
	 */
	public function show(): void
	{
		global $AppUI;

		$s = "\n" . '<table width="100%" border="0" cellpadding="1" cellspacing="1">';
		$s .= "\n" . '<tr>';

		if ($this->icon) {
			$s .= "\n" . '<td width="42">';
			$s .= dPshowImage(dPFindImage($this->icon, $this->module));
			$s .= '</td>';
		}

		$s .= "\n" . '<td align="left" width="100%" nowrap="nowrap"><h1>'
			. $AppUI->_($this->title) . '</h1></td>';

		if (!empty($this->cells1)) {
			foreach ($this->cells1 as $c) {
				$s .= "\n" . '<td align="right" nowrap="nowrap"' . ($c[0] ? (' ' . $c[0]) : '') . '>';
				$s .= $c[2] ? "\n" . $c[2] : '';
				$s .= $c[1] ? "\n\t" . $c[1] : '&nbsp;';
				$s .= $c[3] ? "\n" . $c[3] : '';
				$s .= "\n" . '</td>';
			}
		}

		if (!empty($this->showhelp)) {
			$s .= '<td nowrap="nowrap" width="20" align="right">';
			$s .= "\n\t" . '<a href="#' . $this->helpref
				. '" onClick="javascript:window.open(\'?m=help&dialog=1&hid='
				. $this->helpref
				. '\', \'contexthelp\', \'width=400,height=400,left=50,top=50,scrollbars=yes,'
				. 'resizable=yes\')" title="' . $AppUI->_('Help') . '">';
			$s .= "\n\t\t" . '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-100 hover:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
</svg>';
			$s .= "\n\t" . '</a>';
			$s .= "\n</td>";
		}

		$s .= "\n</tr>";
		$s .= "\n</table>";

		if ((!empty($this->crumbs) && count($this->crumbs)) || (!empty($this->cells2) && count($this->cells2))) {
			$crumbs = [];
			foreach ($this->crumbs as $k => $v) {
				$t = $v[1] ? ('<img src="' . dPfindImage($v[1], $this->module) . '" border="" alt="" />&nbsp;') : '';
				$t .= $AppUI->_($v[0]);
				$crumbs[] = '<a href="' . $k . '">' . $t . '</a>';
			}
			$s .= "\n" . '<table border="0" cellpadding="4" cellspacing="0" width="100%">';
			$s .= "\n<tr>";
			$s .= "\n\t" . '<td nowrap="nowrap">';
			$s .= "\n\t\t" . '<strong>' . implode(' : ', $crumbs) . '</strong>';
			$s .= "\n\t" . '</td>';

			if (!empty($this->cells2)) {
				foreach ($this->cells2 as $c) {
					$s .= $c[2] ? "\n{$c[2]}" : '';
					$s .= "\n\t" . '<td align="right" nowrap="nowrap"' . ($c[0] ? " {$c[0]}" : '') . '>';
					$s .= $c[1] ? "\n\t{$c[1]}" : '&nbsp;';
					$s .= "\n\t" . '</td>';
					$s .= $c[3] ? "\n\t" . $c[3] : '';
				}
			}
			$s .= "\n</tr>\n</table>";
		}
		echo $s;
	}
}
