<?php
/**
 * Notification Service
 * 
 * Servico de notificações simplificado.
 * 
 * @package DotProject\Service
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Service;

use DotProject\Core\Cache;
use DotProject\Core\Database;
use DotProject\Entity\Notification;

/**
 * Servico de Notificações
 */
class NotificationService
{
    private Database $db;
    private Cache $cache;
    
    public function __construct(
        ?Database $db = null,
        ?Cache $cache = null
    ) {
        $this->db = $db ?? Database::getInstance();
        $this->cache = $cache ?? new Cache(prefix: 'notifications:');
    }
    
    /**
     * Obtém notificações de um usuário
     */
    public function getUserNotifications(
        int $userId,
        bool $unreadOnly = false,
        ?int $limit = 50
    ): array {
        $sql = sprintf(
            "SELECT * FROM `dotp_notifications` 
             WHERE notification_user_id = %d
             %s
             ORDER BY notification_created_at DESC
             LIMIT %d",
            $userId,
            $unreadOnly ? 'AND notification_is_read = 0' : '',
            $limit
        );
        
        $results = $this->db->fetchAll($sql);
        $notifications = [];
        
        foreach ($results as $data) {
            $notifications[] = $this->hydrateNotification($data);
        }
        
        return $notifications;
    }
    
    /**
     * Conta notificações não lidas
     */
    public function getUnreadCount(int $userId): int
    {
        $cacheKey = "unread:{$userId}";
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $sql = sprintf(
            "SELECT COUNT(*) FROM `dotp_notifications` 
             WHERE notification_user_id = %d AND notification_is_read = 0",
            $userId
        );
        
        $count = (int) $this->db->fetchValue($sql);
        $this->cache->set($cacheKey, $count, 60);
        
        return $count;
    }
    
    /**
     * Marca notificação como lida
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        $sql = sprintf(
            "UPDATE `dotp_notifications` 
             SET notification_is_read = 1, notification_read_at = NOW()
             WHERE notification_id = %d AND notification_user_id = %d",
            $notificationId,
            $userId
        );
        
        $result = $this->db->query($sql);
        
        if ($result) {
            $this->cache->delete("unread:{$userId}");
        }
        
        return $result;
    }
    
    /**
     * Marca todas como lidas
     */
    public function markAllAsRead(int $userId): bool
    {
        $sql = sprintf(
            "UPDATE `dotp_notifications` 
             SET notification_is_read = 1, notification_read_at = NOW()
             WHERE notification_user_id = %d AND notification_is_read = 0",
            $userId
        );
        
        $result = $this->db->query($sql);
        
        if ($result) {
            $this->cache->delete("unread:{$userId}");
        }
        
        return $result;
    }
    
    /**
     * Hidrata dados da notificação
     */
    private function hydrateNotification(array $data): Notification
    {
        $notification = new Notification();
        $notification->setId((int) $data['notification_id']);
        $notification->setUserId((int) $data['notification_user_id']);
        $notification->setType($data['notification_type']);
        $notification->setTitle($data['notification_title']);
        $notification->setMessage($data['notification_message']);
        $notification->setEntityType($data['notification_entity_type']);
        $notification->setEntityId($data['notification_entity_id'] ? (int) $data['notification_entity_id'] : null);
        $notification->setIsRead((bool) $data['notification_is_read']);
        $notification->setChannel($data['notification_channel']);
        $notification->setIsSent((bool) $data['notification_is_sent']);
        
        return $notification;
    }
}
