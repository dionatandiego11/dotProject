<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Cache;
use App\Entity\ConfigEntity;
use PDO;

/**
 * Repository de Configurações - Gerencia configurações do sistema
 * 
 * @package App\Repository
 */
class ConfigRepository extends BaseRepository
{
    protected function getEntityClass(): string
    {
        return ConfigEntity::class;
    }
    
    protected function getTableName(): string
    {
        return 'config';
    }
    
    protected function getPrimaryKey(): string
    {
        return 'config_id';
    }
    
    /**
     * Buscar por nome
     */
    public function findByName(string $name): ?ConfigEntity
    {
        $results = $this->findBy(['config_name' => $name]);
        return $results[0] ?? null;
    }
    
    /**
     * Buscar por grupo
     */
    public function findByGroup(string $group): array
    {
        return $this->findBy(
            ['config_group' => $group],
            ['config_name' => 'ASC']
        );
    }
    
    /**
     * Buscar todos os grupos
     */
    public function findAllGroups(): array
    {
        $sql = "SELECT DISTINCT config_group FROM {$this->table} ORDER BY config_group";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Obter valor por nome
     */
    public function getValue(string $name, mixed $default = null): mixed
    {
        $entity = $this->findByName($name);
        return $entity ? $entity->getTypedValue() : $default;
    }
    
    /**
     * Definir valor
     */
    public function setValue(string $name, mixed $value, string $group = 'system', string $type = 'text'): bool
    {
        $existing = $this->findByName($name);
        
        $stringValue = match ($type) {
            'checkbox' => $value ? 'true' : 'false',
            'array' => is_array($value) ? implode(',', $value) : $value,
            'json' => json_encode($value),
            default => (string) $value,
        };
        
        if ($existing) {
            return $this->update($existing->getId(), [
                'config_value' => $stringValue,
                'config_type' => $type,
            ]);
        }
        
        $data = [
            'config_name' => $name,
            'config_value' => $stringValue,
            'config_group' => $group,
            'config_type' => $type,
        ];
        
        return $this->create($data) !== null;
    }
    
    /**
     * Obter configurações como array
     */
    public function getAsArray(): array
    {
        $configs = [];
        $entities = $this->findAll();
        
        foreach ($entities as $entity) {
            $configs[$entity->getName()] = $entity->getTypedValue();
        }
        
        return $configs;
    }
    
    /**
     * Obter configurações por grupo
     */
    public function getByGroup(string $group): array
    {
        $configs = [];
        $entities = $this->findByGroup($group);
        
        foreach ($entities as $entity) {
            $configs[$entity->getName()] = $entity->getTypedValue();
        }
        
        return $configs;
    }
    
    /**
     * Verificar se configuração existe
     */
    public function has(string $name): bool
    {
        return $this->findByName($name) !== null;
    }
    
    /**
     * Excluir por nome
     */
    public function deleteByName(string $name): bool
    {
        $entity = $this->findByName($name);
        if (!$entity) {
            return false;
        }
        
        return $this->delete($entity->getId());
    }
    
    /**
     * Obter estatísticas
     */
    public function getStats(): array
    {
        $stats = [
            'total' => $this->count(),
            'by_group' => [],
            'by_type' => [],
        ];
        
        // Por grupo
        $sql = "SELECT config_group, COUNT(*) as count FROM {$this->table} GROUP BY config_group";
        $stmt = $this->db->query($sql);
        $stats['by_group'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Por tipo
        $sql = "SELECT config_type, COUNT(*) as count FROM {$this->table} GROUP BY config_type";
        $stmt = $this->db->query($sql);
        $stats['by_type'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        return $stats;
    }
}
