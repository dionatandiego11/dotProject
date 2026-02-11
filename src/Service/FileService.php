<?php
/**
 * File Service
 * 
 * Serviço para gerenciamento de arquivos anexados.
 * 
 * @package DotProject\Service
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Service;

use DotProject\Core\Database;
use DotProject\Core\Cache;
use DotProject\Core\Logger;
use DotProject\Core\TenantContext;

/**
 * Serviço de Arquivos
 */
class FileService
{
    private Database $db;
    private AuthorizationService $auth;
    private string $uploadDir;
    /** @var array<string, bool> */
    private array $columnPresenceCache = [];
    
    public function __construct(
        ?Database $db = null,
        ?AuthorizationService $auth = null
    ) {
        $this->db = $db ?? Database::getInstance();
        $this->auth = $auth ?? AuthorizationService::getInstance();
        $this->uploadDir = __DIR__ . '/../../files/task_attachments/';
        
        // Cria diretório se não existir
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }
    
    /**
     * Verifica se tarefa existe
     */
    public function taskExists(int $taskId): bool
    {
        $sql = sprintf(
            "SELECT COUNT(*) FROM `dotp_tasks` WHERE task_id = %d%s",
            $taskId,
            $this->tenantAndCondition('dotp_tasks')
        );
        return (int) $this->db->fetchValue($sql) > 0;
    }
    
    /**
     * Verifica se usuário pode acessar a tarefa
     */
    public function canAccessTask(int $taskId, int $userId): bool
    {
        return $this->auth->canAccessTask($taskId, 'view', $userId);
    }
    
    /**
     * Verifica se usuário pode deletar arquivo
     */
    public function canDeleteFile(int $fileId, int $userId): bool
    {
        $taskTenant = $this->tenantAndCondition('dotp_tasks', 't');
        $fileTenant = $this->tenantAndCondition('dotp_task_files', 'f');
        $sql = sprintf(
            "SELECT f.file_task_id, f.file_uploaded_by
             FROM `dotp_task_files` f
             LEFT JOIN `dotp_tasks` t ON t.task_id = f.file_task_id
             WHERE f.file_id = %d%s%s",
            $fileId
            ,
            $fileTenant,
            $taskTenant
        );
        $file = $this->db->fetchOne($sql);
        
        if (!$file) {
            return false;
        }
        
        // Admin pode deletar qualquer arquivo
        if ($this->auth->isAdmin($userId)) {
            return true;
        }
        
        // Quem fez upload pode deletar
        if ($file['file_uploaded_by'] == $userId) {
            return true;
        }
        
        // Quem tem permissão de edit na tarefa pode deletar
        return $this->auth->canAccessTask($file['file_task_id'], 'edit', $userId);
    }
    
    /**
     * Realiza upload de arquivo
     */
    public function upload(array $file, int $taskId, int $userId): array
    {
        $originalName = $file['name'];
        $tmpPath = $file['tmp_name'];
        $size = $file['size'];
        $mimeType = $file['type'];
        
        // Gera nome único
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $uniqueName = bin2hex(random_bytes(16)) . '.' . $extension;
        
        // Organiza por data (ano/mês)
        $dateDir = date('Y/m');
        $targetDir = $this->uploadDir . $dateDir . '/';
        
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        
        $targetPath = $targetDir . $uniqueName;
        
        // Move arquivo
        if (!move_uploaded_file($tmpPath, $targetPath)) {
            throw new \RuntimeException('Falha ao mover arquivo');
        }
        
        // Salva no banco
        $insertData = [
            'file_task_id' => $taskId,
            'file_name' => $originalName,
            'file_path' => $dateDir . '/' . $uniqueName,
            'file_size' => $size,
            'file_mime_type' => $mimeType,
            'file_uploaded_by' => $userId,
        ];
        $tenantId = $this->getTenantId();
        if ($tenantId !== null && $this->hasTableColumn('dotp_task_files', 'tenant_id')) {
            $insertData['tenant_id'] = $tenantId;
        }

        if (!$this->db->insert('dotp_task_files', $insertData)) {
            throw new \RuntimeException('Falha ao registrar arquivo no banco');
        }
        $fileId = (int) $this->db->lastInsertId();
        
        Logger::info('File uploaded', [
            'file_id' => $fileId,
            'task_id' => $taskId,
            'user_id' => $userId,
            'filename' => $originalName
        ]);
        
        return $this->getFile($fileId);
    }
    
    /**
     * Obtém informações do arquivo
     */
    public function getFile(int $fileId): ?array
    {
        $fileTenant = $this->tenantAndCondition('dotp_task_files', 'f');
        $taskTenant = $this->tenantAndCondition('dotp_tasks', 't');
        $userTenant = $this->tenantAndCondition('dotp_users', 'u');
        $sql = sprintf(
            "SELECT f.*, u.user_username as uploaded_by_name 
             FROM `dotp_task_files` f
             LEFT JOIN `dotp_users` u ON u.user_id = f.file_uploaded_by%s
             LEFT JOIN `dotp_tasks` t ON t.task_id = f.file_task_id
             WHERE f.file_id = %d%s%s",
            $userTenant,
            $fileId,
            $fileTenant,
            $taskTenant
        );
        
        $file = $this->db->fetchOne($sql);
        
        if (!$file) {
            return null;
        }
        
        // Formata tamanho
        $file['file_size_formatted'] = $this->formatFileSize((int) $file['file_size']);
        
        return $file;
    }
    
    /**
     * Lista arquivos de uma tarefa
     */
    public function getTaskFiles(int $taskId): array
    {
        $fileTenant = $this->tenantAndCondition('dotp_task_files', 'f');
        $taskTenant = $this->tenantAndCondition('dotp_tasks', 't');
        $userTenant = $this->tenantAndCondition('dotp_users', 'u');
        $sql = sprintf(
            "SELECT f.*, u.user_username as uploaded_by_name 
             FROM `dotp_task_files` f
             LEFT JOIN `dotp_users` u ON u.user_id = f.file_uploaded_by%s
             LEFT JOIN `dotp_tasks` t ON t.task_id = f.file_task_id
             WHERE f.file_task_id = %d
             %s%s
             ORDER BY f.file_created_at DESC",
            $userTenant,
            $taskId,
            $fileTenant,
            $taskTenant
        );
        
        $files = $this->db->fetchAll($sql);
        
        foreach ($files as &$file) {
            $file['file_size_formatted'] = $this->formatFileSize((int) $file['file_size']);
        }
        
        return $files;
    }
    
    /**
     * Deleta arquivo
     */
    public function delete(int $fileId): void
    {
        $file = $this->getFile($fileId);
        
        if (!$file) {
            throw new \RuntimeException('Arquivo não encontrado');
        }
        
        // Remove arquivo físico
        $filePath = $this->uploadDir . $file['file_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // Remove do banco
        $sql = sprintf(
            "DELETE FROM `dotp_task_files` WHERE file_id = %d%s%s",
            $fileId,
            $this->tenantAndCondition('dotp_task_files'),
            $this->taskTenantSubqueryCondition()
        );
        $this->db->query($sql);
        
        Logger::info('File deleted', [
            'file_id' => $fileId,
            'filename' => $file['file_name']
        ]);
    }
    
    /**
     * Obtém caminho completo do arquivo
     */
    public function getFilePath(array $file): string
    {
        return $this->uploadDir . $file['file_path'];
    }
    
    /**
     * Formata tamanho do arquivo
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }

    private function taskTenantSubqueryCondition(): string
    {
        $tenantId = $this->getTenantId();
        if ($tenantId === null || !$this->hasTableColumn('dotp_tasks', 'tenant_id')) {
            return '';
        }

        return " AND file_task_id IN (SELECT task_id FROM `dotp_tasks` WHERE tenant_id = {$tenantId})";
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

        $count = (int) ($this->db->fetchValue(
            "SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND column_name = ?",
            [$tableName, $column]
        ) ?? 0);

        $this->columnPresenceCache[$cacheKey] = $count > 0;
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
