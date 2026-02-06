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
use DotProject\Service\AuthorizationService;
use DotProject\Service\PermissionService;

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
        if (!$this->checkPermission('projects', 'view')) {
            return $this->response->forbidden('Insufficient permissions');
        }

        $userId = $this->getUserId();
        $auth = AuthorizationService::getInstance();
        $perm = new PermissionService();

        $pagination = $this->getPagination();

        // Filtros opcionais
        $companyId = $this->request->getQueryParam('company_id');
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

        if ($companyId !== null) {
            $where .= ' AND project_company = ?';
            $params[] = (int) $companyId;
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

        // Conta total
        $totalSql = sprintf(
            "SELECT COUNT(*) as total FROM %s WHERE %s",
            $this->db->table('projects'),
            $where
        );
        $total = (int) ($this->db->fetchValueParams($totalSql, $params) ?? 0);

        // Busca projetos
        $sql = sprintf(
            "SELECT p.*, c.company_name, u.unidade_nome 
             FROM %s p 
             LEFT JOIN %s c ON p.project_company = c.company_id
             LEFT JOIN dotp_unidades_organizacionais u ON p.project_company = u.unidade_id
             WHERE %s
             ORDER BY p.project_name ASC
             LIMIT ? OFFSET ?",
            $this->db->table('projects'),
            $this->db->table('companies'),
            $where
        );
        $rows = $this->db->fetchAllParams($sql, array_merge($params, [
            $pagination['per_page'],
            $pagination['offset'],
        ]));

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

        if (!$this->checkPermission('projects', 'view')) {
            return $this->response->forbidden('Insufficient permissions');
        }

        if ($guard = $this->ensureProjectAccess($id)) {
            return $guard;
        }

        $sql = sprintf(
            "SELECT p.*, c.company_name, u.unidade_nome 
             FROM %s p 
             LEFT JOIN %s c ON p.project_company = c.company_id
             LEFT JOIN dotp_unidades_organizacionais u ON p.project_company = u.unidade_id
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
        if (!$this->checkPermission('projects', 'add')) {
            return $this->response->forbidden('Insufficient permissions');
        }

        $body = $this->request->getBody();
        if (empty($body['company_id'])) {
            return $this->response->validationError([
                'company_id' => 'A unidade responsavel e obrigatoria.'
            ]);
        }
        if (!empty($body['start_date']) && !empty($body['end_date'])) {
            if (strtotime($body['start_date']) > strtotime($body['end_date'])) {
                return $this->response->validationError([
                    'end_date' => 'A data de término deve ser maior ou igual à data de início.'
                ]);
            }
        }
        $shortName = '';
        if (array_key_exists('short_name', $body)) {
            $shortName = trim((string) $body['short_name']);
        }
        $validationData = [
            'project_name' => $body['name'] ?? '',
            'project_short_name' => $shortName,
            'project_company' => $body['company_id'] ?? null,
            'project_status' => $body['status'] ?? 0,
            'project_priority' => $body['priority'] ?? 0,
        ];
        $validation = $this->validation()->validateProject($validationData);

        if ($validation->fails()) {
            return $this->response->validationError($validation->errors());
        }

        $unidadeExists = $this->db->fetchValue(sprintf(
            "SELECT unidade_id FROM dotp_unidades_organizacionais WHERE unidade_id = %d",
            (int) $body['company_id']
        ));
        if ($unidadeExists === null) {
            return $this->response->validationError([
                'company_id' => 'Unidade responsavel nao encontrada.'
            ]);
        }

        $project = new Project();
        $project->fill([
            'project_name' => $body['name'],
            'project_short_name' => $shortName !== '' ? $shortName : null,
            'project_company' => !empty($body['company_id']) ? (int) $body['company_id'] : null,
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

        if (!$this->checkPermission('projects', 'edit')) {
            return $this->response->forbidden('Insufficient permissions');
        }

        if ($guard = $this->ensureProjectAccess($id)) {
            return $guard;
        }

        $project = Project::find($id);
        if ($project === null) {
            return $this->notFound('Project not found');
        }

        $body = $this->request->getBody();
        if (array_key_exists('short_name', $body) && trim((string) $body['short_name']) === '') {
            $body['short_name'] = null;
        }
        if (!empty($body['start_date']) && !empty($body['end_date'])) {
            if (strtotime($body['start_date']) > strtotime($body['end_date'])) {
                return $this->response->validationError([
                    'end_date' => 'A data de término deve ser maior ou igual à data de início.'
                ]);
            }
        }

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

        $validationData = [];
        if (isset($body['name'])) {
            $validationData['project_name'] = $body['name'];
        }
        if (array_key_exists('short_name', $body)) {
            $validationData['project_short_name'] = $body['short_name'];
        }
        if (array_key_exists('company_id', $body)) {
            $validationData['project_company'] = $body['company_id'];
        }
        if (array_key_exists('status', $body)) {
            $validationData['project_status'] = $body['status'];
        }
        if (array_key_exists('priority', $body)) {
            $validationData['project_priority'] = $body['priority'];
        }

        if (!empty($validationData)) {
            $validation = $this->validation()->validate($validationData)->validateProject($validationData);
            if ($validation->fails()) {
                return $this->response->validationError($validation->errors());
            }
        }

        if (array_key_exists('company_id', $body)) {
            if (empty($body['company_id'])) {
                return $this->response->validationError([
                    'company_id' => 'A unidade responsavel e obrigatoria.'
                ]);
            }
            $unidadeExists = $this->db->fetchValue(sprintf(
                "SELECT unidade_id FROM dotp_unidades_organizacionais WHERE unidade_id = %d",
                (int) $body['company_id']
            ));
            if ($unidadeExists === null) {
                return $this->response->validationError([
                    'company_id' => 'Unidade responsavel nao encontrada.'
                ]);
            }
        }

        foreach ($updateFields as $apiField => $dbField) {
            if (array_key_exists($apiField, $body)) {
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

        if (!$this->checkPermission('projects', 'delete')) {
            return $this->response->forbidden('Insufficient permissions');
        }

        if ($guard = $this->ensureProjectAccess($id)) {
            return $guard;
        }

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

        if (!$this->checkPermission('projects', 'view')) {
            return $this->response->forbidden('Insufficient permissions');
        }

        if ($guard = $this->ensureProjectAccess($id)) {
            return $guard;
        }

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
        $unidadeNome = isset($row['unidade_nome']) ? trim((string) $row['unidade_nome']) : '';
        $companyName = isset($row['company_name']) ? trim((string) $row['company_name']) : '';
        $displayCompanyName = $unidadeNome !== '' ? $unidadeNome : ($companyName !== '' ? $companyName : null);

        $data = [
            'id' => (int) $row['project_id'],
            'name' => $row['project_name'],
            'short_name' => $row['project_short_name'] ?? '',
            'company' => [
                'id' => (int) ($row['project_company'] ?? 0),
                'name' => $displayCompanyName,
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

    /**
     * Ensure authenticated user can access a project.
     * Allows project owner/creator to proceed.
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
