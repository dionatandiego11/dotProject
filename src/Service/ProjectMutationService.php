<?php
/**
 * Project Mutation Service
 *
 * Encapsulates write workflows for project creation/update/status mutation.
 *
 * @package DotProject\Service
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Service;

use DotProject\Core\Database;
use DotProject\Core\TenantAwareTrait;
use DotProject\Entity\ProjectEntity;
use DotProject\Repository\ProjectRepository;

class ProjectMutationService
{
    use TenantAwareTrait;

    private Database $db;
    private ProjectRepository $projectRepository;
    private ProjectStatusService $projectStatusService;
    private ProjectStatusHistoryService $statusHistoryService;
    private ValidationService $validator;

    public function __construct(
        ?Database $db = null,
        ?ProjectRepository $projectRepository = null,
        ?ProjectStatusService $projectStatusService = null,
        ?ProjectStatusHistoryService $statusHistoryService = null,
        ?ValidationService $validator = null
    ) {
        $this->db = $db ?? Database::getInstance();
        $this->projectRepository = $projectRepository ?? new ProjectRepository($this->db);
        $this->projectStatusService = $projectStatusService ?? new ProjectStatusService();
        $this->statusHistoryService = $statusHistoryService ?? new ProjectStatusHistoryService($this->db);
        $this->validator = $validator ?? new ValidationService();
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function create(array $body, ?int $userId): array
    {
        $unidadeId = $this->resolveUnidadeFromBody($body);

        if ($unidadeId === null) {
            return $this->validationResult($this->unidadeValidationError('A unidade responsavel e obrigatoria.'));
        }

        if ($dateValidation = $this->validateDateRange($body)) {
            return $this->validationResult($dateValidation);
        }

        $shortName = array_key_exists('short_name', $body) ? trim((string) $body['short_name']) : '';
        $validationData = [
            'project_name' => $body['name'] ?? '',
            'project_short_name' => $shortName,
            'project_company' => $unidadeId,
            'project_status' => $body['status'] ?? 0,
            'project_percent_complete' => $body['percent_complete'] ?? 0,
            'project_priority' => $body['priority'] ?? 0,
        ];

        $validation = $this->validator->validateProject($validationData);
        if ($validation->fails()) {
            return $this->validationResult($validation->errors());
        }

        $this->projectStatusService->normalizeStatusPercent($body, null, null);

        $unidadeExists = $this->db->fetchValue(sprintf(
            "SELECT unidade_id FROM dotp_unidades_organizacionais WHERE unidade_id = %d%s",
            $unidadeId,
            $this->tenantAndCondition('dotp_unidades_organizacionais')
        ));
        if ($unidadeExists === null) {
            return $this->validationResult($this->unidadeValidationError('Unidade responsavel nao encontrada.'));
        }

        $targetUnidadeAccess = AuthorizationService::getInstance()->resolveTargetUnidadeAccessResult($userId, $unidadeId);
        if ($targetUnidadeAccess !== AuthorizationService::ACCESS_ALLOWED) {
            return $this->accessResult($targetUnidadeAccess);
        }

        $project = new ProjectEntity();
        $project->setName((string) ($body['name'] ?? ''));
        $project->setShortName($shortName !== '' ? $shortName : null);
        $project->setCompanyId($unidadeId);
        $project->setOwnerId($userId);
        $project->setUrl(isset($body['url']) ? (string) $body['url'] : '');
        $project->setStatus((int) ($body['status'] ?? 0));
        $project->setPercentComplete((int) ($body['percent_complete'] ?? 0));
        $project->setColorIdentifier((string) ($body['color'] ?? '#4A90D9'));
        $project->setDescription(isset($body['description']) ? (string) $body['description'] : '');
        $project->setPriority((int) ($body['priority'] ?? 0));
        $project->setStartDate(new \DateTime((string) ($body['start_date'] ?? date('Y-m-d'))));
        if (!empty($body['end_date'])) {
            $project->setEndDate(new \DateTime((string) $body['end_date']));
        }

        $projectId = $this->projectRepository->save($project);
        if ($projectId <= 0) {
            return $this->errorResult('Failed to create project');
        }

        $legacyFields = [];
        if ($this->tableHasColumn($this->db->table('projects'), 'project_parent')) {
            $legacyFields['project_parent'] = (int) ($body['parent_id'] ?? 0);
        }
        if ($this->tableHasColumn($this->db->table('projects'), 'project_creator')) {
            $legacyFields['project_creator'] = $userId;
        }
        if ($this->tableHasColumn($this->db->table('projects'), 'project_demo_url')) {
            $legacyFields['project_demo_url'] = (string) ($body['demo_url'] ?? '');
        }
        if ($this->tableHasColumn($this->db->table('projects'), 'project_target_budget')) {
            $legacyFields['project_target_budget'] = (float) ($body['budget'] ?? 0);
        }
        if ($this->tableHasColumn($this->db->table('projects'), 'project_type')) {
            $legacyFields['project_type'] = (int) ($body['type'] ?? 0);
        }

        if (!$this->updateProjectRow($projectId, $legacyFields)) {
            return $this->errorResult('Failed to create project');
        }

        return [
            'kind' => 'created',
            'data' => [
                'id' => $projectId,
                'unidade_id' => $unidadeId,
                'message' => 'Project created successfully',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function update(ProjectEntity $project, int $projectId, array $body, ?int $userId): array
    {
        if (array_key_exists('short_name', $body) && trim((string) $body['short_name']) === '') {
            $body['short_name'] = null;
        }

        if ($dateValidation = $this->validateDateRange($body)) {
            return $this->validationResult($dateValidation);
        }

        $hasUnidadeField = $this->hasUnidadeField($body);
        $unidadeId = $hasUnidadeField ? $this->resolveUnidadeFromBody($body) : null;

        $validationData = [];
        if (isset($body['name'])) {
            $validationData['project_name'] = $body['name'];
        }
        if (array_key_exists('short_name', $body)) {
            $validationData['project_short_name'] = $body['short_name'];
        }
        if ($hasUnidadeField) {
            $validationData['project_company'] = $unidadeId;
        }
        if (array_key_exists('status', $body)) {
            $validationData['project_status'] = $body['status'];
        }
        if (array_key_exists('percent_complete', $body)) {
            $validationData['project_percent_complete'] = $body['percent_complete'];
        }
        if (array_key_exists('priority', $body)) {
            $validationData['project_priority'] = $body['priority'];
        }

        if (!empty($validationData)) {
            $validation = $this->validator->validate($validationData);

            if (array_key_exists('project_name', $validationData)) {
                $validation
                    ->required('project_name', 'O nome do projeto e obrigatorio.')
                    ->minLength('project_name', 3, 'O nome do projeto deve ter pelo menos 3 caracteres.')
                    ->maxLength('project_name', 255, 'O nome do projeto deve ter no maximo 255 caracteres.');
            }

            if (array_key_exists('project_short_name', $validationData) && $validationData['project_short_name'] !== null) {
                $validation->maxLength('project_short_name', 10, 'O nome curto deve ter no maximo 10 caracteres.');
            }

            if (array_key_exists('project_company', $validationData)) {
                $validation->integer('project_company', 'A empresa deve ser um valor numerico.');
            }

            if (array_key_exists('project_status', $validationData)) {
                $validation->between('project_status', 0, 7, 'Status invalido.');
            }

            if (array_key_exists('project_percent_complete', $validationData)) {
                $validation->between('project_percent_complete', 0, 100, 'Percentual invalido.');
            }

            if (array_key_exists('project_priority', $validationData)) {
                $validation->between('project_priority', -1, 5, 'Prioridade invalida.');
            }

            if ($validation->fails()) {
                return $this->validationResult($validation->errors());
            }
        }

        $currentStatus = $project->getStatus();
        $this->projectStatusService->normalizeStatusPercent($body, $currentStatus, $project->getPercentComplete());

        $targetStatus = null;
        if (array_key_exists('status', $body)) {
            $targetStatus = (int) $body['status'];
            if (!$this->projectStatusService->isAllowedTransition($currentStatus, $targetStatus)) {
                return $this->validationResult($this->projectStatusService->transitionErrorPayload($currentStatus, $targetStatus));
            }
        }

        if ($hasUnidadeField) {
            if ($unidadeId === null) {
                return $this->validationResult($this->unidadeValidationError('A unidade responsavel e obrigatoria.'));
            }

            $unidadeExists = $this->db->fetchValue(sprintf(
                "SELECT unidade_id FROM dotp_unidades_organizacionais WHERE unidade_id = %d%s",
                $unidadeId,
                $this->tenantAndCondition('dotp_unidades_organizacionais')
            ));
            if ($unidadeExists === null) {
                return $this->validationResult($this->unidadeValidationError('Unidade responsavel nao encontrada.'));
            }

            $targetUnidadeAccess = AuthorizationService::getInstance()->resolveTargetUnidadeAccessResult($userId, $unidadeId);
            if ($targetUnidadeAccess !== AuthorizationService::ACCESS_ALLOWED) {
                return $this->accessResult($targetUnidadeAccess);
            }
        }

        if (array_key_exists('name', $body)) {
            $project->setName((string) $body['name']);
        }
        if (array_key_exists('short_name', $body)) {
            $project->setShortName($body['short_name'] !== null ? (string) $body['short_name'] : null);
        }
        if (array_key_exists('url', $body)) {
            $project->setUrl($body['url'] !== null ? (string) $body['url'] : null);
        }
        if (array_key_exists('start_date', $body)) {
            $project->setStartDate($body['start_date'] ? new \DateTime((string) $body['start_date']) : null);
        }
        if (array_key_exists('end_date', $body)) {
            $project->setEndDate($body['end_date'] ? new \DateTime((string) $body['end_date']) : null);
        }
        if (array_key_exists('status', $body)) {
            $project->setStatus((int) $body['status']);
        }
        if (array_key_exists('percent_complete', $body)) {
            $project->setPercentComplete((int) $body['percent_complete']);
        }
        if (array_key_exists('color', $body)) {
            $project->setColorIdentifier((string) $body['color']);
        }
        if (array_key_exists('description', $body)) {
            $project->setDescription($body['description'] !== null ? (string) $body['description'] : null);
        }
        if (array_key_exists('priority', $body)) {
            $project->setPriority((int) $body['priority']);
        }

        if ($hasUnidadeField && $unidadeId !== null) {
            $project->setCompanyId($unidadeId);
        }

        if ($this->projectRepository->save($project) <= 0) {
            return $this->errorResult('Failed to update project');
        }

        $legacyUpdates = [];
        if (array_key_exists('demo_url', $body) && $this->tableHasColumn($this->db->table('projects'), 'project_demo_url')) {
            $legacyUpdates['project_demo_url'] = $body['demo_url'] !== null ? (string) $body['demo_url'] : '';
        }
        if (array_key_exists('budget', $body) && $this->tableHasColumn($this->db->table('projects'), 'project_target_budget')) {
            $legacyUpdates['project_target_budget'] = (float) ($body['budget'] ?? 0);
        }
        if (array_key_exists('type', $body) && $this->tableHasColumn($this->db->table('projects'), 'project_type')) {
            $legacyUpdates['project_type'] = (int) $body['type'];
        }

        if (!$this->updateProjectRow($projectId, $legacyUpdates)) {
            return $this->errorResult('Failed to update project');
        }

        if ($targetStatus !== null && $targetStatus !== $currentStatus) {
            $this->statusHistoryService->recordStatusChange(
                (int) ($project->getId() ?? 0),
                $currentStatus,
                $targetStatus,
                $userId,
                $this->resolveStatusChangeSource($body, 'projects_update')
            );
        }

        return [
            'kind' => 'json',
            'data' => [
                'id' => $project->getId(),
                'message' => 'Project updated successfully',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function updateStatus(ProjectEntity $project, array $body, ?int $userId): array
    {
        if (!array_key_exists('status', $body) || $body['status'] === '' || $body['status'] === null) {
            return $this->validationResult([
                'status' => 'Status e obrigatorio.',
            ]);
        }

        $targetStatus = (int) $body['status'];
        if ($targetStatus < 0 || $targetStatus > 7) {
            return $this->validationResult([
                'status' => 'Status invalido.',
            ]);
        }

        $currentStatus = $project->getStatus();
        if (!$this->projectStatusService->isAllowedTransition($currentStatus, $targetStatus)) {
            return $this->validationResult($this->projectStatusService->transitionErrorPayload($currentStatus, $targetStatus));
        }

        $payload = ['status' => $targetStatus];
        if (array_key_exists('percent_complete', $body)) {
            $payload['percent_complete'] = $body['percent_complete'];
        }

        $this->projectStatusService->normalizeStatusPercent($payload, $currentStatus, $project->getPercentComplete());

        $project->setStatus((int) $payload['status']);
        if (array_key_exists('percent_complete', $payload)) {
            $project->setPercentComplete((int) $payload['percent_complete']);
        }

        if ($this->projectRepository->save($project) <= 0) {
            return $this->errorResult('Failed to update project status');
        }

        if ($currentStatus !== $targetStatus) {
            $this->statusHistoryService->recordStatusChange(
                (int) ($project->getId() ?? 0),
                $currentStatus,
                $targetStatus,
                $userId,
                $this->resolveStatusChangeSource($body, 'projects_status_endpoint')
            );
        }

        return [
            'kind' => 'json',
            'data' => [
                'id' => $project->getId(),
                'status' => $project->getStatus(),
                'percent_complete' => $project->getPercentComplete(),
                'message' => 'Project status updated successfully',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, string>|null
     */
    private function validateDateRange(array $body): ?array
    {
        if (!empty($body['start_date']) && !empty($body['end_date']) && strtotime((string) $body['start_date']) > strtotime((string) $body['end_date'])) {
            return [
                'end_date' => 'A data de termino deve ser maior ou igual a data de inicio.',
            ];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function hasUnidadeField(array $body): bool
    {
        return array_key_exists('unidade_id', $body) || array_key_exists('company_id', $body);
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
     * @return array<string, string>
     */
    private function unidadeValidationError(string $message): array
    {
        return [
            'unidade_id' => $message,
            'company_id' => $message,
        ];
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function updateProjectRow(int $projectId, array $fields): bool
    {
        if ($fields === []) {
            return true;
        }

        return $this->db->update(
            'projects',
            $fields,
            sprintf(
                'project_id = %d%s',
                $projectId,
                $this->tenantAndCondition($this->db->table('projects'))
            )
        );
    }

    /**
     * @param array<string, mixed> $body
     */
    private function resolveStatusChangeSource(array $body, string $defaultSource): string
    {
        $source = $defaultSource;

        if (isset($body['status_change_source']) && is_string($body['status_change_source'])) {
            $source = $body['status_change_source'];
        } elseif (isset($body['source']) && is_string($body['source'])) {
            $source = $body['source'];
        }

        $source = trim($source);
        if ($source === '') {
            return 'system';
        }

        $source = preg_replace('/[^a-zA-Z0-9._-]/', '_', $source) ?? 'system';
        $source = trim($source, '._-');
        if ($source === '') {
            return 'system';
        }

        return mb_substr($source, 0, 64);
    }

    /**
     * @param array<string, string> $errors
     * @return array<string, mixed>
     */
    private function validationResult(array $errors): array
    {
        return [
            'kind' => 'validation_error',
            'errors' => $errors,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function errorResult(string $message): array
    {
        return [
            'kind' => 'error',
            'message' => $message,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function accessResult(string $accessResult): array
    {
        return match ($accessResult) {
            AuthorizationService::ACCESS_UNAUTHORIZED => ['kind' => 'unauthorized'],
            AuthorizationService::ACCESS_FORBIDDEN => ['kind' => 'forbidden', 'message' => 'Access denied'],
            AuthorizationService::ACCESS_NOT_FOUND => ['kind' => 'not_found', 'message' => 'Project not found'],
            default => ['kind' => 'forbidden', 'message' => 'Access denied'],
        };
    }

    protected function getDatabase(): Database
    {
        return $this->db;
    }
}
