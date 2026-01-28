<?php
/**
 * Notification Controller
 * 
 * Controller para API de notificações.
 * 
 * @package DotProject\Api\Controller
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Api\Controller;

use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Dto\ApiResponse;
use DotProject\Service\NotificationService;

/**
 * Controller de Notificações
 */
class NotificationController extends BaseController
{
    private NotificationService $service;
    
    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->service = new NotificationService();
    }
    
    /**
     * GET /v1/notifications
     * Lista notificações do usuário
     */
    public function list(): void
    {
        try {
            $userId = $this->getCurrentUserId();
            $unreadOnly = $this->request->getParam('unread') === 'true';
            $limit = (int) ($this->request->getParam('limit') ?? 50);
            
            $notifications = $this->service->getUserNotifications($userId, $unreadOnly, $limit);
            
            $response = ApiResponse::success([
                'notifications' => array_map(fn($n) => $n->toArray(), $notifications),
                'unread_count' => $this->service->getUnreadCount($userId),
            ]);
            
            $this->json($response->toArray());
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * GET /v1/notifications/count
     * Conta notificações não lidas
     */
    public function count(): void
    {
        try {
            $userId = $this->getCurrentUserId();
            $count = $this->service->getUnreadCount($userId);
            
            $this->json(ApiResponse::success([
                'unread_count' => $count,
            ])->toArray());
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * PUT /v1/notifications/:id/read
     * Marca notificação como lida
     */
    public function markAsRead(int $id): void
    {
        try {
            $userId = $this->getCurrentUserId();
            
            $result = $this->service->markAsRead($id, $userId);
            
            if ($result) {
                $this->json(ApiResponse::success(null, 'Notification marked as read')->toArray());
            } else {
                $response = ApiResponse::error('Notification not found');
                $this->json($response->toArray(), 404);
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * PUT /v1/notifications/read-all
     * Marca todas como lidas
     */
    public function markAllAsRead(): void
    {
        try {
            $userId = $this->getCurrentUserId();
            
            $this->service->markAllAsRead($userId);
            
            $this->json(ApiResponse::success(null, 'All notifications marked as read')->toArray());
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * GET /v1/notifications/stats
     * Estatísticas de notificações
     */
    public function stats(): void
    {
        try {
            $userId = $this->getCurrentUserId();
            
            $stats = $this->service->getStats($userId);
            
            $this->json(ApiResponse::success($stats)->toArray());
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * POST /v1/notifications
     * Cria notificação (admin/system)
     */
    public function create(): void
    {
        try {
            $data = $this->request->getBody();
            $userId = $this->getCurrentUserId();
            
            // Validação básica
            if (empty($data['user_id']) || empty($data['type']) || empty($data['title'])) {
                $response = ApiResponse::validationError([
                    'user_id' => 'Required',
                    'type' => 'Required',
                    'title' => 'Required',
                ]);
                $this->json($response->toArray(), 422);
                return;
            }
            
            $notification = $this->service->create(
                (int) $data['user_id'],
                $data['type'],
                $data['title'],
                $data['message'] ?? '',
                $data['entity_type'] ?? null,
                $data['entity_id'] ?? null,
                $data['data'] ?? null,
                $data['channel'] ?? 'in_app'
            );
            
            if ($notification) {
                $this->json(ApiResponse::success(
                    $notification->toArray(),
                    'Notification created'
                )->toArray(), 201);
            } else {
                $response = ApiResponse::error('Failed to create notification');
                $this->json($response->toArray(), 400);
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }
    
    private function getCurrentUserId(): ?int
    {
        return $this->request->getParam('_user_id');
    }
}
