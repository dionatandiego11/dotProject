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
            
            $this->response->json($response->toArray())->send();
        } catch (\Exception $e) {
            $this->response->error($e->getMessage(), 500)->send();
        }
    }
    
    /**
     * GET /v1/notifications/unread-count
     * Conta notificações não lidas
     */
    public function count(): void
    {
        try {
            $userId = $this->getCurrentUserId();
            $count = $this->service->getUnreadCount($userId);
            
            $this->response->json(ApiResponse::success([
                'unread_count' => $count,
            ])->toArray())->send();
        } catch (\Exception $e) {
            $this->response->error($e->getMessage(), 500)->send();
        }
    }
    
    /**
     * POST /v1/notifications/:id/read
     * Marca notificação como lida
     */
    public function markAsRead(int $id): void
    {
        try {
            $userId = $this->getCurrentUserId();
            
            $result = $this->service->markAsRead($id, $userId);
            
            if ($result) {
                $this->response->json(ApiResponse::success(null, 'Notification marked as read')->toArray())->send();
            } else {
                $response = ApiResponse::error('Notification not found');
                $this->response->json($response->toArray(), 404)->send();
            }
        } catch (\Exception $e) {
            $this->response->error($e->getMessage(), 500)->send();
        }
    }
    
    /**
     * POST /v1/notifications/mark-all-read
     * Marca todas como lidas
     */
    public function markAllAsRead(): void
    {
        try {
            $userId = $this->getCurrentUserId();
            
            $this->service->markAllAsRead($userId);
            
            $this->response->json(ApiResponse::success(null, 'All notifications marked as read')->toArray())->send();
        } catch (\Exception $e) {
            $this->response->error($e->getMessage(), 500)->send();
        }
    }
    
    private function getCurrentUserId(): ?int
    {
        return $this->request->getParam('_user_id');
    }
}
