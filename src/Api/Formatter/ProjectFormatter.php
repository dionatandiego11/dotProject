<?php
/**
 * Project Formatter
 *
 * Responsible for serializing project/task rows into API payloads.
 *
 * @package DotProject\Api\Formatter
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Api\Formatter;

class ProjectFormatter
{
    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function formatProject(array $row, bool $detailed = false): array
    {
        $unidadeId = isset($row['project_company']) && (int) $row['project_company'] > 0
            ? (int) $row['project_company']
            : null;
        $companyCompatId = $unidadeId ?? 0;
        $unidadeNome = isset($row['unidade_nome']) ? trim((string) $row['unidade_nome']) : '';
        $companyName = isset($row['company_name']) ? trim((string) $row['company_name']) : '';
        $displayUnidadeNome = $unidadeNome !== '' ? $unidadeNome : ($companyName !== '' ? $companyName : null);
        $programaId = isset($row['project_programa_id']) && (int) $row['project_programa_id'] > 0
            ? (int) $row['project_programa_id']
            : null;
        $programaNome = isset($row['programa_nome']) ? trim((string) $row['programa_nome']) : null;
        if ($programaNome === '') {
            $programaNome = null;
        }
        $acaoId = isset($row['project_acao_id']) && (int) $row['project_acao_id'] > 0
            ? (int) $row['project_acao_id']
            : null;
        $acaoNome = isset($row['acao_nome']) ? trim((string) $row['acao_nome']) : null;
        if ($acaoNome === '') {
            $acaoNome = null;
        }

        $data = [
            'id' => (int) $row['project_id'],
            'name' => $row['project_name'],
            'short_name' => $row['project_short_name'] ?? '',
            'unidade_id' => $unidadeId,
            'unidade' => [
                'id' => $unidadeId,
                'nome' => $displayUnidadeNome,
            ],
            'company_id' => $companyCompatId,
            'company' => [
                'id' => $companyCompatId,
                'name' => $displayUnidadeNome,
            ],
            'programa_id' => $programaId,
            'programa' => [
                'id' => $programaId,
                'nome' => $programaNome,
            ],
            'acao_id' => $acaoId,
            'acao' => [
                'id' => $acaoId,
                'nome' => $acaoNome,
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
            $data['owner_id'] = isset($row['project_owner']) && (int) $row['project_owner'] > 0
                ? (int) $row['project_owner']
                : null;
            $data['creator_id'] = isset($row['project_creator']) && (int) $row['project_creator'] > 0
                ? (int) $row['project_creator']
                : null;
            $data['type'] = (int) ($row['project_type'] ?? 0);
            $data['parent_id'] = isset($row['project_parent']) && (int) $row['project_parent'] > 0
                ? (int) $row['project_parent']
                : null;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function formatTask(array $row): array
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
