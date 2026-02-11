<?php

declare(strict_types=1);

namespace DotProject\Service;

use DotProject\Core\Database;
use DotProject\Core\TenantContext;
use DotProject\Repository\NivelHierarquicoRepository;
use DotProject\Repository\UnidadeOrganizacionalRepository;
use DotProject\Repository\UsuarioUnidadeRepository;
use DotProject\Repository\UserRepository;

class OnboardingReadinessService
{
    private Database $db;
    private NivelHierarquicoRepository $nivelRepo;
    private UnidadeOrganizacionalRepository $unidadeRepo;
    private UsuarioUnidadeRepository $vinculoRepo;
    private UserRepository $userRepo;
    /** @var array<string, bool> */
    private array $columnPresenceCache = [];

    public function __construct(
        Database $db,
        NivelHierarquicoRepository $nivelRepo,
        UnidadeOrganizacionalRepository $unidadeRepo,
        UsuarioUnidadeRepository $vinculoRepo,
        UserRepository $userRepo
    ) {
        $this->db = $db;
        $this->nivelRepo = $nivelRepo;
        $this->unidadeRepo = $unidadeRepo;
        $this->vinculoRepo = $vinculoRepo;
        $this->userRepo = $userRepo;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildChecklist(): array
    {
        $summary = $this->buildSummary();

        $checklist = [
            $this->buildStep(
                'niveis_hierarquicos',
                'Niveis hierarquicos configurados',
                (int) $summary['niveis_ativos'],
                3,
                'Configure ao menos 3 niveis (ex.: Prefeitura, Secretaria, Departamento).'
            ),
            $this->buildStep(
                'unidade_raiz',
                'Unidade raiz criada',
                (int) $summary['unidades_raiz'],
                1,
                'Crie a unidade raiz da prefeitura para iniciar a arvore organizacional.'
            ),
            $this->buildStep(
                'unidades_com_responsavel',
                'Unidades com responsavel definido',
                (int) $summary['unidades_com_responsavel'],
                1,
                'Defina ao menos um gestor responsavel em unidade ativa.'
            ),
            $this->buildStep(
                'usuarios_ativos',
                'Usuarios ativos cadastrados',
                (int) $summary['usuarios_ativos'],
                2,
                'Cadastre usuarios-chave (admin e gestor inicial) para operacao.'
            ),
            $this->buildStep(
                'vinculos_ativos',
                'Vinculos usuario-unidade ativos',
                (int) $summary['vinculos_ativos'],
                1,
                'Crie vinculos para limitar escopo e habilitar dashboards por unidade.'
            ),
            $this->buildStep(
                'vinculo_principal',
                'Vinculo principal definido',
                (int) $summary['vinculos_principais'],
                1,
                'Defina vinculo principal para usuarios-chave.'
            ),
            $this->buildStep(
                'projeto_piloto',
                'Projeto piloto cadastrado',
                (int) $summary['projetos_cadastrados'],
                1,
                'Cadastre um projeto piloto para validar o fluxo operacional.'
            ),
            $this->buildStep(
                'tarefa_piloto',
                'Tarefa piloto criada',
                (int) $summary['tarefas_cadastradas'],
                1,
                'Crie uma tarefa e mova no Kanban (Backlog -> To Do -> In Progress -> Done).'
            ),
        ];

        $total = count($checklist);
        $completed = count(array_filter(
            $checklist,
            static fn(array $item): bool => ($item['status'] ?? 'pending') === 'done'
        ));

        $pending = array_values(array_filter(
            $checklist,
            static fn(array $item): bool => ($item['status'] ?? 'pending') !== 'done'
        ));

        return [
            'summary' => $summary,
            'progress' => [
                'completed' => $completed,
                'total' => $total,
                'percentage' => $total > 0 ? (int) floor(($completed / $total) * 100) : 0,
            ],
            'checklist' => $checklist,
            'next_steps' => array_map(
                static fn(array $item): string => (string) ($item['hint'] ?? ''),
                array_slice($pending, 0, 3)
            ),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function buildSummary(): array
    {
        $unidadeStats = $this->unidadeRepo->getEstatisticas();
        $vinculoStats = $this->vinculoRepo->getEstatisticas();

        $niveisAtivos = count($this->nivelRepo->findAllAtivos());
        $unidadesAtivas = (int) ($unidadeStats['total_unidades'] ?? 0);
        $unidadesSemResponsavel = (int) ($unidadeStats['sem_responsavel'] ?? 0);
        $unidadesComResponsavel = max(0, $unidadesAtivas - $unidadesSemResponsavel);
        $unidadesRaiz = (int) ($unidadeStats['unidades_raiz'] ?? 0);

        return [
            'niveis_ativos' => $niveisAtivos,
            'unidades_ativas' => $unidadesAtivas,
            'unidades_raiz' => $unidadesRaiz,
            'unidades_com_responsavel' => $unidadesComResponsavel,
            'usuarios_ativos' => $this->countActiveUsers(),
            'vinculos_ativos' => (int) ($vinculoStats['total_vinculos'] ?? 0),
            'usuarios_vinculados' => (int) ($vinculoStats['usuarios_vinculados'] ?? 0),
            'vinculos_principais' => $this->countPrincipalLinks(),
            'projetos_cadastrados' => $this->safeCountTable('projects'),
            'tarefas_cadastradas' => $this->safeCountTable('tasks'),
        ];
    }

    /**
     * @return array<string, int|string>
     */
    private function buildStep(string $key, string $label, int $current, int $target, string $hint): array
    {
        $done = $current >= $target;
        return [
            'key' => $key,
            'label' => $label,
            'status' => $done ? 'done' : 'pending',
            'current' => $current,
            'target' => $target,
            'hint' => $done ? '' : $hint,
        ];
    }

    private function countActiveUsers(): int
    {
        $table = $this->db->table('users');
        $hasUserStatus = $this->userRepo->supportsUserStatus();
        $filter = $hasUserStatus ? 'user_status = 0' : '1 = 1';

        return (int) ($this->db->fetchValue(
            "SELECT COUNT(*) FROM {$table} WHERE {$filter}" . $this->tenantAndCondition($table)
        ) ?? 0);
    }

    private function countPrincipalLinks(): int
    {
        $table = $this->db->table('usuario_unidades');

        if (!$this->tableHasColumn($table, 'vinculo_is_principal')) {
            return 0;
        }

        $statusColumn = $this->tableHasColumn($table, 'vinculo_status') ? 'vinculo_status' : 'vinculo_ativo';
        $statusValue = $statusColumn === 'vinculo_status' ? 'ativo' : 1;

        return (int) ($this->db->fetchValue(
            "SELECT COUNT(*) FROM {$table} WHERE {$statusColumn} = ? AND vinculo_is_principal = 1" .
            $this->tenantAndCondition($table),
            [$statusValue]
        ) ?? 0);
    }

    private function safeCountTable(string $tableWithoutPrefix): int
    {
        $table = $this->db->table($tableWithoutPrefix);
        try {
            return (int) ($this->db->fetchValue(
                "SELECT COUNT(*) FROM {$table} WHERE 1=1" . $this->tenantAndCondition($table)
            ) ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function tableHasColumn(string $tableName, string $columnName): bool
    {
        $table = trim($tableName, '`');
        $cacheKey = $table . ':' . $columnName;
        if (array_key_exists($cacheKey, $this->columnPresenceCache)) {
            return $this->columnPresenceCache[$cacheKey];
        }

        $count = (int) ($this->db->fetchValue(
            "SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND column_name = ?",
            [$table, $columnName]
        ) ?? 0);

        $this->columnPresenceCache[$cacheKey] = $count > 0;
        return $this->columnPresenceCache[$cacheKey];
    }

    private function tenantAndCondition(string $table, ?string $alias = null): string
    {
        $tenantId = $this->getTenantId();
        if ($tenantId === null || !$this->tableHasColumn($table, 'tenant_id')) {
            return '';
        }

        $column = $alias !== null && $alias !== ''
            ? $alias . '.tenant_id'
            : 'tenant_id';

        return " AND {$column} = {$tenantId}";
    }

    private function getTenantId(): ?int
    {
        if (!TenantContext::isEnabled()) {
            return null;
        }

        $tenantId = TenantContext::getTenantId();
        if ($tenantId === null || $tenantId <= 0) {
            return null;
        }

        return $tenantId;
    }
}
