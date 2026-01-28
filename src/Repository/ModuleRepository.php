<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Cache;
use App\Entity\ModuleEntity;
use PDO;

/**
 * Repository de Módulos - Gerencia módulos do sistema
 * 
 * @package App\Repository
 */
class ModuleRepository extends BaseRepository
{
    protected function getEntityClass(): string
    {
        return ModuleEntity::class;
    }
    
    protected function getTableName(): string
    {
        return 'modules';
    }
    
    protected function getPrimaryKey(): string
    {
        return 'mod_id';
    }
    
    /**
     * Buscar todos os módulos ativos
     */
    public function findActive(): array
    {
        return $this->findBy(
            ['mod_active' => 1],
            ['mod_ui_order' => 'ASC', 'mod_name' => 'ASC']
        );
    }
    
    /**
     * Buscar módulos visíveis no menu
     */
    public function findMenuModules(): array
    {
        return $this->findBy(
            ['mod_active' => 1, 'mod_ui_active' => 1],
            ['mod_ui_order' => 'ASC', 'mod_name' => 'ASC']
        );
    }
    
    /**
     * Buscar módulos por tipo
     */
    public function findByType(string $type): array
    {
        return $this->findBy(
            ['mod_type' => $type],
            ['mod_name' => 'ASC']
        );
    }
    
    /**
     * Buscar módulo por diretório
     */
    public function findByDirectory(string $directory): ?ModuleEntity
    {
        $results = $this->findBy(['mod_directory' => $directory]);
        return $results[0] ?? null;
    }
    
    /**
     * Buscar módulos core
     */
    public function findCoreModules(): array
    {
        return $this->findByType('core');
    }
    
    /**
     * Buscar módulos de usuário
     */
    public function findUserModules(): array
    {
        return $this->findByType('user');
    }
    
    /**
     * Ativar módulo
     */
    public function activate(int $moduleId): bool
    {
        return $this->update($moduleId, ['mod_active' => 1]);
    }
    
    /**
     * Desativar módulo
     */
    public function deactivate(int $moduleId): bool
    {
        return $this->update($moduleId, ['mod_active' => 0]);
    }
    
    /**
     * Ativar no menu
     */
    public function activateInMenu(int $moduleId): bool
    {
        return $this->update($moduleId, ['mod_ui_active' => 1]);
    }
    
    /**
     * Desativar no menu
     */
    public function deactivateInMenu(int $moduleId): bool
    {
        return $this->update($moduleId, ['mod_ui_active' => 0]);
    }
    
    /**
     * Ordenar módulos
     */
    public function updateOrder(array $moduleOrders): bool
    {
        $sql = "UPDATE {$this->table} SET mod_ui_order = :order WHERE mod_id = :id";
        $stmt = $this->db->prepare($sql);
        
        $this->db->beginTransaction();
        try {
            foreach ($moduleOrders as $moduleId => $order) {
                $stmt->execute([':order' => $order, ':id' => $moduleId]);
            }
            $this->db->commit();
            $this->cache?->invalidatePattern($this->cachePrefix() . '*');
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }
    
    /**
     * Verificar se módulo existe
     */
    public function exists(string $directory): bool
    {
        return $this->findByDirectory($directory) !== null;
    }
    
    /**
     * Obter estatísticas
     */
    public function getStats(): array
    {
        $stats = [
            'total' => $this->count(),
            'active' => $this->count(['mod_active' => 1]),
            'inactive' => $this->count(['mod_active' => 0]),
            'menu_visible' => $this->count(['mod_active' => 1, 'mod_ui_active' => 1]),
            'core' => $this->count(['mod_type' => 'core']),
            'user' => $this->count(['mod_type' => 'user']),
        ];
        
        return $stats;
    }
}
