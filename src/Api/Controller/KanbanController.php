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
            $projectIdValue = $projectId ? (int) $projectId : null;

            if ($projectIdValue !== null && $projectIdValue > 0) {
                $companyId = $this->resolveCompanyIdForProject($projectIdValue, $companyId);
            }

            if ($companyId <= 0) {
                $response = ApiResponse::success([
                    'boards' => [],
                ]);
                $this->response->json($response->toArray())->send();
                return;
            }
            
            $boards = $this->kanbanService->getAccessibleBoards(
                $companyId,
                $projectIdValue,
                $userId
            );

            $boardsPayload = array_map(
                fn($b) => $this->normalizeBoardPayload($b->toArray()),
                $boards
            );
            
            $response = ApiResponse::success([
                'boards' => $boardsPayload,
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

            $this->response->json(
                ApiResponse::success($this->normalizeBoardEnvelope($board))->toArray()
            )->send();
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
            
            if (!empty($data['unidade_id'])) {
                $data['company_id'] = (int) $data['unidade_id'];
            }

            $projectId = isset($data['project_id']) ? (int) $data['project_id'] : 0;
            if (empty($data['company_id']) && $projectId > 0) {
                $data['company_id'] = $this->resolveCompanyIdForProject($projectId, 0);
            }

            if (empty($data['company_id'])) {
                $data['company_id'] = $this->getCurrentCompanyId();
            }

            $companyId = isset($data['company_id']) ? (int) $data['company_id'] : 0;
            if ($companyId <= 0) {
                $response = ApiResponse::validationError([
                    ...$this->unidadeValidationError('Nao foi possivel identificar a unidade do usuario'),
                ]);
                $this->response->json($response->toArray(), 422)->send();
                return;
            }

            if (!$this->unidadeExists($companyId)) {
                $response = ApiResponse::validationError([
                    ...$this->unidadeValidationError('Unidade informada e invalida'),
                ]);
                $this->response->json($response->toArray(), 422)->send();
                return;
            }

            $data['company_id'] = $companyId;
            
            $board = $this->kanbanService->createBoard($data, $userId);
            
            $this->response->json(ApiResponse::success(
                $this->normalizeBoardPayload($board->toArray()),
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

    /**
     * Keep unidade_id canonical while preserving company_id compatibility.
     *
     * @param array<string, mixed> $board
     * @return array<string, mixed>
     */
    private function normalizeBoardPayload(array $board): array
    {
        $unidadeId = null;
        if (array_key_exists('unidade_id', $board) && $board['unidade_id'] !== null && $board['unidade_id'] !== '') {
            $unidadeId = (int) $board['unidade_id'];
        } elseif (array_key_exists('company_id', $board) && $board['company_id'] !== null && $board['company_id'] !== '') {
            $unidadeId = (int) $board['company_id'];
        }

        if ($unidadeId !== null && $unidadeId > 0) {
            $board['unidade_id'] = $unidadeId;
            $board['company_id'] = $unidadeId;

            if (!isset($board['unidade']) || !is_array($board['unidade'])) {
                $board['unidade'] = ['id' => $unidadeId];
            } elseif (!array_key_exists('id', $board['unidade'])) {
                $board['unidade']['id'] = $unidadeId;
            }

            if (!isset($board['company']) || !is_array($board['company'])) {
                $board['company'] = ['id' => $unidadeId];
            } elseif (!array_key_exists('id', $board['company'])) {
                $board['company']['id'] = $unidadeId;
            }
        }

        return $board;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizeBoardEnvelope(array $payload): array
    {
        if (isset($payload['board']) && is_array($payload['board'])) {
            $payload['board'] = $this->normalizeBoardPayload($payload['board']);
        } elseif (array_key_exists('id', $payload)) {
            $payload = $this->normalizeBoardPayload($payload);
        }

        if (isset($payload['boards']) && is_array($payload['boards'])) {
            $payload['boards'] = array_map(
                fn($board) => is_array($board) ? $this->normalizeBoardPayload($board) : $board,
                $payload['boards']
            );
        }

        return $payload;
    }

    /**
     * @return array<string, string>
     */
    private function unidadeValidationError(string $message): array
    {
        return [
            'unidade_id' => $message,
            'company_id' => $message,
        ];
    }
    
    private function getCurrentUserId(): ?int
    {
        return $this->request->getParam('_user_id');
    }
    
    private function getCurrentCompanyId(): int
    {
        $userId = $this->getCurrentUserId();
        if ($userId === null) {
            return 0;
        }

        $db = \DotProject\Core\Database::getInstance();
        $usersTable = $db->table('users');
        $vinculosTable = $db->table('usuario_unidades');
        $unidadesTable = $db->table('unidades_organizacionais');

        $companyId = (int) ($db->fetchValue(
            sprintf(
                "SELECT u.user_company
                 FROM `%s` u
                 JOIN `%s` un ON un.unidade_id = u.user_company
                 WHERE u.user_id = ? AND u.user_company IS NOT NULL AND u.user_company <> 0
                 LIMIT 1",
                $usersTable,
                $unidadesTable
            ),
            [$userId]
        ) ?? 0);

        if ($companyId > 0) {
            return $companyId;
        }

        $companyId = (int) ($db->fetchValue(
            sprintf(
                "SELECT v.vinculo_unidade_id
                 FROM `%s` v
                 JOIN `%s` un ON un.unidade_id = v.vinculo_unidade_id
                 WHERE v.vinculo_user_id = ?
                   AND v.vinculo_status = 'ativo'
                 ORDER BY v.vinculo_is_principal DESC, v.vinculo_id ASC
                 LIMIT 1",
                $vinculosTable,
                $unidadesTable
            ),
            [$userId]
        ) ?? 0);

        if ($companyId > 0) {
            return $companyId;
        }

        $companyId = (int) ($db->fetchValue(
            sprintf(
                "SELECT un.unidade_id
                 FROM `%s` un
                 WHERE un.unidade_responsavel_id = ?
                   AND un.unidade_status = 'ativo'
                 ORDER BY un.unidade_nivel_id ASC, un.unidade_id ASC
                 LIMIT 1",
                $unidadesTable
            ),
            [$userId]
        ) ?? 0);

        if ($companyId > 0) {
            return $companyId;
        }

        return 0;
    }

    private function unidadeExists(int $unidadeId): bool
    {
        if ($unidadeId <= 0) {
            return false;
        }

        $db = \DotProject\Core\Database::getInstance();
        $unidadesTable = $db->table('unidades_organizacionais');
        $exists = $db->fetchValue(
            sprintf(
                "SELECT unidade_id FROM `%s` WHERE unidade_id = ? LIMIT 1",
                $unidadesTable
            ),
            [$unidadeId]
        );

        return $exists !== null;
    }

    private function resolveCompanyIdForProject(int $projectId, int $fallbackCompanyId = 0): int
    {
        if ($projectId <= 0) {
            return $fallbackCompanyId;
        }

        $db = \DotProject\Core\Database::getInstance();
        $projectsTable = $db->table('projects');
        $companyId = (int) ($db->fetchValue(
            sprintf(
                "SELECT project_company
                 FROM `%s`
                 WHERE project_id = ?
                 LIMIT 1",
                $projectsTable
            ),
            [$projectId]
        ) ?? 0);

        if ($companyId > 0 && $this->unidadeExists($companyId)) {
            return $companyId;
        }

        return $fallbackCompanyId;
    }
}
