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
use DotProject\Entity\Task;

/**
 * Controller de tarefas
 */
class TaskController extends BaseController
{
    /**
     * GET /v1/tasks
     * 
     * Lista todas as tarefas com paginação
     */
    public function index(): Response
    {
        $pagination = $this->getPagination();

        // Filtros opcionais
        $projectId = $this->request->getQueryParam('project_id');
        $status = $this->request->getQueryParam('status');
        $ownerId = $this->request->getQueryParam('owner_id');
        $search = $this->request->getQueryParam('search');
        $overdue = $this->request->getQueryParam('overdue'); // true/false

        // Monta a query
        $where = '1=1';

        if ($projectId !== null) {
            $where .= sprintf(' AND t.task_project = %d', (int) $projectId);
        }

        if ($status !== null) {
            $where .= sprintf(' AND t.task_status = %d', (int) $status);
        }

        if ($ownerId !== null) {
            $where .= sprintf(' AND t.task_owner = %d', (int) $ownerId);
        }

        if ($search !== null) {
            $where .= sprintf(
                " AND (t.task_name LIKE %s OR t.task_description LIKE %s)",
                $this->db->quote("%$search%"),
                $this->db->quote("%$search%")
            );
        }

        if ($overdue === 'true') {
            $where .= sprintf(
                " AND t.task_end_date < %s AND t.task_percent_complete < 100",
                $this->db->quote(date('Y-m-d'))
            );
        }

        // Conta total
        $totalSql = sprintf(
            "SELECT COUNT(*) as total FROM %s t WHERE %s",
            $this->db->table('tasks'),
            $where
        );
        $total = (int) ($this->db->fetchValue($totalSql) ?? 0);

        // Busca tarefas
        $sql = sprintf(
            "SELECT t.*, p.project_name 
             FROM %s t 
             LEFT JOIN %s p ON t.task_project = p.project_id
             WHERE %s
             ORDER BY t.task_start_date ASC, t.task_order ASC
             LIMIT %d OFFSET %d",
            $this->db->table('tasks'),
            $this->db->table('projects'),
            $where,
            $pagination['per_page'],
            $pagination['offset']
        );

        $rows = $this->db->fetchAll($sql);

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
        $errors = $this->validateRequired(['name', 'project_id']);
        if ($errors !== null) {
            return $this->response->validationError($errors);
        }

        $body = $this->request->getBody();

        // Verifica se projeto existe
        $projectExists = $this->db->fetchValue(sprintf(
            "SELECT project_id FROM %s WHERE project_id = %d",
            $this->db->table('projects'),
            (int) $body['project_id']
        ));

        if ($projectExists === null) {
            return $this->error('Project not found', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Calcula próxima ordem
        $maxOrder = $this->db->fetchValue(sprintf(
            "SELECT MAX(task_order) FROM %s WHERE task_project = %d",
            $this->db->table('tasks'),
            (int) $body['project_id']
        )) ?? 0;

        $task = new Task();
        $task->fill([
            'task_name' => $body['name'],
            'task_project' => (int) $body['project_id'],
            'task_parent' => $body['parent_id'] ?? 0,
            'task_milestone' => $body['milestone'] ?? 0,
            'task_owner' => $body['owner_id'] ?? $this->getUserId(),
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
        ]);

        if (!$task->save()) {
            return $this->error('Failed to create task');
        }

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

        $task = Task::find($id);
        if ($task === null) {
            return $this->notFound('Task not found');
        }

        $body = $this->request->getBody();

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

        foreach ($updateFields as $apiField => $dbField) {
            if (isset($body[$apiField])) {
                $task->setAttribute($dbField, $body[$apiField]);
            }
        }

        if (!$task->save()) {
            return $this->error('Failed to update task');
        }

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

        return $this->response->noContent();
    }

    /**
     * Formata dados da tarefa para a API
     */
    private function formatTask(array $row, bool $detailed = false): array
    {
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
            'owner_id' => $row['task_owner'] ? (int) $row['task_owner'] : null,
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
}
