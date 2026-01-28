<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Cache;
use App\Entity\SysValEntity;
use PDO;

/**
 * Repository de SysVals - Gerencia valores de sistema
 * 
 * @package App\Repository
 */
class SysValRepository extends BaseRepository
{
    protected function getEntityClass(): string
    {
        return SysValEntity::class;
    }
    
    protected function getTableName(): string
    {
        return 'sysvals';
    }
    
    protected function getPrimaryKey(): string
    {
        return 'sysval_id';
    }
    
    /**
     * Buscar por título (key)
     */
    public function findByTitle(string $title): ?SysValEntity
    {
        $results = $this->findBy(['sysval_title' => $title]);
        return $results[0] ?? null;
    }
    
    /**
     * Buscar por key_id
     */
    public function findByKeyId(int $keyId): array
    {
        return $this->findBy(
            ['sysval_key_id' => $keyId],
            ['sysval_title' => 'ASC']
        );
    }
    
    /**
     * Buscar listas de seleção
     */
    public function findSelectLists(): array
    {
        return $this->findByKeyId(1);
    }
    
    /**
     * Buscar campos customizados
     */
    public function findCustomFields(): array
    {
        return $this->findByKeyId(2);
    }
    
    /**
     * Buscar seleções de cor
     */
    public function findColorSelections(): array
    {
        return $this->findByKeyId(3);
    }
    
    /**
     * Obter valor parseado por título
     */
    public function getParsedValues(string $title): array
    {
        $entity = $this->findByTitle($title);
        return $entity ? $entity->getParsedValues() : [];
    }
    
    /**
     * Obter label para um valor
     */
    public function getLabel(string $title, string $key): ?string
    {
        $entity = $this->findByTitle($title);
        return $entity ? $entity->getLabel($key) : null;
    }
    
    /**
     * Salvar ou atualizar sysval
     */
    public function saveByTitle(string $title, string $value, int $keyId = 1): bool
    {
        $existing = $this->findByTitle($title);
        
        if ($existing) {
            return $this->update($existing->getId(), ['sysval_value' => $value]);
        }
        
        $data = [
            'sysval_title' => $title,
            'sysval_value' => $value,
            'sysval_key_id' => $keyId,
        ];
        
        return $this->create($data) !== null;
    }
    
    /**
     * Criar ou atualizar lista de valores
     */
    public function saveList(string $title, array $values, int $keyId = 1): bool
    {
        $serialized = SysValEntity::serializeValues($values);
        return $this->saveByTitle($title, $serialized, $keyId);
    }
    
    /**
     * Excluir por título
     */
    public function deleteByTitle(string $title): bool
    {
        $entity = $this->findByTitle($title);
        if (!$entity) {
            return false;
        }
        
        return $this->delete($entity->getId());
    }
}
