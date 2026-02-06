<?php
/**
 * DotProject Modern Database Wrapper
 * 
 * Provides a modern interface to database operations while maintaining
 * compatibility with the existing ADODB-based system.
 * 
 * @package DotProject\Core
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Core;

/**
 * Modern Database wrapper that encapsulates ADODB operations
 * with additional security features and a cleaner API.
 */
class Database
{
    private static ?Database $instance = null;
    
    /** @var mixed ADODB connection */
    private $connection;
    
    /** @var string Table prefix */
    private string $prefix;

    /**
     * Private constructor for singleton pattern
     */
    private function __construct()
    {
        global $db;
        $this->connection = $this->resolveConnection($db);
        // Use dPgetConfig if available (legacy), otherwise get from environment
        $prefix = $this->configValue('dbprefix', getenv('DB_PREFIX') ?: 'dotp_');
        $this->prefix = is_string($prefix) && $prefix !== '' ? $prefix : 'dotp_';
    }

    /**
     * Resolve a usable ADODB connection.
     */
    private function resolveConnection(mixed &$legacyConnection): mixed
    {
        if ($this->isValidConnection($legacyConnection)) {
            return $legacyConnection;
        }

        $fallback = $this->createFallbackConnection();
        if ($this->isValidConnection($fallback)) {
            $legacyConnection = $fallback;
            return $fallback;
        }

        throw new \RuntimeException('Database connection is not initialized');
    }

    /**
     * Check whether a connection object looks like ADODB.
     */
    private function isValidConnection(mixed $connection): bool
    {
        return is_object($connection) && method_exists($connection, 'Execute');
    }

    /**
     * Try to bootstrap a connection when legacy $db was not initialized.
     */
    private function createFallbackConnection(): mixed
    {
        $baseDir = defined('DP_BASE_DIR') ? DP_BASE_DIR : dirname(__DIR__, 2);
        $adodbPath = $baseDir . '/lib/adodb/adodb.inc.php';
        if (!file_exists($adodbPath)) {
            return null;
        }

        require_once $adodbPath;
        if (!function_exists('NewADOConnection')) {
            return null;
        }

        $dbType = (string) $this->configValue('dbtype', getenv('DB_TYPE') ?: 'mysqli');
        $dbUser = (string) $this->configValue('dbuser', getenv('DB_USER') ?: 'dotproject');
        $dbPass = (string) $this->configValue('dbpass', getenv('DB_PASS') ?: 'dotproject123');
        $dbPort = (string) (getenv('DB_PORT') ?: '');

        $hostCandidates = $this->buildHostCandidates($dbPort);
        $databaseCandidates = $this->buildDatabaseCandidates();

        foreach ($hostCandidates as $host) {
            foreach ($databaseCandidates as $database) {
                $connection = \NewADOConnection($dbType);
                if (@$connection->Connect($host, $dbUser, $dbPass, $database)) {
                    return $connection;
                }
            }
        }

        return null;
    }

    /**
     * Build host candidates for fallback DB connection.
     *
     * @return array<int, string>
     */
    private function buildHostCandidates(string $dbPort): array
    {
        $hosts = [];

        foreach ([
            getenv('DB_HOST') ?: null,
            $this->configValue('dbhost', null),
            'mariadb',
            '127.0.0.1',
            'localhost',
        ] as $host) {
            if (!is_string($host) || $host === '') {
                continue;
            }

            $hosts[] = $host;
            if ($dbPort !== '' && !str_contains($host, ':')) {
                $hosts[] = $host . ':' . $dbPort;
            }
        }

        return array_values(array_unique($hosts));
    }

    /**
     * Build database name candidates for fallback DB connection.
     *
     * @return array<int, string>
     */
    private function buildDatabaseCandidates(): array
    {
        $databases = [];

        foreach ([getenv('DB_NAME') ?: null, $this->configValue('dbname', null), 'dotproject'] as $name) {
            if (!is_string($name) || $name === '') {
                continue;
            }

            $databases[] = $name;
            if (str_ends_with($name, '_test')) {
                $databases[] = substr($name, 0, -5);
            }
        }

        return array_values(array_unique($databases));
    }

    /**
     * Read config from legacy dPgetConfig()/globals with environment fallback.
     */
    private function configValue(string $key, mixed $default = null): mixed
    {
        if (function_exists('dPgetConfig')) {
            return dPgetConfig($key, $default);
        }

        return $GLOBALS['dPconfig'][$key] ?? $default;
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get the underlying ADODB connection for legacy compatibility
     * 
     * @return mixed ADODB connection
     */
    public function getConnection(): mixed
    {
        return $this->connection;
    }

    /**
     * Get table prefix
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Get prefixed table name
     */
    public function table(string $name): string
    {
        if ($this->prefix !== '' && str_starts_with($name, $this->prefix)) {
            return $name;
        }

        return $this->prefix . $name;
    }

    /**
     * Execute a raw SQL query
     * 
     * @param string $sql SQL query
     * @return mixed Query result
     */
    public function query(string $sql): mixed
    {
        return $this->connection->Execute($sql);
    }

    /**
     * Execute a parameterized SQL query
     * 
     * @param string $sql SQL query with placeholders
     * @param array<int, mixed> $params Parameters to bind
     * @return mixed Query result
     */
    public function queryParams(string $sql, array $params): mixed
    {
        return $this->connection->Execute($sql, $params);
    }

    /**
     * Execute a statement (INSERT/UPDATE/DELETE) with optional parameters.
     *
     * @param string $sql SQL query with optional placeholders
     * @param array<int, mixed> $params Parameters to bind
     * @return mixed Query result
     */
    public function execute(string $sql, array $params = []): mixed
    {
        return empty($params)
            ? $this->connection->Execute($sql)
            : $this->connection->Execute($sql, $params);
    }

    /**
     * Execute a SELECT query and return all rows as an array
     * 
     * @param string $sql SQL query
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $result = empty($params)
            ? $this->connection->Execute($sql)
            : $this->connection->Execute($sql, $params);
        if (!$result) {
            return [];
        }
        
        $rows = [];
        while ($row = $result->FetchRow()) {
            $rows[] = $row;
        }
        $result->Close();
        
        return $rows;
    }

    /**
     * Execute a parameterized SELECT query and return all rows as an array
     * 
     * @param string $sql SQL query with placeholders
     * @param array<int, mixed> $params Parameters to bind
     * @return array<int, array<string, mixed>>
     */
    public function fetchAllParams(string $sql, array $params): array
    {
        $result = $this->connection->Execute($sql, $params);
        if (!$result) {
            return [];
        }

        $rows = [];
        while ($row = $result->FetchRow()) {
            $rows[] = $row;
        }
        $result->Close();

        return $rows;
    }

    /**
     * Execute a SELECT query and return a single row
     * 
     * @param string $sql SQL query
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $result = empty($params)
            ? $this->connection->Execute($sql)
            : $this->connection->Execute($sql, $params);
        if (!$result) {
            return null;
        }
        
        $row = $result->FetchRow();
        $result->Close();
        
        return $row ?: null;
    }

    /**
     * Execute a parameterized SELECT query and return a single row
     * 
     * @param string $sql SQL query with placeholders
     * @param array<int, mixed> $params Parameters to bind
     * @return array<string, mixed>|null
     */
    public function fetchOneParams(string $sql, array $params): ?array
    {
        $result = $this->connection->Execute($sql, $params);
        if (!$result) {
            return null;
        }

        $row = $result->FetchRow();
        $result->Close();

        return $row ?: null;
    }

    /**
     * Execute a SELECT query and return a single value
     * 
     * @param string $sql SQL query
     * @return mixed
     */
    public function fetchValue(string $sql, array $params = []): mixed
    {
        $row = $this->fetchOne($sql, $params);
        if ($row === null) {
            return null;
        }
        return reset($row);
    }

    /**
     * Execute a SELECT query and return a single column value
     *
     * @param string $sql SQL query with optional placeholders
     * @param array<int, mixed> $params Parameters to bind
     * @return mixed
     */
    public function fetchColumn(string $sql, array $params = []): mixed
    {
        return $this->fetchValue($sql, $params);
    }

    /**
     * Execute a parameterized SELECT query and return a single value
     * 
     * @param string $sql SQL query with placeholders
     * @param array<int, mixed> $params Parameters to bind
     * @return mixed
     */
    public function fetchValueParams(string $sql, array $params): mixed
    {
        $row = $this->fetchOneParams($sql, $params);
        if ($row === null) {
            return null;
        }
        return reset($row);
    }

    /**
     * Insert a row into a table
     * 
     * @param string $table Table name (without prefix)
     * @param array<string, mixed> $data Column => value pairs
     * @return int|false Insert ID or false on failure
     */
    public function insert(string $table, array $data): int|false
    {
        if (empty($data)) {
            return false;
        }

        $columns = array_keys($data);
        $values = array_map(fn($v) => $this->quote($v), array_values($data));
        
        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $this->table($table),
            implode('`, `', $columns),
            implode(', ', $values)
        );
        
        if ($this->connection->Execute($sql)) {
            return (int) $this->connection->Insert_ID();
        }
        
        return false;
    }

    /**
     * Update rows in a table
     * 
     * @param string $table Table name (without prefix)
     * @param array<string, mixed> $data Column => value pairs
     * @param string $where WHERE clause
     * @return bool Success
     */
    public function update(string $table, array $data, string $where): bool
    {
        if (empty($data) || empty($where)) {
            return false;
        }

        $sets = [];
        foreach ($data as $column => $value) {
            $sets[] = sprintf('`%s` = %s', $column, $this->quote($value));
        }
        
        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            $this->table($table),
            implode(', ', $sets),
            $where
        );
        
        return (bool) $this->connection->Execute($sql);
    }

    /**
     * Delete rows from a table
     * 
     * @param string $table Table name (without prefix)
     * @param string $where WHERE clause
     * @return bool Success
     */
    public function delete(string $table, string $where): bool
    {
        if (empty($where)) {
            return false; // Prevent accidental full table deletion
        }
        
        $sql = sprintf(
            'DELETE FROM `%s` WHERE %s',
            $this->table($table),
            $where
        );
        
        return (bool) $this->connection->Execute($sql);
    }

    /**
     * Quote a value for safe SQL insertion
     * 
     * @param mixed $value Value to quote
     * @return string Quoted value
     */
    public function quote(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        
        return "'" . $this->escape((string) $value) . "'";
    }

    /**
     * Escape a string for safe SQL insertion
     * 
     * @param string $value String to escape
     * @return string Escaped string
     */
    public function escape(string $value): string
    {
        return addslashes($value);
    }

    /**
     * Get last error message
     */
    public function getError(): string
    {
        return $this->connection->ErrorMsg() ?? '';
    }

    /**
     * Get last insert ID
     */
    public function lastInsertId(): int
    {
        return (int) $this->connection->Insert_ID();
    }

    /**
     * Get affected rows from last query
     */
    public function affectedRows(): int
    {
        if (method_exists($this->connection, 'Affected_Rows')) {
            return (int) $this->connection->Affected_Rows();
        }

        return 0;
    }

    /**
     * Backwards-compatible alias for affectedRows().
     */
    public function rowCount(): int
    {
        return $this->affectedRows();
    }

    /**
     * Begin a transaction
     */
    public function beginTransaction(): bool
    {
        return (bool) $this->connection->BeginTrans();
    }

    /**
     * Commit a transaction
     */
    public function commit(): bool
    {
        return (bool) $this->connection->CommitTrans();
    }

    /**
     * Rollback a transaction
     */
    public function rollback(): bool
    {
        return (bool) $this->connection->RollbackTrans();
    }
}
