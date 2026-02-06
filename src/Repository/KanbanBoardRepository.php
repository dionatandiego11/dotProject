<?php
/**
 * Repository para KanbanBoard
 * 
 * @package DotProject\Repository
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Repository;

use DotProject\Entity\KanbanBoard;
use DateTime;

class KanbanBoardRepository extends BaseRepository
{
    protected string $table = 'dotp_kanban_boards';
    protected string $primaryKey = 'board_id';

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

    public function save(object $entity): int
    {
        if (!$entity instanceof KanbanBoard) {
            throw new \InvalidArgumentException('Entity must be KanbanBoard');
        }
        
        $data = $this->extract($entity);
        $result = false;
        
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
        
        return $result ? (int) $entity->getId() : 0;
    }

    public function findByCompany(int $companyId, ?int $projectId = null): array
    {
        if ($projectId !== null) {
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
        
        return $this->findBy(
            ['board_company' => $companyId, 'board_status' => 0],
            ['board_created_at' => 'DESC']
        );
    }
}
