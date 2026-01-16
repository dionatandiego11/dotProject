<?php
/**
 * Generic database connection and query functions
 *
 * @package dotProject
 * @license GPL version 2 or later
 */

declare(strict_types=1);

if (!defined('DP_BASE_DIR')) {
	die('You should not access this file directly.');
}

require_once DP_BASE_DIR . '/includes/db_adodb.php';

// Make the connection to the database
db_connect(
	dPgetConfig('dbhost'),
	dPgetConfig('dbname'),
	dPgetConfig('dbuser'),
	dPgetConfig('dbpass'),
	dPgetConfig('dbpersist')
);

// Ensure MySQL behaves correctly
$db->Execute("SET sql_mode := ''");

// Load system configuration from database
$sql = 'SELECT config_name, config_value, config_type FROM ' . dPgetConfig('dbprefix', '') . 'config';
$rs = $db->Execute($sql);

if ($rs) {
	$rsArr = $rs->GetArray();
	foreach ($rsArr as $c) {
		if ($c['config_type'] === 'checkbox') {
			$c['config_value'] = $c['config_value'] === 'true';
		}
		$dPconfig[$c['config_name']] = $c['config_value'];
	}
}

/**
 * Load the first field of the first row returned by the query.
 */
function db_loadResult(string $sql): mixed
{
	$cur = db_exec($sql);
	if (!$cur) {
		exit(db_error());
	}
	$ret = null;
	if ($row = db_fetch_row($cur)) {
		$ret = $row[0];
	}
	db_free_result($cur);
	return $ret;
}

/**
 * Load the first row of a query into an object.
 */
function db_loadObject(string $sql, ?object &$object, bool $bindAll = false, bool $strip = true): bool
{
	if (!empty($object)) {
		$hash = [];
		if (empty(db_loadHash($sql, $hash))) {
			return false;
		}
		bindHashToObject($hash, $object, null, $strip, $bindAll);
		return true;
	}

	$cur = db_exec($sql);
	if (empty($cur)) {
		exit(db_error());
	}
	$object = db_fetch_object($cur);
	if (!empty($object)) {
		db_free_result($cur);
	} else {
		$object = null;
	}
	return !empty($object);
}

/**
 * Return a result row as an associative array.
 *
 * @param array<string, mixed> $hash
 */
function db_loadHash(string $sql, array &$hash): bool
{
	$cur = db_exec($sql);
	if (!$cur) {
		exit(db_error());
	}
	$hash = db_fetch_assoc($cur);
	db_free_result($cur);
	return !empty($hash);
}

/**
 * Load hash list from query.
 *
 * @return array<int|string, mixed>
 */
function db_loadHashList(string $sql, string $index = ''): array
{
	$cur = db_exec($sql);
	if (!$cur) {
		exit(db_error());
	}
	$hashlist = [];
	while ($hash = db_fetch_array($cur)) {
		$hashlist[$hash[$index ?: 0]] = $index ? $hash : $hash[1];
	}
	db_free_result($cur);
	return $hashlist;
}

/**
 * Load list of rows from query.
 *
 * @return array<int, array<string, mixed>>|false
 */
function db_loadList(string $sql, ?int $maxrows = null): array|false
{
	global $AppUI;

	$cur = db_exec($sql);
	if (!$cur) {
		$AppUI->setMsg(db_error(), UI_MSG_ERROR);
		return false;
	}

	$list = [];
	$cnt = 0;
	while ($hash = db_fetch_assoc($cur)) {
		$list[] = $hash;
		if ($maxrows !== null && ++$cnt >= $maxrows) {
			break;
		}
	}
	db_free_result($cur);
	return $list;
}

/**
 * Load a single column from query.
 *
 * @return array<int, mixed>|false
 */
function db_loadColumn(string $sql, ?int $maxrows = null): array|false
{
	global $AppUI;

	$cur = db_exec($sql);
	if (!$cur) {
		$AppUI->setMsg(db_error(), UI_MSG_ERROR);
		return false;
	}

	$list = [];
	$cnt = 0;
	$row_index = null;

	while ($row = db_fetch_row($cur)) {
		if ($row_index === null) {
			if (isset($row[0])) {
				$row_index = 0;
			} else {
				$row_indices = array_keys($row);
				$row_index = $row_indices[0];
			}
		}
		$list[] = $row[$row_index];
		if ($maxrows !== null && ++$cnt >= $maxrows) {
			break;
		}
	}
	db_free_result($cur);
	return $list;
}

/**
 * Return an array of objects from a SQL SELECT query.
 *
 * @return array<int, object>
 */
function db_loadObjectList(string $sql, object $object, ?int $maxrows = null): array
{
	$cur = db_exec($sql);
	if (!$cur) {
		die('db_loadObjectList : ' . db_error());
	}

	$list = [];
	$cnt = 0;
	$row_index = null;

	while ($row = db_fetch_array($cur)) {
		if ($row_index === null) {
			$row_index = isset($row[0]) ? 0 : array_keys($row)[0];
		}
		$object->load($row[$row_index]);
		$list[] = $object;
		if ($maxrows !== null && ++$cnt >= $maxrows) {
			break;
		}
	}
	db_free_result($cur);
	return $list;
}

/**
 * Insert an array into a database table.
 *
 * @param array<string, mixed> $hash
 */
function db_insertArray(string $table, array &$hash, bool $verbose = false): bool
{
	$dbprefix = dPgetConfig('dbprefix', '');
	$tableName = ($dbprefix !== '' && !str_contains($table, $dbprefix))
		? $dbprefix . $table
		: $table;

	$fields = [];
	$values = [];

	foreach ($hash as $k => $v) {
		if (is_array($v) || is_object($v) || $v === null) {
			continue;
		}
		$fields[] = $k;
		$values[] = "'" . db_escape((string) $v) . "'";
	}

	$sql = sprintf(
		"INSERT INTO `%s` (%s) VALUES (%s)",
		$tableName,
		implode(',', $fields),
		implode(',', $values)
	);

	if ($verbose) {
		print "$sql<br />\n";
	}

	if (!db_exec($sql)) {
		return false;
	}
	db_insert_id();
	return true;
}

/**
 * Update an array in a database table.
 *
 * @param array<string, mixed> $hash
 */
function db_updateArray(string $table, array &$hash, string $keyName, bool $verbose = false): mixed
{
	$dbprefix = dPgetConfig('dbprefix', '');
	$tableName = ($dbprefix !== '' && !str_contains($table, $dbprefix))
		? $dbprefix . $table
		: $table;

	$tmp = [];
	$where = '';

	foreach ($hash as $k => $v) {
		if (is_array($v) || is_object($v) || str_starts_with($k, '_')) {
			continue;
		}

		if ($k === $keyName) {
			$where = "$keyName='" . db_escape((string) $v) . "'";
			continue;
		}
		$val = $v === '' ? 'NULL' : "'" . db_escape((string) $v) . "'";
		$tmp[] = "$k=$val";
	}

	$sql = sprintf("UPDATE `%s` SET %s WHERE %s", $tableName, implode(',', $tmp), $where);

	if ($verbose) {
		print "$sql<br />\n";
	}

	return db_exec($sql);
}

/**
 * Delete a row from a database table.
 */
function db_delete(string $table, string $keyName, string|int $keyValue): mixed
{
	$dbprefix = dPgetConfig('dbprefix', '');
	$tableName = ($dbprefix !== '' && !str_contains($table, $dbprefix))
		? $dbprefix . $table
		: $table;

	$keyName = db_escape($keyName);
	$keyValue = db_escape((string) $keyValue);
	$sql = "DELETE FROM $tableName WHERE $keyName='$keyValue'";

	return db_exec($sql);
}

/**
 * Insert an object into a database table.
 */
function db_insertObject(string $table, object &$object, ?string $keyName = null, bool $verbose = false): bool
{
	$dbprefix = dPgetConfig('dbprefix', '');
	$tableName = ($dbprefix !== '' && !str_contains($table, $dbprefix))
		? $dbprefix . $table
		: $table;

	$fields = [];
	$values = [];

	foreach (get_object_vars($object) as $k => $v) {
		if (is_array($v) || is_object($v) || $v === null) {
			continue;
		}
		if (str_starts_with($k, '_')) {
			continue;
		}
		$fields[] = $k;
		$values[] = "'" . db_escape((string) $v) . "'";
	}

	$sql = sprintf(
		"INSERT INTO `%s` (%s) VALUES (%s)",
		$tableName,
		implode(',', $fields),
		implode(',', $values)
	);

	if ($verbose) {
		print "$sql<br />\n";
	}

	if (!db_exec($sql)) {
		return false;
	}

	$id = db_insert_id();
	if ($verbose) {
		print "id=[$id]<br />\n";
	}

	if ($keyName && $id) {
		$object->$keyName = $id;
	}
	return true;
}

/**
 * Update an object in a database table.
 */
function db_updateObject(
	string $table,
	object &$object,
	string $keyName,
	bool $updateNulls = true,
	?string $descriptionField = null
): bool {
	global $AppUI;
	$perms = $AppUI->acl();

	$dbprefix = dPgetConfig('dbprefix', '');
	$tableName = ($dbprefix !== '' && !str_contains($table, $dbprefix))
		? $dbprefix . $table
		: $table;

	$obj_vars_arr = get_object_vars($object);
	$tmp = [];
	$where = '';

	foreach ($obj_vars_arr as $k => $v) {
		if (is_array($v) || is_object($v) || str_starts_with($k, '_')) {
			continue;
		}
		if ($k === $keyName) {
			$where = "$keyName='" . db_escape((string) $v) . "'";
			continue;
		}
		if ($v === null && !$updateNulls) {
			continue;
		}
		$val = $v === '' ? "''" : "'" . db_escape((string) $v) . "'";
		$tmp[] = "$k=$val";
	}

	if (count($tmp)) {
		$sql = sprintf("UPDATE `%s` SET %s WHERE %s", $tableName, implode(',', $tmp), $where);
		$retval = db_exec($sql);

		if ($retval) {
			$perm_item_id = $perms->get_object_id($table, $obj_vars_arr[$keyName], 'axo');
			if ($perm_item_id) {
				if ($descriptionField) {
					$keyDesc = $descriptionField;
				} else {
					$tableToQuery = ($dbprefix !== '' && !str_contains($table, $dbprefix))
						? $table
						: str_replace($dbprefix, '', $table);
					$keyDesc = db_loadResult(
						'SELECT permissions_item_label FROM ' . $dbprefix . "modules WHERE permissions_item_table = '" . $tableToQuery . "'"
					);
				}

				if ($keyDesc) {
					$perms->edit_object(
						$perm_item_id,
						$table,
						$obj_vars_arr[$keyDesc],
						$obj_vars_arr[$keyName],
						0,
						0,
						'axo'
					);
				}
			}
		}
	} else {
		$retval = true;
	}

	return (bool) $retval;
}

/**
 * Convert a date string to timestamp.
 */
function db_dateConvert(string $src, int &$dest, string $srcFmt): bool
{
	$result = strtotime($src);
	$dest = $result ?: 0;
	return $result !== false && $result !== 0;
}

/**
 * Format a timestamp or CDate object as datetime string.
 */
function db_datetime(mixed $timestamp = null): ?string
{
	if (!$timestamp) {
		return null;
	}

	if (is_object($timestamp) && method_exists($timestamp, 'toString')) {
		return $timestamp->toString('%Y-%m-%d %H:%M:%S');
	}

	return date('Y-m-d H:i:s', (int) $timestamp);
}

/**
 * Convert datetime to locale format.
 */
function db_dateTime2locale(string $dateTime, string $format): ?string
{
	$result = null;
	if (intval($dateTime)) {
		$date = new CDate($dateTime);
		$result = $date->format($format);
	}
	return $result;
}

/**
 * Copy the hash array content into the object as properties.
 *
 * @param array<string, mixed> $hash
 */
function bindHashToObject(
	array $hash,
	object &$obj,
	?string $prefix = null,
	bool $checkSlashes = true,
	bool $bindAll = false
): void {
	if (!is_array($hash)) {
		die('bindHashToObject : hash expected');
	}
	if (!is_object($obj)) {
		die('bindHashToObject : object expected');
	}

	// Validate all hash values are non-objects
	foreach ($hash as $k => $v) {
		if (is_object($v)) {
			die('bindHashToObject : non-object expected for hash value with key ' . $k);
		}
	}

	// Magic quotes were removed in PHP 5.4, so checkSlashes has no effect now
	if ($bindAll) {
		foreach ($hash as $k => $v) {
			$obj->$k = $v;
		}
	} elseif ($prefix) {
		foreach (get_object_vars($obj) as $k => $v) {
			if (isset($hash[$prefix . $k])) {
				$obj->$k = $hash[$prefix . $k];
			}
		}
	} else {
		foreach (get_object_vars($obj) as $k => $v) {
			if (isset($hash[$k])) {
				$obj->$k = $hash[$k];
			}
		}
	}
}
