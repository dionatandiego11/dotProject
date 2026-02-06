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

class KpiCalculationService
{
    private Database $db;
    private Cache $cache;
    private ?string $unidadeNivelColumn = null;
    
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
        LEFT JOIN dotp_projects pr ON pr.project_programa_id = p.id";
        
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
        LEFT JOIN dotp_projects pr ON pr.project_programa_id = p.id
        WHERE p.unidade_id IN ($placeholders)
        GROUP BY p.id
        ORDER BY p.percent_execucao DESC";
        
        return $this->db->fetchAll($sql, $unidades);
    }
    
    /**
     * KPIs do Dashboard do Coordenador
     */
    public function getDashboardCoordenador(int $userId): array
    {
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
            AND t.estado != 'Concluida'
            AND t.etapa_id = pr.project_etapa_atual
        WHERE pr.project_coordenador_id = ?
        GROUP BY pr.project_id
        ORDER BY e.data_prevista_fim ASC";
        
        return $this->db->fetchAll($sql, [$userId]);
    }
    
    /**
     * KPIs do Dashboard do Técnico
     */
    public function getDashboardTecnico(int $userId): array
    {
        $sql = "SELECT 
            t.task_id as id,
            t.task_name as tarefa,
            t.estado,
            p.project_name as projeto,
            e.nome as etapa,
            t.task_end_date as prazo,
            t.task_priority as prioridade,
            DATEDIFF(t.task_end_date, CURDATE()) as dias_restantes
        FROM dotp_tasks t
        JOIN dotp_projects p ON p.project_id = t.task_project
        JOIN dotp_etapas e ON e.projeto_id = p.project_id AND e.numero = p.project_etapa_atual
        WHERE t.task_assigned_to = ?
        AND t.estado IN ('A_Fazer', 'Em_Andamento')
        ORDER BY t.task_priority DESC, t.task_end_date ASC
        LIMIT 20";
        
        return $this->db->fetchAll($sql, [$userId]);
    }
    
    /**
     * Ranking de secretarias por % de execução
     */
    private function getRankingSecretarias(int $ppaId): array
    {
        $nivelColumn = $this->getUnidadeNivelColumn();

        $sql = "SELECT 
            u.unidade_id,
            u.unidade_nome as secretaria,
            COUNT(DISTINCT p.id) as total_programas,
            AVG(p.percent_execucao) as media_execucao,
            COUNT(DISTINCT CASE WHEN p.estado = 'Concluido' THEN p.id END) as concluidos
        FROM dotp_unidades_organizacionais u
        JOIN dotp_programas p ON p.unidade_id = u.unidade_id
        WHERE u.{$nivelColumn} = 2
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
        $sql = "SELECT 
            pr.project_id as id,
            pr.project_name as projeto,
            pr.project_estado as estado,
            p.nome as programa,
            u.unidade_nome as secretaria,
            e.dias_atraso,
            e.justificativa_atraso
        FROM dotp_projects pr
        JOIN dotp_programas p ON p.id = pr.project_programa_id
        JOIN dotp_unidades_organizacionais u ON u.unidade_id = p.unidade_id
        JOIN dotp_etapas e ON e.projeto_id = pr.project_id AND e.numero = pr.project_etapa_atual
        WHERE (pr.project_estado = 'Atrasado' OR e.estado = 'Critica')
        ORDER BY e.dias_atraso DESC
        LIMIT ?";
        
        return $this->db->fetchAll($sql, [$limite]);
    }
    
    /**
     * Estatísticas de tarefas
     */
    public function getEstatisticasTarefas(int $projetoId): array
    {
        $sql = "SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN estado = 'Concluida' THEN 1 END) as concluidas,
            COUNT(CASE WHEN estado = 'Em_Andamento' THEN 1 END) as em_andamento,
            COUNT(CASE WHEN estado = 'A_Fazer' THEN 1 END) as a_fazer,
            COUNT(CASE WHEN estado = 'Bloqueada' THEN 1 END) as bloqueadas
        FROM dotp_tasks
        WHERE task_project = ?";
        
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
        $sql = "SELECT vinculo_unidade_id 
                FROM dotp_usuario_unidades 
                WHERE vinculo_user_id = ? 
                AND vinculo_ativo = 1";
        
        $results = $this->db->fetchAll($sql, [$userId]);
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
}
