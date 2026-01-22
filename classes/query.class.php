<?php
/**
 * Container for creating prefix-safe queries.
 *
 * @package dotProject
 * @license GPL version 2 or later
 * @copyright (c) 2003 Adam Donnison
 */

declare(strict_types=1);

if (!defined('DP_BASE_DIR')) {
	die('This file should not be called directly.');
}

require_once DP_BASE_DIR . '/lib/adodb/adodb.inc.php';

define('QUERY_STYLE_ASSOC', ADODB_FETCH_ASSOC);
define('QUERY_STYLE_NUM', ADODB_FETCH_NUM);
define('QUERY_STYLE_BOTH', ADODB_FETCH_BOTH);

/**
 * Container for creating prefix-safe queries.
 * Allows build up of a select statement by adding components one at a time.
 *
 * @author Adam Donnison <adam@saki.com.au>
 */
class DBQuery
{
	public ?array $query = null;
	public ?array $table_list = null;
	public ?array $where = null;
	public ?array $order_by = null;
	public ?array $group_by = null;
	public ?int $limit = null;
	public int $offset = -1;
	public ?array $join = null;
	public string $type = 'select';
	public ?array $update_list = null;
	public ?array $value_list = null;
	public string|array|null $create_table = null;
	public string|array|null $create_definition = null;
	public bool $include_count = false;

	protected string $_table_prefix;
	protected mixed $_query_id = null;
	protected ?int $_old_style = null;

	public function __construct(?string $prefix = null)
	{
		$this->_table_prefix = $prefix ?? dPgetConfig('dbprefix', '');
		$this->include_count = false;
		$this->clear();
	}

	public function clear(): void
	{
		global $ADODB_FETCH_MODE;

		if (!empty($this->_old_style)) {
			$ADODB_FETCH_MODE = $this->_old_style;
			$this->_old_style = null;
		}

		$this->type = 'select';
		$this->query = null;
		$this->table_list = null;
		$this->where = null;
		$this->order_by = null;
		$this->group_by = null;
		$this->limit = null;
		$this->offset = -1;
		$this->join = null;
		$this->value_list = null;
		$this->update_list = null;
		$this->create_table = null;
		$this->create_definition = null;

		if ($this->_query_id) {
			$this->_query_id->Close();
		}
		$this->_query_id = null;
	}

	public function clearQuery(): void
	{
		if ($this->_query_id) {
			$this->_query_id->Close();
		}
		$this->_query_id = null;
	}

	/**
	 * Add a hash item to an array.
	 */
	protected function addMap(string $varname, mixed $name, int|string|null $id): void
	{
		if (!isset($this->$varname)) {
			$this->$varname = [];
		}

		if ($id !== null) {
			$this->{$varname}[$id] = $name;
		} else {
			$this->{$varname}[] = $name;
		}
	}

	/**
	 * Adds a table to the query.
	 */
	public function addTable(string $name, ?string $id = null): void
	{
		$this->addMap('table_list', $name, $id);
	}

	/**
	 * Add a clause to an array.
	 */
	public function addClause(string $clause, mixed $value, bool $check_array = true): void
	{
		dprint(__FILE__, __LINE__, 8, "[INFO] Adding '" . print_r($value, true) . "' to '" . print_r($clause, true) . "' clause");

		if (!isset($this->$clause)) {
			$this->$clause = [];
		}

		if ($check_array && is_array($value)) {
			foreach ($value as $v) {
				$this->{$clause}[] = $v;
			}
		} else {
			$this->{$clause}[] = $value;
		}
	}

	/**
	 * Add the actual select part of the query.
	 */
	public function addQuery(string|array $query): void
	{
		$this->addClause('query', $query);
	}

	public function addInsert(
		string|array $field,
		string|array $value,
		bool $set = false,
		bool $func = false
	): void {
		if ($set) {
			$fields = is_array($field) ? $field : explode(',', $field);
			$values = is_array($value) ? $value : explode(',', (string) $value);

			for ($i = 0, $fc = count($fields); $i < $fc; $i++) {
				$this->addMap('value_list', $this->quote($values[$i]), $fields[$i]);
			}
		} elseif (!$func) {
			$this->addMap('value_list', $this->quote((string) $value), $field);
		} else {
			$this->addMap('value_list', $value, $field);
		}
		$this->type = 'insert';
	}

	/**
	 * @param array<int, string> $fields
	 * @param array<int, array<int, mixed>> $values
	 */
	public function addInsertMulti(array $fields, array $values): void
	{
		foreach ($fields as $k => $field) {
			$vals = [];
			foreach ($values as $value) {
				$vals[] = $this->quote($value[$k]);
			}
			$this->addMap('value_list', $vals, $field);
		}
		$this->type = 'insertMulti';
	}

	public function addReplace(
		string|array $field,
		string|array $value,
		bool $set = false,
		bool $func = false
	): void {
		$this->addInsert($field, $value, $set, $func);
		$this->type = 'replace';
	}

	public function addUpdate(string|array $field, string|array $value, bool $set = false): void
	{
		if ($set) {
			$fields = is_array($field) ? $field : explode(',', $field);
			$values = is_array($value) ? $value : explode(',', (string) $value);

			for ($i = 0, $fc = count($fields); $i < $fc; $i++) {
				$this->addMap('update_list', $values[$i], $fields[$i]);
			}
		} else {
			$this->addMap('update_list', $value, $field);
		}
		$this->type = 'update';
	}

	public function createTable(string $table): void
	{
		$this->type = 'createPermanent';
		$this->create_table = $table;
	}

	public function createTemp(string $table): void
	{
		$this->type = 'create';
		$this->create_table = $table;
	}

	public function dropTable(string|array $table): void
	{
		$this->type = 'drop';
		$this->create_table = $table;
	}

	public function dropTemp(string|array $table): void
	{
		$this->type = 'drop';
		$this->create_table = $table;
	}

	public function alterTable(string $table): void
	{
		$this->create_table = $table;
		$this->type = 'alter';
	}

	public function addField(string $name, string $type): void
	{
		if (!is_array($this->create_definition)) {
			$this->create_definition = [];
		}
		$this->create_definition[] = [
			'action' => 'ADD',
			'type' => '',
			'spec' => $name . ' ' . $type
		];
	}

	public function alterField(string $name, string $type): void
	{
		if (empty($this->create_definition) || !is_array($this->create_definition)) {
			$this->create_definition = [];
		}
		$this->create_definition[] = [
			'action' => 'CHANGE',
			'type' => '',
			'spec' => $name . ' ' . $name . ' ' . $type
		];
	}

	public function dropField(string $name): void
	{
		if (empty($this->create_definition) || !is_array($this->create_definition)) {
			$this->create_definition = [];
		}
		$this->create_definition[] = [
			'action' => 'DROP',
			'type' => '',
			'spec' => $name
		];
	}

	public function addIndex(string $name, string $type): void
	{
		if (empty($this->create_definition) || !is_array($this->create_definition)) {
			$this->create_definition = [];
		}
		$this->create_definition[] = [
			'action' => 'ADD',
			'type' => 'INDEX',
			'spec' => $name . ' ' . $type
		];
	}

	public function dropIndex(string $name): void
	{
		if (empty($this->create_definition) || !is_array($this->create_definition)) {
			$this->create_definition = [];
		}
		$this->create_definition[] = [
			'action' => 'DROP',
			'type' => 'INDEX',
			'spec' => $name
		];
	}

	public function dropPrimary(): void
	{
		if (empty($this->create_definition) || !is_array($this->create_definition)) {
			$this->create_definition = [];
		}
		$this->create_definition[] = [
			'action' => 'DROP',
			'type' => 'PRIMARY KEY',
			'spec' => ''
		];
	}

	public function createDefinition(string|array $def): void
	{
		$this->create_definition = $def;
	}

	public function setDelete(string $table): void
	{
		$this->type = 'delete';
		$this->addMap('table_list', $table, null);
	}

	/**
	 * Add where sub-clauses.
	 */
	public function addWhere(string $query): void
	{
		if (!empty($query)) {
			$this->addClause('where', $query);
		}
	}

	/**
	 * Add a join condition to the query.
	 */
	public function addJoin(
		string|DBQuery $table,
		string $alias,
		string|array $join,
		string $type = 'left'
	): void {
		$var = [
			'table' => $table,
			'alias' => $alias,
			'condition' => $join,
			'type' => $type
		];
		$this->addClause('join', $var, false);
	}

	public function leftJoin(string|DBQuery $table, string $alias, string|array $join): void
	{
		$this->addJoin($table, $alias, $join, 'left');
	}

	public function rightJoin(string|DBQuery $table, string $alias, string|array $join): void
	{
		$this->addJoin($table, $alias, $join, 'right');
	}

	public function innerJoin(string|DBQuery $table, string $alias, string|array $join): void
	{
		$this->addJoin($table, $alias, $join, 'inner');
	}

	/**
	 * Add an order by clause.
	 */
	public function addOrder(string|array $order): void
	{
		if (!empty($order)) {
			$this->addClause('order_by', $order);
		}
	}

	/**
	 * Add a group by clause.
	 */
	public function addGroup(string|array $group): void
	{
		if (!empty($group)) {
			$this->addClause('group_by', $group);
		}
	}

	/**
	 * Set a limit on the query.
	 */
	public function setLimit(int $limit = 10, int $start = -1): void
	{
		$this->limit = $limit;
		$this->offset = $start;
	}

	/**
	 * Set include count feature.
	 */
	public function includeCount(): void
	{
		$this->include_count = true;
	}

	/**
	 * Prepare a query for execution.
	 */
	public function prepare(bool $clear = false): string|false
	{
		$q = match ($this->type) {
			'select' => $this->prepareSelect(),
			'update' => $this->prepareUpdate(),
			'insert' => $this->prepareInsert(),
			'insertMulti' => $this->prepareInsertMulti(),
			'replace' => $this->prepareReplace(),
			'delete' => $this->prepareDelete(),
			'create' => $this->prepareCreateTemp(),
			'alter' => $this->prepareAlter(),
			'createPermanent' => $this->prepareCreatePermanent(),
			'drop' => $this->prepareDrop(),
			default => false
		};

		if ($clear) {
			$this->clear();
		}
		return $q;
	}

	protected function prepareCreateTemp(): string|false
	{
		$s = $this->prepareSelect();
		if ($s === false)
			return false;

		$q = 'CREATE TEMPORARY TABLE ' . $this->_table_prefix . $this->create_table;
		if (!empty($this->create_definition) && is_string($this->create_definition)) {
			$q .= ' ' . $this->create_definition;
		}
		$q .= ' ' . $s;
		return $q;
	}

	protected function prepareCreatePermanent(): string|false
	{
		$s = $this->prepareSelect();
		if ($s === false)
			return false;

		$q = 'CREATE TABLE ' . $this->_table_prefix . $this->create_table;
		if (!empty($this->create_definition) && is_string($this->create_definition)) {
			$q .= ' ' . $this->create_definition;
		}
		$q .= ' ' . $s;
		return $q;
	}

	protected function prepareDrop(): string
	{
		$q = 'DROP TABLE IF EXISTS ';
		if (is_array($this->create_table)) {
			$q .= $this->_table_prefix . implode(',' . $this->_table_prefix, $this->create_table);
		} else {
			$q .= $this->_table_prefix . $this->create_table;
		}
		return $q;
	}

	public function prepareSelect(): string|false
	{
		$q = 'SELECT ';
		if ($this->include_count) {
			$q .= 'SQL_CALC_FOUND_ROWS ';
		}

		if (isset($this->query)) {
			$q .= is_array($this->query) ? implode(',', $this->query) : $this->query;
		} else {
			$q .= '*';
		}

		$q .= ' FROM ';

		if (!isset($this->table_list)) {
			return false;
		}

		if (is_array($this->table_list)) {
			$q .= '(';
			$tables = [];
			foreach ($this->table_list as $table_id => $table) {
				$tableStr = '`' . $this->_table_prefix . $table . '`';
				if (!is_numeric($table_id)) {
					$tableStr .= " as $table_id";
				}
				$tables[] = $tableStr;
			}
			$q .= implode(',', $tables) . ')';
		} else {
			$q .= '`' . $this->_table_prefix . $this->table_list . '`';
		}

		$q .= $this->make_join($this->join);
		$q .= $this->make_where_clause($this->where);
		$q .= $this->make_group_clause($this->group_by);
		$q .= $this->make_order_clause($this->order_by);

		return $q;
	}

	public function prepareUpdate(): string|false
	{
		$q = 'UPDATE ';

		if (!isset($this->table_list)) {
			return false;
		}

		if (is_array($this->table_list)) {
			$tables = [];
			foreach ($this->table_list as $table_id => $table) {
				$tableStr = '`' . $this->_table_prefix . $table . '`';
				if (!is_numeric($table_id)) {
					$tableStr .= " as $table_id";
				}
				$tables[] = $tableStr;
			}
			$q .= implode(',', $tables);
		} else {
			$q .= '`' . $this->_table_prefix . $this->table_list . '`';
		}

		$q .= $this->make_join($this->join);
		$q .= ' SET ';

		$sets = [];
		foreach ($this->update_list as $field => $value) {
			$sets[] = "`$field` = " . $this->quote($value);
		}
		$q .= implode(', ', $sets);
		$q .= $this->make_where_clause($this->where);

		return $q;
	}

	public function prepareInsert(): string|false
	{
		$q = 'INSERT INTO ';

		if (!isset($this->table_list)) {
			return false;
		}

		$table = is_array($this->table_list) ? current($this->table_list) : $this->table_list;
		$q .= '`' . $this->_table_prefix . $table . '`';

		$fieldlist = [];
		$valuelist = [];

		foreach ($this->value_list as $field => $value) {
			$fieldlist[] = '`' . trim($field) . '`';
			$valuelist[] = $value;
		}

		$q .= '(' . implode(',', $fieldlist) . ') values (' . implode(',', $valuelist) . ')';
		return $q;
	}

	public function prepareInsertMulti(): string|false
	{
		$q = 'INSERT INTO ';

		if (!isset($this->table_list)) {
			return false;
		}

		$table = is_array($this->table_list) ? current($this->table_list) : $this->table_list;
		$q .= '`' . $this->_table_prefix . $table . '`';

		$fields = array_keys($this->value_list);
		$values = array_values($this->value_list);

		$fieldlist = [];
		foreach ($fields as $field) {
			$fieldlist[] = '`' . trim($field) . '`';
		}

		$inverted_values = [];
		foreach ($values as $k => $value) {
			foreach ($value as $ix => $data) {
				$inverted_values[$ix][$k] = $data;
			}
		}

		$valuelist = [];
		foreach ($inverted_values as $val) {
			$valuelist[] = '(' . implode(',', $val) . ')';
		}

		$q .= '(' . implode(',', $fieldlist) . ') values ' . implode(',', $valuelist);
		return $q;
	}

	public function prepareReplace(): string|false
	{
		$q = 'REPLACE INTO ';

		if (!isset($this->table_list)) {
			return false;
		}

		$table = is_array($this->table_list) ? current($this->table_list) : $this->table_list;
		$q .= '`' . $this->_table_prefix . $table . '`';

		$fieldlist = [];
		$valuelist = [];

		foreach ($this->value_list as $field => $value) {
			$fieldlist[] = '`' . trim($field) . '`';
			$valuelist[] = $value;
		}

		$q .= '(' . implode(',', $fieldlist) . ') values (' . implode(',', $valuelist) . ')';
		return $q;
	}

	public function prepareDelete(): string|false
	{
		$q = 'DELETE FROM ';

		if (!isset($this->table_list)) {
			return false;
		}

		$table = is_array($this->table_list) ? current($this->table_list) : $this->table_list;
		$q .= '`' . $this->_table_prefix . $table . '`';
		$q .= $this->make_where_clause($this->where);

		return $q;
	}

	public function prepareAlter(): string
	{
		$q = 'ALTER TABLE `' . $this->_table_prefix . $this->create_table . '` ';

		if (isset($this->create_definition)) {
			$alters = [];
			if (is_array($this->create_definition)) {
				foreach ($this->create_definition as $def) {
					$alters[] = $def['action'] . ' ' . $def['type'] . ' ' . $def['spec'];
				}
			} else {
				$alters[] = 'ADD ' . $this->create_definition;
			}
			$q .= implode(', ', $alters);
		}

		return $q;
	}

	/**
	 * Execute the query and return a handle.
	 */
	public function exec(int $style = ADODB_FETCH_BOTH, bool $debug = false): mixed
	{
		global $ADODB_FETCH_MODE;
		$db = \DotProject\Core\Database::getInstance()->getConnection();

		if (empty($this->_old_style)) {
			$this->_old_style = $ADODB_FETCH_MODE;
		}

		$ADODB_FETCH_MODE = $style;
		$this->clearQuery();

		$q = $this->prepare();
		if (!$q) {
			return false;
		}

		dprint(__FILE__, __LINE__, 7, "executing query(" . $q . ")");

		if ($debug) {
			$qid = $db->Execute('EXPLAIN ' . $q);
			if ($qid) {
				$res = [];
				while ($row = $this->fetchRow()) {
					$res[] = $row;
				}
				dprint(__FILE__, __LINE__, 2, "QUERY DEBUG: " . print_r($res, true));
				$qid->Close();
			}
		}

		$this->_query_id = isset($this->limit)
			? $db->SelectLimit($q, $this->limit, $this->offset)
			: $db->Execute($q);

		if (empty($this->_query_id) || $this->_query_id === false) {
			$error = $db->ErrorMsg();
			dprint(__FILE__, __LINE__, 2, "query failed(" . $q . "); error: " . ($error ?? "[unknown]"));
			return $this->_query_id;
		}

		dprint(__FILE__, __LINE__, 11, "_query_id is now: " . print_r($this->_query_id, true));
		return $this->_query_id;
	}

	public function fetchRow(): array|false|null
	{
		if (empty($this->_query_id)) {
			return false;
		}
		return $this->_query_id->FetchRow();
	}

	/**
	 * @return array<int, array<string, mixed>>|false
	 */
	public function loadList(?int $maxrows = null): array|false
	{
		global $AppUI;
		$db = \DotProject\Core\Database::getInstance()->getConnection();

		if (empty($this->exec(ADODB_FETCH_ASSOC))) {
			$AppUI->setMsg(__FUNCTION__ . ": " . $db->ErrorMsg(), UI_MSG_ERROR);
			$this->clear();
			return false;
		}

		$list = [];
		$cnt = 0;

		while ($hash = $this->fetchRow()) {
			$list[] = $hash;
			if ($maxrows !== null && ++$cnt >= $maxrows) {
				break;
			}
		}

		$this->clear();
		return $list;
	}

	/**
	 * @return array<int|string, mixed>|null
	 */
	public function loadHashList(?string $index = null): ?array
	{
		global $AppUI;
		$db = \DotProject\Core\Database::getInstance()->getConnection();

		if (empty($this->exec(ADODB_FETCH_ASSOC))) {
			dprint(__FILE__, __LINE__, 1, "[ERROR]: " . __FUNCTION__ . " couldn't fetch hash list; error was " . $db->ErrorMsg());
			$AppUI->setMsg(__FUNCTION__ . ": " . $db->ErrorMsg(), UI_MSG_ERROR);
			return null;
		}

		$hashlist = [];
		$keys = null;

		while ($hash = $this->fetchRow()) {
			if ($index) {
				$hashlist[$hash[$index]] = $hash;
			} else {
				$keys ??= array_keys($hash);
				$hashlist[$hash[$keys[0]]] = $hash[$keys[1]];
			}
		}

		$this->clear();
		return $hashlist;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function loadHash(): ?array
	{
		global $AppUI;
		$db = \DotProject\Core\Database::getInstance()->getConnection();

		if (empty($this->exec(ADODB_FETCH_ASSOC))) {
			dprint(__FILE__, __LINE__, 1, "[ERROR]: " . __FUNCTION__ . " couldn't fetch hash; error was " . $db->ErrorMsg());
			$AppUI->setMsg(__FUNCTION__ . ": " . $db->ErrorMsg(), UI_MSG_ERROR);
			return null;
		}

		$hash = $this->fetchRow();
		$this->clear();
		return $hash ?: null;
	}

	/**
	 * @return array<int|string, array>|null
	 */
	public function loadArrayList(int $index = 0): ?array
	{
		global $AppUI;
		$db = \DotProject\Core\Database::getInstance()->getConnection();

		if (empty($this->exec(ADODB_FETCH_NUM))) {
			dprint(__FILE__, __LINE__, 1, "[ERROR]: " . __FUNCTION__ . " couldn't fetch array list; error was " . $db->ErrorMsg());
			$AppUI->setMsg(__FUNCTION__ . ": " . $db->ErrorMsg(), UI_MSG_ERROR);
			return null;
		}

		$hashlist = [];
		while ($hash = $this->fetchRow()) {
			$hashlist[$hash[$index]] = $hash;
		}

		$this->clear();
		return $hashlist;
	}

	/**
	 * @return array<int, mixed>|null
	 */
	public function loadColumn(): ?array
	{
		global $db, $AppUI;

		if (empty($this->exec(ADODB_FETCH_NUM))) {
			dprint(__FILE__, __LINE__, 1, "[ERROR]: " . __FUNCTION__ . " couldn't fetch column; error was " . $db->ErrorMsg());
			$AppUI->setMsg(__FUNCTION__ . ": " . $db->ErrorMsg(), UI_MSG_ERROR);
			return null;
		}

		$result = [];
		while ($row = $this->fetchRow()) {
			$result[] = $row[0];
		}

		$this->clear();
		return $result;
	}

	public function loadObject(object &$object, bool $bindAll = false, bool $strip = true): bool
	{
		global $db, $AppUI;

		if (empty($this->exec(ADODB_FETCH_NUM))) {
			dprint(__FILE__, __LINE__, 1, "[ERROR]: " . __FUNCTION__ . " couldn't fetch OBJECT; error was " . $db->ErrorMsg());
			$AppUI->setMsg(__FUNCTION__ . ": " . $db->ErrorMsg(), UI_MSG_ERROR);
			return false;
		}

		$hash = $this->fetchRow();
		$this->clear();

		if (empty($hash)) {
			return false;
		}

		$this->bindHashToObject($hash, $object, null, $strip, $bindAll);
		return true;
	}

	/**
	 * Using an XML string, build or update a table.
	 */
	public function execXML(string $xml, string $mode = 'REPLACE'): bool
	{
		global $db, $AppUI;

		include_once DP_BASE_DIR . '/lib/adodb/adodb-xmlschema.inc.php';

		$schema = new adoSchema($db);
		$schema->setUpgradeMode($mode);

		if (isset($this->_table_prefix) && $this->_table_prefix) {
			$schema->setPrefix($this->_table_prefix, false);
		}

		$schema->ContinueOnError(true);
		$sql = $schema->ParseSchemaString($xml);

		if ($sql === false) {
			$AppUI->setMsg([__FUNCTION__ . ': Error in XML Schema', 'Error', $db->ErrorMsg()], UI_MSG_ERROR);
			return false;
		}

		return $schema->ExecuteSchema($sql, true) ? true : false;
	}

	/**
	 * Load a single column result from a single row.
	 */
	public function loadResult(): mixed
	{
		global $AppUI, $db;

		$result = false;

		if (!$this->exec(ADODB_FETCH_NUM)) {
			$AppUI->setMsg(__FUNCTION__ . ": " . $db->ErrorMsg(), UI_MSG_ERROR);
		} elseif ($data = $this->fetchRow()) {
			$result = $data[0];
		}

		$this->clear();
		return $result;
	}

	/**
	 * Create a where clause based upon supplied field.
	 */
	public function make_where_clause(?array $where_clause): string
	{
		if (!isset($where_clause) || empty($where_clause)) {
			return '';
		}

		if (is_array($where_clause) && count($where_clause)) {
			return ' WHERE ' . implode(' AND ', $where_clause);
		}

		return '';
	}

	/**
	 * Create an order by clause.
	 */
	public function make_order_clause(?array $order_clause): string
	{
		if (empty($order_clause)) {
			return '';
		}

		if (is_array($order_clause)) {
			return ' ORDER BY ' . implode(',', $order_clause);
		}

		return '';
	}

	/**
	 * Create a group by clause.
	 */
	public function make_group_clause(?array $group_clause): string
	{
		if (!isset($group_clause) || empty($group_clause)) {
			return '';
		}

		if (is_array($group_clause) && count($group_clause)) {
			return ' GROUP BY ' . implode(',', $group_clause);
		}

		return '';
	}

	/**
	 * Create join clause.
	 */
	public function make_join(?array $join_clause): string
	{
		if (!isset($join_clause)) {
			return '';
		}

		$result = '';

		if (is_array($join_clause)) {
			foreach ($join_clause as $join) {
				$result .= ' ' . mb_strtoupper($join['type']) . ' JOIN ';

				if (is_object($join['table']) && $join['table'] instanceof DBQuery) {
					$result .= '(' . $join['table']->prepare() . ')';
				} else {
					$result .= '`' . $this->_table_prefix . $join['table'] . '`';
				}

				if ($join['alias']) {
					$result .= ' AS ' . $join['alias'];
				}

				$result .= is_array($join['condition'])
					? ' USING (' . implode(',', $join['condition']) . ')'
					: ' ON ' . $join['condition'];
			}
		}

		return $result;
	}

	public function foundRows(): int|false
	{
		global $db;

		if (!$this->include_count) {
			return false;
		}

		$qid = $db->Execute('SELECT FOUND_ROWS() as rc');
		if ($qid) {
			$data = $qid->FetchRow();
			return (int) ($data['rc'] ?? $data[0] ?? 0);
		}

		return false;
	}

	public function quote(mixed $string): string
	{
		global $db;
		return $db->qstr((string) $string, false);
	}

	/**
	 * Sanitise input to prevent SQL injection.
	 */
	public function sanitise(string $string): string
	{
		return str_replace(["'", '"', ')', '(', ';', '--'], '', $string);
	}

	public function quote_sanitised(string $string): string
	{
		return $this->quote($this->sanitise($string));
	}

	/**
	 * Bind hash to object (helper method).
	 */
	protected function bindHashToObject(
		array $hash,
		object &$object,
		?string $prefix = null,
		bool $strip = true,
		bool $bindAll = false
	): void {
		bindHashToObject($hash, $object, $prefix, $strip, $bindAll);
	}
}
