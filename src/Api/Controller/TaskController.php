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

use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Core\Logger;
use DotProject\Entity\Task;
use DotProject\Service\AuthorizationService;
use DotProject\Service\PermissionService;
use DotProject\Service\ProjectProgressSyncService;

/**
 * Controller de tarefas
 */
class TaskController extends BaseController
{
    private ?bool $hasTaskAssignedTo = null;
    private ?ProjectProgressSyncService $projectProgressSync = null;

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
        $auth = AuthorizationService::getInstance();
        $perm = new PermissionService();

        $pagination = $this->getPagination();

        // Filtros opcionais
        $projectId = $this->request->getQueryParam('project_id');
        $status = $this->request->getQueryParam('status');
        $ownerId = $this->request->getQueryParam('owner_id');
        $search = $this->request->getQueryParam('search');
        $overdue = $this->request->getQueryParam('overdue'); // true/false

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
                $projectRows = $this->db->fetchAllParams(
                    "SELECT project_id FROM {$this->db->table('projects')} WHERE project_company IN ({$placeholders})",
                    $unidades
                );
                $accessibleProjects = array_map(fn($row) => (int) $row['project_id'], $projectRows);
                if (empty($accessibleProjects)) {
                    return $this->response->paginated([], 0, $pagination['page'], $pagination['per_page']);
                }
                $projectPlaceholders = implode(',', array_fill(0, count($accessibleProjects), '?'));
                $where .= " AND t.task_project IN ({$projectPlaceholders})";
                $params = array_merge($params, $accessibleProjects);
            }
        }

        if ($projectId !== null) {
            $where .= ' AND t.task_project = ?';
            $params[] = (int) $projectId;
        }

        if ($status !== null) {
            $where .= ' AND t.task_status = ?';
            $params[] = (int) $status;
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
             LEFT JOIN %s p ON t.task_project = p.project_id
             WHERE %s
             ORDER BY t.task_start_date ASC, t.task_order ASC
             LIMIT ? OFFSET ?",
            $this->db->table('tasks'),
            $this->db->table('projects'),
            $where
        );
        $rows = $this->db->fetchAllParams($sql, array_merge($params, [
            $pagination['per_page'],
            $pagination['offset'],
        ]));

        $tasks = array_map(fn($row) => $this->formatTask($row), $rows);

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
             LEFT JOIN %s p ON t.task_project = p.project_id
             WHERE t.task_id = %d",
            $this->db->table('tasks'),
            $this->db->table('projects'),
            $id
        );

        $row = $this->db->fetchOne($sql);

        if ($row === null) {
            return $this->notFound('Task not found');
        }

        return $this->json($this->formatTask($row, true));
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

        $this->normalizeTaskStatusPercent($body, null, null);

        // Verifica se projeto existe
        $projectExists = $this->db->fetchValue(sprintf(
            "SELECT project_id FROM %s WHERE project_id = %d",
            $this->db->table('projects'),
            (int) $body['project_id']
        ));

        if ($projectExists === null) {
            return $this->error('Project not found', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($guard = $this->ensureProjectAccess((int) $body['project_id'])) {
            return $guard;
        }

        // Calcula próxima ordem
        $maxOrder = $this->db->fetchValue(sprintf(
            "SELECT MAX(task_order) FROM %s WHERE task_project = %d",
            $this->db->table('tasks'),
            (int) $body['project_id']
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
            'task_parent' => $body['parent_id'] ?? 0,
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

        if ($this->supportsTaskAssignedToColumn()) {
            $taskPayload['task_assigned_to'] = $assigneeId;
        }

        $task = new Task();
        $task->fill($taskPayload);

        if (!$task->save()) {
            return $this->error('Failed to create task');
        }

        $this->syncProjectProgress((int) $body['project_id']);

        return $this->created([
            'id' => $task->getId(),
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

        $task = Task::find($id);
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
            if ($status < 0 || $status > 7) {
                return $this->response->validationError([
                    'status' => 'Status inválido.'
                ]);
            }
        }

        // Atualiza apenas campos fornecidos
        $updateFields = [
            'name' => 'task_name',
            'description' => 'task_description',
            'owner_id' => 'task_owner',
            'start_date' => 'task_start_date',
            'end_date' => 'task_end_date',
            'duration' => 'task_duration',
            'priority' => 'task_priority',
            'percent_complete' => 'task_percent_complete',
            'status' => 'task_status',
            'milestone' => 'task_milestone',
            'parent_id' => 'task_parent',
            'order' => 'task_order',
        ];

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
                $validationData['task_name'] = (string) ($task->getAttribute('task_name') ?? '');
            }
            if (!array_key_exists('task_project', $validationData)) {
                $validationData['task_project'] = (int) ($task->getAttribute('task_project') ?? 0);
            }

            $validation = $this->validation()->validateTask($validationData);
            if ($validation->fails()) {
                return $this->response->validationError($validation->errors());
            }
        }

        $this->normalizeTaskStatusPercent(
            $body,
            (int) ($task->getAttribute('task_status') ?? 0),
            (int) ($task->getAttribute('task_percent_complete') ?? 0)
        );

        foreach ($updateFields as $apiField => $dbField) {
            if (isset($body[$apiField])) {
                $task->setAttribute($dbField, $body[$apiField]);
            }
        }

        if ($this->supportsTaskAssignedToColumn()) {
            if (array_key_exists('assigned_to', $body) || array_key_exists('assigned_to_id', $body)) {
                $assigneeRaw = $body['assigned_to'] ?? $body['assigned_to_id'];
                $task->setAttribute('task_assigned_to', ($assigneeRaw !== null && (int) $assigneeRaw > 0) ? (int) $assigneeRaw : null);
            } elseif (array_key_exists('owner_id', $body)) {
                $ownerId = (int) ($body['owner_id'] ?? 0);
                $task->setAttribute('task_assigned_to', $ownerId > 0 ? $ownerId : null);
            }
        }

        if (!$task->save()) {
            return $this->error('Failed to update task');
        }

        $this->syncProjectProgress((int) ($task->getAttribute('task_project') ?? 0));

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

        $task = Task::find($id);
        if ($task === null) {
            return $this->notFound('Task not found');
        }

        // Verifica se há subtarefas
        $childCount = $this->db->fetchValue(sprintf(
            "SELECT COUNT(*) FROM %s WHERE task_parent = %d",
            $this->db->table('tasks'),
            $id
        ));

        if ($childCount > 0) {
            return $this->error('Cannot delete task with subtasks. Delete subtasks first.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!$task->delete()) {
            return $this->error('Failed to delete task');
        }

        $this->syncProjectProgress((int) ($task->getAttribute('task_project') ?? 0));

        return $this->response->noContent();
    }

    /**
     * Formata dados da tarefa para a API
     */
    private function formatTask(array $row, bool $detailed = false): array
    {
        $ownerId = !empty($row['task_owner']) ? (int) $row['task_owner'] : null;
        $assigneeId = null;
        if (array_key_exists('task_assigned_to', $row) && !empty($row['task_assigned_to'])) {
            $assigneeId = (int) $row['task_assigned_to'];
        } else {
            $assigneeId = $ownerId;
        }

        $data = [
            'id' => (int) $row['task_id'],
            'name' => $row['task_name'],
            'project' => [
                'id' => (int) ($row['task_project'] ?? 0),
                'name' => $row['project_name'] ?? null,
            ],
            'status' => (int) ($row['task_status'] ?? 0),
            'priority' => (int) ($row['task_priority'] ?? 0),
            'percent_complete' => (int) ($row['task_percent_complete'] ?? 0),
            'start_date' => $row['task_start_date'] ?? null,
            'end_date' => $row['task_end_date'] ?? null,
            'duration' => (int) ($row['task_duration'] ?? 0),
            'owner_id' => $ownerId,
            'assigned_to' => $assigneeId,
            'milestone' => (bool) ($row['task_milestone'] ?? false),
        ];

        if ($detailed) {
            $data['description'] = $row['task_description'] ?? '';
            $data['hours_worked'] = (float) ($row['task_hours_worked'] ?? 0);
            $data['creator_id'] = $row['task_creator'] ? (int) $row['task_creator'] : null;
            $data['parent_id'] = $row['task_parent'] ? (int) $row['task_parent'] : null;
            $data['order'] = (int) ($row['task_order'] ?? 0);
            $data['type'] = (int) ($row['task_type'] ?? 0);
            $data['access'] = (int) ($row['task_access'] ?? 0);
            $data['created'] = $row['task_created'] ?? null;
            $data['updated'] = $row['task_updated'] ?? null;
        }

        return $data;
    }

    /**
     * Keep task status/percent coherence for canonical workflow statuses.
     *
     * @param array<string, mixed> $payload
     */
    private function normalizeTaskStatusPercent(array &$payload, ?int $currentStatus, ?int $currentPercent): void
    {
        $hasStatus = array_key_exists('status', $payload)
            && $payload['status'] !== null
            && $payload['status'] !== '';
        $hasPercent = array_key_exists('percent_complete', $payload)
            && $payload['percent_complete'] !== null
            && $payload['percent_complete'] !== '';

        if (!$hasStatus && !$hasPercent) {
            return;
        }

        $status = $hasStatus ? (int) $payload['status'] : (int) ($currentStatus ?? 0);
        $percent = $hasPercent ? (int) $payload['percent_complete'] : (int) ($currentPercent ?? 0);
        $percent = max(0, min(100, $percent));

        if ($status === 0) {
            $percent = 0;
        } elseif ($status === 3) {
            $percent = 100;
        } elseif ($status === 1 && ($percent < 0 || $percent > 99)) {
            $percent = 0;
        } elseif ($status === 2 && ($percent <= 0 || $percent >= 100)) {
            $percent = 50;
        }

        if ($hasStatus) {
            $payload['status'] = $status;
        }

        if ($hasPercent || in_array($status, [0, 1, 2, 3], true)) {
            $payload['percent_complete'] = $percent;
        }
    }

    private function supportsTaskAssignedToColumn(): bool
    {
        if ($this->hasTaskAssignedTo !== null) {
            return $this->hasTaskAssignedTo;
        }

        try {
            $tasksTable = $this->db->table('tasks');
            $count = (int) ($this->db->fetchValue(
                "SELECT COUNT(*)
                 FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = ?
                   AND column_name = 'task_assigned_to'",
                [$tasksTable]
            ) ?? 0);
            $this->hasTaskAssignedTo = $count > 0;
        } catch (\Throwable $e) {
            $this->hasTaskAssignedTo = false;
        }

        return $this->hasTaskAssignedTo;
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
     * Ensure authenticated user can access a task.
     * Allows task owner/creator or owning project owner/creator.
     */
    private function ensureTaskAccess(int $taskId): ?Response
    {
        $userId = $this->getUserId();
        if ($userId === null) {
            return $this->response->unauthorized();
        }

        $auth = AuthorizationService::getInstance();
        if ($auth->isAdmin($userId)) {
            return null;
        }

        $perm = new PermissionService();

        $row = $this->db->fetchOne(sprintf(
            "SELECT t.task_owner, t.task_creator, p.project_owner, p.project_creator, p.project_company
             FROM %s t
             LEFT JOIN %s p ON t.task_project = p.project_id
             WHERE t.task_id = %d",
            $this->db->table('tasks'),
            $this->db->table('projects'),
            $taskId
        ));

        if ($row === null) {
            return $this->notFound('Task not found');
        }

        $taskOwner = $row['task_owner'] ? (int) $row['task_owner'] : null;
        $taskCreator = $row['task_creator'] ? (int) $row['task_creator'] : null;
        $projectOwner = $row['project_owner'] ? (int) $row['project_owner'] : null;
        $projectCreator = $row['project_creator'] ? (int) $row['project_creator'] : null;
        $projectCompany = $row['project_company'] ? (int) $row['project_company'] : null;

        $escopo = $perm->getEscopoDados($userId);
        if ($escopo) {
            if ($escopo['role'] === PermissionService::ROLE_PREFEITO) {
                return null;
            }
            if ($projectCompany && in_array($projectCompany, $escopo['unidades_escopo'], true)) {
                return null;
            }
        }

        if (
            ($taskOwner && $taskOwner === $userId) ||
            ($taskCreator && $taskCreator === $userId) ||
            ($projectOwner && $projectOwner === $userId) ||
            ($projectCreator && $projectCreator === $userId)
        ) {
            return null;
        }

        return $this->response->forbidden('Access denied');
    }

    /**
     * Ensure authenticated user can access a project.
     */
    private function ensureProjectAccess(int $projectId): ?Response
    {
        $userId = $this->getUserId();
        if ($userId === null) {
            return $this->response->unauthorized();
        }

        $auth = AuthorizationService::getInstance();
        if ($auth->isAdmin($userId)) {
            return null;
        }

        $perm = new PermissionService();

        $row = $this->db->fetchOne(sprintf(
            "SELECT project_owner, project_creator, project_company FROM %s WHERE project_id = %d",
            $this->db->table('projects'),
            $projectId
        ));

        if ($row === null) {
            return $this->notFound('Project not found');
        }

        $ownerId = $row['project_owner'] ? (int) $row['project_owner'] : null;
        $creatorId = $row['project_creator'] ? (int) $row['project_creator'] : null;
        $companyId = $row['project_company'] ? (int) $row['project_company'] : null;

        $escopo = $perm->getEscopoDados($userId);
        if ($escopo) {
            if ($escopo['role'] === PermissionService::ROLE_PREFEITO) {
                return null;
            }
            if ($companyId && in_array($companyId, $escopo['unidades_escopo'], true)) {
                return null;
            }
        }

        if (!$companyId && (($ownerId && $ownerId === $userId) || ($creatorId && $creatorId === $userId))) {
            return null;
        }

        return $this->response->forbidden('Access denied');
    }
}
