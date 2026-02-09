<?php
/**
 * Notification Repository
 * 
 * Repository para gerenciar notificações.
 * 
 * @package DotProject\Repository
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Repository;

use DotProject\Entity\Notification;
use DateTime;

/**
 * Repository para Notification
 */
class NotificationRepository extends BaseRepository
{
    protected string $table = 'dotp_notifications';
    protected string $primaryKey = 'notification_id';
    protected int $cacheTtl = 60;
    
    /**
     * {@inheritdoc}
     */
    protected function hydrate(array $data): Notification
    {
        $entity = new Notification();
        $entity->setId((int) $data['notification_id']);
        $entity->setUserId((int) $data['notification_user_id']);
        $entity->setType($data['notification_type']);
        $entity->setTitle($data['notification_title']);
        $entity->setMessage($data['notification_message']);
        $entity->setEntityType($data['notification_entity_type'] ?? null);
        $entity->setEntityId($data['notification_entity_id'] ? (int) $data['notification_entity_id'] : null);
        
        if (!empty($data['notification_data'])) {
            $entity->setData(json_decode($data['notification_data'], true));
        }
        
        $entity->setIsRead((bool) $data['notification_is_read']);
        
        if (!empty($data['notification_read_at'])) {
            $entity->setReadAt(new DateTime($data['notification_read_at']));
        }
        
        $entity->setChannel($data['notification_channel'] ?? Notification::CHANNEL_IN_APP);
        $entity->setIsSent((bool) ($data['notification_is_sent'] ?? 0));
        
        if (!empty($data['notification_sent_at'])) {
            $entity->setSentAt(new DateTime($data['notification_sent_at']));
        }
        
        if (!empty($data['notification_created_at'])) {
            $entity->setCreatedAt(new DateTime($data['notification_created_at']));
        }
        
        return $entity;
    }
    
    /**
     * {@inheritdoc}
     */
    protected function extract(object $entity): array
    {
        if (!$entity instanceof Notification) {
            throw new \InvalidArgumentException('Entity must be Notification');
        }
        
        return [
            'notification_id' => $entity->getId(),
            'notification_user_id' => $entity->getUserId(),
            'notification_type' => $entity->getType(),
            'notification_title' => $entity->getTitle(),
            'notification_message' => $entity->getMessage(),
            'notification_entity_type' => $entity->getEntityType(),
            'notification_entity_id' => $entity->getEntityId(),
            'notification_data' => $entity->getData() ? json_encode($entity->getData()) : null,
            'notification_is_read' => $entity->isRead() ? 1 : 0,
            'notification_read_at' => $entity->getReadAt()?->format('Y-m-d H:i:s'),
            'notification_channel' => $entity->getChannel(),
            'notification_is_sent' => $entity->isSent() ? 1 : 0,
            'notification_sent_at' => $entity->getSentAt()?->format('Y-m-d H:i:s'),
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function save(object $entity): int
    {
        if (!$entity instanceof Notification) {
            throw new \InvalidArgumentException('Entity must be Notification');
        }
        
        $data = $this->extract($entity);
        $result = false;
        
        if ($entity->getId() === null) {
            unset($data['notification_id']);
            $result = $this->db->insert($this->table, $data);
            if ($result) {
                $entity->setId((int) $this->db->lastInsertId());
            }
        } else {
            $id = $data['notification_id'];
            unset($data['notification_id']);
            $result = $this->db->update($this->table, $data, "{$this->primaryKey} = {$id}");
        }
        
        if ($result) {
            $this->clearCache();
        }
        
        return $result ? (int) $entity->getId() : 0;
    }
    
    /**
     * Encontra notificações de um usuário
     * 
     * @return array<Notification>
     */
    public function findByUser(int $userId, bool $unreadOnly = false, ?int $limit = null): array
    {
        $criteria = ['notification_user_id' => $userId];
        
        if ($unreadOnly) {
            $criteria['notification_is_read'] = 0;
        }
        
        return $this->findBy(
            $criteria,
            ['notification_created_at' => 'DESC'],
            $limit
        );
    }
    
    /**
     * Conta notificações não lidas
     */
    public function countUnread(int $userId): int
    {
        $cacheKey = $this->cacheKey("countUnread:{$userId}");
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $count = $this->count([
            'notification_user_id' => $userId,
            'notification_is_read' => 0,
        ]);
        
        $this->cache->set($cacheKey, $count, 60);
        
        return $count;
    }
    
    /**
     * Marca todas as notificações como lidas
     */
    public function markAllAsRead(int $userId): bool
    {
        $result = $this->db->update(
            $this->table,
            [
                'notification_is_read' => 1,
                'notification_read_at' => date('Y-m-d H:i:s'),
            ],
            "notification_user_id = {$userId} AND notification_is_read = 0"
        );
        
        if ($result) {
            $this->clearCache();
        }
        
        return $result;
    }
    
    /**
     * Marca notificações como enviadas
     */
    public function markAsSent(array $notificationIds): bool
    {
        if (empty($notificationIds)) {
            return true;
        }
        
        $ids = implode(',', array_map('intval', $notificationIds));
        
        $result = $this->db->update(
            $this->table,
            [
                'notification_is_sent' => 1,
                'notification_sent_at' => date('Y-m-d H:i:s'),
            ],
            "notification_id IN ({$ids})"
        );
        
        if ($result) {
            $this->clearCache();
        }
        
        return $result;
    }
    
    /**
     * Obtém notificações pendentes de envio
     * 
     * @return array<Notification>
     */
    public function findPending(string $channel = Notification::CHANNEL_EMAIL, ?int $limit = 100): array
    {
        return $this->findBy(
            [
                'notification_is_sent' => 0,
                'notification_channel' => $channel,
            ],
            ['notification_created_at' => 'ASC'],
            $limit
        );
    }
    
    /**
     * Remove notificações antigas
     */
    public function deleteOld(int $days = 30): int
    {
        $sql = sprintf(
            "DELETE FROM `%s` WHERE notification_created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $this->table,
            $days
        );
        
        $result = $this->db->query($sql);
        
        if ($result) {
            $this->clearCache();
        }
        
        return $result ? $this->db->affectedRows() : 0;
    }
    
    /**
     * Encontra notificações por tipo
     * 
     * @return array<Notification>
     */
    public function findByType(string $type, int $userId): array
    {
        return $this->findBy(
            [
                'notification_user_id' => $userId,
                'notification_type' => $type,
            ],
            ['notification_created_at' => 'DESC']
        );
    }
    
    /**
     * Encontra notificações de uma entidade específica
     * 
     * @return array<Notification>
     */
    public function findByEntity(string $entityType, int $entityId, int $userId): array
    {
        return $this->findBy(
            [
                'notification_user_id' => $userId,
                'notification_entity_type' => $entityType,
                'notification_entity_id' => $entityId,
            ],
            ['notification_created_at' => 'DESC']
        );
    }
    
    /**
     * Verifica se existe notificação não lida para entidade
     */
    public function hasUnreadForEntity(string $entityType, int $entityId, int $userId): bool
    {
        $sql = sprintf(
            "SELECT COUNT(*) FROM `%s` 
             WHERE notification_user_id = %d 
             AND notification_entity_type = '%s'
             AND notification_entity_id = %d
             AND notification_is_read = 0",
            $this->table,
            $userId,
            $this->db->escape($entityType),
            $entityId
        );
        
        return (int) $this->db->fetchValue($sql) > 0;
    }
    
    /**
     * Obtém estatísticas de notificações
     */
    public function getStats(int $userId): array
    {
        $sql = sprintf(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN notification_is_read = 0 THEN 1 ELSE 0 END) as unread,
                SUM(CASE WHEN notification_is_read = 1 THEN 1 ELSE 0 END) as read,
                notification_type,
                COUNT(*) as type_count
            FROM `%s`
            WHERE notification_user_id = %d
            AND notification_created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY notification_type
            ORDER BY type_count DESC",
            $this->table,
            $userId
        );
        
        $byType = $this->db->fetchAll($sql);
        
        // Totais
        $sql = sprintf(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN notification_is_read = 0 THEN 1 ELSE 0 END) as unread
            FROM `%s`
            WHERE notification_user_id = %d",
            $this->table,
            $userId
        );
        
        $totals = $this->db->fetchOne($sql);
        
        return [
            'total' => (int) ($totals['total'] ?? 0),
            'unread' => (int) ($totals['unread'] ?? 0),
            'read' => (int) ($totals['total'] ?? 0) - (int) ($totals['unread'] ?? 0),
            'by_type' => $byType,
        ];
    }
}
