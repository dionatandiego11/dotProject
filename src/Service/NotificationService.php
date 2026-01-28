<?php
/**
 * Notification Service
 * 
 * Serviço de notificações multi-canal.
 * 
 * @package DotProject\Service
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Service;

use DotProject\Core\Cache;
use DotProject\Core\DomainEvents;
use DotProject\Core\EventDispatcher;
use DotProject\Core\Logger;
use DotProject\Entity\Notification;
use DotProject\Entity\TaskEntity;
use DotProject\Entity\ProjectEntity;
use DotProject\Entity\UserEntity;
use DotProject\Repository\NotificationRepository;
use DotProject\Repository\UserRepository;

/**
 * Serviço de Notificações
 */
class NotificationService
{
    private NotificationRepository $repository;
    private UserRepository $userRepo;
    private AuthorizationService $auth;
    private EventDispatcher $dispatcher;
    private Cache $cache;
    
    /**
     * Templates de notificação
     */
    private array $templates = [
        Notification::TYPE_TASK_ASSIGNED => [
            'title' => 'Nova tarefa atribuída',
            'message' => 'Você foi atribuído à tarefa "{task_name}" no projeto "{project_name}"',
        ],
        Notification::TYPE_TASK_COMPLETED => [
            'title' => 'Tarefa concluída',
            'message' => 'A tarefa "{task_name}" foi marcada como concluída',
        ],
        Notification::TYPE_TASK_OVERDUE => [
            'title' => 'Tarefa atrasada',
            'message' => 'A tarefa "{task_name}" está atrasada desde {due_date}',
        ],
        Notification::TYPE_TASK_COMMENT => [
            'title' => 'Novo comentário',
            'message' => '{user_name} comentou na tarefa "{task_name}"',
        ],
        Notification::TYPE_PROJECT_STATUS => [
            'title' => 'Status do projeto alterado',
            'message' => 'O projeto "{project_name}" mudou para "{new_status}"',
        ],
        Notification::TYPE_MENTION => [
            'title' => 'Você foi mencionado',
            'message' => '{user_name} mencionou você em "{entity_name}"',
        ],
        Notification::TYPE_DEADLINE_APPROACHING => [
            'title' => 'Prazo se aproximando',
            'message' => 'A tarefa "{task_name}" vence em {days} dias',
        ],
    ];
    
    public function __construct(
        ?NotificationRepository $repository = null,
        ?UserRepository $userRepo = null,
        ?AuthorizationService $auth = null,
        ?EventDispatcher $dispatcher = null,
        ?Cache $cache = null
    ) {
        $this->repository = $repository ?? new NotificationRepository();
        $this->userRepo = $userRepo ?? new UserRepository();
        $this->auth = $auth ?? AuthorizationService::getInstance();
        $this->dispatcher = $dispatcher ?? EventDispatcher::getInstance();
        $this->cache = $cache ?? new Cache(prefix: 'notifications:');
        
        $this->registerEventListeners();
    }
    
    /**
     * Registra listeners de eventos
     */
    private function registerEventListeners(): void
    {
        // Task events
        $this->dispatcher->on(DomainEvents::TASK_ASSIGNED, [$this, 'onTaskAssigned']);
        $this->dispatcher->on(DomainEvents::TASK_COMPLETED, [$this, 'onTaskCompleted']);
        $this->dispatcher->on(DomainEvents::TASK_OVERDUE, [$this, 'onTaskOverdue']);
        
        // Project events
        $this->dispatcher->on(DomainEvents::PROJECT_STATUS_CHANGED, [$this, 'onProjectStatusChanged']);
        
        // User events
        $this->dispatcher->on(DomainEvents::USER_LOGIN, [$this, 'onUserLogin']);
    }
    
    // =====================================================
    // CREATE NOTIFICATIONS
    // =====================================================
    
    /**
     * Cria notificação
     */
    public function create(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $data = null,
        string $channel = Notification::CHANNEL_IN_APP
    ): ?Notification {
        $notification = new Notification();
        $notification->setUserId($userId);
        $notification->setType($type);
        $notification->setTitle($title);
        $notification->setMessage($message);
        $notification->setEntityType($entityType);
        $notification->setEntityId($entityId);
        $notification->setData($data);
        $notification->setChannel($channel);
        $notification->setIsRead(false);
        $notification->setIsSent(false);
        
        if ($this->repository->save($notification)) {
            // Limpa cache de contagem
            $this->cache->delete("notifications:unread:{$userId}");
            
            Logger::info('Notification created', [
                'user_id' => $userId,
                'type' => $type,
            ]);
            
            return $notification;
        }
        
        return null;
    }
    
    /**
     * Cria notificação a partir de template
     */
    public function createFromTemplate(
        int $userId,
        string $type,
        array $variables = [],
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $data = null,
        string $channel = Notification::CHANNEL_IN_APP
    ): ?Notification {
        if (!isset($this->templates[$type])) {
            throw new \InvalidArgumentException("Unknown notification type: {$type}");
        }
        
        $template = $this->templates[$type];
        
        $title = $this->replaceVariables($template['title'], $variables);
        $message = $this->replaceVariables($template['message'], $variables);
        
        return $this->create(
            $userId,
            $type,
            $title,
            $message,
            $entityType,
            $entityId,
            array_merge($variables, $data ?? []),
            $channel
        );
    }
    
    /**
     * Substitui variáveis no template
     */
    private function replaceVariables(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $text = str_replace('{' . $key . '}', (string) $value, $text);
        }
        return $text;
    }
    
    /**
     * Notifica múltiplos usuários
     */
    public function notifyMultiple(
        array $userIds,
        string $type,
        string $title,
        string $message,
        ?string $entityType = null,
        ?int $entityId = null
    ): int {
        $count = 0;
        
        foreach ($userIds as $userId) {
            if ($this->create($userId, $type, $title, $message, $entityType, $entityId)) {
                $count++;
            }
        }
        
        return $count;
    }
    
    // =====================================================
    // GET NOTIFICATIONS
    // =====================================================
    
    /**
     * Obtém notificações de um usuário
     */
    public function getUserNotifications(
        int $userId,
        bool $unreadOnly = false,
        ?int $limit = 50
    ): array {
        return $this->repository->findByUser($userId, $unreadOnly, $limit);
    }
    
    /**
     * Conta notificações não lidas
     */
    public function getUnreadCount(int $userId): int
    {
        return $this->repository->countUnread($userId);
    }
    
    /**
     * Obtém estatísticas
     */
    public function getStats(int $userId): array
    {
        return $this->repository->getStats($userId);
    }
    
    // =====================================================
    // MARK AS READ
    // =====================================================
    
    /**
     * Marca notificação como lida
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        $notification = $this->repository->find($notificationId);
        
        if (!$notification || $notification->getUserId() !== $userId) {
            return false;
        }
        
        $notification->markAsRead();
        
        $result = $this->repository->save($notification);
        
        if ($result) {
            $this->cache->delete("notifications:unread:{$userId}");
        }
        
        return $result;
    }
    
    /**
     * Marca todas como lidas
     */
    public function markAllAsRead(int $userId): bool
    {
        $result = $this->repository->markAllAsRead($userId);
        
        if ($result) {
            $this->cache->delete("notifications:unread:{$userId}");
        }
        
        return $result;
    }
    
    // =====================================================
    // EVENT HANDLERS
    // =====================================================
    
    /**
     * Handler: Tarefa atribuída
     */
    public function onTaskAssigned($event): void
    {
        $task = $event->getTask();
        $assignedTo = $event->getAssignedTo();
        $assignedBy = $event->getAssignedBy();
        
        // Notifica quem foi atribuído
        $this->createFromTemplate(
            $assignedTo,
            Notification::TYPE_TASK_ASSIGNED,
            [
                'task_name' => $task->getName(),
                'project_name' => $this->getProjectName($task->getProjectId()),
                'task_id' => $task->getId(),
            ],
            'task',
            $task->getId()
        );
        
        Logger::info('Notification sent: task assigned', [
            'task_id' => $task->getId(),
            'assigned_to' => $assignedTo,
        ]);
    }
    
    /**
     * Handler: Tarefa concluída
     */
    public function onTaskCompleted($event): void
    {
        $task = $event->getTask();
        $userId = $event->getUserId();
        
        // Notifica o criador da tarefa (se diferente de quem completou)
        if ($task->getOwnerId() && $task->getOwnerId() !== $userId) {
            $this->createFromTemplate(
                $task->getOwnerId(),
                Notification::TYPE_TASK_COMPLETED,
                [
                    'task_name' => $task->getName(),
                    'completed_by' => $this->getUserName($userId),
                ],
                'task',
                $task->getId()
            );
        }
    }
    
    /**
     * Handler: Tarefa atrasada
     */
    public function onTaskOverdue($event): void
    {
        $task = $event->getTask();
        
        // Notifica assignee
        if ($task->getAssignedTo()) {
            $this->createFromTemplate(
                $task->getAssignedTo(),
                Notification::TYPE_TASK_OVERDUE,
                [
                    'task_name' => $task->getName(),
                    'due_date' => $task->getEndDate()?->format('d/m/Y'),
                ],
                'task',
                $task->getId()
            );
        }
        
        // Notifica owner
        if ($task->getOwnerId() && $task->getOwnerId() !== $task->getAssignedTo()) {
            $this->createFromTemplate(
                $task->getOwnerId(),
                Notification::TYPE_TASK_OVERDUE,
                [
                    'task_name' => $task->getName(),
                    'due_date' => $task->getEndDate()?->format('d/m/Y'),
                ],
                'task',
                $task->getId()
            );
        }
    }
    
    /**
     * Handler: Status do projeto alterado
     */
    public function onProjectStatusChanged($event): void
    {
        $project = $event->getProject();
        $changedFields = $event->getChangedFields();
        
        if (!in_array('project_status', $changedFields ?? [])) {
            return;
        }
        
        // Notifica todos do projeto (implementar lógica de equipe)
        // Placeholder para notificação em massa
    }
    
    /**
     * Handler: Login do usuário
     */
    public function onUserLogin($event): void
    {
        // Limpa notificações antigas
        $userId = $event->getUserId();
        $this->repository->deleteOld(90); // Mantém 90 dias
    }
    
    // =====================================================
    // HELPER METHODS
    // =====================================================
    
    private function getProjectName(int $projectId): string
    {
        // Implementar cache
        return "Project #{$projectId}";
    }
    
    private function getUserName(int $userId): string
    {
        $user = $this->userRepo->find($userId);
        return $user?->getFullName() ?? "User #{$userId}";
    }
    
    // =====================================================
    // EMAIL NOTIFICATIONS (Async)
    // =====================================================
    
    /**
     * Processa notificações pendentes de email
     */
    public function processEmailQueue(int $batchSize = 100): int
    {
        $pending = $this->repository->findPending(Notification::CHANNEL_EMAIL, $batchSize);
        
        $sent = [];
        foreach ($pending as $notification) {
            // Aqui integraria com serviço de email
            // Por enquanto apenas marca como enviado
            $sent[] = $notification->getId();
        }
        
        if (!empty($sent)) {
            $this->repository->markAsSent($sent);
        }
        
        return count($sent);
    }
    
    // =====================================================
    // SCHEDULED NOTIFICATIONS
    // =====================================================
    
    /**
     * Envia alertas de prazo se aproximando
     * Deve ser chamado por cron job diário
     */
    public function sendDeadlineAlerts(): int
    {
        // Busca tarefas com prazo em 1, 2 ou 3 dias
        $sql = "SELECT t.*, u.user_id as assigned_to 
                FROM dotp_tasks t
                JOIN dotp_user_tasks ut ON ut.task_id = t.task_id
                JOIN dotp_users u ON u.user_id = ut.user_id
                WHERE t.task_end_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY)
                AND t.task_percent_complete < 100
                AND t.task_status = 0";
        
        $tasks = $this->db->fetchAll($sql);
        
        $count = 0;
        foreach ($tasks as $taskData) {
            $days = (int) ((strtotime($taskData['task_end_date']) - time()) / 86400);
            
            $this->createFromTemplate(
                $taskData['assigned_to'],
                Notification::TYPE_DEADLINE_APPROACHING,
                [
                    'task_name' => $taskData['task_name'],
                    'days' => max(1, $days),
                ],
                'task',
                $taskData['task_id']
            );
            
            $count++;
        }
        
        return $count;
    }
}
