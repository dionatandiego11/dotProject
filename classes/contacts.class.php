<?php
/**
 * Contacts Module Class
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
 * Contacts class
 */
class CContact extends CDpObject
{
    public ?int $contact_id = null;
    public ?string $contact_first_name = null;
    public ?string $contact_last_name = null;
    public ?string $contact_order_by = null;
    public ?string $contact_title = null;
    public ?string $contact_job = null;
    public ?string $contact_birthday = null;
    public ?int $contact_company = null;
    public ?int $contact_department = null;
    public ?int $contact_type = null;
    public ?string $contact_email = null;
    public ?string $contact_email2 = null;
    public ?string $contact_phone = null;
    public ?string $contact_phone2 = null;
    public ?string $contact_fax = null;
    public ?string $contact_mobile = null;
    public ?string $contact_address1 = null;
    public ?string $contact_address2 = null;
    public ?string $contact_city = null;
    public ?string $contact_state = null;
    public ?string $contact_zip = null;
    public ?string $contact_url = null;
    public ?string $contact_icq = null;
    public ?string $contact_aol = null;
    public ?string $contact_yahoo = null;
    public ?string $contact_msn = null;
    public ?string $contact_jabber = null;
    public ?string $contact_notes = null;
    public ?int $contact_project = null;
    public ?string $contact_country = null;
    public ?string $contact_icon = null;
    public ?int $contact_owner = null;
    public ?int $contact_private = null;

    public function __construct()
    {
        parent::__construct('contacts', 'contact_id');
    }

    public function check(): ?string
    {
        if ($this->contact_id === null) {
            return 'contact id is NULL';
        }
        $this->contact_private = (int) $this->contact_private;
        $this->contact_owner = (int) $this->contact_owner;
        return null;
    }

    public function canDelete(string &$msg, ?int $oid = null, ?array $joins = null): bool
    {
        global $AppUI;

        if ($oid) {
            $q = new DBQuery();
            $q->addTable('users');
            $q->addQuery('count(*) as user_count');
            $q->addWhere('user_contact = ' . (int) $oid);
            $user_count = (int) $q->loadResult();

            if ($user_count > 0) {
                $msg = $AppUI->_('contactsDeleteUserError');
                return false;
            }
        }
        return parent::canDelete($msg, $oid, $joins);
    }

    public function getCompanyName(): ?string
    {
        $q = new DBQuery();
        $q->addTable('companies');
        $q->addQuery('company_name');
        $q->addWhere('company_id = ' . (int) $this->contact_company);
        return $q->loadResult();
    }

    /**
     * @return array<string, int|string>
     */
    public function getCompanyDetails(): array
    {
        $result = ['company_id' => 0, 'company_name' => ''];

        if (!$this->contact_company) {
            return $result;
        }

        $q = new DBQuery();
        $q->addTable('companies');
        $q->addQuery('company_id, company_name');
        $q->addWhere('company_id = ' . (int) $this->contact_company);

        return $q->loadHash() ?? $result;
    }

    /**
     * @return array<string, int|string>
     */
    public function getDepartmentDetails(): array
    {
        $result = ['dept_id' => 0, 'dept_name' => ''];

        if (!$this->contact_department) {
            return $result;
        }

        $q = new DBQuery();
        $q->addTable('departments');
        $q->addQuery('dept_id, dept_name');
        $q->addWhere('dept_id = ' . (int) $this->contact_department);

        return $q->loadHash() ?? $result;
    }
}
