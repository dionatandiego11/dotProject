<?php
/**
 * Companies Module Class
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

/**
 * Companies Class
 */
class CCompany extends CDpObject
{
	public ?int $company_id = null;
	public ?string $company_name = null;

	// Address fields (todo: move to generic table)
	public ?string $company_phone1 = null;
	public ?string $company_phone2 = null;
	public ?string $company_fax = null;
	public ?string $company_address1 = null;
	public ?string $company_address2 = null;
	public ?string $company_city = null;
	public ?string $company_state = null;
	public ?string $company_zip = null;
	public ?string $company_email = null;

	public ?string $company_primary_url = null;
	public ?int $company_owner = null;
	public ?string $company_description = null;
	public ?int $company_type = null;
	public mixed $company_custom = null;

	public function __construct()
	{
		parent::__construct('companies', 'company_id');
	}

	public function check(): ?string
	{
		if ($this->company_id === null) {
			return 'company id is NULL';
		}
		if (empty($this->company_name)) {
			return 'company name cannot be blank';
		}
		$this->company_id = (int) $this->company_id;

		return null;
	}

	public function canDelete(string &$msg, ?int $oid = null, ?array $joins = null): bool
	{
		$tables = [
			['label' => 'Projects', 'name' => 'projects', 'idfield' => 'project_id', 'joinfield' => 'project_company'],
			['label' => 'Departments', 'name' => 'departments', 'idfield' => 'dept_id', 'joinfield' => 'dept_company'],
			['label' => 'Users', 'name' => 'users', 'idfield' => 'user_id', 'joinfield' => 'user_company']
		];
		return parent::canDelete($msg, $oid, $tables);
	}

	/**
	 * Retrieve a hash list of companies filtered by company_type
	 *
	 * @param array<int, int> $type Array of types, e.g. [6, 5]
	 * @return array<int, string>
	 */
	public function listCompaniesByType(array $type): array
	{
		global $AppUI;

		$q = new DBQuery();
		$q->addQuery('company_id, company_name');
		$q->addTable('companies');

		foreach ($type as $t) {
			$q->addWhere('company_type =' . (int) $t);
		}

		$this->setAllowedSQL($AppUI->user_id, $q);
		$q->addOrder('company_name');

		return $q->loadHashList() ?? [];
	}
}
