<?php
/**
 * Kanban Controller
 * 
 * Controller para API de Kanban.
 * 
 * @package DotProject\Api\Controller
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Api\Controller;

use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Dto\ApiResponse;
use DotProject\Service\AuthorizationService;
use DotProject\Service\KanbanService;

/**
 * Controller Kanban
 */
class KanbanController extends BaseController
{
    private ?KanbanService $kanbanService = null;
    
    /**
     * GET /v1/kanban/boards
     * Lista boards acessiveis
     */
    public function listBoards(): void
    {
        try {
            $this->kanbanService = $this->kanbanService ?? new KanbanService();
            $userId = $this->getCurrentUserId();
            $companyId = $this->getCurrentCompanyId();
            $projectId = $this->request->getQueryParam('project_id');
            
            $boards = $this->kanbanService->getAccessibleBoards(
                $companyId,
                $projectId ? (int) $projectId : null,
                $userId
            );
            
            $response = ApiResponse::success([
                'boards' => array_map(fn($b) => $b->toArray(), $boards),
            ]);
            
            $this->response->json($response->toArray())->send();
        } catch (\Throwable $e) {
            $this->response->error($e->getMessage(), 500)->send();
        }
    }
    
    /**
     * GET /v1/kanban/boards/:id
     * Obtém board completo com colunas e tarefas
     */
    public function getBoard(int $id): void
    {
        try {
            $this->kanbanService = $this->kanbanService ?? new KanbanService();
            $userId = $this->getCurrentUserId();
            
            $board = $this->kanbanService->getBoard($id, $userId);
            
            if (!$board) {
                $response = ApiResponse::notFound('Board');
                $this->response->json($response->toArray(), 404)->send();
                return;
            }
            
            $this->response->json(ApiResponse::success($board)->toArray())->send();
        } catch (\Throwable $e) {
            $this->response->error($e->getMessage(), 500)->send();
        }
    }
    
    /**
     * POST /v1/kanban/boards
     * Cria novo board
     */
    public function createBoard(): void
    {
        try {
            $this->kanbanService = $this->kanbanService ?? new KanbanService();
            $data = $this->request->getBody();
            $userId = $this->getCurrentUserId();
            
            // Validação
            if (empty($data['name'])) {
                $response = ApiResponse::validationError(['name' => 'Board name is required']);
                $this->response->json($response->toArray(), 422)->send();
                return;
            }
            
            if (empty($data['company_id'])) {
                $data['company_id'] = $this->getCurrentCompanyId();
            }
            
            $board = $this->kanbanService->createBoard($data, $userId);
            
            $this->response->json(ApiResponse::success(
                $board->toArray(),
                'Board created successfully'
            )->toArray(), 201)->send();
        } catch (\RuntimeException $e) {
            $response = ApiResponse::error($e->getMessage());
            $this->response->json($response->toArray(), 400)->send();
        } catch (\Throwable $e) {
            $this->response->error($e->getMessage(), 500)->send();
        }
    }

    /**
     * POST /v1/kanban/boards/{id}/columns
     * Cria coluna no board
     */
    public function addColumn(int $boardId): void
    {
        try {
            $this->kanbanService = $this->kanbanService ?? new KanbanService();
            $data = $this->request->getBody();
            if (empty($data['name'])) {
                $response = ApiResponse::validationError(['name' => 'Column name is required']);
                $this->response->json($response->toArray(), 422)->send();
                return;
            }
            
            $column = $this->kanbanService->addColumn($boardId, $data);
            
            $this->response->json(ApiResponse::success(
                $column->toArray(),
                'Column created successfully'
            )->toArray(), 201)->send();
        } catch (\RuntimeException $e) {
            $response = ApiResponse::error($e->getMessage());
            $this->response->json($response->toArray(), 400)->send();
        } catch (\Throwable $e) {
            $this->response->error($e->getMessage(), 500)->send();
        }
    }

    /**
     * PUT /v1/kanban/columns/{id}
     * Atualiza coluna
     */
    public function updateColumn(int $columnId): void
    {
        try {
            $this->kanbanService = $this->kanbanService ?? new KanbanService();
            $data = $this->request->getBody();
            $column = $this->kanbanService->updateColumn($columnId, $data);
            
            if (!$column) {
                $response = ApiResponse::notFound('Column');
                $this->response->json($response->toArray(), 404)->send();
                return;
            }
            
            $this->response->json(ApiResponse::success(
                $column->toArray(),
                'Column updated successfully'
            )->toArray())->send();
        } catch (\RuntimeException $e) {
            $response = ApiResponse::error($e->getMessage());
            $this->response->json($response->toArray(), 400)->send();
        } catch (\Throwable $e) {
            $this->response->error($e->getMessage(), 500)->send();
        }
    }

    /**
     * PUT /v1/kanban/tasks/{id}/move
     * Move tarefa entre colunas
     */
    public function moveTask(int $kanbanTaskId): void
    {
        try {
            $this->kanbanService = $this->kanbanService ?? new KanbanService();
            $data = $this->request->getBody();
            $targetColumnId = (int) ($data['column_id'] ?? 0);
            $order = (int) ($data['order'] ?? 0);
            $userId = $this->getCurrentUserId();
            
            if ($targetColumnId <= 0) {
                $response = ApiResponse::validationError(['column_id' => 'Target column is required']);
                $this->response->json($response->toArray(), 422)->send();
                return;
            }
            
            $ok = $this->kanbanService->moveTask($kanbanTaskId, $targetColumnId, $order, $userId);
            
            if (!$ok) {
                $response = ApiResponse::error('Failed to move task');
                $this->response->json($response->toArray(), 400)->send();
                return;
            }
            
            $this->response->json(ApiResponse::success(
                ['id' => $kanbanTaskId],
                'Task moved successfully'
            )->toArray())->send();
        } catch (\Throwable $e) {
            $this->response->error($e->getMessage(), 500)->send();
        }
    }

    /**
     * GET /v1/kanban/boards/{id}/analytics
     * Retorna estatisticas do board
     */
    public function getAnalytics(int $boardId): void
    {
        try {
            $this->kanbanService = $this->kanbanService ?? new KanbanService();
            $stats = $this->kanbanService->getAnalytics($boardId);
            $this->response->json(ApiResponse::success($stats)->toArray())->send();
        } catch (\Throwable $e) {
            $this->response->error($e->getMessage(), 500)->send();
        }
    }
    
    // =====================================================
    // HELPERS
    // =====================================================
    
    private function getCurrentUserId(): ?int
    {
        return $this->request->getParam('_user_id');
    }
    
    private function getCurrentCompanyId(): int
    {
        $userId = $this->getCurrentUserId();
        if ($userId === null) {
            return 1;
        }

        $db = \DotProject\Core\Database::getInstance();
        $companyId = $db->fetchValue(
            sprintf("SELECT user_company FROM `%s` WHERE user_id = %d", $db->table('users'), $userId)
        );

        return $companyId ? (int) $companyId : 1;
    }
}
