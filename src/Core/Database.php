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
        $this->connection = $db;
        $this->prefix = dPgetConfig('dbprefix', '');
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
     * Execute a SELECT query and return all rows as an array
     * 
     * @param string $sql SQL query
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(string $sql): array
    {
        $result = $this->connection->Execute($sql);
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
    public function fetchOne(string $sql): ?array
    {
        $result = $this->connection->Execute($sql);
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
    public function fetchValue(string $sql): mixed
    {
        $row = $this->fetchOne($sql);
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
