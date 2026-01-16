<?php
/**
 * Departments Module Class
 *
 * @package dotProject
 * @subpackage modules
 * @license GPL version 2 or later
 */

declare(strict_types=1);

if (!defined('DP_BASE_DIR')) {
	die('You should not access this file directly.');
}

/**
 * CDepartment Class
 */
class CDepartment extends CDpObject
{
	public ?int $dept_id = null;
	public ?int $dept_parent = null;
	public ?int $dept_company = null;
	public ?string $dept_name = null;
	public ?string $dept_phone = null;
	public ?string $dept_fax = null;
	public ?string $dept_address1 = null;
	public ?string $dept_address2 = null;
	public ?string $dept_city = null;
	public ?string $dept_state = null;
	public ?string $dept_zip = null;
	public ?string $dept_url = null;
	public ?string $dept_desc = null;
	public ?int $dept_owner = null;

	public function __construct()
	{
		// Empty constructor - no parent call as this class has custom table handling
	}

	public function load(?int $oid = null, bool $strip = true): bool
	{
		if (!$oid) {
			return false;
		}

		$q = new DBQuery();
		$q->addTable('departments', 'dep');
		$q->addQuery('dep.*');
		$q->addWhere('dep.dept_id = ' . $oid);
		$sql = $q->prepare();
		$q->clear();

		return db_loadObject($sql, $this);
	}

	/**
	 * @param array<string, mixed> $hash
	 */
	public function bind(array $hash): bool
	{
		if (!is_array($hash)) {
			// return get_class($this) . '::bind failed';
			return false;
		}
		bindHashToObject($hash, $this);
		return true;
	}

	public function check(): ?string
	{
		if ($this->dept_id === null) {
			return 'department id is NULL';
		}
		if ($this->dept_id && $this->dept_id === $this->dept_parent) {
			return 'cannot make myself my own parent (' . $this->dept_id . '=' . $this->dept_parent . ')';
		}
		return null;
	}

	public function store(bool $updateNulls = false): ?string
	{
		$msg = $this->check();
		if ($msg) {
			return get_class($this) . '::store-check failed - ' . $msg;
		}

		if ($this->dept_id) {
			$ret = db_updateObject('departments', $this, 'dept_id', false);
		} else {
			$ret = db_insertObject('departments', $this, 'dept_id');
		}

		if (!$ret) {
			return get_class($this) . '::store failed <br />' . db_error();
		}
		return null;
	}

	public function delete(?int $oid = null, string $history_desc = '', int $history_proj = 0): ?string
	{
		$q = new DBQuery();
		$q->addTable('departments', 'dep');
		$q->addQuery('dep.*');
		$q->addWhere('dep.dept_parent = ' . $this->dept_id);
		$res = $q->exec();

		if (db_num_rows($res)) {
			$q->clear();
			return 'deptWithSub';
		}

		$q->clear();
		$q->addTable('projects', 'p');
		$q->addQuery('p.*');
		$q->addWhere('p.project_department = ' . $this->dept_id);
		$res = $q->exec();

		if (db_num_rows($res)) {
			$q->clear();
			return 'deptWithProject';
		}

		$q->clear();
		$q->addQuery('*');
		$q->setDelete('departments');
		$q->addWhere('dept_id = ' . $this->dept_id);

		$result = !$q->exec() ? db_error() : null;
		$q->clear();
		return $result;
	}
}

/**
 * Writes out a single <option> element for display of departments
 *
 * @param array<string, mixed> $a
 */
function showchilddept(array &$a, int $level = 1): void
{
	global $AppUI, $cBuffer, $department;

	$s = '<option value="' . $AppUI->___((string) $a['dept_id']) . '"'
		. (isset($department) && $department == $a['dept_id'] ? 'selected="selected"' : '')
		. '>';

	for ($y = 0; $y < $level; $y++) {
		$s .= ($y + 1 === $level) ? '' : '&nbsp;&nbsp;';
	}

	$s .= '&nbsp;&nbsp;' . $AppUI->___($a['dept_name']) . "</option>\n";
	$cBuffer .= $s;
}

/**
 * Recursive function to display children departments
 *
 * @param array<int, array<string, mixed>> $tarr
 */
function findchilddept(array &$tarr, int|string $parent, int $level = 1): void
{
	$level++;
	$n = count($tarr);

	for ($x = 0; $x < $n; $x++) {
		if (
			$tarr[$x]['dept_parent'] == $parent
			&& $tarr[$x]['dept_parent'] != $tarr[$x]['dept_id']
		) {
			showchilddept($tarr[$x], $level);
			findchilddept($tarr, $tarr[$x]['dept_id'], $level);
		}
	}
}

/**
 * Add department IDs recursively
 *
 * @param array<int, array<string, mixed>> $dataset
 */
function addDeptId(array $dataset, int|string $parent): void
{
	global $dept_ids;

	foreach ($dataset as $data) {
		if ($data['dept_parent'] == $parent) {
			$dept_ids[] = $data['dept_id'];
			addDeptId($dataset, $data['dept_id']);
		}
	}
}
