<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Cache;
use App\Entity\FileEntity;
use PDO;

/**
 * Repository de Arquivos - Gerencia operações de banco para arquivos
 * 
 * @package App\Repository
 */
class FileRepository extends BaseRepository
{
    protected function getEntityClass(): string
    {
        return FileEntity::class;
    }
    
    protected function getTableName(): string
    {
        return 'files';
    }
    
    protected function getPrimaryKey(): string
    {
        return 'file_id';
    }
    
    /**
     * Buscar arquivos por projeto
     */
    public function findByProject(int $projectId, ?int $folderId = null): array
    {
        $conditions = ['file_project' => $projectId];
        if ($folderId !== null) {
            $conditions['file_folder'] = $folderId;
        }
        
        return $this->findBy($conditions, ['file_date' => 'DESC']);
    }
    
    /**
     * Buscar arquivos por tarefa
     */
    public function findByTask(int $taskId): array
    {
        return $this->findBy(
            ['file_task' => $taskId],
            ['file_date' => 'DESC']
        );
    }
    
    /**
     * Buscar arquivos por pasta
     */
    public function findByFolder(int $folderId): array
    {
        return $this->findBy(
            ['file_folder' => $folderId],
            ['file_name' => 'ASC']
        );
    }
    
    /**
     * Buscar arquivos por proprietário
     */
    public function findByOwner(int $ownerId): array
    {
        return $this->findBy(
            ['file_owner' => $ownerId],
            ['file_date' => 'DESC']
        );
    }
    
    /**
     * Buscar arquivos por categoria
     */
    public function findByCategory(int $category): array
    {
        return $this->findBy(
            ['file_category' => $category],
            ['file_date' => 'DESC']
        );
    }
    
    /**
     * Buscar arquivos bloqueados (checkout)
     */
    public function findCheckedOut(): array
    {
        return $this->findBy(
            ['file_checkout' => ['operator' => '!=', 'value' => '']],
            ['file_date' => 'DESC']
        );
    }
    
    /**
     * Buscar arquivos por tipo MIME
     */
    public function findByType(string $type): array
    {
        return $this->findBy(
            ['file_type' => ['operator' => 'LIKE', 'value' => '%' . $type . '%']],
            ['file_date' => 'DESC']
        );
    }
    
    /**
     * Buscar arquivos de imagem
     */
    public function findImages(): array
    {
        return $this->findBy(
            ['file_type' => ['operator' => 'LIKE', 'value' => 'image/%']],
            ['file_date' => 'DESC']
        );
    }
    
    /**
     * Buscar versões de um arquivo
     */
    public function findVersions(int $fileId): array
    {
        $entity = $this->findById($fileId);
        if (!$entity) {
            return [];
        }
        
        return $this->findBy(
            [
                'file_version_id' => $entity->getVersionId(),
                'file_version_id' => ['operator' => '>', 'value' => 0]
            ],
            ['file_version' => 'DESC']
        );
    }
    
    /**
     * Buscar arquivos recentes
     */
    public function findRecent(int $limit = 10): array
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY file_date DESC LIMIT :limit";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = $this->hydrate($row);
        }
        
        return $results;
    }
    
    /**
     * Contar arquivos por projeto
     */
    public function countByProject(int $projectId): int
    {
        return $this->count(['file_project' => $projectId]);
    }
    
    /**
     * Contar arquivos por tarefa
     */
    public function countByTask(int $taskId): int
    {
        return $this->count(['file_task' => $taskId]);
    }
    
    /**
     * Obter tamanho total de arquivos por projeto
     */
    public function getTotalSizeByProject(int $projectId): int
    {
        $sql = "SELECT SUM(file_size) as total FROM {$this->table} WHERE file_project = :project_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':project_id' => $projectId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int) ($result['total'] ?? 0);
    }
    
    /**
     * Obter estatísticas de arquivos
     */
    public function getStats(): array
    {
        $stats = [
            'total_files' => 0,
            'total_size' => 0,
            'total_size_formatted' => '0 B',
            'by_type' => [],
            'checked_out' => 0,
        ];
        
        // Contagem total
        $sql = "SELECT COUNT(*) as count, SUM(file_size) as size FROM {$this->table}";
        $result = $this->db->query($sql)->fetch(PDO::FETCH_ASSOC);
        $stats['total_files'] = (int) ($result['count'] ?? 0);
        $stats['total_size'] = (int) ($result['size'] ?? 0);
        
        // Formatado
        $size = $stats['total_size'];
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unitIndex = 0;
        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }
        $stats['total_size_formatted'] = round($size, 2) . ' ' . $units[$unitIndex];
        
        // Por tipo
        $sql = "SELECT file_type, COUNT(*) as count FROM {$this->table} 
                WHERE file_type IS NOT NULL GROUP BY file_type ORDER BY count DESC LIMIT 10";
        $stmt = $this->db->query($sql);
        $stats['by_type'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Bloqueados
        $stats['checked_out'] = $this->count(['file_checkout' => ['operator' => '!=', 'value' => '']]);
        
        return $stats;
    }
    
    /**
     * Fazer checkout de arquivo
     */
    public function checkout(int $fileId, string $username, ?string $reason = null): bool
    {
        $sql = "UPDATE {$this->table} 
                SET file_checkout = :username, file_co_reason = :reason 
                WHERE file_id = :id";
        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute([
            ':username' => $username,
            ':reason' => $reason,
            ':id' => $fileId
        ]);
        
        if ($success) {
            $this->cache?->invalidate($this->cacheKey((string) $fileId));
        }
        
        return $success;
    }
    
    /**
     * Fazer checkin de arquivo
     */
    public function checkin(int $fileId): bool
    {
        $sql = "UPDATE {$this->table} 
                SET file_checkout = '', file_co_reason = NULL 
                WHERE file_id = :id";
        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute([':id' => $fileId]);
        
        if ($success) {
            $this->cache?->invalidate($this->cacheKey((string) $fileId));
        }
        
        return $success;
    }
    
    /**
     * Verificar se arquivo está bloqueado
     */
    public function isCheckedOut(int $fileId): bool
    {
        $entity = $this->findById($fileId);
        return $entity && $entity->isCheckedOut();
    }
    
    /**
     * Incrementar versão
     */
    public function incrementVersion(int $fileId): bool
    {
        $entity = $this->findById($fileId);
        if (!$entity) {
            return false;
        }
        
        $newVersion = $entity->getVersion() + 0.1;
        
        $sql = "UPDATE {$this->table} SET file_version = :version WHERE file_id = :id";
        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute([
            ':version' => $newVersion,
            ':id' => $fileId
        ]);
        
        if ($success) {
            $this->cache?->invalidate($this->cacheKey((string) $fileId));
        }
        
        return $success;
    }
}
