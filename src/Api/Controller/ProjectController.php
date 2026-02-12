<?php
/**
 * DotProject Project Controller
 * 
 * Controller para gerenciamento de projetos na API.
 * 
 * @package DotProject\Api\Controller
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Api\Controller;

use DotProject\Api\Formatter\ProjectFormatter;
use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Core\Database;
use DotProject\Core\TenantAwareTrait;
use DotProject\Entity\ProjectEntity;
use DotProject\Repository\ProjectRepository;
use DotProject\Service\AuthorizationService;
use DotProject\Service\PermissionService;
use DotProject\Service\ProjectService;
use DotProject\Service\ProjectMutationService;
use DotProject\Service\ProjectStatusHistoryService;

/**
 * Controller de projetos
 */
class ProjectController extends BaseController
{
    use TenantAwareTrait;

    private ?ProjectStatusHistoryService $statusHistoryService = null;
    private ?ProjectRepository $projectRepository = null;
    private ?ProjectService $projectService = null;
    private ?ProjectMutationService $projectMutationService = null;
    private ?ProjectFormatter $projectFormatter = null;

    /**
     * GET /v1/projects
     * 
     * Lista todos os projetos com paginação
     */
    public function index(): Response
    {
        if (!$this->checkPermission('projects', 'view')) {
            return $this->response->forbidden('Insufficient permissions');
        }

        $userId = $this->getUserId();
        $auth = AuthorizationService::getInstance();
        $perm = new PermissionService();

        $pagination = $this->getPagination();

        // Filtros opcionais
        $unidadeId = $this->resolveUnidadeQueryParam();
        $status = $this->request->getQueryParam('status');
        $search = $this->request->getQueryParam('search');

        // Monta a query
        $where = '1=1';
        $params = [];

        if ($userId !== null && !$auth->isAdmin($userId)) {
            $escopo = $perm->getEscopoDados($userId);
            if (!$escopo) {
                return $this->response->paginated([], 0, $pagination['page'], $pagination['per_page']);
            }
            if ($escopo['role'] !== PermissionService::ROLE_PREFEITO) {
                $unidades = $escopo['unidades_escopo'];
                if (empty($unidades)) {
                    return $this->response->paginated([], 0, $pagination['page'], $pagination['per_page']);
                }
                $placeholders = implode(',', array_fill(0, count($unidades), '?'));
                $where .= " AND project_company IN ({$placeholders})";
                $params = array_merge($params, $unidades);
            }
        }

        if ($unidadeId !== null) {
            $where .= ' AND project_company = ?';
            $params[] = $unidadeId;
        }

        if ($status !== null) {
            $where .= ' AND project_status = ?';
            $params[] = (int) $status;
        }

        if ($search !== null) {
            $where .= " AND (project_name LIKE ? OR project_description LIKE ?)";
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $totalWhere = $where . $this->tenantAndCondition($this->db->table('projects'));
        $listWhere = $where . $this->tenantAndCondition($this->db->table('projects'), 'p');

        // Conta total
        $totalSql = sprintf(
            "SELECT COUNT(*) as total FROM %s WHERE %s",
            $this->db->table('projects'),
            $totalWhere
        );
        $total = (int) ($this->db->fetchValueParams($totalSql, $params) ?? 0);

        // Busca projetos
        $sql = sprintf(
            "SELECT p.*, c.company_name, u.unidade_nome 
             FROM %s p 
             LEFT JOIN %s c ON p.project_company = c.company_id%s
             LEFT JOIN dotp_unidades_organizacionais u ON p.project_company = u.unidade_id%s
             WHERE %s
             ORDER BY p.project_name ASC
             LIMIT ? OFFSET ?",
            $this->db->table('projects'),
            $this->db->table('companies'),
            $this->tenantAndCondition($this->db->table('companies'), 'c'),
            $this->tenantAndCondition('dotp_unidades_organizacionais', 'u'),
            $listWhere
        );
        $rows = $this->db->fetchAllParams($sql, array_merge($params, [
            $pagination['per_page'],
            $pagination['offset'],
        ]));

        $projects = array_map(fn($row) => $this->projectFormatter()->formatProject($row), $rows);

        return $this->response->paginated(
            $projects,
            $total,
            $pagination['page'],
            $pagination['per_page']
        );
    }

    /**
     * GET /v1/projects/{id}
     * 
     * Retorna um projeto específico
     */
    public function show(): Response
    {
        $id = (int) $this->request->getParam('id');

        if (!$this->checkPermission('projects', 'view')) {
            return $this->response->forbidden('Insufficient permissions');
        }

        if ($guard = $this->ensureProjectAccess($id)) {
            return $guard;
        }

        $sql = sprintf(
            "SELECT p.*, c.company_name, u.unidade_nome 
             FROM %s p 
             LEFT JOIN %s c ON p.project_company = c.company_id%s
             LEFT JOIN dotp_unidades_organizacionais u ON p.project_company = u.unidade_id%s
             WHERE p.project_id = %d%s",
            $this->db->table('projects'),
            $this->db->table('companies'),
            $this->tenantAndCondition($this->db->table('companies'), 'c'),
            $this->tenantAndCondition('dotp_unidades_organizacionais', 'u'),
            $id,
            $this->tenantAndCondition($this->db->table('projects'), 'p')
        );

        $row = $this->db->fetchOne($sql);

        if ($row === null) {
            return $this->notFound('Project not found');
        }

        return $this->json($this->projectFormatter()->formatProject($row, true));
    }

    /**
     * POST /v1/projects
     * 
     * Cria um novo projeto
     */
    public function store(): Response
    {
        if (!$this->checkPermission('projects', 'add')) {
            return $this->response->forbidden('Insufficient permissions');
        }

        $result = $this->projectMutationService()->create($this->request->getBody(), $this->getUserId());
        return $this->mutationResponse($result);
    }

    /**
     * PUT /v1/projects/{id}
     * 
     * Atualiza um projeto
     */
    public function update(): Response
    {
        $id = (int) $this->request->getParam('id');

        if (!$this->checkPermission('projects', 'edit')) {
            return $this->response->forbidden('Insufficient permissions');
        }

        if ($guard = $this->ensureProjectAccess($id)) {
            return $guard;
        }

        $project = $this->projectRepository()->find($id);
        if ($project === null) {
            return $this->notFound('Project not found');
        }

        $result = $this->projectMutationService()->update($project, $id, $this->request->getBody(), $this->getUserId());
        return $this->mutationResponse($result);
    }

    /**
     * PUT /v1/projects/{id}/status
     *
     * Atualiza somente o status macro do projeto.
     */
    public function updateStatus(): Response
    {
        $id = (int) $this->request->getParam('id');

        if (!$this->checkPermission('projects', 'edit')) {
            return $this->response->forbidden('Insufficient permissions');
        }

        if ($guard = $this->ensureProjectAccess($id)) {
            return $guard;
        }

        $project = $this->projectRepository()->find($id);
        if ($project === null) {
            return $this->notFound('Project not found');
        }

        $result = $this->projectMutationService()->updateStatus($project, $this->request->getBody(), $this->getUserId());
        return $this->mutationResponse($result);
    }

    /**
     * DELETE /v1/projects/{id}
     * 
     * Exclui um projeto
     */
    public function destroy(): Response
    {
        $id = (int) $this->request->getParam('id');

        if (!$this->checkPermission('projects', 'delete')) {
            return $this->response->forbidden('Insufficient permissions');
        }

        if ($guard = $this->ensureProjectAccess($id)) {
            return $guard;
        }

        $project = $this->projectRepository()->find($id);
        if ($project === null) {
            return $this->notFound('Project not found');
        }

        // Permite exclusao apenas se nao houver tarefas ativas (limpa tarefas finais automaticamente).
        $taskStats = $this->projectService()->projectTaskDeletionStats($id);
        if ($taskStats['active'] > 0) {
            return $this->error(
                sprintf(
                    'Cannot delete project with active tasks (%d active of %d). Move all tasks to Done, Cancelled or Archived first.',
                    $taskStats['active'],
                    $taskStats['total']
                ),
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $this->db->beginTransaction();
        try {
            if ($taskStats['total'] > 0 && !$this->projectService()->purgeProjectTasks($id)) {
                $this->db->rollback();
                return $this->error('Failed to purge project tasks before delete');
            }

            if (!$this->projectRepository()->delete($id)) {
                $this->db->rollback();
                return $this->error('Failed to delete project');
            }

            $this->db->commit();
            return $this->response->noContent();
        } catch (\Throwable $e) {
            $this->db->rollback();
            return $this->error('Failed to delete project');
        }
    }

    /**
     * GET /v1/projects/{id}/tasks
     * 
     * Lista tarefas de um projeto
     */
    public function tasks(): Response
    {
        $id = (int) $this->request->getParam('id');
        $pagination = $this->getPagination();

        if (!$this->checkPermission('projects', 'view')) {
            return $this->response->forbidden('Insufficient permissions');
        }

        if ($guard = $this->ensureProjectAccess($id)) {
            return $guard;
        }

        // Verifica se projeto existe
        $exists = $this->db->fetchValue(sprintf(
            "SELECT project_id FROM %s WHERE project_id = %d%s",
            $this->db->table('projects'),
            $id,
            $this->tenantAndCondition($this->db->table('projects'))
        ));

        if ($exists === null) {
            return $this->notFound('Project not found');
        }

        // Conta total de tarefas
        $total = (int) $this->db->fetchValue(sprintf(
            "SELECT COUNT(*) FROM %s WHERE task_project = %d%s",
            $this->db->table('tasks'),
            $id,
            $this->tenantAndCondition($this->db->table('tasks'))
        ));

        // Busca tarefas
        $sql = sprintf(
            "SELECT * FROM %s
             WHERE task_project = %d%s
             ORDER BY task_order ASC, task_start_date ASC
             LIMIT %d OFFSET %d",
            $this->db->table('tasks'),
            $id,
            $this->tenantAndCondition($this->db->table('tasks')),
            $pagination['per_page'],
            $pagination['offset']
        );

        $rows = $this->db->fetchAll($sql);

        $tasks = array_map(fn($row) => $this->projectFormatter()->formatTask($row), $rows);

        return $this->response->paginated(
            $tasks,
            $total,
            $pagination['page'],
            $pagination['per_page']
        );
    }

    /**
     * GET /v1/projects/{id}/status-history
     *
     * Lista auditoria de transicoes de status do projeto.
     */
    public function statusHistory(): Response
    {
        $id = (int) $this->request->getParam('id');

        if (!$this->checkPermission('projects', 'view')) {
            return $this->response->forbidden('Insufficient permissions');
        }

        if ($guard = $this->ensureProjectAccess($id)) {
            return $guard;
        }

        $exists = $this->db->fetchValue(sprintf(
            "SELECT project_id FROM %s WHERE project_id = %d%s",
            $this->db->table('projects'),
            $id,
            $this->tenantAndCondition($this->db->table('projects'))
        ));
        if ($exists === null) {
            return $this->notFound('Project not found');
        }

        $limit = (int) ($this->request->getQueryParam('limit', 50) ?? 50);
        $history = $this->statusHistoryService()->getByProjectId($id, $limit);

        return $this->json([
            'data' => $history,
        ]);
    }

    /**
     * Backward-compatible wrapper kept for unit tests/reflection callers.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function formatProject(array $row, bool $detailed = false): array
    {
        return $this->projectFormatter()->formatProject($row, $detailed);
    }

    /**
     * Backward-compatible wrapper kept for unit tests/reflection callers.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function formatTask(array $row): array
    {
        return $this->projectFormatter()->formatTask($row);
    }

    private function statusHistoryService(): ProjectStatusHistoryService
    {
        if ($this->statusHistoryService === null) {
            $this->statusHistoryService = new ProjectStatusHistoryService($this->db);
        }

        return $this->statusHistoryService;
    }

    private function resolveUnidadeQueryParam(): ?int
    {
        $value = $this->request->getQueryParam('unidade_id');
        if ($value === null || $value === '') {
            $value = $this->request->getQueryParam('company_id');
        }

        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function resolveUnidadeFromBody(array $body): ?int
    {
        if (array_key_exists('unidade_id', $body) && $body['unidade_id'] !== '' && $body['unidade_id'] !== null) {
            return (int) $body['unidade_id'];
        }

        if (array_key_exists('company_id', $body) && $body['company_id'] !== '' && $body['company_id'] !== null) {
            return (int) $body['company_id'];
        }

        return null;
    }

    /**
     * Ensure authenticated user can access a project.
     * Allows project owner/creator to proceed.
     */
    private function ensureProjectAccess(int $projectId): ?Response
    {
        return $this->resolveAuthorizationAccessResponse(
            AuthorizationService::getInstance()->resolveProjectAccessResult($this->getUserId(), $projectId),
            'Project not found'
        );
    }

    private function resolveAuthorizationAccessResponse(string $result, ?string $notFoundMessage = null): ?Response
    {
        return match ($result) {
            AuthorizationService::ACCESS_ALLOWED => null,
            AuthorizationService::ACCESS_UNAUTHORIZED => $this->response->unauthorized(),
            AuthorizationService::ACCESS_NOT_FOUND => $notFoundMessage !== null
                ? $this->notFound($notFoundMessage)
                : $this->response->forbidden('Access denied'),
            default => $this->response->forbidden('Access denied'),
        };
    }

    /**
     * @param array<string, mixed> $result
     */
    private function mutationResponse(array $result): Response
    {
        $kind = (string) ($result['kind'] ?? 'error');

        return match ($kind) {
            'created' => $this->created($result['data'] ?? []),
            'json' => $this->json($result['data'] ?? []),
            'validation_error' => $this->response->validationError((array) ($result['errors'] ?? [])),
            'unauthorized' => $this->response->unauthorized(),
            'forbidden' => $this->response->forbidden((string) ($result['message'] ?? 'Access denied')),
            'not_found' => $this->notFound((string) ($result['message'] ?? 'Resource not found')),
            default => $this->error((string) ($result['message'] ?? 'Request failed')),
        };
    }

    private function projectService(): ProjectService
    {
        if ($this->projectService === null) {
            $this->projectService = new ProjectService($this->db);
        }

        return $this->projectService;
    }

    private function projectMutationService(): ProjectMutationService
    {
        if ($this->projectMutationService === null) {
            $this->projectMutationService = new ProjectMutationService($this->db, $this->projectRepository());
        }

        return $this->projectMutationService;
    }

    private function projectFormatter(): ProjectFormatter
    {
        if ($this->projectFormatter === null) {
            $this->projectFormatter = new ProjectFormatter();
        }

        return $this->projectFormatter;
    }

    protected function getDatabase(): Database
    {
        return $this->db;
    }

    private function projectRepository(): ProjectRepository
    {
        if ($this->projectRepository === null) {
            $this->projectRepository = new ProjectRepository($this->db);
        }

        return $this->projectRepository;
    }
}



