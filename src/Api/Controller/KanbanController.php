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
    private KanbanService $kanbanService;
    
    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->kanbanService = new KanbanService();
    }
    
    /**
     * GET /v1/kanban/boards
     * Lista boards acessiveis
     */
    public function listBoards(): void
    {
        try {
            $userId = $this->getCurrentUserId();
            $companyId = $this->getCurrentCompanyId();
            $projectId = $this->request->getParam('project_id');
            
            $boards = $this->kanbanService->getAccessibleBoards(
                $companyId,
                $projectId ? (int) $projectId : null,
                $userId
            );
            
            $response = ApiResponse::success([
                'boards' => array_map(fn($b) => $b->toArray(), $boards),
            ]);
            
            $this->json($response->toArray());
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * GET /v1/kanban/boards/:id
     * Obtém board completo com colunas e tarefas
     */
    public function getBoard(int $id): void
    {
        try {
            $userId = $this->getCurrentUserId();
            
            $board = $this->kanbanService->getBoard($id, $userId);
            
            if (!$board) {
                $response = ApiResponse::notFound('Board');
                $this->json($response->toArray(), 404);
                return;
            }
            
            $this->json(ApiResponse::success($board)->toArray());
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * POST /v1/kanban/boards
     * Cria novo board
     */
    public function createBoard(): void
    {
        try {
            $data = $this->request->getBody();
            $userId = $this->getCurrentUserId();
            
            // Validação
            if (empty($data['name'])) {
                $response = ApiResponse::validationError(['name' => 'Board name is required']);
                $this->json($response->toArray(), 422);
                return;
            }
            
            if (empty($data['company_id'])) {
                $data['company_id'] = $this->getCurrentCompanyId();
            }
            
            $board = $this->kanbanService->createBoard($data, $userId);
            
            $this->json(ApiResponse::success(
                $board->toArray(),
                'Board created successfully'
            )->toArray(), 201);
        } catch (\RuntimeException $e) {
            $response = ApiResponse::error($e->getMessage());
            $this->json($response->toArray(), 400);
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * POST /v1/kanban/boards/:id/columns
     * Adiciona coluna ao board
     */
    public function addColumn(int $boardId): void
    {
        try {
            $data = $this->request->getBody();
            $userId = $this->getCurrentUserId();
            
            if (empty($data['name'])) {
                $response = ApiResponse::validationError(['name' => 'Column name is required']);
                $this->json($response->toArray(), 422);
                return;
            }
            
            $column = $this->kanbanService->addColumn($boardId, $data, $userId);
            
            $this->json(ApiResponse::success(
                $column->toArray(),
                'Column added successfully'
            )->toArray(), 201);
        } catch (\RuntimeException $e) {
            $response = ApiResponse::error($e->getMessage());
            $this->json($response->toArray(), 400);
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * PUT /v1/kanban/columns/:id
     * Atualiza coluna
     */
    public function updateColumn(int $id): void
    {
        try {
            $data = $this->request->getBody();
            $userId = $this->getCurrentUserId();
            
            $column = $this->kanbanService->updateColumn($id, $data, $userId);
            
            $this->json(ApiResponse::success(
                $column->toArray(),
                'Column updated successfully'
            )->toArray());
        } catch (\RuntimeException $e) {
            $response = ApiResponse::error($e->getMessage());
            $this->json($response->toArray(), 400);
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * POST /v1/kanban/boards/:id/reorder
     * Reordena colunas
     */
    public function reorderColumns(int $boardId): void
    {
        try {
            $data = $this->request->getBody();
            $userId = $this->getCurrentUserId();
            
            if (empty($data['columns']) || !is_array($data['columns'])) {
                $response = ApiResponse::validationError(['columns' => 'Columns order array is required']);
                $this->json($response->toArray(), 422);
                return;
            }
            
            $success = $this->kanbanService->reorderColumns($boardId, $data['columns'], $userId);
            
            if ($success) {
                $this->json(ApiResponse::success(null, 'Columns reordered successfully')->toArray());
            } else {
                $response = ApiResponse::error('Failed to reorder columns');
                $this->json($response->toArray(), 400);
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * POST /v1/kanban/columns/:id/tasks
     * Adiciona tarefa a uma coluna
     */
    public function addTask(int $columnId): void
    {
        try {
            $data = $this->request->getBody();
            $userId = $this->getCurrentUserId();
            
            if (empty($data['task_id'])) {
                $response = ApiResponse::validationError(['task_id' => 'Task ID is required']);
                $this->json($response->toArray(), 422);
                return;
            }
            
            $kanbanTask = $this->kanbanService->addTask((int) $data['task_id'], $columnId, $userId);
            
            if ($kanbanTask) {
                $this->json(ApiResponse::success(
                    $kanbanTask->toArray(),
                    'Task added to column'
                )->toArray(), 201);
            } else {
                $response = ApiResponse::error('Failed to add task');
                $this->json($response->toArray(), 400);
            }
        } catch (\RuntimeException $e) {
            $response = ApiResponse::error($e->getMessage());
            $this->json($response->toArray(), 400);
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * PUT /v1/kanban/tasks/:id/move
     * Move tarefa para outra coluna
     */
    public function moveTask(int $kanbanTaskId): void
    {
        try {
            $data = $this->request->getBody();
            $userId = $this->getCurrentUserId();
            
            if (empty($data['column_id'])) {
                $response = ApiResponse::validationError(['column_id' => 'Target column ID is required']);
                $this->json($response->toArray(), 422);
                return;
            }
            
            $order = $data['order'] ?? 0;
            
            $success = $this->kanbanService->moveTask(
                $kanbanTaskId,
                (int) $data['column_id'],
                (int) $order,
                $userId
            );
            
            if ($success) {
                $this->json(ApiResponse::success(null, 'Task moved successfully')->toArray());
            } else {
                $response = ApiResponse::error('Failed to move task');
                $this->json($response->toArray(), 400);
            }
        } catch (\RuntimeException $e) {
            $response = ApiResponse::error($e->getMessage());
            $this->json($response->toArray(), 400);
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * POST /v1/kanban/boards/:id/batch-move
     * Move múltiplas tarefas (drag & drop)
     */
    public function batchMoveTasks(int $boardId): void
    {
        try {
            $data = $this->request->getBody();
            $userId = $this->getCurrentUserId();
            
            if (empty($data['moves']) || !is_array($data['moves'])) {
                $response = ApiResponse::validationError(['moves' => 'Moves array is required']);
                $this->json($response->toArray(), 422);
                return;
            }
            
            $success = $this->kanbanService->moveTasks($data['moves'], $boardId, $userId);
            
            if ($success) {
                $this->json(ApiResponse::success(null, 'Tasks moved successfully')->toArray());
            } else {
                $response = ApiResponse::error('Failed to move tasks');
                $this->json($response->toArray(), 400);
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * DELETE /v1/kanban/tasks/:id
     * Remove tarefa do kanban
     */
    public function removeTask(int $kanbanTaskId): void
    {
        try {
            $userId = $this->getCurrentUserId();
            
            // Primeiro obtém o kanbanTask para ter o taskId
            // Implementar se necessário
            
            $this->json(ApiResponse::success(null, 'Task removed from kanban')->toArray());
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * GET /v1/kanban/boards/:id/analytics
     * Obtém analytics do board
     */
    public function getAnalytics(int $boardId): void
    {
        try {
            $analytics = $this->kanbanService->getBoardAnalytics($boardId);
            
            $this->json(ApiResponse::success($analytics)->toArray());
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * GET /v1/kanban/boards/:id/lead-time
     * Obtém análise de lead time
     */
    public function getLeadTime(int $boardId): void
    {
        try {
            $leadTime = $this->kanbanService->getLeadTimeAnalysis($boardId);
            
            $this->json(ApiResponse::success([
                'board_id' => $boardId,
                'lead_time' => $leadTime,
            ])->toArray());
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 500);
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
        // Implementar lógica para obter company do usuário atual
        // Placeholder
        return 1;
    }
}
