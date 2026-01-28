<?php
/**
 * Kanban Board Repository
 * 
 * Repository para gerenciar boards Kanban.
 * 
 * @package DotProject\Repository
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Repository;

use DotProject\Core\Cache;
use DotProject\Core\Database;
use DotProject\Entity\KanbanBoard;
use DateTime;

/**
 * Repository para KanbanBoard
 */
class KanbanBoardRepository extends BaseRepository
{
    protected string $table = 'dotp_kanban_boards';
    protected string $primaryKey = 'board_id';
    protected int $cacheTtl = 300; // 5 minutos
    
    /**
     * {@inheritdoc}
     */
    protected function hydrate(array $data): KanbanBoard
    {
        $entity = new KanbanBoard();
        $entity->setId((int) $data['board_id']);
        $entity->setName($data['board_name']);
        $entity->setDescription($data['board_description'] ?? null);
        $entity->setProjectId($data['board_project'] ? (int) $data['board_project'] : null);
        $entity->setCompanyId((int) $data['board_company']);
        $entity->setCreatedBy($data['board_created_by'] ? (int) $data['board_created_by'] : null);
        $entity->setStatus((int) $data['board_status']);
        
        if (!empty($data['board_created_at'])) {
            $entity->setCreatedAt(new DateTime($data['board_created_at']));
        }
        if (!empty($data['board_updated_at'])) {
            $entity->setUpdatedAt(new DateTime($data['board_updated_at']));
        }
        
        return $entity;
    }
    
    /**
     * {@inheritdoc}
     */
    protected function extract(object $entity): array
    {
        if (!$entity instanceof KanbanBoard) {
            throw new \InvalidArgumentException('Entity must be KanbanBoard');
        }
        
        return [
            'board_id' => $entity->getId(),
            'board_name' => $entity->getName(),
            'board_description' => $entity->getDescription(),
            'board_project' => $entity->getProjectId(),
            'board_company' => $entity->getCompanyId(),
            'board_created_by' => $entity->getCreatedBy(),
            'board_status' => $entity->getStatus(),
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function save(object $entity): bool
    {
        if (!$entity instanceof KanbanBoard) {
            throw new \InvalidArgumentException('Entity must be KanbanBoard');
        }
        
        $data = $this->extract($entity);
        
        if ($entity->getId() === null) {
            unset($data['board_id']);
            $result = $this->db->insert($this->table, $data);
            if ($result) {
                $entity->setId((int) $this->db->lastInsertId());
            }
        } else {
            $id = $data['board_id'];
            unset($data['board_id']);
            $result = $this->db->update($this->table, $data, "{$this->primaryKey} = {$id}");
        }
        
        if ($result) {
            $this->clearCache();
        }
        
        return $result;
    }
    
    /**
     * Encontra boards por projeto
     * 
     * @return array<KanbanBoard>
     */
    public function findByProject(int $projectId): array
    {
        return $this->findBy(
            ['board_project' => $projectId, 'board_status' => 0],
            ['board_created_at' => 'DESC']
        );
    }
    
    /**
     * Encontra boards por empresa (globais ou do projeto)
     * 
     * @return array<KanbanBoard>
     */
    public function findByCompany(int $companyId, ?int $projectId = null): array
    {
        $criteria = ['board_company' => $companyId, 'board_status' => 0];
        
        if ($projectId !== null) {
            // Boards do projeto específico OU boards globais da empresa
            $sql = sprintf(
                "SELECT * FROM `%s` WHERE board_company = %d AND board_status = 0 
                 AND (board_project = %d OR board_project IS NULL)
                 ORDER BY board_project IS NULL, board_created_at DESC",
                $this->table,
                $companyId,
                $projectId
            );
            $results = $this->db->fetchAll($sql);
            return array_map([$this, 'hydrate'], $results);
        }
        
        return $this->findBy($criteria, ['board_created_at' => 'DESC']);
    }
    
    /**
     * Encontra board padrão para um projeto
     * Cria um novo se não existir
     */
    public function findOrCreateDefault(int $projectId, int $companyId, int $userId): KanbanBoard
    {
        $boards = $this->findByProject($projectId);
        
        if (!empty($boards)) {
            return $boards[0];
        }
        
        // Cria board padrão
        $board = new KanbanBoard();
        $board->setName('Project Board');
        $board->setDescription('Default kanban board for project');
        $board->setProjectId($projectId);
        $board->setCompanyId($companyId);
        $board->setCreatedBy($userId);
        $board->setStatus(0);
        
        $this->save($board);
        
        return $board;
    }
    
    /**
     * Busca boards por nome (busca parcial)
     * 
     * @return array<KanbanBoard>
     */
    public function searchByName(string $query, int $companyId): array
    {
        $sql = sprintf(
            "SELECT * FROM `%s` 
             WHERE board_company = %d 
             AND board_status = 0 
             AND board_name LIKE ?
             ORDER BY board_name ASC
             LIMIT 20",
            $this->table,
            $companyId
        );
        
        $pattern = '%' . $query . '%';
        $results = $this->db->fetchAll($sql, [$pattern]);
        
        return array_map([$this, 'hydrate'], $results);
    }
    
    /**
     * Obtém estatísticas do board
     */
    public function getBoardStats(int $boardId): array
    {
        $cacheKey = $this->cacheKey("stats:{$boardId}");
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $sql = sprintf(
            "SELECT 
                COUNT(DISTINCT c.column_id) as total_columns,
                COUNT(DISTINCT kt.kanban_task_id) as total_tasks,
                COUNT(DISTINCT CASE WHEN col_done.column_is_done = 1 THEN kt.kanban_task_id END) as completed_tasks
            FROM `%s` b
            LEFT JOIN `dotp_kanban_columns` c ON c.column_board_id = b.board_id AND c.column_status = 0
            LEFT JOIN `dotp_kanban_tasks` kt ON kt.kanban_task_column_id = c.column_id
            LEFT JOIN `dotp_kanban_columns` col_done ON col_done.column_id = kt.kanban_task_column_id AND col_done.column_is_done = 1
            WHERE b.board_id = %d",
            $this->table,
            $boardId
        );
        
        $stats = $this->db->fetchOne($sql) ?: [
            'total_columns' => 0,
            'total_tasks' => 0,
            'completed_tasks' => 0,
        ];
        
        $stats = [
            'total_columns' => (int) $stats['total_columns'],
            'total_tasks' => (int) $stats['total_tasks'],
            'completed_tasks' => (int) $stats['completed_tasks'],
            'progress' => $stats['total_tasks'] > 0 
                ? round(($stats['completed_tasks'] / $stats['total_tasks']) * 100, 1) 
                : 0,
        ];
        
        $this->cache->set($cacheKey, $stats, $this->cacheTtl);
        
        return $stats;
    }
    
    /**
     * Arquiva um board (soft delete)
     */
    public function archive(int $boardId): bool
    {
        $result = $this->db->update(
            $this->table,
            ['board_status' => 1],
            "{$this->primaryKey} = {$boardId}"
        );
        
        if ($result) {
            $this->clearCache();
        }
        
        return $result;
    }
    
    /**
     * Duplica um board com todas as colunas (mas sem as tarefas)
     */
    public function duplicate(int $boardId, string $newName, int $userId): ?KanbanBoard
    {
        $original = $this->find($boardId);
        if (!$original) {
            return null;
        }
        
        // Cria novo board
        $newBoard = new KanbanBoard();
        $newBoard->setName($newName);
        $newBoard->setDescription($original->getDescription());
        $newBoard->setProjectId($original->getProjectId());
        $newBoard->setCompanyId($original->getCompanyId());
        $newBoard->setCreatedBy($userId);
        $newBoard->setStatus(0);
        
        if (!$this->save($newBoard)) {
            return null;
        }
        
        return $newBoard;
    }
}
