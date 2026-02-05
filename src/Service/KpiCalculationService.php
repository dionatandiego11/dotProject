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
            COUNT(DISTINCT pr.id) as total_projetos,
            COUNT(DISTINCT CASE WHEN pr.estado = 'Concluido' THEN pr.id END) as projetos_concluidos,
            COUNT(DISTINCT CASE WHEN pr.estado = 'Atrasado' THEN pr.id END) as projetos_atrasados,
            AVG(p.percent_execucao) as percent_execucao_media
        FROM ppa ppa
        LEFT JOIN programas p ON p.ppa_id = ppa.id
        LEFT JOIN projetos pr ON pr.programa_id = p.id
        WHERE ppa.id = ?";
        
        $result = $this->db->fetchOne($sql, [$ppaId]) ?? [];
        
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
            COUNT(DISTINCT pr.id) as total_projetos,
            COUNT(DISTINCT CASE WHEN pr.estado = 'Concluido' THEN pr.id END) as concluidos,
            COUNT(DISTINCT CASE WHEN pr.estado = 'Atrasado' THEN pr.id END) as atrasados,
            COUNT(DISTINCT CASE WHEN pr.estado = 'Em_Execucao' THEN pr.id END) as em_execucao
        FROM programas p
        LEFT JOIN projetos pr ON pr.programa_id = p.id
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
            pr.id,
            pr.nome as projeto,
            pr.estado,
            pr.etapa_atual,
            e.nome as etapa_nome,
            e.estado as etapa_estado,
            e.data_prevista_fim,
            e.dias_atraso,
            pr.percent_execucao,
            COUNT(DISTINCT t.task_id) as tarefas_pendentes
        FROM projetos pr
        JOIN etapas e ON e.projeto_id = pr.id AND e.numero = pr.etapa_atual
        LEFT JOIN dotp_tasks t ON t.task_project = pr.id 
            AND t.estado != 'Concluida'
            AND t.etapa_id = pr.etapa_atual
        WHERE pr.coordenador_id = ?
        GROUP BY pr.id
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
            p.nome as projeto,
            e.nome as etapa,
            t.task_end_date as prazo,
            t.task_priority as prioridade,
            DATEDIFF(t.task_end_date, CURDATE()) as dias_restantes
        FROM dotp_tasks t
        JOIN projetos p ON p.id = t.task_project
        JOIN etapas e ON e.projeto_id = p.id AND e.numero = p.etapa_atual
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
        $sql = "SELECT 
            u.unidade_id,
            u.unidade_nome as secretaria,
            COUNT(DISTINCT p.id) as total_programas,
            AVG(p.percent_execucao) as media_execucao,
            COUNT(DISTINCT CASE WHEN p.estado = 'Concluido' THEN p.id END) as concluidos
        FROM dotp_unidades_organizacionais u
        JOIN programas p ON p.unidade_id = u.unidade_id
        WHERE p.ppa_id = ?
        AND u.unidade_nivel = 2
        GROUP BY u.unidade_id
        ORDER BY media_execucao DESC
        LIMIT 10";
        
        return $this->db->fetchAll($sql, [$ppaId]);
    }
    
    /**
     * Projetos em risco (atrasados ou críticos)
     */
    public function getProjetosRisco(int $ppaId, int $limite = 10): array
    {
        $sql = "SELECT 
            pr.id,
            pr.nome as projeto,
            pr.estado,
            p.nome as programa,
            u.unidade_nome as secretaria,
            e.dias_atraso,
            e.justificativa_atraso
        FROM projetos pr
        JOIN programas p ON p.id = pr.programa_id
        JOIN dotp_unidades_organizacionais u ON u.unidade_id = p.unidade_id
        JOIN etapas e ON e.projeto_id = pr.id AND e.numero = pr.etapa_atual
        WHERE p.ppa_id = ?
        AND (pr.estado = 'Atrasado' OR e.estado = 'Critica')
        ORDER BY e.dias_atraso DESC
        LIMIT ?";
        
        return $this->db->fetchAll($sql, [$ppaId, $limite]);
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
}
