<?php
/**
 * Notification Entity
 * 
 * Entidade para notificações do sistema.
 * 
 * @package DotProject\Entity
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Entity;

use DateTime;

/**
 * Entidade Notification
 */
class Notification
{
    // Tipos de notificação
    public const TYPE_TASK_ASSIGNED = 'task_assigned';
    public const TYPE_TASK_COMPLETED = 'task_completed';
    public const TYPE_TASK_OVERDUE = 'task_overdue';
    public const TYPE_TASK_COMMENT = 'task_comment';
    public const TYPE_PROJECT_STATUS = 'project_status';
    public const TYPE_MENTION = 'mention';
    public const TYPE_DEADLINE_APPROACHING = 'deadline_approaching';
    public const TYPE_SYSTEM = 'system';
    
    // Canais
    public const CHANNEL_IN_APP = 'in_app';
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_PUSH = 'push';
    
    private ?int $id = null;
    private int $userId;
    private string $type;
    private string $title;
    private string $message;
    private ?string $entityType = null;  // project, task, etc
    private ?int $entityId = null;
    private ?array $data = null;  // Dados adicionais em JSON
    private bool $isRead = false;
    private ?DateTime $readAt = null;
    private string $channel = self::CHANNEL_IN_APP;
    private bool $isSent = false;
    private ?DateTime $sentAt = null;
    private ?DateTime $createdAt = null;
    
    public function getId(): ?int
    {
        return $this->id;
    }
    
    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }
    
    public function getUserId(): int
    {
        return $this->userId;
    }
    
    public function setUserId(int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }
    
    public function getType(): string
    {
        return $this->type;
    }
    
    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }
    
    public function getTitle(): string
    {
        return $this->title;
    }
    
    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }
    
    public function getMessage(): string
    {
        return $this->message;
    }
    
    public function setMessage(string $message): self
    {
        $this->message = $message;
        return $this;
    }
    
    public function getEntityType(): ?string
    {
        return $this->entityType;
    }
    
    public function setEntityType(?string $entityType): self
    {
        $this->entityType = $entityType;
        return $this;
    }
    
    public function getEntityId(): ?int
    {
        return $this->entityId;
    }
    
    public function setEntityId(?int $entityId): self
    {
        $this->entityId = $entityId;
        return $this;
    }
    
    public function getData(): ?array
    {
        return $this->data;
    }
    
    public function setData(?array $data): self
    {
        $this->data = $data;
        return $this;
    }
    
    public function isRead(): bool
    {
        return $this->isRead;
    }
    
    public function setIsRead(bool $isRead): self
    {
        $this->isRead = $isRead;
        return $this;
    }
    
    public function getReadAt(): ?DateTime
    {
        return $this->readAt;
    }
    
    public function setReadAt(?DateTime $readAt): self
    {
        $this->readAt = $readAt;
        return $this;
    }
    
    public function getChannel(): string
    {
        return $this->channel;
    }
    
    public function setChannel(string $channel): self
    {
        $this->channel = $channel;
        return $this;
    }
    
    public function isSent(): bool
    {
        return $this->isSent;
    }
    
    public function setIsSent(bool $isSent): self
    {
        $this->isSent = $isSent;
        return $this;
    }
    
    public function getSentAt(): ?DateTime
    {
        return $this->sentAt;
    }
    
    public function setSentAt(?DateTime $sentAt): self
    {
        $this->sentAt = $sentAt;
        return $this;
    }
    
    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }
    
    public function setCreatedAt(?DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }
    
    /**
     * Marca notificação como lida
     */
    public function markAsRead(): self
    {
        $this->isRead = true;
        $this->readAt = new DateTime();
        return $this;
    }
    
    /**
     * Marca notificação como enviada
     */
    public function markAsSent(): self
    {
        $this->isSent = true;
        $this->sentAt = new DateTime();
        return $this;
    }
    
    /**
     * Retorna ícone baseado no tipo
     */
    public function getIcon(): string
    {
        return match ($this->type) {
            self::TYPE_TASK_ASSIGNED => '👤',
            self::TYPE_TASK_COMPLETED => '✅',
            self::TYPE_TASK_OVERDUE => '⚠️',
            self::TYPE_TASK_COMMENT => '💬',
            self::TYPE_PROJECT_STATUS => '📊',
            self::TYPE_MENTION => '@',
            self::TYPE_DEADLINE_APPROACHING => '⏰',
            self::TYPE_SYSTEM => '🔔',
            default => '📌',
        };
    }
    
    /**
     * Retorna cor baseada no tipo
     */
    public function getColor(): string
    {
        return match ($this->type) {
            self::TYPE_TASK_ASSIGNED => '#3B82F6',
            self::TYPE_TASK_COMPLETED => '#10B981',
            self::TYPE_TASK_OVERDUE => '#EF4444',
            self::TYPE_TASK_COMMENT => '#8B5CF6',
            self::TYPE_PROJECT_STATUS => '#F59E0B',
            self::TYPE_MENTION => '#EC4899',
            self::TYPE_DEADLINE_APPROACHING => '#F97316',
            self::TYPE_SYSTEM => '#6B7280',
            default => '#6B7280',
        };
    }
    
    /**
     * Retorna tempo relativo (ex: "2 minutos atrás")
     */
    public function getTimeAgo(): string
    {
        if (!$this->createdAt) {
            return '';
        }
        
        $now = new DateTime();
        $diff = $this->createdAt->diff($now);
        
        if ($diff->y > 0) {
            return $diff->y . ' ano' . ($diff->y > 1 ? 's' : '') . ' atrás';
        }
        if ($diff->m > 0) {
            return $diff->m . ' mês' . ($diff->m > 1 ? 'es' : '') . ' atrás';
        }
        if ($diff->d > 0) {
            return $diff->d . ' dia' . ($diff->d > 1 ? 's' : '') . ' atrás';
        }
        if ($diff->h > 0) {
            return $diff->h . ' hora' . ($diff->h > 1 ? 's' : '') . ' atrás';
        }
        if ($diff->i > 0) {
            return $diff->i . ' min' . ' atrás';
        }
        return 'agora';
    }
    
    /**
     * Converte para array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'data' => $this->data,
            'is_read' => $this->isRead,
            'read_at' => $this->readAt?->format('Y-m-d H:i:s'),
            'channel' => $this->channel,
            'is_sent' => $this->isSent,
            'sent_at' => $this->sentAt?->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'icon' => $this->getIcon(),
            'color' => $this->getColor(),
            'time_ago' => $this->getTimeAgo(),
        ];
    }
}
