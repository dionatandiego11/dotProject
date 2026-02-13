<?php
/**
 * DotProject Task Controller
 * 
 * Controller para gerenciamento de tarefas na API.
 * 
 * @package DotProject\Api\Controller
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Api\Controller;

use DotProject\Api\Formatter\TaskFormatter;
use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Core\Database;
use DotProject\Core\Logger;
use DotProject\Core\TenantAwareTrait;
use DotProject\Entity\TaskEntity;
use DotProject\Repository\TaskRepository;
use DotProject\Service\AuthorizationService;
use DotProject\Service\PermissionService;
use DotProject\Service\ProjectProgressSyncService;
use DotProject\Service\ScopeFilterService;
use DotProject\Service\TaskAccessService;
use DotProject\Service\TaskStatusService;

/**
 * Controller de tarefas
 */
class TaskController extends BaseController
{
    use TenantAwareTrait;

    private ?bool $hasTaskAssignedTo = null;
    private ?ProjectProgressSyncService $projectProgressSync = null;
    private ?TaskRepository $taskRepository = null;
    private ?TaskFormatter $taskFormatter = null;
    private ?TaskStatusService $taskStatusService = null;
    private ?TaskAccessService $taskAccessService = null;
    private ?ScopeFilterService $scopeFilter = null;

    /**
     * GET /v1/tasks
     * 
     * Lista todas as tarefas com paginação
     */
    public function index(): Response
    {
        if (!$this->checkPermission('tasks', 'view')) {
            return $this->response->forbidden('Insufficient permissions');
        }

        $userId = $this->getUserId();

        $pagination = $this->getPagination();

        // Filtros opcionais
        $projectId = $this->request->getQueryParam('project_id');
        $status = $this->request->getQueryParam('status');
        $priority = $this->request->getQueryParam('priority');
        $ownerId = $this->request->getQueryParam('owner_id');
        $search = $this->request->getQueryParam('search');
        $overdue = $this->request->getQueryParam('overdue'); // true/false

        // Monta a query
        $where = '1=1';
        $params = [];
        $where .= $this->tenantAndCondition($this->db->table('tasks'), 't');

        $scope = $this->scopeFilter()->resolveUserScope($userId);
        if ($scope['restricted'] && empty($scope['project_ids'])) {
            return $this->response->paginated([], 0, $pagination['page'], $pagination['per_page']);
        }
        [$where, $params] = $this->scopeFilter()->applyTaskScope($where, $params, 't.task_project', $scope);

        if ($projectId !== null) {
            $where .= ' AND t.task_project = ?';
            $params[] = (int) $projectId;
        }

        if ($status !== null) {
            $where .= ' AND t.task_status = ?';
            $params[] = (int) $status;
        }

        if ($priority !== null) {
            $where .= ' AND t.task_priority = ?';
            $params[] = (int) $priority;
        }

        if ($ownerId !== null) {
            $where .= ' AND t.task_owner = ?';
            $params[] = (int) $ownerId;
        }

        if ($search !== null) {
            $where .= " AND (t.task_name LIKE ? OR t.task_description LIKE ?)";
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }

        if ($overdue === 'true') {
            $where .= " AND t.task_end_date < ? AND t.task_percent_complete < 100";
            $params[] = date('Y-m-d');
        }

        // Conta total
        $totalSql = sprintf(
            "SELECT COUNT(*) as total FROM %s t WHERE %s",
            $this->db->table('tasks'),
            $where
        );
        $total = (int) ($this->db->fetchValueParams($totalSql, $params) ?? 0);

        // Busca tarefas
        $sql = sprintf(
            "SELECT t.*, p.project_name 
             FROM %s t 
             LEFT JOIN %s p ON t.task_project = p.project_id%s
             WHERE %s
             ORDER BY t.task_start_date ASC, t.task_order ASC
             LIMIT ? OFFSET ?",
            $this->db->table('tasks'),
            $this->db->table('projects'),
            $this->tenantAndCondition($this->db->table('projects'), 'p'),
            $where
        );
        $rows = $this->db->fetchAllParams($sql, array_merge($params, [
            $pagination['per_page'],
            $pagination['offset'],
        ]));

        $tasks = array_map(fn($row) => $this->taskFormatter()->format($row), $rows);

        return $this->response->paginated(
            $tasks,
            $total,
            $pagination['page'],
            $pagination['per_page']
        );
    }

    /**
     * GET /v1/tasks/{id}
     * 
     * Retorna uma tarefa específica
     */
    public function show(): Response
    {
        $id = (int) $this->request->getParam('id');

        if (!$this->checkPermission('tasks', 'view')) {
            return $this->response->forbidden('Insufficient permissions');
        }

        if ($guard = $this->ensureTaskAccess($id)) {
            return $guard;
        }

        $sql = sprintf(
            "SELECT t.*, p.project_name 
             FROM %s t 
             LEFT JOIN %s p ON t.task_project = p.project_id%s
             WHERE t.task_id = %d%s",
            $this->db->table('tasks'),
            $this->db->table('projects'),
            $this->tenantAndCondition($this->db->table('projects'), 'p'),
            $id,
            $this->tenantAndCondition($this->db->table('tasks'), 't')
        );

        $row = $this->db->fetchOne($sql);

        if ($row === null) {
            return $this->notFound('Task not found');
        }

        return $this->json($this->taskFormatter()->format($row, true));
    }

    /**
     * POST /v1/tasks
     * 
     * Cria uma nova tarefa
     */
    public function store(): Response
    {
        if (!$this->checkPermission('tasks', 'add')) {
            return $this->response->forbidden('Insufficient permissions');
        }

        $body = $this->request->getBody();
        if (empty($body['project_id'])) {
            return $this->response->validationError([
                'project_id' => 'O projeto é obrigatório.'
            ]);
        }
        if (!empty($body['start_date']) && !empty($body['end_date'])) {
            if (strtotime($body['start_date']) > strtotime($body['end_date'])) {
                return $this->response->validationError([
                    'end_date' => 'A data de término deve ser maior ou igual à data de início.'
                ]);
            }
        }
        $validationData = [
            'task_name' => $body['name'] ?? '',
            'task_project' => $body['project_id'] ?? null,
            'task_percent_complete' => $body['percent_complete'] ?? 0,
            'task_duration' => $body['duration'] ?? 1,
        ];
        $validation = $this->validation()->validateTask($validationData);
        if ($validation->fails()) {
            return $this->response->validationError($validation->errors());
        }

        $this->taskStatus()->normalizeStatusPercent($body, null, null);

        // Verifica se projeto existe
        $projectExists = $this->db->fetchValue(sprintf(
            "SELECT project_id FROM %s WHERE project_id = %d%s",
            $this->db->table('projects'),
            (int) $body['project_id'],
            $this->tenantAndCondition($this->db->table('projects'))
        ));

        if ($projectExists === null) {
            return $this->error('Project not found', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($guard = $this->ensureProjectAccess((int) $body['project_id'])) {
            return $guard;
        }

        // Calcula próxima ordem
        $maxOrder = $this->db->fetchValue(sprintf(
            "SELECT MAX(task_order) FROM %s WHERE task_project = %d%s",
            $this->db->table('tasks'),
            (int) $body['project_id'],
            $this->tenantAndCondition($this->db->table('tasks'))
        )) ?? 0;

        $ownerId = isset($body['owner_id']) ? (int) $body['owner_id'] : (int) ($this->getUserId() ?? 0);
        if ($ownerId <= 0) {
            $ownerId = (int) ($this->getUserId() ?? 0);
        }

        $assigneeRaw = $body['assigned_to'] ?? $body['assigned_to_id'] ?? $body['owner_id'] ?? null;
        $assigneeId = ($assigneeRaw !== null && (int) $assigneeRaw > 0) ? (int) $assigneeRaw : null;

        $taskPayload = [
            'task_name' => $body['name'],
            'task_project' => (int) $body['project_id'],
            'task_milestone' => $body['milestone'] ?? 0,
            'task_owner' => $ownerId,
            'task_creator' => $this->getUserId(),
            'task_start_date' => $body['start_date'] ?? date('Y-m-d'),
            'task_end_date' => $body['end_date'] ?? null,
            'task_duration' => $body['duration'] ?? 1,
            'task_duration_type' => $body['duration_type'] ?? 1, // 1 = days
            'task_hours_worked' => 0,
            'task_priority' => $body['priority'] ?? 0,
            'task_percent_complete' => $body['percent_complete'] ?? 0,
            'task_description' => $body['description'] ?? '',
            'task_status' => $body['status'] ?? 0,
            'task_order' => $maxOrder + 1,
            'task_access' => $body['access'] ?? 0,
            'task_type' => $body['type'] ?? 0,
        ];

        $task = new TaskEntity();
        $task->setName((string) $taskPayload['task_name']);
        $task->setProjectId((int) $taskPayload['task_project']);
        $task->setOwnerId((int) $ownerId);
        $task->setAssignedTo($assigneeId);
        $task->setStatus((int) ($taskPayload['task_status'] ?? 0));
        $task->setPriority((int) ($taskPayload['task_priority'] ?? 0));
        $task->setPercentComplete((int) ($taskPayload['task_percent_complete'] ?? 0));
        $task->setDescription((string) ($taskPayload['task_description'] ?? ''));
        $task->setParentTaskId(isset($body['parent_id']) ? (int) $body['parent_id'] : null);
        $task->setEstimatedHours(isset($taskPayload['task_duration']) ? (float) $taskPayload['task_duration'] : null);
        $task->setActualHours(0.0);
        $task->setStartDate(new \DateTime((string) ($taskPayload['task_start_date'] ?? date('Y-m-d'))));
        if (!empty($taskPayload['task_end_date'])) {
            $task->setEndDate(new \DateTime((string) $taskPayload['task_end_date']));
        }

        $taskId = $this->taskRepository()->save($task);
        if ($taskId <= 0) {
            return $this->error('Failed to create task');
        }

        $legacyPayload = [
            'task_parent' => isset($body['parent_id']) ? (int) $body['parent_id'] : 0,
            'task_milestone' => (int) ($body['milestone'] ?? 0),
            'task_creator' => $this->getUserId(),
            'task_duration' => (float) ($body['duration'] ?? 1),
            'task_duration_type' => (int) ($body['duration_type'] ?? 1),
            'task_hours_worked' => 0,
            'task_order' => (int) ($maxOrder + 1),
            'task_access' => (int) ($body['access'] ?? 0),
            'task_type' => (int) ($body['type'] ?? 0),
        ];
        if ($this->supportsTaskAssignedToColumn()) {
            $legacyPayload['task_assigned_to'] = $assigneeId;
        }

        if (!$this->updateTaskRow($taskId, $legacyPayload)) {
            return $this->error('Failed to create task');
        }

        $this->syncProjectProgress((int) $body['project_id']);

        return $this->created([
            'id' => $taskId,
            'message' => 'Task created successfully',
        ]);
    }

    /**
     * PUT /v1/tasks/{id}
     * 
     * Atualiza uma tarefa
     */
    public function update(): Response
    {
        $id = (int) $this->request->getParam('id');

        if (!$this->checkPermission('tasks', 'edit')) {
            return $this->response->forbidden('Insufficient permissions');
        }

        if ($guard = $this->ensureTaskAccess($id)) {
            return $guard;
        }

        $task = $this->taskRepository()->find($id);
        if ($task === null) {
            return $this->notFound('Task not found');
        }

        $body = $this->request->getBody();
        if (!empty($body['start_date']) && !empty($body['end_date'])) {
            if (strtotime($body['start_date']) > strtotime($body['end_date'])) {
                return $this->response->validationError([
                    'end_date' => 'A data de término deve ser maior ou igual à data de início.'
                ]);
            }
        }
        if (array_key_exists('status', $body)) {
            $status = (int) $body['status'];
            if (!$this->taskStatus()->isValidStatus($status)) {
                return $this->response->validationError([
                    'status' => 'Status inválido.'
                ]);
            }
        }

        $validationData = [];
        if (isset($body['name'])) {
            $validationData['task_name'] = $body['name'];
        }
        if (array_key_exists('project_id', $body)) {
            $validationData['task_project'] = $body['project_id'];
        }
        if (array_key_exists('percent_complete', $body)) {
            $validationData['task_percent_complete'] = $body['percent_complete'];
        }
        if (array_key_exists('duration', $body)) {
            $validationData['task_duration'] = $body['duration'];
        }

        if (!empty($validationData)) {
            if (!array_key_exists('task_name', $validationData)) {
                $validationData['task_name'] = $task->getName();
            }
            if (!array_key_exists('task_project', $validationData)) {
                $validationData['task_project'] = $task->getProjectId();
            }

            $validation = $this->validation()->validateTask($validationData);
            if ($validation->fails()) {
                return $this->response->validationError($validation->errors());
            }
        }

        $this->taskStatus()->normalizeStatusPercent(
            $body,
            $task->getStatus(),
            $task->getPercentComplete()
        );

        if (array_key_exists('name', $body)) {
            $task->setName((string) $body['name']);
        }
        if (array_key_exists('description', $body)) {
            $task->setDescription($body['description'] !== null ? (string) $body['description'] : null);
        }
        if (array_key_exists('owner_id', $body)) {
            $task->setOwnerId((int) $body['owner_id']);
        }
        if (array_key_exists('start_date', $body)) {
            $task->setStartDate($body['start_date'] ? new \DateTime((string) $body['start_date']) : null);
        }
        if (array_key_exists('end_date', $body)) {
            $task->setEndDate($body['end_date'] ? new \DateTime((string) $body['end_date']) : null);
        }
        if (array_key_exists('duration', $body)) {
            $task->setEstimatedHours((float) $body['duration']);
        }
        if (array_key_exists('priority', $body)) {
            $task->setPriority((int) $body['priority']);
        }
        if (array_key_exists('percent_complete', $body)) {
            $task->setPercentComplete((int) $body['percent_complete']);
        }
        if (array_key_exists('status', $body)) {
            $task->setStatus((int) $body['status']);
        }
        if (array_key_exists('parent_id', $body)) {
            $task->setParentTaskId($body['parent_id'] !== null ? (int) $body['parent_id'] : null);
        }

        if ($this->supportsTaskAssignedToColumn()) {
            if (array_key_exists('assigned_to', $body) || array_key_exists('assigned_to_id', $body)) {
                $assigneeRaw = $body['assigned_to'] ?? $body['assigned_to_id'];
                $task->setAssignedTo(($assigneeRaw !== null && (int) $assigneeRaw > 0) ? (int) $assigneeRaw : null);
            } elseif (array_key_exists('owner_id', $body)) {
                $ownerId = (int) ($body['owner_id'] ?? 0);
                $task->setAssignedTo($ownerId > 0 ? $ownerId : null);
            }
        }

        if ($this->taskRepository()->save($task) <= 0) {
            return $this->error('Failed to update task');
        }

        $legacyUpdates = [];
        if (array_key_exists('milestone', $body)) {
            $legacyUpdates['task_milestone'] = (int) $body['milestone'];
        }
        if (array_key_exists('order', $body)) {
            $legacyUpdates['task_order'] = (int) $body['order'];
        }
        if (!$this->updateTaskRow($id, $legacyUpdates)) {
            return $this->error('Failed to update task');
        }

        $this->syncProjectProgress($task->getProjectId());

        return $this->json([
            'id' => $task->getId(),
            'message' => 'Task updated successfully',
        ]);
    }

    /**
     * DELETE /v1/tasks/{id}
     * 
     * Exclui uma tarefa
     */
    public function destroy(): Response
    {
        $id = (int) $this->request->getParam('id');

        if (!$this->checkPermission('tasks', 'delete')) {
            return $this->response->forbidden('Insufficient permissions');
        }

        if ($guard = $this->ensureTaskAccess($id)) {
            return $guard;
        }

        $task = $this->taskRepository()->find($id);
        if ($task === null) {
            return $this->notFound('Task not found');
        }

        // Verifica se há subtarefas
        $childCount = $this->db->fetchValue(sprintf(
            "SELECT COUNT(*) FROM %s WHERE task_parent = %d%s",
            $this->db->table('tasks'),
            $id,
            $this->tenantAndCondition($this->db->table('tasks'))
        ));

        if ($childCount > 0) {
            return $this->error('Cannot delete task with subtasks. Delete subtasks first.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!$this->taskRepository()->delete($id)) {
            return $this->error('Failed to delete task');
        }

        $this->syncProjectProgress($task->getProjectId());

        return $this->response->noContent();
    }

    /**
     * GET /v1/projetos/{id}/tarefas
     *
     * Compatibilidade com rota do módulo PPA.
     */
    public function porProjeto(): Response
    {
        $projectId = (int) $this->request->getParam('id');
        $pagination = $this->getPagination();

        if (!$this->checkPermission('tasks', 'view')) {
            return $this->response->forbidden('Insufficient permissions');
        }

        if ($guard = $this->ensureProjectAccess($projectId)) {
            return $guard;
        }

        $where = 't.task_project = ?' . $this->tenantAndCondition($this->db->table('tasks'), 't');
        $params = [$projectId];

        $totalSql = sprintf(
            'SELECT COUNT(*) AS total FROM %s t WHERE %s',
            $this->db->table('tasks'),
            $where
        );
        $total = (int) ($this->db->fetchValueParams($totalSql, $params) ?? 0);

        $sql = sprintf(
            "SELECT t.*, p.project_name
             FROM %s t
             LEFT JOIN %s p ON t.task_project = p.project_id%s
             WHERE %s
             ORDER BY t.task_order ASC, t.task_start_date ASC
             LIMIT ? OFFSET ?",
            $this->db->table('tasks'),
            $this->db->table('projects'),
            $this->tenantAndCondition($this->db->table('projects'), 'p'),
            $where
        );
        $rows = $this->db->fetchAllParams($sql, array_merge($params, [
            $pagination['per_page'],
            $pagination['offset'],
        ]));

        $tasks = array_map(fn($row) => $this->taskFormatter()->format($row), $rows);

        return $this->response->paginated(
            $tasks,
            $total,
            $pagination['page'],
            $pagination['per_page']
        );
    }

    /**
     * GET /v1/projetos/{id}/etapas/{etapaId}/tarefas
     *
     * Compatibilidade com rota do módulo PPA.
     */
    public function porEtapa(): Response
    {
        $projectId = (int) $this->request->getParam('id');
        $etapaId = (int) $this->request->getParam('etapaId');
        $pagination = $this->getPagination();

        if (!$this->checkPermission('tasks', 'view')) {
            return $this->response->forbidden('Insufficient permissions');
        }

        if ($guard = $this->ensureProjectAccess($projectId)) {
            return $guard;
        }

        $tasksTable = $this->db->table('tasks');
        if (!$this->tableHasColumn($tasksTable, 'task_etapa_id')) {
            return $this->response->paginated([], 0, $pagination['page'], $pagination['per_page']);
        }

        $where = 't.task_project = ? AND t.task_etapa_id = ?' . $this->tenantAndCondition($tasksTable, 't');
        $params = [$projectId, $etapaId];

        $totalSql = sprintf(
            'SELECT COUNT(*) AS total FROM %s t WHERE %s',
            $tasksTable,
            $where
        );
        $total = (int) ($this->db->fetchValueParams($totalSql, $params) ?? 0);

        $sql = sprintf(
            "SELECT t.*, p.project_name
             FROM %s t
             LEFT JOIN %s p ON t.task_project = p.project_id%s
             WHERE %s
             ORDER BY t.task_order ASC, t.task_start_date ASC
             LIMIT ? OFFSET ?",
            $tasksTable,
            $this->db->table('projects'),
            $this->tenantAndCondition($this->db->table('projects'), 'p'),
            $where
        );
        $rows = $this->db->fetchAllParams($sql, array_merge($params, [
            $pagination['per_page'],
            $pagination['offset'],
        ]));

        $tasks = array_map(fn($row) => $this->taskFormatter()->format($row), $rows);

        return $this->response->paginated(
            $tasks,
            $total,
            $pagination['page'],
            $pagination['per_page']
        );
    }

    /**
     * PUT /v1/tarefas/{id}/estado
     *
     * Atualização rápida de estado para compatibilidade com o fluxo PPA.
     */
    public function atualizarEstado(): Response
    {
        $id = (int) $this->request->getParam('id');

        if (!$this->checkPermission('tasks', 'edit')) {
            return $this->response->forbidden('Insufficient permissions');
        }

        if ($guard = $this->ensureTaskAccess($id)) {
            return $guard;
        }

        $task = $this->taskRepository()->find($id);
        if ($task === null) {
            return $this->notFound('Task not found');
        }

        $body = $this->request->getBody();
        $statusRaw = $body['status'] ?? $body['estado'] ?? null;
        if ($statusRaw === null || $statusRaw === '') {
            return $this->response->validationError([
                'status' => 'Status é obrigatório.',
            ]);
        }

        $status = $this->normalizeQuickStatus($statusRaw);
        if ($status === null || !$this->taskStatus()->isValidStatus($status)) {
            return $this->response->validationError([
                'status' => 'Status inválido.',
            ]);
        }

        $payload = ['status' => $status];
        if (array_key_exists('percent_complete', $body)) {
            $payload['percent_complete'] = $body['percent_complete'];
        }

        $this->taskStatus()->normalizeStatusPercent(
            $payload,
            $task->getStatus(),
            $task->getPercentComplete()
        );

        $task->setStatus((int) $payload['status']);
        if (array_key_exists('percent_complete', $payload)) {
            $task->setPercentComplete((int) $payload['percent_complete']);
        }

        if ($this->taskRepository()->save($task) <= 0) {
            return $this->error('Failed to update task status');
        }

        $this->syncProjectProgress($task->getProjectId());

        return $this->json([
            'id' => $task->getId(),
            'status' => $task->getStatus(),
            'percent_complete' => $task->getPercentComplete(),
            'message' => 'Task status updated successfully',
        ]);
    }

    private function supportsTaskAssignedToColumn(): bool
    {
        if ($this->hasTaskAssignedTo !== null) {
            return $this->hasTaskAssignedTo;
        }

        try {
            $this->hasTaskAssignedTo = $this->tableHasColumn($this->db->table('tasks'), 'task_assigned_to');
        } catch (\Throwable $e) {
            $this->hasTaskAssignedTo = false;
        }

        return $this->hasTaskAssignedTo;
    }

    private function normalizeQuickStatus(mixed $statusRaw): ?int
    {
        if (is_numeric($statusRaw)) {
            return (int) $statusRaw;
        }

        $normalized = mb_strtolower(trim((string) $statusRaw));
        if ($normalized === '') {
            return null;
        }

        $map = [
            'backlog' => 0,
            'a_fazer' => 1,
            'a fazer' => 1,
            'todo' => 1,
            'em_andamento' => 2,
            'em andamento' => 2,
            'doing' => 2,
            'concluida' => 3,
            'concluido' => 3,
            'done' => 3,
            'em_espera' => 4,
            'em espera' => 4,
            'cancelado' => 5,
            'cancelada' => 5,
            'arquivado' => 6,
            'arquivada' => 6,
            'revisao' => 7,
            'revisão' => 7,
        ];

        return $map[$normalized] ?? null;
    }

    private function syncProjectProgress(int $projectId): void
    {
        if ($projectId <= 0) {
            return;
        }

        try {
            $this->projectProgressSync = $this->projectProgressSync ?? new ProjectProgressSyncService($this->db);
            $this->projectProgressSync->syncProject($projectId, $this->getUserId(), 'task_controller');
        } catch (\Throwable $e) {
            Logger::warning('Failed to sync project progress after task mutation', [
                'project_id' => $projectId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function updateTaskRow(int $taskId, array $fields): bool
    {
        if ($fields === []) {
            return true;
        }

        $tasksTable = $this->db->table('tasks');
        foreach (array_keys($fields) as $column) {
            if (!$this->tableHasColumn($tasksTable, $column)) {
                unset($fields[$column]);
            }
        }

        if ($fields === []) {
            return true;
        }

        return $this->db->update(
            'tasks',
            $fields,
            sprintf(
                'task_id = %d%s',
                $taskId,
                $this->tenantAndCondition($tasksTable)
            )
        );
    }

    /**
     * Ensure authenticated user can access a task.
     * Allows task owner/creator or owning project owner/creator.
     */
    private function ensureTaskAccess(int $taskId): ?Response
    {
        return $this->resolveAccessResponse(
            $this->taskAccessService()->resolveTaskAccess($this->getUserId(), $taskId),
            'Task not found'
        );
    }

    /**
     * Ensure authenticated user can access a project.
     */
    private function ensureProjectAccess(int $projectId): ?Response
    {
        return $this->resolveAccessResponse(
            $this->taskAccessService()->resolveProjectAccess($this->getUserId(), $projectId),
            'Project not found'
        );
    }

    private function resolveAccessResponse(string $result, string $notFoundMessage): ?Response
    {
        return match ($result) {
            TaskAccessService::RESULT_ALLOWED => null,
            TaskAccessService::RESULT_UNAUTHORIZED => $this->response->unauthorized(),
            TaskAccessService::RESULT_NOT_FOUND => $this->notFound($notFoundMessage),
            default => $this->response->forbidden('Access denied'),
        };
    }

    private function taskFormatter(): TaskFormatter
    {
        if ($this->taskFormatter === null) {
            $this->taskFormatter = new TaskFormatter();
        }

        return $this->taskFormatter;
    }

    private function taskStatus(): TaskStatusService
    {
        if ($this->taskStatusService === null) {
            $this->taskStatusService = new TaskStatusService();
        }

        return $this->taskStatusService;
    }

    private function taskAccessService(): TaskAccessService
    {
        if ($this->taskAccessService === null) {
            $this->taskAccessService = new TaskAccessService($this->db);
        }

        return $this->taskAccessService;
    }

    private function scopeFilter(): ScopeFilterService
    {
        if ($this->scopeFilter === null) {
            $this->scopeFilter = new ScopeFilterService($this->db);
        }

        return $this->scopeFilter;
    }

    protected function getDatabase(): Database
    {
        return $this->db;
    }

    private function taskRepository(): TaskRepository
    {
        if ($this->taskRepository === null) {
            $this->taskRepository = new TaskRepository($this->db);
        }

        return $this->taskRepository;
    }
}
