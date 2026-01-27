<?php
/**
 * Projects Module Class
 *
 * @package dotProject
 * @subpackage modules
 * @license GPL version 2 or later
 */

declare(strict_types=1);

if (!defined('DP_BASE_DIR')) {
	die('You should not access this file directly.');
}

require_once $AppUI->getSystemClass('dp');
require_once $AppUI->getSystemClass('date');
require_once $AppUI->getModuleClass('tasks');
require_once $AppUI->getModuleClass('companies');
require_once $AppUI->getModuleClass('departments');

/**
 * The Project Class
 */
class CProject extends CDpObject
{
	public ?int $project_id = null;
	public ?int $project_company = null;
	public ?int $project_company_internal = null;
	public ?int $project_department = null;
	public ?string $project_name = null;
	public ?string $project_short_name = null;
	public ?int $project_owner = null;
	public ?string $project_url = null;
	public ?string $project_demo_url = null;
	public ?string $project_start_date = null;
	public ?string $project_end_date = null;
	public ?string $project_actual_end_date = null;
	public ?int $project_status = null;
	public ?float $project_percent_complete = null;
	public ?string $project_color_identifier = null;
	public ?string $project_description = null;
	public ?float $project_target_budget = null;
	public ?float $project_actual_budget = null;
	public ?int $project_creator = null;
	public ?int $project_private = null;
	public ?string $project_departments = null;
	public ?string $project_contacts = null;
	public ?int $project_priority = null;
	public ?int $project_type = null;

	public function __construct()
	{
		parent::__construct('projects', 'project_id');

		// Security: Define allowed fields for bind()
		$this->setAllowedFields([
			'project_id',
			'project_company',
			'project_department',
			'project_name',
			'project_short_name',
			'project_owner',
			'project_url',
			'project_demo_url',
			'project_start_date',
			'project_end_date',
			'project_actual_end_date',
			'project_status',
			'project_percent_complete',
			'project_color_identifier',
			'project_description',
			'project_target_budget',
			'project_actual_budget',
			'project_creator',
			'project_private',
			'project_departments',
			'project_contacts',
			'project_priority',
			'project_type'
		]);
	}

	public function check(): ?string
	{
		if (empty($this->project_name)) {
			return 'project name cannot be blank';
		}
		if (empty($this->project_short_name)) {
			return 'project short name cannot be blank';
		}

		$this->project_private = (int) $this->project_private;

		if (mb_strlen($this->project_short_name) > 10) {
			$this->project_short_name = mb_substr($this->project_short_name, 0, 10);
		}

		return null;
	}

	public function load(?int $oid = null, bool $strip = true): bool
	{
		$result = parent::load($oid, $strip);

		if ($result && $oid) {
			$working_hours = dPgetConfig('daily_working_hours') ?: 8;

			$q = new DBQuery();
			$q->addTable('projects', 'p');
			$q->addQuery(
				'SUM(t1.task_duration * t1.task_percent_complete'
				. ' * IF(t1.task_duration_type = 24, ' . $working_hours
				. ', t1.task_duration_type)) / SUM(t1.task_duration'
				. ' * IF(t1.task_duration_type = 24, ' . $working_hours
				. ', t1.task_duration_type)) AS project_percent_complete'
			);
			$q->addJoin('tasks', 't1', 'p.project_id = t1.task_project');
			$q->addWhere('project_id = ' . $oid . ' AND t1.task_id = t1.task_parent');
			$this->project_percent_complete = (float) $q->loadResult();
		}
		return $result;
	}

	public function canDelete(string &$msg, ?int $oid = null, ?array $joins = null): bool
	{
		global $AppUI;
		return getPermission('projects', 'delete', $oid);
	}

	public function delete(?int $oid = null, string $history_desc = '', int $history_proj = 0): ?string
	{
		$this->load($this->project_id);
		addHistory('projects', $this->project_id, 'delete', $this->project_name, $this->project_id);

		$q = new DBQuery();
		$q->addTable('tasks');
		$q->addQuery('task_id');
		$q->addWhere('task_project = ' . $this->project_id);
		$sql = $q->prepare();
		$q->clear();

		$tasks_to_delete = db_loadColumn($sql) ?: [];

		foreach ($tasks_to_delete as $task_id) {
			$q->setDelete('user_tasks');
			$q->addWhere('task_id =' . $task_id);
			$q->exec();
			$q->clear();

			$q->setDelete('task_dependencies');
			$q->addWhere('dependencies_req_task_id =' . $task_id);
			$q->exec();
			$q->clear();
		}

		$q->setDelete('tasks');
		$q->addWhere('task_project =' . $this->project_id);
		$q->exec();
		$q->clear();

		$q->setDelete('project_contacts');
		$q->addWhere('project_id =' . $this->project_id);
		$q->exec();
		$q->clear();

		$q->setDelete('project_departments');
		$q->addWhere('project_id =' . $this->project_id);
		$q->exec();
		$q->clear();

		$q->setDelete('projects');
		$q->addWhere('project_id =' . $this->project_id);

		$result = !$q->exec() ? db_error() : null;
		$q->clear();
		return $result;
	}

	/**
	 * Import tasks from another project
	 */
	public function importTasks(int $from_project_id, bool $scale_project = false): void
	{
		$origProject = new CProject();
		$origProject->load($from_project_id);

		$q = new DBQuery();
		$q->addTable('tasks');
		$q->addQuery('task_id');
		$q->addWhere('task_project =' . $from_project_id);
		$sql = $q->prepare();
		$q->clear();

		$tasks = array_flip(db_loadColumn($sql) ?: []);

		$origStartDate = new CDate($origProject->project_start_date);
		$origEndDate = new CDate($origProject->project_end_date);
		$destStartDate = new CDate($this->project_start_date);
		$destEndDate = new CDate($this->project_end_date);

		$dateOffset = $destStartDate->dateDiff($origStartDate);

		if (
			empty($origProject->project_start_date) || empty($origProject->project_end_date)
			|| empty($this->project_start_date) || empty($this->project_end_date)
			|| $origProject->project_start_date === '0000-00-00 00:00:00'
			|| $origProject->project_end_date === '0000-00-00 00:00:00'
			|| $this->project_start_date === '0000-00-00 00:00:00'
			|| $this->project_end_date === '0000-00-00 00:00:00'
		) {
			$scale_project = false;
		}

		$ratio = 1.0;
		if ($scale_project) {
			$ratio = (abs($destEndDate->dateDiff($destStartDate)) + 1)
				/ (abs($origEndDate->dateDiff($origStartDate)) + 1);
		}

		$deps = [];
		$newDeps = [];
		$taskXref = [];
		$nid2op = [];

		foreach ($tasks as $orig => $void) {
			$objTask = new CTask();
			$objTask->load($orig);

			$oldParent = (int) $objTask->task_parent;
			$deps[$orig] = $objTask->getDependencies();
			$destTask = $objTask->copy($this->project_id, 0);
			$nid2op[$destTask->task_id] = $oldParent;
			$tasks[$orig] = $destTask;
			$taskXref[$orig] = (int) $destTask->task_id;
		}

		foreach ($deps as $odkey => $od) {
			$ndt = '';
			$ndkey = $taskXref[$odkey];
			$odep = explode(',', $od);
			foreach ($odep as $odt) {
				if (isset($taskXref[$odt])) {
					$ndt .= $taskXref[$odt] . ',';
				}
			}
			$ndt = rtrim($ndt, ',');
			$newDeps[$ndkey] = $ndt;
		}

		$q->addTable('tasks');
		$q->addQuery('task_id');
		$q->addWhere('task_project =' . $this->project_id);
		$newTaskIds = $q->loadColumn() ?: [];

		$origDate = new CDate($origProject->project_start_date);
		$origStartHour = new CDate($this->project_start_date);
		$destDate = new CDate($this->project_start_date);

		foreach ($newTaskIds as $task_id) {
			$newTask = new CTask();
			$newTask->load($task_id);

			if (in_array($task_id, $taskXref)) {
				$task_date_vars = ['task_start_date', 'task_end_date'];

				foreach ($task_date_vars as $my_date) {
					if (!empty($newTask->$my_date) && $newTask->$my_date !== '0000-00-00 00:00:00') {
						$origDate->setDate($newTask->$my_date);
						$origStartHour->setDate($newTask->$my_date);
						$origStartHour->setTime((int) dPgetConfig('cal_day_start'));
						$destDate->setDate($newTask->$my_date);
						$destDate->addDays($dateOffset);

						if ($scale_project) {
							$offsetAdd = round(($origDate->dateDiff($origStartDate)) * $ratio)
								- $origDate->dateDiff($origStartDate);
							$destDate->addDays((int) $offsetAdd);

							$hours_in = $origStartHour->calcDuration($origDate);
							$offsetAddHours = round($hours_in * $ratio) - $hours_in;
							if ($offsetAddHours % dPgetConfig('daily_working_hours')) {
								$destDate->addDuration((int) $offsetAddHours);
							}
						}
						$destDate = $destDate->next_working_day();
						$newTask->$my_date = $destDate->format(FMT_DATETIME_MYSQL);
					}
				}

				if ($scale_project) {
					$newTask->task_duration = round($newTask->task_duration * $ratio, 2);
				}
				$newTask->task_parent = $taskXref[$nid2op[$newTask->task_id]] ?? $newTask->task_parent;
				$newTask->store();
				if (isset($newDeps[$task_id])) {
					$newTask->updateDependencies($newDeps[$task_id]);
				}
			}
			$newTask->store();
		}
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function getAllowedRecords(
		int $uid,
		string $fields = '*',
		string $orderby = '',
		?string $index = null,
		?array $extra = null
	): array {
		$oCpy = new CCompany();
		$aCpies = $oCpy->getAllowedRecords($uid, 'company_id, company_name');

		$buffer = count($aCpies)
			? '(project_company IN (' . implode(',', array_keys($aCpies)) . '))'
			: '1 = 0';

		$extra ??= [];
		$extra['where'] = (!empty($extra['where']) ? $extra['where'] . ' AND ' : '') . $buffer;

		return parent::getAllowedRecords($uid, $fields, $orderby, $index, $extra);
	}

	/**
	 * @return array<int, string>
	 */
	public function getAllowedSQL(int $uid, ?string $index = null, ?string $alt_mod = null): array
	{
		$oCpy = new CCompany();
		$where = $oCpy->getAllowedSQL($uid, 'project_company');
		$project_where = parent::getAllowedSQL($uid, $index);
		return array_merge($where, $project_where);
	}

	public function setAllowedSQL(
		int $uid,
		DBQuery $query,
		?string $index = null,
		?string $key = null,
		?string $alt_mod = null
	): void {
		$oCpy = new CCompany();
		parent::setAllowedSQL($uid, $query, $index, $key);
		$oCpy->setAllowedSQL($uid, $query, ($key ? $key . '.' : '') . 'project_company');
	}

	/**
	 * @return array<int, int>
	 */
	public function getDeniedRecords(int $uid): array
	{
		$aBuf1 = parent::getDeniedRecords($uid);

		$oCpy = new CCompany();
		$aCpiesAllowed = $oCpy->getAllowedRecords($uid, 'company_id,company_name');

		$q = new DBQuery();
		$q->addTable('projects');
		$q->addQuery('project_id');
		if (count($aCpiesAllowed)) {
			$q->addWhere('NOT (project_company IN (' . implode(',', array_keys($aCpiesAllowed)) . '))');
		}
		$sql = $q->prepare();
		$q->clear();
		$aBuf2 = db_loadColumn($sql) ?: [];

		return array_merge($aBuf1, $aBuf2);
	}

	public function getAllowedProjectsInRows(int $userId): mixed
	{
		$q = new DBQuery();
		$q->addQuery('project_id, project_status, project_name, project_description, project_short_name');
		$q->addTable('projects');
		$q->addOrder('project_short_name');
		$this->setAllowedSQL($userId, $q);
		return $q->exec();
	}

	public function getAssignedProjectsInRows(int $userId): mixed
	{
		$q = new DBQuery();
		$q->addQuery('project_id, project_status, project_name, project_description, project_short_name');
		$q->addTable('projects');
		$q->addJoin('tasks', 't', 't.task_project = project_id');
		$q->addJoin('user_tasks', 'ut', 'ut.task_id = t.task_id');
		$q->addWhere('ut.user_id = ' . $userId);
		$q->addGroup('project_id');
		$q->addOrder('project_name');
		$this->setAllowedSQL($userId, $q);
		return $q->exec();
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function getCriticalTasks(?int $project_id = null, int $limit = 1): array
	{
		$project_id = $project_id ?? $this->project_id;

		$q = new DBQuery();
		$q->addTable('tasks');
		if ($project_id) {
			$q->addWhere('task_project = ' . $project_id);
		}
		$q->addWhere("!isnull(task_end_date) AND task_end_date != '0000-00-00 00:00:00'");
		$q->addOrder('task_end_date DESC');
		$q->setLimit($limit);

		return $q->loadList() ?: [];
	}

	public function store(bool $updateNulls = false): ?string
	{
		$this->dPTrimAll();

		$msg = $this->check();
		if ($msg) {
			return get_class($this) . '::store-check failed - ' . $msg;
		}

		if ($this->project_id) {
			$ret = db_updateObject('projects', $this, 'project_id', false);
			addHistory('projects', $this->project_id, 'update', $this->project_name, $this->project_id);
		} else {
			$ret = db_insertObject('projects', $this, 'project_id');
			addHistory('projects', $this->project_id, 'add', $this->project_name, $this->project_id);
		}

		$q = new DBQuery();
		$q->setDelete('project_departments');
		$q->addWhere('project_id=' . $this->project_id);
		$q->exec();
		$q->clear();

		if ($this->project_departments) {
			$departments = explode(',', $this->project_departments);
			foreach ($departments as $department) {
				$q->addTable('project_departments');
				$q->addInsert('project_id', (string) $this->project_id);
				$q->addInsert('department_id', $department);
				$q->exec();
				$q->clear();
			}
		}

		$q->setDelete('project_contacts');
		$q->addWhere('project_id=' . $this->project_id);
		$q->exec();
		$q->clear();

		if ($this->project_contacts) {
			$contacts = explode(',', $this->project_contacts);
			foreach ($contacts as $contact) {
				if ($contact) {
					$q->addTable('project_contacts');
					$q->addInsert('project_id', (string) $this->project_id);
					$q->addInsert('contact_id', $contact);
					$q->exec();
					$q->clear();
				}
			}
		}

		return !$ret ? get_class($this) . '::store failed <br />' . db_error() : null;
	}
}

/**
 * Build project list data for display
 */
function projects_list_data(int|false $user_id = false): void
{
	global $AppUI, $addPwOiD, $cBuffer, $company, $company_id, $company_prefix, $deny, $department;
	global $dept_ids, $dPconfig, $orderby, $orderdir, $projects, $tasks_critical, $tasks_problems;
	global $tasks_sum, $tasks_summy, $tasks_total, $owner, $projectTypeId, $project_status;
	global $currentTabId;

	$addProjectsWithAssignedTasks = $AppUI->getState('addProjWithTasks') ?: 0;
	$obj_project = new CProject();

	$q = new DBQuery();
	$table_list = ['tasks_sum', 'tasks_total', 'tasks_summy', 'tasks_critical', 'tasks_problems', 'tasks_users'];
	$q->dropTemp($table_list);
	$q->exec();
	$q->clear();

	$working_hours = $dPconfig['daily_working_hours'] ?? 8;

	// Task sum table
	$q->createTemp('tasks_sum');
	$q->addTable('tasks', 't');
	$q->addQuery(
		't.task_project, SUM(t.task_duration * t.task_percent_complete'
		. ' * IF(t.task_duration_type = 24, ' . $working_hours
		. ', t.task_duration_type)) / SUM(t.task_duration'
		. ' * IF(t.task_duration_type = 24, ' . $working_hours
		. ', t.task_duration_type)) AS project_percent_complete, SUM(t.task_duration'
		. ' * IF(t.task_duration_type = 24, ' . $working_hours
		. ', t.task_duration_type)) AS project_duration'
	);
	if ($user_id) {
		$q->addJoin('user_tasks', 'ut', 'ut.task_id = t.task_id');
		$q->addWhere('ut.user_id = ' . $user_id);
	}
	$q->addWhere('t.task_id = t.task_parent');
	$q->addGroup('t.task_project');
	$tasks_sum = $q->exec();
	$q->clear();

	// Task total table
	$q->createTemp('tasks_total');
	$q->addTable('tasks', 't');
	$q->addQuery('t.task_project, COUNT(distinct t.task_id) AS total_tasks');
	if ($user_id) {
		$q->addJoin('user_tasks', 'ut', 'ut.task_id = t.task_id');
		$q->addWhere('ut.user_id = ' . $user_id);
	}
	$q->addGroup('t.task_project');
	$tasks_total = $q->exec();
	$q->clear();

	// My tasks table
	$q->createTemp('tasks_summy');
	$q->addTable('tasks', 't');
	$q->addQuery('t.task_project, COUNT(DISTINCT t.task_id) AS my_tasks');
	$q->addWhere('t.task_owner = ' . ($user_id ?: $AppUI->user_id));
	$q->addGroup('t.task_project');
	$tasks_summy = $q->exec();
	$q->clear();

	// Critical tasks table
	$q->createTemp('tasks_critical');
	$q->addTable('tasks', 't');
	$q->addQuery('t.task_project, t.task_id AS critical_task, MAX(t.task_end_date) AS project_actual_end_date');
	$q->addOrder('t.task_end_date DESC');
	$q->addGroup('t.task_project');
	$tasks_critical = $q->exec();
	$q->clear();

	// Task problems table
	$q->createTemp('tasks_problems');
	$q->addTable('tasks', 't');
	$q->addQuery('t.task_project, tl.task_log_problem');
	$q->addJoin('task_log', 'tl', 'tl.task_log_task = t.task_id');
	$q->addWhere('tl.task_log_problem > 0');
	$q->addGroup('t.task_project');
	$tasks_problems = $q->exec();
	$q->clear();

	if ($addProjectsWithAssignedTasks) {
		$q->createTemp('tasks_users');
		$q->addTable('tasks', 't');
		$q->addQuery('t.task_project, ut.user_id');
		$q->addJoin('user_tasks', 'ut', 'ut.task_id = t.task_id');
		if ($user_id) {
			$q->addWhere('ut.user_id = ' . $user_id);
		}
		$q->addOrder('t.task_end_date DESC');
		$q->addGroup('t.task_project');
		$tasks_users = $q->exec();
		$q->clear();
	}

	$owner_ids = [];
	if ($addPwOiD && isset($department)) {
		$q->addTable('users', 'u');
		$q->addQuery('u.user_id');
		$q->addJoin('contacts', 'c', 'c.contact_id = u.user_contact');
		$q->addWhere('c.contact_department = ' . $department);
		$owner_ids = $q->loadColumn() ?: [];
		$q->clear();
	}

	$rows = [];
	if (isset($department)) {
		$dept_ids = [];
		$q->addTable('departments');
		$q->addQuery('dept_id, dept_parent');
		$q->addOrder('dept_parent,dept_name');
		$rows = $q->loadList() ?: [];
		addDeptId($rows, $department);
		$dept_ids[] = $department;
	}
	$q->clear();

	// Main project query
	$q->addTable('projects', 'p');
	$q->addQuery(
		'p.project_id, p.project_status, p.project_color_identifier, p.project_type'
		. ', p.project_name, p.project_description, p.project_start_date'
		. ', p.project_end_date, p.project_color_identifier, p.project_company'
		. ', p.project_status, p.project_priority, com.company_name'
		. ', com.company_description, tc.critical_task, tc.project_actual_end_date'
		. ', if (tp.task_log_problem IS NULL, 0, tp.task_log_problem) AS task_log_problem'
		. ', tt.total_tasks, tsy.my_tasks, ts.project_percent_complete'
		. ', ts.project_duration, u.user_username'
	);
	$q->addJoin('companies', 'com', 'p.project_company = com.company_id');
	$q->addJoin('users', 'u', 'p.project_owner = u.user_id');
	$q->addJoin('tasks_critical', 'tc', 'p.project_id = tc.task_project');
	$q->addJoin('tasks_problems', 'tp', 'p.project_id = tp.task_project');
	$q->addJoin('tasks_sum', 'ts', 'p.project_id = ts.task_project');
	$q->addJoin('tasks_total', 'tt', 'p.project_id = tt.task_project');
	$q->addJoin('tasks_summy', 'tsy', 'p.project_id = tsy.task_project');

	if ($addProjectsWithAssignedTasks) {
		$q->addJoin('tasks_users', 'tu', 'p.project_id = tu.task_project');
	}
	if (isset($project_status) && $currentTabId != 500) {
		$q->addWhere('p.project_status = ' . $project_status);
	}
	if (isset($department)) {
		$q->addJoin('project_departments', 'pd', 'pd.project_id = p.project_id');
		if (!$addPwOiD) {
			$q->addWhere('pd.department_id in (' . implode(',', $dept_ids) . ')');
		} else {
			$q->addWhere('p.project_owner IN (' . (!empty($owner_ids) ? implode(',', $owner_ids) : '0') . ')');
		}
	} elseif ($company_id && !$addPwOiD) {
		$q->addWhere('p.project_company = ' . $company_id);
	}

	if ($projectTypeId > -1) {
		$q->addWhere('p.project_type = ' . $projectTypeId);
	}
	if ($user_id && $addProjectsWithAssignedTasks) {
		$q->addWhere('(tu.user_id = ' . $user_id . ' OR p.project_owner = ' . $user_id . ')');
	} elseif ($user_id) {
		$q->addWhere('p.project_owner = ' . $user_id);
	}
	if ($owner > 0) {
		$q->addWhere('p.project_owner = ' . $owner);
	}

	$q->addGroup('p.project_id');
	$q->addOrder($orderby . ' ' . $orderdir);
	$obj_project->setAllowedSQL((int) $AppUI->user_id, $q, null, 'p');
	$projects_data = $q->loadList();
	$projects = [];
	if ($projects_data) {
		foreach ($projects_data as $row) {
			$projects[] = \DotProject\Entity\Project::fromArray($row);
		}
	}

	$obj_company = new CCompany();
	$companies = $obj_company->getAllowedRecords((int) $AppUI->user_id, 'company_id,company_name', 'company_name');
	if (count($companies) === 0) {
		$companies = [0];
	}
	$companies = arrayMerge(['0' => $AppUI->_('All')], $companies);

	$q->clear();
	$q->addTable('companies', 'c');
	$q->addQuery('distinct c.company_id, c.company_name, dep.*');
	$q->addJoin('departments', 'dep', 'c.company_id = dep.dept_company');
	$q->addJoin('projects', 'p', 'p.project_company = c.company_id');
	$q->addWhere('p.project_status NOT IN (1, 4, 5, 6, 7)');
	$q->addOrder('c.company_name, dep.dept_parent, dep.dept_name');
	$obj_company->setAllowedSQL((int) $AppUI->user_id, $q);
	$active_companies = $q->loadList() ?: [];

	$q->clear();
	$q->addTable('companies', 'c');
	$q->addQuery('distinct c.company_id, c.company_name, dep.*');
	$q->addJoin('departments', 'dep', 'c.company_id = dep.dept_company');
	$q->addJoin('projects', 'p', 'p.project_company = c.company_id');
	$q->addOrder('c.company_name, dep.dept_parent, dep.dept_name');
	$obj_company->setAllowedSQL((int) $AppUI->user_id, $q);
	$all_companies = $q->loadList() ?: [];

	// Build select list
	$cBuffer = '<select name="department" onchange="javascript:document.pickCompany.submit()" class="text">';
	$cBuffer .= '<option value="company_0" style="font-weight:bold;">' . $AppUI->_('All') . '</option>' . "\n";
	$company = '';
	$active_company_ids = [];

	$cBuffer .= '<optgroup label="Active">';
	foreach ($active_companies as $row) {
		if ($row['dept_parent'] == 0) {
			if ($company != $row['company_id']) {
				$cBuffer .= '<option value="' . $AppUI->___($company_prefix . $row['company_id'])
					. '" style="font-weight:bold;"'
					. ($company_id == $row['company_id'] ? 'selected="selected"' : '')
					. '>' . $AppUI->___($row['company_name']) . '</option>' . "\n";
				$company = $row['company_id'];
				$active_company_ids[] = $company;
			}
			if ($row['dept_parent'] !== null) {
				showchilddept($row);
				findchilddept($rows, $row['dept_id']);
			}
		}
	}
	$cBuffer .= '</optgroup>';

	$cBuffer .= '<optgroup label="Inactive">';
	foreach ($all_companies as $row) {
		if ($row['dept_parent'] == 0 && !in_array($row['company_id'], $active_company_ids)) {
			if ($company != $row['company_id']) {
				$cBuffer .= '<option value="' . $AppUI->___($company_prefix . $row['company_id'])
					. '" style="font-weight:bold;"'
					. ($company_id == $row['company_id'] ? 'selected="selected"' : '')
					. '>' . $AppUI->___($row['company_name']) . '</option>' . "\n";
				$company = $row['company_id'];
			}
			if ($row['dept_parent'] !== null) {
				showchilddept($row);
				findchilddept($rows, $row['dept_id']);
			}
		}
	}
	$cBuffer .= '</optgroup>';
	$cBuffer .= '</select>';
}
