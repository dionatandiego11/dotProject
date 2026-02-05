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

/**
 * Serviço de Arquivos
 */
class FileService
{
    private Database $db;
    private AuthorizationService $auth;
    private string $uploadDir;
    
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
        $sql = sprintf("SELECT COUNT(*) FROM `dotp_tasks` WHERE task_id = %d", $taskId);
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
        $sql = sprintf(
            "SELECT file_task_id, file_uploaded_by FROM `dotp_task_files` WHERE file_id = %d",
            $fileId
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
        $sql = sprintf(
            "INSERT INTO `dotp_task_files` 
             (file_task_id, file_name, file_path, file_size, file_mime_type, file_uploaded_by) 
             VALUES (%d, '%s', '%s', %d, '%s', %d)",
            $taskId,
            $this->db->escape($originalName),
            $this->db->escape($dateDir . '/' . $uniqueName),
            $size,
            $this->db->escape($mimeType),
            $userId
        );
        
        $this->db->query($sql);
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
        $sql = sprintf(
            "SELECT f.*, u.user_username as uploaded_by_name 
             FROM `dotp_task_files` f
             LEFT JOIN `dotp_users` u ON u.user_id = f.file_uploaded_by
             WHERE f.file_id = %d",
            $fileId
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
        $sql = sprintf(
            "SELECT f.*, u.user_username as uploaded_by_name 
             FROM `dotp_task_files` f
             LEFT JOIN `dotp_users` u ON u.user_id = f.file_uploaded_by
             WHERE f.file_task_id = %d
             ORDER BY f.file_created_at DESC",
            $taskId
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
        $sql = sprintf("DELETE FROM `dotp_task_files` WHERE file_id = %d", $fileId);
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
}
