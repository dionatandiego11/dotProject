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

use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Entity\Project;

/**
 * Controller de projetos
 */
class ProjectController extends BaseController
{
    /**
     * GET /v1/projects
     * 
     * Lista todos os projetos com paginação
     */
    public function index(): Response
    {
        $pagination = $this->getPagination();

        // Filtros opcionais
        $companyId = $this->request->getQueryParam('company_id');
        $status = $this->request->getQueryParam('status');
        $search = $this->request->getQueryParam('search');

        // Monta a query
        $where = '1=1';

        if ($companyId !== null) {
            $where .= sprintf(' AND project_company = %d', (int) $companyId);
        }

        if ($status !== null) {
            $where .= sprintf(' AND project_status = %d', (int) $status);
        }

        if ($search !== null) {
            $where .= sprintf(
                " AND (project_name LIKE %s OR project_description LIKE %s)",
                $this->db->quote("%$search%"),
                $this->db->quote("%$search%")
            );
        }

        // Conta total
        $totalSql = sprintf(
            "SELECT COUNT(*) as total FROM %s WHERE %s",
            $this->db->table('projects'),
            $where
        );
        $total = (int) ($this->db->fetchValue($totalSql) ?? 0);

        // Busca projetos
        $sql = sprintf(
            "SELECT p.*, c.company_name 
             FROM %s p 
             LEFT JOIN %s c ON p.project_company = c.company_id
             WHERE %s
             ORDER BY p.project_name ASC
             LIMIT %d OFFSET %d",
            $this->db->table('projects'),
            $this->db->table('companies'),
            $where,
            $pagination['per_page'],
            $pagination['offset']
        );

        $rows = $this->db->fetchAll($sql);

        $projects = array_map(fn($row) => $this->formatProject($row), $rows);

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

        $sql = sprintf(
            "SELECT p.*, c.company_name 
             FROM %s p 
             LEFT JOIN %s c ON p.project_company = c.company_id
             WHERE p.project_id = %d",
            $this->db->table('projects'),
            $this->db->table('companies'),
            $id
        );

        $row = $this->db->fetchOne($sql);

        if ($row === null) {
            return $this->notFound('Project not found');
        }

        return $this->json($this->formatProject($row, true));
    }

    /**
     * POST /v1/projects
     * 
     * Cria um novo projeto
     */
    public function store(): Response
    {
        $errors = $this->validateRequired(['name', 'company_id']);
        if ($errors !== null) {
            return $this->response->validationError($errors);
        }

        $body = $this->request->getBody();

        $project = new Project();
        $project->fill([
            'project_name' => $body['name'],
            'project_short_name' => $body['short_name'] ?? substr($body['name'], 0, 25),
            'project_company' => (int) $body['company_id'],
            'project_parent' => $body['parent_id'] ?? 0,
            'project_owner' => $this->getUserId(),
            'project_creator' => $this->getUserId(),
            'project_url' => $body['url'] ?? '',
            'project_demo_url' => $body['demo_url'] ?? '',
            'project_start_date' => $body['start_date'] ?? date('Y-m-d'),
            'project_end_date' => $body['end_date'] ?? null,
            'project_target_budget' => $body['budget'] ?? 0,
            'project_status' => $body['status'] ?? 0,
            'project_percent_complete' => 0,
            'project_color_identifier' => $body['color'] ?? '#4A90D9',
            'project_type' => $body['type'] ?? 0,
            'project_description' => $body['description'] ?? '',
            'project_priority' => $body['priority'] ?? 0,
        ]);

        if (!$project->save()) {
            return $this->error('Failed to create project');
        }

        return $this->created([
            'id' => $project->getId(),
            'message' => 'Project created successfully',
        ]);
    }

    /**
     * PUT /v1/projects/{id}
     * 
     * Atualiza um projeto
     */
    public function update(): Response
    {
        $id = (int) $this->request->getParam('id');

        $project = Project::find($id);
        if ($project === null) {
            return $this->notFound('Project not found');
        }

        $body = $this->request->getBody();

        // Atualiza apenas campos fornecidos
        $updateFields = [
            'name' => 'project_name',
            'short_name' => 'project_short_name',
            'company_id' => 'project_company',
            'url' => 'project_url',
            'demo_url' => 'project_demo_url',
            'start_date' => 'project_start_date',
            'end_date' => 'project_end_date',
            'budget' => 'project_target_budget',
            'status' => 'project_status',
            'color' => 'project_color_identifier',
            'type' => 'project_type',
            'description' => 'project_description',
            'priority' => 'project_priority',
        ];

        foreach ($updateFields as $apiField => $dbField) {
            if (isset($body[$apiField])) {
                $project->setAttribute($dbField, $body[$apiField]);
            }
        }

        if (!$project->save()) {
            return $this->error('Failed to update project');
        }

        return $this->json([
            'id' => $project->getId(),
            'message' => 'Project updated successfully',
        ]);
    }

    /**
     * DELETE /v1/projects/{id}
     * 
     * Exclui um projeto
     */
    public function destroy(): Response
    {
        $id = (int) $this->request->getParam('id');

        $project = Project::find($id);
        if ($project === null) {
            return $this->notFound('Project not found');
        }

        // Verifica se há tarefas associadas
        $taskCount = $this->db->fetchValue(sprintf(
            "SELECT COUNT(*) FROM %s WHERE task_project = %d",
            $this->db->table('tasks'),
            $id
        ));

        if ($taskCount > 0) {
            return $this->error('Cannot delete project with tasks. Delete tasks first.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!$project->delete()) {
            return $this->error('Failed to delete project');
        }

        return $this->response->noContent();
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

        // Verifica se projeto existe
        $exists = $this->db->fetchValue(sprintf(
            "SELECT project_id FROM %s WHERE project_id = %d",
            $this->db->table('projects'),
            $id
        ));

        if ($exists === null) {
            return $this->notFound('Project not found');
        }

        // Conta total de tarefas
        $total = (int) $this->db->fetchValue(sprintf(
            "SELECT COUNT(*) FROM %s WHERE task_project = %d",
            $this->db->table('tasks'),
            $id
        ));

        // Busca tarefas
        $sql = sprintf(
            "SELECT * FROM %s 
             WHERE task_project = %d
             ORDER BY task_order ASC, task_start_date ASC
             LIMIT %d OFFSET %d",
            $this->db->table('tasks'),
            $id,
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
     * Formata dados do projeto para a API
     */
    private function formatProject(array $row, bool $detailed = false): array
    {
        $data = [
            'id' => (int) $row['project_id'],
            'name' => $row['project_name'],
            'short_name' => $row['project_short_name'] ?? '',
            'company' => [
                'id' => (int) ($row['project_company'] ?? 0),
                'name' => $row['company_name'] ?? null,
            ],
            'status' => (int) ($row['project_status'] ?? 0),
            'percent_complete' => (int) ($row['project_percent_complete'] ?? 0),
            'priority' => (int) ($row['project_priority'] ?? 0),
            'color' => $row['project_color_identifier'] ?? '#4A90D9',
            'start_date' => $row['project_start_date'] ?? null,
            'end_date' => $row['project_end_date'] ?? null,
        ];

        if ($detailed) {
            $data['description'] = $row['project_description'] ?? '';
            $data['url'] = $row['project_url'] ?? '';
            $data['demo_url'] = $row['project_demo_url'] ?? '';
            $data['budget'] = (float) ($row['project_target_budget'] ?? 0);
            $data['actual_budget'] = (float) ($row['project_actual_budget'] ?? 0);
            $data['owner_id'] = $row['project_owner'] ? (int) $row['project_owner'] : null;
            $data['creator_id'] = $row['project_creator'] ? (int) $row['project_creator'] : null;
            $data['type'] = (int) ($row['project_type'] ?? 0);
            $data['parent_id'] = $row['project_parent'] ? (int) $row['project_parent'] : null;
        }

        return $data;
    }

    /**
     * Formata dados da tarefa para a API
     */
    private function formatTask(array $row): array
    {
        return [
            'id' => (int) $row['task_id'],
            'name' => $row['task_name'],
            'description' => $row['task_description'] ?? '',
            'status' => (int) ($row['task_status'] ?? 0),
            'priority' => (int) ($row['task_priority'] ?? 0),
            'percent_complete' => (int) ($row['task_percent_complete'] ?? 0),
            'start_date' => $row['task_start_date'] ?? null,
            'end_date' => $row['task_end_date'] ?? null,
            'duration' => (int) ($row['task_duration'] ?? 0),
            'owner_id' => $row['task_owner'] ? (int) $row['task_owner'] : null,
        ];
    }
}
