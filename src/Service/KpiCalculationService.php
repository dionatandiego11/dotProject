<?php
/**
 * Serviço de Cálculo de KPIs
 * 
 * @package DotProject\Service
 */

declare(strict_types=1);

namespace DotProject\Service;

use DotProject\Core\Database;
use DotProject\Core\Cache;
use DotProject\Core\TenantContext;

class KpiCalculationService
{
    private Database $db;
    private Cache $cache;
    private ?string $unidadeNivelColumn = null;
    private ?string $vinculoStatusColumn = null;
    private ?bool $hasTaskAssignedToColumn = null;
    private ?bool $hasTaskEstadoColumn = null;
    private ?bool $hasUserTasksTable = null;
    /**
     * @var array<string, bool>
     */
    private array $tableHasTenantColumn = [];
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->cache = new Cache(prefix: 'kpi:');
    }
    
    /**
     * KPIs do Dashboard Executivo (Prefeito)
     */
    public function getDashboardExecutivo(int $ppaId): array
    {
        $cacheKey = "executivo:{$ppaId}";
        $tenantProgramasAlias = $this->tenantAndCondition('dotp_programas', 'p');
        $tenantProjectsAlias = $this->tenantAndCondition('dotp_projects', 'pr');
        
        if ($cached = $this->cache->get($cacheKey)) {
            return $cached;
        }
        
        $sql = "SELECT 
            COUNT(DISTINCT p.id) as total_programas,
            COUNT(DISTINCT CASE WHEN p.estado = 'Concluido' THEN p.id END) as programas_concluidos,
            COUNT(DISTINCT CASE WHEN p.estado = 'Critico' THEN p.id END) as programas_criticos,
            COUNT(DISTINCT CASE WHEN p.estado = 'Parado' THEN p.id END) as programas_parados,
            COUNT(DISTINCT CASE WHEN p.estado = 'Atencao' THEN p.id END) as programas_atencao,
            COUNT(DISTINCT pr.project_id) as total_projetos,
            COUNT(DISTINCT CASE WHEN pr.project_estado = 'Concluido' THEN pr.project_id END) as projetos_concluidos,
            COUNT(DISTINCT CASE WHEN pr.project_estado = 'Atrasado' THEN pr.project_id END) as projetos_atrasados,
            AVG(p.percent_execucao) as percent_execucao_media
        FROM dotp_programas p
        LEFT JOIN dotp_projects pr ON pr.project_programa_id = p.id{$tenantProjectsAlias}
        WHERE 1=1{$tenantProgramasAlias}";
        
        $result = $this->db->fetchOne($sql) ?? [];
        
        $kpis = [
            'ppa_id' => $ppaId,
            'total_programas' => (int) ($result['total_programas'] ?? 0),
            'programas_concluidos' => (int) ($result['programas_concluidos'] ?? 0),
            'programas_criticos' => (int) ($result['programas_criticos'] ?? 0),
            'programas_parados' => (int) ($result['programas_parados'] ?? 0),
            'programas_atencao' => (int) ($result['programas_atencao'] ?? 0),
            'total_projetos' => (int) ($result['total_projetos'] ?? 0),
            'projetos_concluidos' => (int) ($result['projetos_concluidos'] ?? 0),
            'projetos_atrasados' => (int) ($result['projetos_atrasados'] ?? 0),
            'percent_execucao_media' => round((float) ($result['percent_execucao_media'] ?? 0), 2),
            'ranking_secretarias' => $this->getRankingSecretarias($ppaId),
        ];
        
        $this->cache->set($cacheKey, $kpis, 300);
        
        return $kpis;
    }
    
    /**
     * KPIs do Dashboard do Secretário
     */
    public function getDashboardSecretario(int $userId): array
    {
        $unidades = $this->getUserUnidades($userId);
        $tenantProgramasAlias = $this->tenantAndCondition('dotp_programas', 'p');
        $tenantProjectsAlias = $this->tenantAndCondition('dotp_projects', 'pr');
        
        if (empty($unidades)) {
            return [];
        }
        
        $placeholders = implode(',', array_fill(0, count($unidades), '?'));
        
        $sql = "SELECT 
            p.id,
            p.nome as programa,
            p.estado,
            p.percent_execucao,
            COUNT(DISTINCT pr.project_id) as total_projetos,
            COUNT(DISTINCT CASE WHEN pr.project_estado = 'Concluido' THEN pr.project_id END) as concluidos,
            COUNT(DISTINCT CASE WHEN pr.project_estado = 'Atrasado' THEN pr.project_id END) as atrasados,
            COUNT(DISTINCT CASE WHEN pr.project_estado IN ('Execucao', 'Em_Andamento') THEN pr.project_id END) as em_execucao
        FROM dotp_programas p
        LEFT JOIN dotp_projects pr ON pr.project_programa_id = p.id{$tenantProjectsAlias}
        WHERE p.unidade_id IN ($placeholders)
        {$tenantProgramasAlias}
        GROUP BY p.id
        ORDER BY p.percent_execucao DESC";
        
        return $this->db->fetchAll($sql, $unidades);
    }
    
    /**
     * KPIs do Dashboard do Coordenador
     */
    public function getDashboardCoordenador(int $userId): array
    {
        $tenantProjectsAlias = $this->tenantAndCondition('dotp_projects', 'pr');
        $tenantTasksAlias = $this->tenantAndCondition('dotp_tasks', 't');

        $sql = "SELECT 
            pr.project_id as id,
            pr.project_name as projeto,
            pr.project_estado as estado,
            pr.project_etapa_atual as etapa_atual,
            e.nome as etapa_nome,
            e.estado as etapa_estado,
            e.data_prevista_fim,
            e.dias_atraso,
            pr.project_percent_execucao as percent_execucao,
            COUNT(DISTINCT t.task_id) as tarefas_pendentes
        FROM dotp_projects pr
        JOIN dotp_etapas e ON e.projeto_id = pr.project_id AND e.numero = pr.project_etapa_atual
        LEFT JOIN dotp_tasks t ON t.task_project = pr.project_id 
            AND COALESCE(t.task_percent_complete, 0) < 100{$tenantTasksAlias}
        WHERE pr.project_coordenador_id = ?
        {$tenantProjectsAlias}
        GROUP BY pr.project_id
        ORDER BY e.data_prevista_fim ASC";
        
        return $this->db->fetchAll($sql, [$userId]);
    }
    
    /**
     * KPIs do Dashboard do Técnico
     */
    public function getDashboardTecnico(int $userId): array
    {
        $tenantTasksAlias = $this->tenantAndCondition('dotp_tasks', 't');
        $tenantProjectsAlias = $this->tenantAndCondition('dotp_projects', 'p');
        $assignmentJoin = '';
        $assignmentWhere = 't.task_owner = ?';
        $params = [$userId];

        if ($this->hasTaskAssignedToColumn()) {
            $assignmentWhere = 't.task_assigned_to = ?';
        } elseif ($this->hasUserTasksTable()) {
            $assignmentJoin = 'JOIN dotp_user_tasks ut ON ut.task_id = t.task_id' . $this->tenantAndCondition('dotp_user_tasks', 'ut');
            $assignmentWhere = 'ut.user_id = ?';
        }

        $estadoSelect = $this->hasTaskEstadoColumn()
            ? 't.estado'
            : "CASE
                    WHEN COALESCE(t.task_percent_complete, 0) >= 100 THEN 'Concluida'
                    WHEN COALESCE(t.task_percent_complete, 0) > 0 THEN 'Em_Andamento'
                    ELSE 'A_Fazer'
               END";

        $sql = "SELECT 
            t.task_id as id,
            t.task_name as tarefa,
            {$estadoSelect} as estado,
            p.project_name as projeto,
            e.nome as etapa,
            t.task_end_date as prazo,
            t.task_priority as prioridade,
            CASE
                WHEN t.task_end_date IS NULL THEN NULL
                ELSE DATEDIFF(DATE(t.task_end_date), CURDATE())
            END as dias_restantes
        FROM dotp_tasks t
        JOIN dotp_projects p ON p.project_id = t.task_project{$tenantProjectsAlias}
        LEFT JOIN dotp_etapas e ON e.projeto_id = p.project_id AND e.numero = p.project_etapa_atual
        {$assignmentJoin}
        WHERE {$assignmentWhere}
        {$tenantTasksAlias}
        AND COALESCE(t.task_percent_complete, 0) < 100
        ORDER BY t.task_priority DESC, t.task_end_date ASC
        LIMIT 20";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Ranking de secretarias por % de execução
     */
    private function getRankingSecretarias(int $ppaId): array
    {
        $nivelColumn = $this->getUnidadeNivelColumn();
        $tenantUnidadesAlias = $this->tenantAndCondition('dotp_unidades_organizacionais', 'u');
        $tenantProgramasAlias = $this->tenantAndCondition('dotp_programas', 'p');

        $sql = "SELECT 
            u.unidade_id,
            u.unidade_nome as secretaria,
            COUNT(DISTINCT p.id) as total_programas,
            AVG(p.percent_execucao) as media_execucao,
            COUNT(DISTINCT CASE WHEN p.estado = 'Concluido' THEN p.id END) as concluidos
        FROM dotp_unidades_organizacionais u
        JOIN dotp_programas p ON p.unidade_id = u.unidade_id{$tenantProgramasAlias}
        WHERE u.{$nivelColumn} = 2
        {$tenantUnidadesAlias}
        GROUP BY u.unidade_id
        ORDER BY media_execucao DESC
        LIMIT 10";
        
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Projetos em risco (atrasados ou críticos)
     */
    public function getProjetosRisco(int $ppaId, int $limite = 10): array
    {
        $tenantProjectsAlias = $this->tenantAndCondition('dotp_projects', 'pr');
        $tenantProgramasAlias = $this->tenantAndCondition('dotp_programas', 'p');
        $tenantUnidadesAlias = $this->tenantAndCondition('dotp_unidades_organizacionais', 'u');

        $sql = "SELECT 
            pr.project_id as id,
            pr.project_name as projeto,
            pr.project_estado as estado,
            p.nome as programa,
            u.unidade_nome as secretaria,
            e.dias_atraso,
            e.justificativa_atraso
        FROM dotp_projects pr
        JOIN dotp_programas p ON p.id = pr.project_programa_id{$tenantProgramasAlias}
        JOIN dotp_unidades_organizacionais u ON u.unidade_id = p.unidade_id{$tenantUnidadesAlias}
        JOIN dotp_etapas e ON e.projeto_id = pr.project_id AND e.numero = pr.project_etapa_atual
        WHERE (pr.project_estado = 'Atrasado' OR e.estado = 'Critica')
        {$tenantProjectsAlias}
        ORDER BY e.dias_atraso DESC
        LIMIT ?";
        
        return $this->db->fetchAll($sql, [$limite]);
    }
    
    /**
     * Estatísticas de tarefas
     */
    public function getEstatisticasTarefas(int $projetoId): array
    {
        $tenantTasks = $this->tenantAndCondition('dotp_tasks');

        if ($this->hasTaskEstadoColumn()) {
            $sql = "SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN estado = 'Concluida' THEN 1 END) as concluidas,
                COUNT(CASE WHEN estado = 'Em_Andamento' THEN 1 END) as em_andamento,
                COUNT(CASE WHEN estado = 'A_Fazer' THEN 1 END) as a_fazer,
                COUNT(CASE WHEN estado = 'Bloqueada' THEN 1 END) as bloqueadas
            FROM dotp_tasks
            WHERE task_project = ?{$tenantTasks}";
        } else {
            $sql = "SELECT
                COUNT(*) as total,
                COUNT(CASE WHEN COALESCE(task_percent_complete, 0) >= 100 THEN 1 END) as concluidas,
                COUNT(CASE WHEN COALESCE(task_percent_complete, 0) > 0 AND COALESCE(task_percent_complete, 0) < 100 THEN 1 END) as em_andamento,
                COUNT(CASE WHEN COALESCE(task_percent_complete, 0) = 0 THEN 1 END) as a_fazer,
                0 as bloqueadas
            FROM dotp_tasks
            WHERE task_project = ?{$tenantTasks}";
        }
        
        return $this->db->fetchOne($sql, [$projetoId]) ?? [
            'total' => 0,
            'concluidas' => 0,
            'em_andamento' => 0,
            'a_fazer' => 0,
            'bloqueadas' => 0,
        ];
    }
    
    /**
     * Obtém IDs das unidades do usuário
     * 
     * @return int[]
     */
    private function getUserUnidades(int $userId): array
    {
        $statusColumn = $this->resolveVinculoStatusColumn();
        $activeStatus = $this->vinculoStatusValueForSql(true, $statusColumn);
        $tenantVinculos = $this->tenantAndCondition('dotp_usuario_unidades');
        $sql = "SELECT vinculo_unidade_id 
                FROM dotp_usuario_unidades 
                WHERE vinculo_user_id = ? 
                AND {$statusColumn} = ?{$tenantVinculos}";
        
        $results = $this->db->fetchAll($sql, [$userId, $activeStatus]);
        return array_column($results, 'vinculo_unidade_id');
    }

    private function getUnidadeNivelColumn(): string
    {
        if ($this->unidadeNivelColumn !== null) {
            return $this->unidadeNivelColumn;
        }

        $exists = $this->db->fetchValue(
            "SELECT COUNT(*) FROM information_schema.columns 
             WHERE table_schema = DATABASE() 
               AND table_name = 'dotp_unidades_organizacionais' 
               AND column_name = 'unidade_nivel_id'"
        );

        $this->unidadeNivelColumn = ((int) $exists > 0) ? 'unidade_nivel_id' : 'unidade_nivel';
        return $this->unidadeNivelColumn;
    }

    private function resolveVinculoStatusColumn(): string
    {
        if ($this->vinculoStatusColumn !== null) {
            return $this->vinculoStatusColumn;
        }

        $hasStatus = (int) ($this->db->fetchValue(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'dotp_usuario_unidades'
               AND column_name = 'vinculo_status'"
        ) ?? 0);

        $this->vinculoStatusColumn = $hasStatus > 0 ? 'vinculo_status' : 'vinculo_ativo';
        return $this->vinculoStatusColumn;
    }

    private function vinculoStatusValueForSql(bool $ativo, string $column): int|string
    {
        if ($column === 'vinculo_status') {
            return $ativo ? 'ativo' : 'inativo';
        }

        return $ativo ? 1 : 0;
    }

    private function hasTaskAssignedToColumn(): bool
    {
        if ($this->hasTaskAssignedToColumn !== null) {
            return $this->hasTaskAssignedToColumn;
        }

        $exists = (int) ($this->db->fetchValue(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'dotp_tasks'
               AND column_name = 'task_assigned_to'"
        ) ?? 0);

        $this->hasTaskAssignedToColumn = $exists > 0;
        return $this->hasTaskAssignedToColumn;
    }

    private function hasTaskEstadoColumn(): bool
    {
        if ($this->hasTaskEstadoColumn !== null) {
            return $this->hasTaskEstadoColumn;
        }

        $exists = (int) ($this->db->fetchValue(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'dotp_tasks'
               AND column_name = 'estado'"
        ) ?? 0);

        $this->hasTaskEstadoColumn = $exists > 0;
        return $this->hasTaskEstadoColumn;
    }

    private function hasUserTasksTable(): bool
    {
        if ($this->hasUserTasksTable !== null) {
            return $this->hasUserTasksTable;
        }

        $exists = (int) ($this->db->fetchValue(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = 'dotp_user_tasks'"
        ) ?? 0);

        $this->hasUserTasksTable = $exists > 0;
        return $this->hasUserTasksTable;
    }

    private function tenantAndCondition(string $table, ?string $alias = null): string
    {
        $tenantId = $this->getTenantId();
        if ($tenantId === null || !$this->tableHasTenantColumn($table)) {
            return '';
        }

        $column = $alias !== null && $alias !== ''
            ? $alias . '.tenant_id'
            : 'tenant_id';

        return " AND {$column} = {$tenantId}";
    }

    private function tableHasTenantColumn(string $table): bool
    {
        $table = trim($table, '`');
        if (array_key_exists($table, $this->tableHasTenantColumn)) {
            return $this->tableHasTenantColumn[$table];
        }

        try {
            $exists = (int) ($this->db->fetchValue(
                "SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = ?
                   AND column_name = 'tenant_id'",
                [$table]
            ) ?? 0);
            $this->tableHasTenantColumn[$table] = $exists > 0;
        } catch (\Throwable) {
            $this->tableHasTenantColumn[$table] = false;
        }

        return $this->tableHasTenantColumn[$table];
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
