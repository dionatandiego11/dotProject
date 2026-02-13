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
        $projectAcaoLinkTable = $this->resolveProjectAcaoLinkTable();

        // Filtros opcionais
        $unidadeId = $this->resolveUnidadeQueryParam();
        $programaId = $this->request->getQueryParam('programa_id');
        $acaoId = $this->request->getQueryParam('acao_id');
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

        if ($programaId !== null && $programaId !== '' && $this->tableHasColumn($this->db->table('projects'), 'project_programa_id')) {
            $where .= ' AND project_programa_id = ?';
            $params[] = (int) $programaId;
        }

        if ($acaoId !== null && $acaoId !== '') {
            $acaoIdInt = (int) $acaoId;
            if ($projectAcaoLinkTable !== null) {
                $where .= sprintf(
                    ' AND (project_id IN (SELECT pa.project_id FROM `%s` pa WHERE pa.acao_id = ?%s)',
                    $projectAcaoLinkTable,
                    $this->tenantAndCondition($projectAcaoLinkTable, 'pa')
                );
                $params[] = $acaoIdInt;

                if ($this->tableHasColumn($this->db->table('projects'), 'project_acao_id')) {
                    $where .= ' OR project_acao_id = ?';
                    $params[] = $acaoIdInt;
                }

                $where .= ')';
            } elseif ($this->tableHasColumn($this->db->table('projects'), 'project_acao_id')) {
                $where .= ' AND project_acao_id = ?';
                $params[] = $acaoIdInt;
            }
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

        $programJoin = '';
        $programSelect = '';
        if ($this->tableHasColumn($this->db->table('projects'), 'project_programa_id')) {
            if ($this->tableHasColumn('dotp_programas', 'id')) {
                $programJoin = ' LEFT JOIN `dotp_programas` prog ON p.project_programa_id = prog.id' . $this->tenantAndCondition('dotp_programas', 'prog');
                $programSelect = ', prog.nome AS programa_nome';
            } elseif ($this->tableHasColumn('programas', 'id')) {
                $programJoin = ' LEFT JOIN `programas` prog ON p.project_programa_id = prog.id' . $this->tenantAndCondition('programas', 'prog');
                $programSelect = ', prog.nome AS programa_nome';
            }
        }

        $acaoJoin = '';
        $acaoSelect = '';
        if ($this->tableHasColumn($this->db->table('projects'), 'project_acao_id')) {
            if ($this->tableHasColumn('dotp_acoes', 'id')) {
                $acaoJoin = ' LEFT JOIN `dotp_acoes` ac ON p.project_acao_id = ac.id' . $this->tenantAndCondition('dotp_acoes', 'ac');
                $acaoSelect = ', ac.nome AS acao_nome';
            } elseif ($this->tableHasColumn('acoes', 'id')) {
                $acaoJoin = ' LEFT JOIN `acoes` ac ON p.project_acao_id = ac.id' . $this->tenantAndCondition('acoes', 'ac');
                $acaoSelect = ', ac.nome AS acao_nome';
            }
        }

        // Conta total
        $totalSql = sprintf(
            "SELECT COUNT(*) as total FROM %s WHERE %s",
            $this->db->table('projects'),
            $totalWhere
        );
        $total = (int) ($this->db->fetchValueParams($totalSql, $params) ?? 0);

        // Busca projetos
        $sql = sprintf(
            "SELECT p.*, c.company_name, u.unidade_nome%s%s
             FROM %s p 
             LEFT JOIN %s c ON p.project_company = c.company_id%s
             LEFT JOIN dotp_unidades_organizacionais u ON p.project_company = u.unidade_id%s
             %s%s
             WHERE %s
             ORDER BY p.project_name ASC
             LIMIT ? OFFSET ?",
            $programSelect,
            $acaoSelect,
            $this->db->table('projects'),
            $this->db->table('companies'),
            $this->tenantAndCondition($this->db->table('companies'), 'c'),
            $this->tenantAndCondition('dotp_unidades_organizacionais', 'u'),
            $programJoin,
            $acaoJoin,
            $listWhere
        );
        $rows = $this->db->fetchAllParams($sql, array_merge($params, [
            $pagination['per_page'],
            $pagination['offset'],
        ]));

        $projectIds = array_values(array_unique(array_map(
            static fn(array $row): int => (int) ($row['project_id'] ?? 0),
            $rows
        )));
        $projectActions = $projectAcaoLinkTable !== null
            ? $this->fetchProjectActionsByProjectIds($projectIds)
            : [];

        $projects = array_map(function (array $row) use ($projectAcaoLinkTable, $projectActions): array {
            $projectId = (int) ($row['project_id'] ?? 0);
            $actions = $projectAcaoLinkTable !== null
                ? ($projectActions[$projectId] ?? null)
                : null;

            return $this->projectFormatter()->formatProject($row, false, $actions);
        }, $rows);

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

        $projectAcaoLinkTable = $this->resolveProjectAcaoLinkTable();

        $programJoin = '';
        $programSelect = '';
        if ($this->tableHasColumn($this->db->table('projects'), 'project_programa_id')) {
            if ($this->tableHasColumn('dotp_programas', 'id')) {
                $programJoin = ' LEFT JOIN `dotp_programas` prog ON p.project_programa_id = prog.id' . $this->tenantAndCondition('dotp_programas', 'prog');
                $programSelect = ', prog.nome AS programa_nome';
            } elseif ($this->tableHasColumn('programas', 'id')) {
                $programJoin = ' LEFT JOIN `programas` prog ON p.project_programa_id = prog.id' . $this->tenantAndCondition('programas', 'prog');
                $programSelect = ', prog.nome AS programa_nome';
            }
        }

        $acaoJoin = '';
        $acaoSelect = '';
        if ($this->tableHasColumn($this->db->table('projects'), 'project_acao_id')) {
            if ($this->tableHasColumn('dotp_acoes', 'id')) {
                $acaoJoin = ' LEFT JOIN `dotp_acoes` ac ON p.project_acao_id = ac.id' . $this->tenantAndCondition('dotp_acoes', 'ac');
                $acaoSelect = ', ac.nome AS acao_nome';
            } elseif ($this->tableHasColumn('acoes', 'id')) {
                $acaoJoin = ' LEFT JOIN `acoes` ac ON p.project_acao_id = ac.id' . $this->tenantAndCondition('acoes', 'ac');
                $acaoSelect = ', ac.nome AS acao_nome';
            }
        }

        $sql = sprintf(
            "SELECT p.*, c.company_name, u.unidade_nome%s%s
             FROM %s p 
             LEFT JOIN %s c ON p.project_company = c.company_id%s
             LEFT JOIN dotp_unidades_organizacionais u ON p.project_company = u.unidade_id%s
             %s%s
             WHERE p.project_id = %d%s",
            $programSelect,
            $acaoSelect,
            $this->db->table('projects'),
            $this->db->table('companies'),
            $this->tenantAndCondition($this->db->table('companies'), 'c'),
            $this->tenantAndCondition('dotp_unidades_organizacionais', 'u'),
            $programJoin,
            $acaoJoin,
            $id,
            $this->tenantAndCondition($this->db->table('projects'), 'p')
        );

        $row = $this->db->fetchOne($sql);

        if ($row === null) {
            return $this->notFound('Project not found');
        }

        $projectActions = $projectAcaoLinkTable !== null
            ? $this->fetchProjectActionsByProjectIds([$id])
            : [];

        return $this->json($this->projectFormatter()->formatProject(
            $row,
            true,
            $projectAcaoLinkTable !== null ? ($projectActions[$id] ?? null) : null
        ));
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
     * GET /v1/projects/{id}/etapas
     *
     * Lista etapas macro do projeto.
     */
    public function etapas(): Response
    {
        $projectId = (int) $this->request->getParam('id');

        if (!$this->checkPermission('projects', 'view')) {
            return $this->response->forbidden('Insufficient permissions');
        }

        if ($guard = $this->ensureProjectAccess($projectId)) {
            return $guard;
        }

        if (!$this->projectExists($projectId)) {
            return $this->notFound('Project not found');
        }

        $etapasTable = $this->resolveEtapasTable();
        if ($etapasTable === null) {
            return $this->json(['data' => []]);
        }

        $rows = $this->fetchProjectEtapasRows($projectId, $etapasTable);
        $data = array_map(fn(array $row): array => $this->formatEtapaRow($row), $rows);

        return $this->json([
            'data' => $data,
        ]);
    }

    /**
     * PUT /v1/projects/{id}/etapas/{etapaId}
     *
     * Atualiza dados operacionais de uma etapa.
     */
    public function atualizarEtapa(): Response
    {
        $projectId = (int) $this->request->getParam('id');
        $etapaNumero = (int) $this->request->getParam('etapaId');
        $body = $this->request->getBody();

        if (!$this->checkPermission('projects', 'edit')) {
            return $this->response->forbidden('Insufficient permissions');
        }

        if ($guard = $this->ensureProjectAccess($projectId)) {
            return $guard;
        }

        if ($etapaNumero <= 0) {
            return $this->validationError([
                'etapaId' => 'Invalid stage number.',
            ]);
        }

        if (!$this->projectExists($projectId)) {
            return $this->notFound('Project not found');
        }

        $etapasTable = $this->resolveEtapasTable();
        if ($etapasTable === null) {
            return $this->notFound('Project stage not found');
        }

        $etapa = $this->findProjectEtapaByNumero($projectId, $etapaNumero, $etapasTable);
        if ($etapa === null) {
            return $this->notFound('Project stage not found');
        }

        $updates = [];
        $params = [];

        try {
            if (array_key_exists('data_prevista_fim', $body)) {
                $updates[] = 'data_prevista_fim = ?';
                $params[] = $this->parseEtapaDateInput($body['data_prevista_fim'], 'data_prevista_fim');
            }

            if (array_key_exists('responsavel_id', $body)) {
                $updates[] = 'responsavel_id = ?';
                $params[] = $this->parseNullableInt($body['responsavel_id'], 'responsavel_id');
            }
        } catch (\InvalidArgumentException $e) {
            return $this->validationError([
                'etapa' => $e->getMessage(),
            ]);
        }

        if ($updates !== []) {
            $sql = sprintf(
                'UPDATE `%s` SET %s WHERE id = ?%s',
                $etapasTable,
                implode(', ', $updates),
                $this->tenantAndCondition($etapasTable)
            );
            $params[] = (int) ($etapa['id'] ?? 0);

            if (!$this->db->execute($sql, $params)) {
                return $this->error('Failed to update project stage');
            }
        }

        $formatted = $this->fetchFormattedEtapa($projectId, $etapaNumero, $etapasTable);
        if ($formatted === null) {
            return $this->notFound('Project stage not found');
        }

        return $this->json([
            'message' => 'Etapa atualizada',
            'data' => $formatted,
        ]);
    }

    /**
     * POST /v1/projects/{id}/etapas/{etapaId}/concluir
     *
     * Conclui uma etapa e aplica validacao de atraso.
     */
    public function concluirEtapa(): Response
    {
        $projectId = (int) $this->request->getParam('id');
        $etapaNumero = (int) $this->request->getParam('etapaId');
        $body = $this->request->getBody();

        if (!$this->checkPermission('projects', 'edit')) {
            return $this->response->forbidden('Insufficient permissions');
        }

        if ($guard = $this->ensureProjectAccess($projectId)) {
            return $guard;
        }

        if ($etapaNumero <= 0) {
            return $this->validationError([
                'etapaId' => 'Invalid stage number.',
            ]);
        }

        if (!$this->projectExists($projectId)) {
            return $this->notFound('Project not found');
        }

        $etapasTable = $this->resolveEtapasTable();
        if ($etapasTable === null) {
            return $this->notFound('Project stage not found');
        }

        $etapa = $this->findProjectEtapaByNumero($projectId, $etapaNumero, $etapasTable);
        if ($etapa === null) {
            return $this->notFound('Project stage not found');
        }

        $justificativa = array_key_exists('justificativa_atraso', $body)
            ? trim((string) $body['justificativa_atraso'])
            : '';

        $diasAtraso = $this->resolveEtapaDelayDays($etapa, (string) ($etapa['estado'] ?? ''));
        if ($diasAtraso > 0 && $justificativa === '') {
            return $this->validationError([
                'justificativa_atraso' => 'Justificativa de atraso e obrigatoria.',
            ]);
        }

        $estadoFinal = $diasAtraso > 0 ? 'Concluida_Com_Atraso' : 'Concluida';
        $updates = [
            'estado = ?',
            'percent_conclusao = ?',
            'data_real_fim = ?',
        ];
        $params = [
            $estadoFinal,
            100,
            (new \DateTimeImmutable('today'))->format('Y-m-d'),
        ];

        if ($this->tableHasColumn($etapasTable, 'dias_atraso')) {
            $updates[] = 'dias_atraso = ?';
            $params[] = $diasAtraso;
        }

        if ($this->tableHasColumn($etapasTable, 'justificativa_atraso') && $justificativa !== '') {
            $updates[] = 'justificativa_atraso = ?';
            $params[] = $justificativa;
        }

        if ($this->tableHasColumn($etapasTable, 'evidencia_url') && array_key_exists('evidencia_url', $body)) {
            $evidencia = trim((string) ($body['evidencia_url'] ?? ''));
            $updates[] = 'evidencia_url = ?';
            $params[] = $evidencia !== '' ? $evidencia : null;
        }

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE id = ?%s',
            $etapasTable,
            implode(', ', $updates),
            $this->tenantAndCondition($etapasTable)
        );
        $params[] = (int) ($etapa['id'] ?? 0);

        if (!$this->db->execute($sql, $params)) {
            return $this->error('Failed to conclude project stage');
        }

        $formatted = $this->fetchFormattedEtapa($projectId, $etapaNumero, $etapasTable);
        if ($formatted === null) {
            return $this->notFound('Project stage not found');
        }

        return $this->json([
            'message' => 'Etapa concluida',
            'data' => $formatted,
            'proxima_acao' => $etapaNumero < 5 ? 'avancar_etapa' : null,
        ]);
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
     * @param array<int, array<string, mixed>>|null $projectAcoes
     * @return array<string, mixed>
     */
    private function formatProject(array $row, bool $detailed = false, ?array $projectAcoes = null): array
    {
        return $this->projectFormatter()->formatProject($row, $detailed, $projectAcoes);
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

    private function projectExists(int $projectId): bool
    {
        $exists = $this->db->fetchValue(
            sprintf(
                'SELECT project_id FROM %s WHERE project_id = ?%s',
                $this->db->table('projects'),
                $this->tenantAndCondition($this->db->table('projects'))
            ),
            [$projectId]
        );

        return $exists !== null;
    }

    private function resolveEtapasTable(): ?string
    {
        $preferred = $this->db->table('etapas');
        if (
            $this->tableHasColumn($preferred, 'projeto_id') &&
            $this->tableHasColumn($preferred, 'numero')
        ) {
            return $preferred;
        }

        if (
            $this->tableHasColumn('dotp_etapas', 'projeto_id') &&
            $this->tableHasColumn('dotp_etapas', 'numero')
        ) {
            return 'dotp_etapas';
        }

        if (
            $this->tableHasColumn('etapas', 'projeto_id') &&
            $this->tableHasColumn('etapas', 'numero')
        ) {
            return 'etapas';
        }

        return null;
    }

    private function resolveTaskEtapaColumn(): ?string
    {
        $tasksTable = $this->db->table('tasks');
        if ($this->tableHasColumn($tasksTable, 'task_etapa_id')) {
            return 'task_etapa_id';
        }

        if ($this->tableHasColumn($tasksTable, 'etapa_id')) {
            return 'etapa_id';
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findProjectEtapaByNumero(int $projectId, int $etapaNumero, string $etapasTable): ?array
    {
        $sql = sprintf(
            'SELECT * FROM `%s` WHERE projeto_id = ? AND numero = ?%s LIMIT 1',
            $etapasTable,
            $this->tenantAndCondition($etapasTable)
        );

        return $this->db->fetchOne($sql, [$projectId, $etapaNumero]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchProjectEtapasRows(int $projectId, string $etapasTable): array
    {
        $taskEtapaColumn = $this->resolveTaskEtapaColumn();
        $tenantEtapas = $this->tenantAndCondition($etapasTable, 'e');
        $hasDiasAtraso = $this->tableHasColumn($etapasTable, 'dias_atraso');
        $diasAtrasoSelect = $hasDiasAtraso
            ? 'e.dias_atraso AS dias_atraso'
            : 'NULL AS dias_atraso';

        if ($taskEtapaColumn === null) {
            $sql = sprintf(
                "SELECT e.id, e.projeto_id, e.numero, e.nome, e.estado, e.percent_conclusao,
                        e.data_prevista_fim, e.data_real_fim, %s,
                        0 AS total_tarefas, 0 AS tarefas_concluidas
                 FROM `%s` e
                 WHERE e.projeto_id = ?%s
                 ORDER BY e.numero ASC",
                $diasAtrasoSelect,
                $etapasTable,
                $tenantEtapas
            );

            return $this->db->fetchAll($sql, [$projectId]);
        }

        $tasksTable = $this->db->table('tasks');
        $tenantTasks = $this->tenantAndCondition($tasksTable, 't');
        $groupByDias = $hasDiasAtraso ? ', e.dias_atraso' : '';

        $sql = sprintf(
            "SELECT e.id, e.projeto_id, e.numero, e.nome, e.estado, e.percent_conclusao,
                    e.data_prevista_fim, e.data_real_fim, %s,
                    COUNT(t.task_id) AS total_tarefas,
                    SUM(CASE WHEN t.task_status = 3 OR t.task_percent_complete >= 100 THEN 1 ELSE 0 END) AS tarefas_concluidas
             FROM `%s` e
             LEFT JOIN `%s` t
               ON t.task_project = e.projeto_id
              AND t.%s = e.numero%s
             WHERE e.projeto_id = ?%s
             GROUP BY e.id, e.projeto_id, e.numero, e.nome, e.estado, e.percent_conclusao,
                      e.data_prevista_fim, e.data_real_fim%s
             ORDER BY e.numero ASC",
            $diasAtrasoSelect,
            $etapasTable,
            $tasksTable,
            $taskEtapaColumn,
            $tenantTasks,
            $tenantEtapas,
            $groupByDias
        );

        return $this->db->fetchAll($sql, [$projectId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchFormattedEtapa(int $projectId, int $etapaNumero, string $etapasTable): ?array
    {
        $rows = $this->fetchProjectEtapasRows($projectId, $etapasTable);
        foreach ($rows as $row) {
            if ((int) ($row['numero'] ?? 0) !== $etapaNumero) {
                continue;
            }

            return $this->formatEtapaRow($row);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function formatEtapaRow(array $row): array
    {
        $estado = trim((string) ($row['estado'] ?? 'Nao_Iniciada'));

        return [
            'id' => isset($row['id']) ? (int) $row['id'] : null,
            'numero' => (int) ($row['numero'] ?? 0),
            'nome' => (string) ($row['nome'] ?? ''),
            'estado' => $estado,
            'cor_status' => $this->resolveEtapaColor($estado),
            'data_prevista_fim' => $this->normalizeDateOutput($row['data_prevista_fim'] ?? null),
            'data_real_fim' => $this->normalizeDateOutput($row['data_real_fim'] ?? null),
            'dias_atraso' => $this->resolveEtapaDelayDays($row, $estado),
            'percent_conclusao' => (float) ($row['percent_conclusao'] ?? 0),
            'total_tarefas' => (int) ($row['total_tarefas'] ?? 0),
            'tarefas_concluidas' => (int) ($row['tarefas_concluidas'] ?? 0),
        ];
    }

    private function resolveEtapaColor(string $estado): string
    {
        return match ($estado) {
            'Concluida' => '#22c55e',
            'Concluida_Com_Atraso', 'Proximo_Prazo', 'Recuperacao' => '#f59e0b',
            'Dentro_Prazo', 'Em_Andamento' => '#3b82f6',
            'Atrasada' => '#ef4444',
            'Critica' => '#dc2626',
            'Impedida', 'Bloqueada', 'Cancelada', 'Adiada' => '#6b7280',
            default => '#9ca3af',
        };
    }

    private function normalizeDateOutput(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable((string) $value))->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function resolveEtapaDelayDays(array $row, string $estado): int
    {
        $stored = (int) ($row['dias_atraso'] ?? 0);
        if ($stored > 0) {
            return $stored;
        }

        if (in_array($estado, ['Concluida', 'Concluida_Com_Atraso', 'Cancelada'], true)) {
            return max(0, $stored);
        }

        $dataPrevistaFim = $this->normalizeDateOutput($row['data_prevista_fim'] ?? null);
        if ($dataPrevistaFim === null) {
            return 0;
        }

        try {
            $hoje = new \DateTimeImmutable('today');
            $prazo = new \DateTimeImmutable($dataPrevistaFim);
        } catch (\Throwable) {
            return 0;
        }

        if ($hoje <= $prazo) {
            return 0;
        }

        return (int) $prazo->diff($hoje)->days;
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function parseEtapaDateInput(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable((string) $value))->format('Y-m-d');
        } catch (\Throwable) {
            throw new \InvalidArgumentException("Field {$field} contains an invalid date.");
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function parseNullableInt(mixed $value, string $field): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value) && (int) $value > 0) {
            return (int) $value;
        }

        throw new \InvalidArgumentException("Field {$field} must be a positive integer.");
    }

    private function statusHistoryService(): ProjectStatusHistoryService
    {
        if ($this->statusHistoryService === null) {
            $this->statusHistoryService = new ProjectStatusHistoryService($this->db);
        }

        return $this->statusHistoryService;
    }

    /**
     * @param array<int, int> $projectIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function fetchProjectActionsByProjectIds(array $projectIds): array
    {
        $projectIds = array_values(array_unique(array_filter(
            array_map(static fn(mixed $id): int => (int) $id, $projectIds),
            static fn(int $id): bool => $id > 0
        )));
        if ($projectIds === []) {
            return [];
        }

        $linkTable = $this->resolveProjectAcaoLinkTable();
        if ($linkTable === null) {
            return [];
        }
        $acaoTable = $this->resolveAcaoTable();

        $principalSelect = $this->tableHasColumn($linkTable, 'principal')
            ? 'COALESCE(pa.principal, 0)'
            : '0';
        $acaoJoin = '';
        $acaoSelect = 'NULL AS acao_nome';
        $acaoOrder = 'pa.acao_id ASC';
        if ($acaoTable !== null) {
            $acaoJoin = sprintf(
                ' LEFT JOIN `%s` ac ON ac.id = pa.acao_id%s',
                $acaoTable,
                $this->tenantAndCondition($acaoTable, 'ac')
            );
            $acaoSelect = 'ac.nome AS acao_nome';
            $acaoOrder = 'ac.nome ASC, pa.acao_id ASC';
        }
        $placeholders = implode(', ', array_fill(0, count($projectIds), '?'));
        $sql = sprintf(
            'SELECT pa.project_id, pa.acao_id, %s AS principal, %s
             FROM `%s` pa
             %s
             WHERE pa.project_id IN (%s)%s
             ORDER BY pa.project_id ASC, principal DESC, %s',
            $principalSelect,
            $acaoSelect,
            $linkTable,
            $acaoJoin,
            $placeholders,
            $this->tenantAndCondition($linkTable, 'pa'),
            $acaoOrder
        );
        $rows = $this->db->fetchAllParams($sql, $projectIds);

        $grouped = [];
        foreach ($rows as $row) {
            $projectId = (int) ($row['project_id'] ?? 0);
            $acaoId = (int) ($row['acao_id'] ?? 0);
            if ($projectId <= 0 || $acaoId <= 0) {
                continue;
            }

            $nome = isset($row['acao_nome']) ? trim((string) $row['acao_nome']) : null;
            $grouped[$projectId][] = [
                'id' => $acaoId,
                'nome' => $nome !== '' ? $nome : null,
                'principal' => ((int) ($row['principal'] ?? 0)) === 1,
            ];
        }

        return $grouped;
    }

    private function resolveProjectAcaoLinkTable(): ?string
    {
        if (
            $this->tableExists('dotp_projeto_acoes') &&
            $this->tableHasColumn('dotp_projeto_acoes', 'project_id') &&
            $this->tableHasColumn('dotp_projeto_acoes', 'acao_id')
        ) {
            return 'dotp_projeto_acoes';
        }

        if (
            $this->tableExists('projeto_acoes') &&
            $this->tableHasColumn('projeto_acoes', 'project_id') &&
            $this->tableHasColumn('projeto_acoes', 'acao_id')
        ) {
            return 'projeto_acoes';
        }

        return null;
    }

    private function resolveAcaoTable(): ?string
    {
        if ($this->tableHasColumn('dotp_acoes', 'id')) {
            return 'dotp_acoes';
        }

        if ($this->tableHasColumn('acoes', 'id')) {
            return 'acoes';
        }

        return null;
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



