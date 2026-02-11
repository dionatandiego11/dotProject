<?php
/**
 * DotProject Project Status History Service
 *
 * Registra e consulta trilha de mudancas de status de projetos.
 *
 * @package DotProject\Service
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Service;

use DotProject\Core\Database;
use DotProject\Core\Logger;
use DotProject\Core\TenantContext;

class ProjectStatusHistoryService
{
    private Database $db;
    private ?bool $historyTableAvailable = null;
    /** @var array<string, bool> */
    private array $columnPresenceCache = [];

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function recordStatusChange(
        int $projectId,
        int $fromStatus,
        int $toStatus,
        ?int $changedByUserId = null,
        string $source = 'system',
        ?string $note = null
    ): void {
        if ($projectId <= 0 || $fromStatus === $toStatus) {
            return;
        }

        if (!$this->isHistoryTableAvailable()) {
            return;
        }

        $source = $this->normalizeSource($source);
        $note = $note !== null ? trim($note) : null;
        if ($note !== null && $note !== '') {
            $note = mb_substr($note, 0, 255);
        } else {
            $note = null;
        }

        $insertData = [
            'history_project_id' => $projectId,
            'history_from_status' => $fromStatus,
            'history_to_status' => $toStatus,
            'history_changed_by_user_id' => ($changedByUserId !== null && $changedByUserId > 0) ? $changedByUserId : null,
            'history_source' => $source,
            'history_note' => $note,
        ];
        $tenantId = $this->getTenantId();
        if ($tenantId !== null && $this->hasTableColumn($this->db->table('project_status_history'), 'tenant_id')) {
            $insertData['tenant_id'] = $tenantId;
        }
        $inserted = $this->db->insert('project_status_history', $insertData);

        if ($inserted === false) {
            Logger::warning('Failed to persist project status history', [
                'project_id' => $projectId,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'changed_by_user_id' => $changedByUserId,
                'source' => $source,
            ]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getByProjectId(int $projectId, int $limit = 50): array
    {
        if ($projectId <= 0 || !$this->isHistoryTableAvailable()) {
            return [];
        }

        $limit = max(1, min(200, $limit));

        $sql = sprintf(
            "SELECT
                h.history_id,
                h.history_project_id,
                h.history_from_status,
                h.history_to_status,
                h.history_changed_by_user_id,
                h.history_source,
                h.history_note,
                h.history_created_at,
                u.user_username,
                c.contact_first_name,
                c.contact_last_name
             FROM `%s` h
             LEFT JOIN `%s` u ON u.user_id = h.history_changed_by_user_id%s
             LEFT JOIN `%s` c ON c.contact_id = u.user_contact
             WHERE h.history_project_id = ?%s
             ORDER BY h.history_created_at DESC, h.history_id DESC
             LIMIT %d",
            $this->db->table('project_status_history'),
            $this->db->table('users'),
            $this->tenantAndCondition($this->db->table('users'), 'u'),
            $this->db->table('contacts'),
            $this->tenantAndCondition($this->db->table('project_status_history'), 'h'),
            $limit
        );

        $rows = $this->db->fetchAll($sql, [$projectId]);
        $history = [];

        foreach ($rows as $row) {
            $firstName = trim((string) ($row['contact_first_name'] ?? ''));
            $lastName = trim((string) ($row['contact_last_name'] ?? ''));
            $fullName = trim($firstName . ' ' . $lastName);
            $username = trim((string) ($row['user_username'] ?? ''));

            $history[] = [
                'id' => (int) ($row['history_id'] ?? 0),
                'project_id' => (int) ($row['history_project_id'] ?? 0),
                'from_status' => (int) ($row['history_from_status'] ?? 0),
                'to_status' => (int) ($row['history_to_status'] ?? 0),
                'changed_by_user_id' => !empty($row['history_changed_by_user_id']) ? (int) $row['history_changed_by_user_id'] : null,
                'changed_by_name' => $fullName !== '' ? $fullName : ($username !== '' ? $username : null),
                'source' => (string) ($row['history_source'] ?? 'system'),
                'note' => $row['history_note'] ?? null,
                'changed_at' => $row['history_created_at'] ?? null,
            ];
        }

        return $history;
    }

    private function normalizeSource(string $source): string
    {
        $source = trim($source);
        if ($source === '') {
            return 'system';
        }

        $source = preg_replace('/[^a-zA-Z0-9._-]/', '_', $source) ?? 'system';
        $source = trim($source, '._-');
        if ($source === '') {
            return 'system';
        }

        return mb_substr($source, 0, 64);
    }

    private function isHistoryTableAvailable(): bool
    {
        if ($this->historyTableAvailable !== null) {
            return $this->historyTableAvailable;
        }

        try {
            $table = trim((string) $this->db->table('project_status_history'), '`');
            $exists = (int) ($this->db->fetchValue(
                "SELECT COUNT(*)
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name = ?",
                [$table]
            ) ?? 0);
            $this->historyTableAvailable = $exists > 0;
        } catch (\Throwable $e) {
            $this->historyTableAvailable = false;
        }

        return $this->historyTableAvailable;
    }

    private function tenantAndCondition(string $table, ?string $alias = null): string
    {
        $tenantId = $this->getTenantId();
        if ($tenantId === null || !$this->hasTableColumn($table, 'tenant_id')) {
            return '';
        }

        $column = $alias !== null && $alias !== ''
            ? $alias . '.tenant_id'
            : 'tenant_id';

        return " AND {$column} = {$tenantId}";
    }

    private function hasTableColumn(string $table, string $column): bool
    {
        $tableName = trim($table, '`');
        $cacheKey = $tableName . ':' . $column;
        if (array_key_exists($cacheKey, $this->columnPresenceCache)) {
            return $this->columnPresenceCache[$cacheKey];
        }

        $exists = (int) ($this->db->fetchValue(
            "SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND column_name = ?",
            [$tableName, $column]
        ) ?? 0);

        $this->columnPresenceCache[$cacheKey] = $exists > 0;
        return $this->columnPresenceCache[$cacheKey];
    }

    private function getTenantId(): ?int
    {
        if (!TenantContext::isEnabled()) {
            return null;
        }

        $tenantId = TenantContext::getTenantId();
        if ($tenantId === null || $tenantId <= 0) {
            return null;
        }

        return $tenantId;
    }
}
