<?php
/**
 * @package dotproject
 * @subpackage modules
 * @license http://opensource.org/licenses/gpl-license.php GPL License Version 2
 */

declare(strict_types=1);

if (!defined('DP_BASE_DIR')) {
    die('You should not access this file directly.');
}

require_once $AppUI->getSystemClass('query');
require_once $AppUI->getModuleClass('system');

/**
 * CDpObject Abstract Class.
 *
 * Parent class to all database table derived objects
 * @author Andrew Eddie <eddieajau@users.sourceforge.net>
 */
class CDpObject
{
    /**
     * Name of the table in the db schema relating to child class
     */
    protected string $_tbl = '';

    /**
     * Name of the primary key field in the table
     */
    protected string $_tbl_key = '';

    /**
     * Permission module name relating to child class
     */
    protected string $_permission_name = '';

    /**
     * Module directory name
     */
    protected string $_module_directory = '';

    /**
     * White list of fields allowed to be bound
     * @var array<string>|null
     */
    protected ?array $_allowed_fields = null;

    /**
     * Error message
     */
    protected string $_error = '';

    /**
     * Generic message
     */
    public string $_message = '';

    /**
     * Query Handler
     */
    protected DBQuery $_query;

    /**
     * Object constructor to set table and key field
     *
     * Can be overloaded/supplemented by the child class
     * @param string $table name of the table in the db schema relating to child class
     * @param string $key name of the primary key field in the table
     * @param string $perm_name permission module name relating to child class (default $table)
     * @param string $mod_dir module directory name
     */
    public function __construct(
        string $table,
        string $key,
        string $perm_name = '',
        string $mod_dir = ''
    ) {
        $this->_tbl = $table;
        $this->_tbl_key = $key;
        $this->_permission_name = $perm_name ?: $table;
        $this->_module_directory = $mod_dir;
        $this->_query = new DBQuery();
    }

    /**
     * Returns the error message
     */
    public function getError(): string
    {
        return $this->_error;
    }

    /**
     * Clears any existing data in the current object
     */
    public function clear(): void
    {
        foreach (array_keys(array_diff_key(get_object_vars($this), get_class_vars(get_class($this)))) as $k) {
            unset($this->$k);
        }
    }

    /**
     * Set allowed fields for bind()
     * @param array<string> $fields
     */
    public function setAllowedFields(array $fields): void
    {
        $this->_allowed_fields = $fields;
    }

    /**
     * Binds a named array/hash to this object
     *
     * Can be overloaded/supplemented by the child class
     * @param array<string, mixed> $hash named array
     * @return bool true on success, false on failure
     */
    public function bind(array $hash): bool
    {
        if (empty($hash)) {
            $this->_error = get_class($this) . '::bind failed.';
            return false;
        }

        // Filter out any object values from the array/hash
        $filtered_hash = array_filter($hash, fn($v) => !is_object($v));

        // Security: Filter by allowed fields if defined
        if ($this->_allowed_fields !== null) {
            $filtered_hash = array_intersect_key($filtered_hash, array_flip($this->_allowed_fields));
        }

        bindHashToObject($filtered_hash, $this);
        return true;
    }

    /**
     * Binds an array/hash to this object
     * @param int|null $oid optional argument, if not specified then the value of current key is used
     * @param bool $strip whether to strip slashes
     * @return mixed result from the database operation
     */
    public function load(?int $oid = null, bool $strip = true): mixed
    {
        $k = $this->_tbl_key;
        if ($oid !== null) {
            $this->$k = $oid;
        }
        $oid = $this->$k ?? null;
        if ($oid === null) {
            return false;
        }
        $this->_query->clear();
        $this->_query->addTable($this->_tbl);
        $this->_query->addWhere("{$this->_tbl_key} = {$oid}");
        $sql = $this->_query->prepare();
        $this->_query->clear();
        $this->clear();
        return db_loadObject($sql, $this, false, $strip);
    }

    /**
     * Returns an array, keyed by the key field, of all elements that meet
     * the where clause provided. Ordered by $order key.
     * @param string|null $order order clause
     * @param string|null $where where clause
     * @return array<int|string, mixed>
     */
    public function loadAll(?string $order = null, ?string $where = null): array
    {
        $this->_query->clear();
        $this->_query->addTable($this->_tbl);
        if ($order) {
            $this->_query->addOrder($order);
        }
        if ($where) {
            $this->_query->addWhere($where);
        }
        $sql = $this->_query->prepare();
        $this->_query->clear();
        return db_loadHashList($sql, $this->_tbl_key);
    }

    /**
     * Return a DBQuery object seeded with the table name.
     * @param string|null $alias optional alias for table queries.
     * @return DBQuery object
     */
    public function getQuery(?string $alias = null): DBQuery
    {
        $this->_query->clear();
        $this->_query->addTable($this->_tbl, $alias);
        return $this->_query;
    }

    /**
     * Generic check method
     *
     * Can be overloaded/supplemented by the child class
     * @return string|null null if the object is ok
     */
    public function check(): ?string
    {
        return null;
    }

    /**
     * Clone the current record
     *
     * @return static The new record object
     */
    public function duplicate(): static
    {
        $newObj = clone $this;
        $_key = $this->_tbl_key;
        $newObj->$_key = '';
        return $newObj;
    }

    /**
     * Default trimming method for class variables of type string
     *
     * Can be overloaded/supplemented by the child class
     */
    public function dPTrimAll(): void
    {
        foreach (get_object_vars($this) as $key => $val) {
            if (is_string($val)) {
                $this->$key = trim($val);
            }
        }
    }

    /**
     * Inserts a new row if id is zero or updates an existing row in the database table
     *
     * Can be overloaded/supplemented by the child class
     * @param bool $updateNulls whether to update null values
     * @return string|null null if successful otherwise returns an error message
     */
    public function store(bool $updateNulls = false): ?string
    {
        $this->dPTrimAll();

        $msg = $this->check();
        if ($msg) {
            return get_class($this) . '::store-check failed<br />' . $msg;
        }

        $k = $this->_tbl_key;
        if (!empty($this->$k)) {
            $store_type = 'update';
            $ret = db_updateObject($this->_tbl, $this, $this->_tbl_key, $updateNulls);
        } else {
            $store_type = 'add';
            $ret = db_insertObject($this->_tbl, $this, $this->_tbl_key);
        }

        if ($ret) {
            // only record history if an update or insert actually occurs.
            addHistory(
                $this->_tbl,
                $this->$k,
                $store_type,
                "{$this->_tbl}_{$store_type}({$this->$k})"
            );
        }

        return $ret ? null : get_class($this) . '::store failed <br />' . db_error();
    }

    /**
     * Generic check for whether dependencies exist for this object in the db schema
     *
     * Can be overloaded/supplemented by the child class
     * @param string $msg Error message returned
     * @param int|null $oid Optional key index
     * @param array<int, array<string, string>>|null $joins Optional array to compile standard joins
     * @return bool
     */
    public function canDelete(string &$msg, ?int $oid = null, ?array $joins = null): bool
    {
        global $AppUI;

        // First things first. Are we allowed to delete?
        $acl = $AppUI->acl();
        if (!$acl->checkModuleItem($this->_permission_name, 'delete', $oid)) {
            $msg = $AppUI->_('noDeletePermission');
            return false;
        }

        $k = $this->_tbl_key;
        if ($oid !== null) {
            $this->$k = $oid;
        }

        if (is_array($joins)) {
            $q = new DBQuery();
            $q->addTable($this->_tbl, 'k');
            $q->addQuery($k);
            $i = 0;
            foreach ($joins as $table) {
                $table_alias = 't' . $i++;
                $q->addJoin(
                    $table['name'],
                    $table_alias,
                    "{$table_alias}.{$table['joinfield']} = k.{$k}"
                );
                $q->addQuery(
                    "COUNT(DISTINCT {$table_alias}.{$table['idfield']}) AS {$table['idfield']}{$table_alias}"
                );
            }
            $q->addWhere("{$k} = '{$this->$k}'");
            $q->addGroup($k);
            $sql = $q->prepare(true);

            $obj = null;
            if (!db_loadObject($sql, $obj)) {
                $msg = db_error();
                return false;
            }

            $msgArray = [];
            $i = 0;
            foreach ($joins as $table) {
                $table_alias = 't' . $i++;
                $fieldKey = $table['idfield'] . $table_alias;
                if ($obj->$fieldKey ?? 0) {
                    $msgArray[] = $table_alias . '.' . $AppUI->_($table['label']);
                }
            }

            if (count($msgArray)) {
                $msg = $AppUI->_('noDeleteRecord') . ': ' . implode(', ', $msgArray);
                return false;
            }
        }

        return true;
    }

    /**
     * Default delete method
     *
     * Can be overloaded/supplemented by the child class
     * @param int|null $oid optional object id
     * @param string $history_desc history description
     * @param int $history_proj history project id
     * @return string|null null if successful otherwise returns an error message
     */
    public function delete(?int $oid = null, string $history_desc = '', int $history_proj = 0): ?string
    {
        $k = $this->_tbl_key;
        if ($oid !== null) {
            $this->$k = $oid;
        }

        $msg = '';
        if (!$this->canDelete($msg)) {
            return $msg;
        }

        $q = new DBQuery();
        $q->setDelete($this->_tbl);
        $q->addWhere("{$this->_tbl_key} = '{$this->$k}'");
        $result = $q->exec() ? null : db_error();

        if (!$result) {
            // only record history if deletion actually occurred
            addHistory($this->_tbl, $this->$k, 'delete', $history_desc, $history_proj);
        }
        $q->clear();
        return $result;
    }

    /**
     * Get specifically denied records from a table/module based on a user
     * @param int $uid User id number
     * @return array<int, mixed>
     */
    public function getDeniedRecords(int $uid): array
    {
        global $AppUI;
        $perms = $AppUI->acl();

        if ($uid === 0) {
            exit('FATAL ERROR<br />' . get_class($this) . '::getDeniedRecords failed, user id = 0');
        }

        return $perms->getDeniedItems($this->_tbl, $uid);
    }

    /**
     * Returns a list of records exposed to the user
     * @param int $uid User id number
     * @param string $fields Optional fields to be returned by the query, default is all
     * @param string $orderby Optional sort order for the query
     * @param string|null $index Optional name of field to index the returned array
     * @param array<string, mixed>|null $extra Optional array of additional sql parameters
     * @return array<int|string, mixed>
     */
    public function getAllowedRecords(
        int $uid,
        string $fields = '*',
        string $orderby = '',
        ?string $index = null,
        ?array $extra = null
    ): array {
        global $AppUI;
        $perms = $AppUI->acl();

        if ($uid === 0) {
            exit('FATAL ERROR<br />' . get_class($this) . '::getAllowedRecords failed');
        }

        $deny = $perms->getDeniedItems($this->_tbl, $uid);
        $allow = $perms->getAllowedItems($this->_tbl, $uid);

        if (!$perms->checkModule($this->_tbl, 'view', $uid)) {
            if (empty($allow)) {
                return []; // No access, and no allow overrides, so nothing to show.
            }
        } else {
            $allow = []; // Full access, allow overrides don't mean anything.
        }

        $this->_query->clear();
        $this->_query->addQuery($fields);
        $this->_query->addTable($this->_tbl);

        if (!empty($extra['from'])) {
            $this->_query->addTable($extra['from']);
        }

        if (!empty($allow)) {
            $this->_query->addWhere("{$this->_tbl_key} IN (" . implode(',', $allow) . ')');
        }
        if (!empty($deny)) {
            $this->_query->addWhere("{$this->_tbl_key} NOT IN (" . implode(',', $deny) . ')');
        }
        if (isset($extra['where'])) {
            $this->_query->addWhere($extra['where']);
        }

        if ($orderby) {
            $this->_query->addOrder($orderby);
        }

        return $this->_query->loadHashList($index);
    }

    /**
     * Get allowed SQL conditions
     * @param int $uid User id
     * @param string|null $index field index
     * @param string|null $alt_mod alternative module name
     * @return array<int, string>
     */
    public function getAllowedSQL(int $uid, ?string $index = null, ?string $alt_mod = null): array
    {
        global $AppUI;
        $perms = $AppUI->acl();
        $mod = $alt_mod ?? $this->_tbl;

        if ($uid === 0) {
            exit('FATAL ERROR<br />' . get_class($this) . '::getAllowedSQL failed');
        }

        $deny = $perms->getDeniedItems($mod, $uid);
        $allow = $perms->getAllowedItems($mod, $uid);

        if (!$perms->checkModule($mod, 'view', $uid)) {
            if (empty($allow)) {
                return ['1=0']; // No access, and no allow overrides, so nothing to show.
            }
        } else {
            $allow = []; // Full access, allow overrides don't mean anything.
        }

        $index ??= $this->_tbl_key;
        $where = [];

        if (!empty($allow)) {
            $where[] = "{$index} IN (" . implode(',', $allow) . ')';
        }
        if (!empty($deny)) {
            $where[] = "{$index} NOT IN (" . implode(',', $deny) . ')';
        }

        return $where;
    }

    /**
     * Set allowed SQL on a query object
     * @param int $uid User id
     * @param DBQuery $query Query object
     * @param string|null $index field index
     * @param string|null $key table key/alias
     * @param string|null $alt_mod alternative module name
     */
    public function setAllowedSQL(
        int $uid,
        DBQuery $query,
        ?string $index = null,
        ?string $key = null,
        ?string $alt_mod = null
    ): void {
        global $AppUI;
        $perms = $AppUI->acl();
        $mod = $alt_mod ?? $this->_tbl;

        if ($uid === 0) {
            exit('FATAL ERROR<br />' . get_class($this) . '::getAllowedSQL failed');
        }

        $deny = $perms->getDeniedItems($mod, $uid);
        $allow = $perms->getAllowedItems($mod, $uid);

        // Make sure that we add the table otherwise dependencies break
        if ($index !== null) {
            $key ??= mb_substr($this->_tbl, 0, 2);
            $query->leftJoin($this->_tbl, $key, "{$key}.{$this->_tbl_key} = {$index}");
        }

        if (!$perms->checkModule($mod, 'view', $uid)) {
            if (empty($allow)) {
                // We need to ensure that we don't just break complex SQLs
                $prefix = $key ? "{$key}." : '';
                $query->addWhere("{$prefix}{$this->_tbl_key} = 0");
                return;
            }
        } else {
            $allow = []; // Full access, allow overrides don't mean anything.
        }

        $prefix = $key ? "{$key}." : '';

        if (!empty($allow)) {
            $query->addWhere("{$prefix}{$this->_tbl_key} IN (" . implode(',', $allow) . ')');
        }
        if (!empty($deny)) {
            $query->addWhere("{$prefix}{$this->_tbl_key} NOT IN (" . implode(',', $deny) . ')');
        }
    }

    /**
     * Decode HTML entities in object vars
     */
    public function htmlDecode(): void
    {
        foreach (get_object_vars($this) as $k => $v) {
            if (is_array($v) || is_object($v) || $v === null) {
                continue;
            }
            if (str_starts_with($k, '_')) { // internal field
                continue;
            }
            $this->$k = htmlspecialchars_decode((string) $v);
        }
    }

    /**
     * Utility function to return the current module name
     * It first tries to get the name based on the table name,
     * and infer the name from the class name. If neither of these
     * are appropriate, children should implement this function themselves
     * or set _module_directory after construction.
     */
    public function getModuleName(): string
    {
        // If we've already done this, or our sub-class has set this
        if (!empty($this->_module_directory)) {
            return $this->_module_directory;
        }

        // Now the guessing game begins
        $mods = new CModule();

        if (!empty($this->_permission_name)) {
            $mod_name = $mods->getModuleByName($this->_permission_name);
            if ($mod_name) {
                $this->_module_directory = $mod_name;
                return $mod_name;
            }
        }

        $class = get_class($this);
        // Class usually includes an initial C and is camel case
        $class = strtolower(substr($class, 1));
        $mod_name = $mods->getModuleByName($class);
        if ($mod_name) {
            $this->_module_directory = $mod_name;
            return $mod_name;
        }

        return 'unknown';
    }
}
