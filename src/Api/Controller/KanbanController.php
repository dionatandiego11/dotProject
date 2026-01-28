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
            
            $this->response->json($response->toArray())->send();
        } catch (\Exception $e) {
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
            $userId = $this->getCurrentUserId();
            
            $board = $this->kanbanService->getBoard($id, $userId);
            
            if (!$board) {
                $response = ApiResponse::notFound('Board');
                $this->response->json($response->toArray(), 404)->send();
                return;
            }
            
            $this->response->json(ApiResponse::success($board)->toArray())->send();
        } catch (\Exception $e) {
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
        } catch (\Exception $e) {
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
        // Placeholder
        return 1;
    }
}
