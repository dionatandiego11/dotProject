<?php
/**
 * Repository para Project
 * 
 * @package DotProject\Repository
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Repository;

use DotProject\Entity\ProjectEntity;
use DateTime;

/**
 * Repository para gerenciar projetos
 */
class ProjectRepository extends BaseRepository
{
    protected string $table = 'dotp_projects';
    protected string $primaryKey = 'project_id';

    /**
     * {@inheritdoc}
     */
    protected function hydrate(array $data): ProjectEntity
    {
        $entity = new ProjectEntity();
        $entity->setId((int) $data['project_id']);
        $entity->setName($data['project_name']);
        $entity->setShortName($data['project_short_name'] ?? null);
        $entity->setDescription($data['project_description'] ?? null);
        
        if (!empty($data['project_start_date'])) {
            $entity->setStartDate(new DateTime($data['project_start_date']));
        }
        
        if (!empty($data['project_end_date'])) {
            $entity->setEndDate(new DateTime($data['project_end_date']));
        }
        
        if (!empty($data['project_actual_end_date'])) {
            $entity->setActualEndDate(new DateTime($data['project_actual_end_date']));
        }
        
        $entity->setStatus((int) ($data['project_status'] ?? 0));
        $entity->setPriority((int) ($data['project_priority'] ?? 3));
        $entity->setPercentComplete((int) ($data['project_percent_complete'] ?? 0));
        $entity->setOwnerId($data['project_owner'] ? (int) $data['project_owner'] : null);
        $entity->setCompanyId($data['project_company'] ? (int) $data['project_company'] : null);
        $entity->setColorIdentifier($data['project_color_identifier'] ?? null);
        $entity->setUrl($data['project_url'] ?? null);
        
        if (!empty($data['project_created'])) {
            $entity->setCreatedAt(new DateTime($data['project_created']));
        }
        
        if (!empty($data['project_updated'])) {
            $entity->setUpdatedAt(new DateTime($data['project_updated']));
        }

        return $entity;
    }

    /**
     * {@inheritdoc}
     */
    protected function extract(object $entity): array
    {
        if (!$entity instanceof ProjectEntity) {
            throw new \InvalidArgumentException('Entity must be ProjectEntity');
        }

        return [
            'project_id' => $entity->getId(),
            'project_name' => $entity->getName(),
            'project_short_name' => $entity->getShortName(),
            'project_description' => $entity->getDescription(),
            'project_start_date' => $entity->getStartDate()?->format('Y-m-d'),
            'project_end_date' => $entity->getEndDate()?->format('Y-m-d'),
            'project_actual_end_date' => $entity->getActualEndDate()?->format('Y-m-d'),
            'project_status' => $entity->getStatus(),
            'project_priority' => $entity->getPriority(),
            'project_percent_complete' => $entity->getPercentComplete(),
            'project_owner' => $entity->getOwnerId(),
            'project_company' => $entity->getCompanyId(),
            'project_color_identifier' => $entity->getColorIdentifier(),
            'project_url' => $entity->getUrl(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function save(object $entity): int
    {
        if (!$entity instanceof ProjectEntity) {
            throw new \InvalidArgumentException('Entity must be ProjectEntity');
        }

        $data = $this->extract($entity);
        $tenantColumn = $this->getTenantColumn();
        $tenantId = $this->getTenantId();
        $applyTenant = $this->shouldApplyTenantScope() && $tenantColumn !== null && $tenantId !== null;
        if ($applyTenant && (!array_key_exists($tenantColumn, $data) || $data[$tenantColumn] === null || $data[$tenantColumn] === '')) {
            $data[$tenantColumn] = $tenantId;
        }
        $result = false;
        
        if ($entity->getId() === null) {
            // Insert
            unset($data['project_id']);
            $result = $this->db->insert($this->table, $data);
            if ($result) {
                $entity->setId((int) $this->db->lastInsertId());
            }
        } else {
            // Update
            $id = $data['project_id'];
            unset($data['project_id']);
            $result = $this->db->update(
                $this->table,
                $data,
                $applyTenant
                    ? "{$this->primaryKey} = {$id} AND {$tenantColumn} = {$tenantId}"
                    : "{$this->primaryKey} = {$id}"
            );
        }

        if ($result) {
            $this->clearCache();
        }

        return $result ? (int) $entity->getId() : 0;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(int $id): bool
    {
        $tenantColumn = $this->getTenantColumn();
        $tenantId = $this->getTenantId();
        $where = "{$this->primaryKey} = {$id}";
        if ($this->shouldApplyTenantScope() && $tenantColumn !== null && $tenantId !== null) {
            $where .= " AND {$tenantColumn} = {$tenantId}";
        }

        $result = $this->db->delete(
            $this->table,
            $where
        );

        if ($result) {
            $this->clearCache();
        }

        return $result;
    }

    /**
     * Encontra projetos ativos
     * 
     * @return array<ProjectEntity>
     */
    public function findActive(): array
    {
        return $this->findBy(['project_status' => 0], ['project_name' => 'ASC']);
    }

    /**
     * Encontra projetos por dono
     * 
     * @return array<ProjectEntity>
     */
    public function findByOwner(int $ownerId): array
    {
        return $this->findBy(
            ['project_owner' => $ownerId],
            ['project_start_date' => 'DESC']
        );
    }

    /**
     * Encontra projetos atrasados
     * 
     * @return array<ProjectEntity>
     */
    public function findOverdue(): array
    {
        $params = [];
        $sql = "SELECT * FROM {$this->table} 
                WHERE project_end_date < CURDATE() 
                AND project_status = 0";
        $sql = $this->appendTenantScopeToSql($sql, $params);
        $sql .= " ORDER BY project_end_date ASC";
        $results = $this->db->fetchAll($sql, $params);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Busca projetos por nome (LIKE)
     * 
     * @return array<ProjectEntity>
     */
    public function searchByName(string $query): array
    {
        $params = [];
        $sql = "SELECT * FROM {$this->table} 
                WHERE (project_name LIKE ? 
                OR project_short_name LIKE ?)";
        $params[] = '%' . $query . '%';
        $params[] = '%' . $query . '%';
        $sql = $this->appendTenantScopeToSql($sql, $params);
        $sql .= " ORDER BY project_name ASC LIMIT 20";

        $results = $this->db->fetchAll($sql, $params);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Atualiza percentual completo
     */
    public function updatePercentComplete(int $projectId, int $percent): bool
    {
        $tenantColumn = $this->getTenantColumn();
        $tenantId = $this->getTenantId();
        $where = "{$this->primaryKey} = {$projectId}";
        if ($this->shouldApplyTenantScope() && $tenantColumn !== null && $tenantId !== null) {
            $where .= " AND {$tenantColumn} = {$tenantId}";
        }

        $result = $this->db->update(
            $this->table,
            ['project_percent_complete' => $percent],
            $where
        );

        if ($result) {
            $this->clearCache();
        }

        return $result;
    }
}
