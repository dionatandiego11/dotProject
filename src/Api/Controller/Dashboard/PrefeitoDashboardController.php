<?php
/**
 * Dashboard do Prefeito — Visão Executiva Geral.
 *
 * @package DotProject\Api\Controller\Dashboard
 */

declare(strict_types=1);

namespace DotProject\Api\Controller\Dashboard;

use DotProject\Api\Controller\BaseController;
use DotProject\Api\Request;
use DotProject\Api\Response;

class PrefeitoDashboardController extends BaseController
{
    use DashboardHelperTrait;

    /**
     * GET /api/v1/dashboard/prefeito
     */
    public function prefeito(): Response
    {
        $cacheKey = $this->cacheKey('dashboard', 'prefeito');
        $cached = $this->cache->get($cacheKey);

        if ($cached) {
            return $this->json($cached);
        }

        if ($this->checkModernTables()) {
            $data = $this->getModern();
        } else {
            $data = $this->getLegacy();
        }

        $this->cache->set($cacheKey, $data, 300);

        return $this->json(['data' => $data]);
    }

    private function getModern(): array
    {
        $db = \DotProject\Core\Database::getInstance();

        $ppaExecucao = $db->fetchAll(
            "SELECT 
                COUNT(*) as total_programas,
                COUNT(CASE WHEN estado = 'Concluido' THEN 1 END) as concluidos,
                COUNT(CASE WHEN estado IN ('Critico', 'Parado') THEN 1 END) as criticos,
                COUNT(CASE WHEN estado = 'Atencao' THEN 1 END) as atencao,
                AVG(percent_execucao) as percentual_medio
            FROM dotp_programas 
            WHERE estado != 'Arquivado'"
        )[0] ?? [];

        $projetosStatus = $db->fetchAll(
            "SELECT 
                estado,
                COUNT(*) as total,
                SUM(valor_previsto) as valor_total
            FROM dotp_projetos_prefeitura 
            WHERE estado != 'Cancelado'
            GROUP BY estado"
        );

        $nivelColumn = $this->getUnidadeNivelColumn();
        $statusFilter = $this->getUnidadeStatusFilter('u');
        $porSecretaria = $db->fetchAll(
            "SELECT 
                u.unidade_id,
                u.unidade_nome,
                u.unidade_sigla,
                COUNT(p.id) as total_projetos,
                COUNT(CASE WHEN p.estado = 'Atrasado' THEN 1 END) as atrasados,
                COUNT(CASE WHEN p.estado = 'Concluido' THEN 1 END) as concluidos,
                AVG(p.percent_execucao) as percentual_execucao
            FROM dotp_unidades_organizacionais u
            LEFT JOIN dotp_projetos_prefeitura p ON p.unidade_id = u.unidade_id AND p.estado != 'Cancelado'
            WHERE u.{$nivelColumn} = 2 AND {$statusFilter}
            GROUP BY u.unidade_id
            ORDER BY u.unidade_nome"
        );

        $obrasDestaque = $db->fetchAll(
            "SELECT 
                p.id,
                p.nome,
                p.estado,
                p.percent_execucao,
                p.data_prevista_fim,
                p.justificativa_atraso,
                u.unidade_nome as secretaria,
                et.nome as etapa_atual,
                et.estado as etapa_estado,
                DATEDIFF(CURDATE(), et.data_prevista_fim) as dias_atraso
            FROM dotp_projetos_prefeitura p
            JOIN dotp_unidades_organizacionais u ON u.unidade_id = p.unidade_id
            LEFT JOIN dotp_etapas et ON et.projeto_id = p.id AND et.numero = p.etapa_atual
            WHERE p.estado = 'Atrasado'
            ORDER BY dias_atraso DESC
            LIMIT 10"
        );

        $conveniosVencer = $db->fetchAll(
            "SELECT 
                p.id,
                p.nome,
                p.data_prevista_fim,
                p.valor_previsto,
                u.unidade_nome,
                DATEDIFF(p.data_prevista_fim, CURDATE()) as dias_restantes
            FROM dotp_projetos_prefeitura p
            JOIN dotp_unidades_organizacionais u ON u.unidade_id = p.unidade_id
            WHERE p.tipo = 'Convenio'
            AND p.estado NOT IN ('Concluido', 'Cancelado')
            AND p.data_prevista_fim IS NOT NULL
            AND DATEDIFF(p.data_prevista_fim, CURDATE()) <= 60
            ORDER BY dias_restantes ASC
            LIMIT 10"
        );

        $emendas = $db->fetchAll(
            "SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN percent_execucao >= 80 THEN 1 END) as executadas,
                COUNT(CASE WHEN percent_execucao < 30 AND DATEDIFF(CURDATE(), data_prevista_fim) < 120 THEN 1 END) as em_risco
            FROM dotp_projetos_prefeitura 
            WHERE tipo = 'Emenda' AND estado != 'Cancelado'"
        )[0] ?? [];

        $timeline = $db->fetchAll(
            "SELECT 
                p.id,
                p.nome,
                p.data_prevista_fim as data,
                'vencimento' as tipo_evento,
                u.unidade_nome
            FROM dotp_projetos_prefeitura p
            JOIN dotp_unidades_organizacionais u ON u.unidade_id = p.unidade_id
            WHERE p.estado NOT IN ('Concluido', 'Cancelado')
            AND p.data_prevista_fim IS NOT NULL
            AND DATEDIFF(p.data_prevista_fim, CURDATE()) BETWEEN 0 AND 30
            ORDER BY p.data_prevista_fim ASC
            LIMIT 15"
        );

        $orcamento = $db->fetchAll(
            "SELECT 
                COALESCE(SUM(valor_previsto), 0) as previsto,
                COALESCE(SUM(CASE WHEN situacao_orcamentaria = 'empenhado' THEN valor_previsto * 0.3 END), 0) as empenhado,
                COALESCE(SUM(CASE WHEN situacao_orcamentaria = 'pago' THEN valor_previsto END), 0) as pago
            FROM dotp_projetos_prefeitura 
            WHERE estado != 'Cancelado'"
        )[0] ?? [];

        return [
            'perfil' => 'prefeito',
            'source' => 'modern_tables',
            'ppa_execucao' => [
                'total_programas' => (int) ($ppaExecucao['total_programas'] ?? 0),
                'concluidos' => (int) ($ppaExecucao['concluidos'] ?? 0),
                'criticos' => (int) ($ppaExecucao['criticos'] ?? 0),
                'atencao' => (int) ($ppaExecucao['atencao'] ?? 0),
                'percentual_medio' => round((float) ($ppaExecucao['percentual_medio'] ?? 0), 2),
            ],
            'projetos_status' => $projetosStatus,
            'por_secretaria' => $porSecretaria,
            'obras_atrasadas' => $obrasDestaque,
            'convenios_vencer' => $conveniosVencer,
            'emendas' => $emendas,
            'timeline_30dias' => $timeline,
            'orcamento' => [
                'previsto' => (float) ($orcamento['previsto'] ?? 0),
                'empenhado' => (float) ($orcamento['empenhado'] ?? 0),
                'pago' => (float) ($orcamento['pago'] ?? 0),
                'percentual_executado' => ($orcamento['previsto'] ?? 0) > 0
                    ? round((($orcamento['pago'] ?? 0) / $orcamento['previsto']) * 100, 2)
                    : 0,
            ],
        ];
    }

    private function getLegacy(): array
    {
        $db = \DotProject\Core\Database::getInstance();

        $projectStats = $db->fetchAll(
            "SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN project_status = 5 THEN 1 END) as concluidos,
                COUNT(CASE WHEN project_status IN (4, 7) THEN 1 END) as criticos,
                COUNT(CASE WHEN project_status = 3 THEN 1 END) as em_andamento,
                AVG(project_percent_complete) as percentual_medio
            FROM dotp_projects"
        )[0] ?? [];

        $projetosStatus = $db->fetchAll(
            "SELECT 
                CASE project_status 
                    WHEN 0 THEN 'Nao_Definido'
                    WHEN 1 THEN 'Proposto'
                    WHEN 2 THEN 'Em_Planejamento'
                    WHEN 3 THEN 'Em_Andamento'
                    WHEN 4 THEN 'Em_Espera'
                    WHEN 5 THEN 'Concluido'
                    WHEN 6 THEN 'Template'
                    WHEN 7 THEN 'Arquivado'
                    ELSE 'Outro'
                END as estado,
                COUNT(*) as total,
                SUM(project_target_budget) as valor_total
            FROM dotp_projects 
            WHERE project_status NOT IN (6, 7)
            GROUP BY project_status"
        );

        $porSecretaria = $db->fetchAll(
            "SELECT 
                c.company_id as unidade_id,
                COALESCE(NULLIF(TRIM(u.unidade_nome), ''), c.company_name) as unidade_nome,
                SUBSTRING(COALESCE(NULLIF(TRIM(u.unidade_nome), ''), c.company_name), 1, 5) as unidade_sigla,
                COUNT(p.project_id) as total_projetos,
                COUNT(CASE WHEN p.project_status = 4 THEN 1 END) as atrasados,
                COUNT(CASE WHEN p.project_status = 5 THEN 1 END) as concluidos,
                AVG(p.project_percent_complete) as percentual_execucao
            FROM dotp_companies c
            LEFT JOIN dotp_projects p ON p.project_company = c.company_id
            LEFT JOIN dotp_unidades_organizacionais u ON u.unidade_id = c.company_id
            GROUP BY c.company_id
            ORDER BY unidade_nome"
        );

        $projetos = $db->fetchAll(
            "SELECT 
                project_id as id,
                project_name as nome,
                CASE project_status 
                    WHEN 4 THEN 'Em_Espera'
                    WHEN 3 THEN 'Em_Andamento'
                    ELSE 'Outro'
                END as estado,
                project_percent_complete as percent_execucao,
                project_end_date as data_prevista_fim,
                '' as justificativa_atraso,
                '' as secretaria
            FROM dotp_projects 
            WHERE project_status IN (2, 3, 4)
            ORDER BY project_end_date ASC
            LIMIT 10"
        );

        $timeline = $db->fetchAll(
            "SELECT 
                t.task_id as id,
                t.task_name as nome,
                t.task_end_date as data,
                'tarefa' as tipo_evento,
                p.project_name as unidade_nome
            FROM dotp_tasks t
            JOIN dotp_projects p ON p.project_id = t.task_project
            WHERE t.task_percent_complete < 100
            AND t.task_end_date IS NOT NULL
            AND DATEDIFF(t.task_end_date, CURDATE()) BETWEEN 0 AND 30
            ORDER BY t.task_end_date ASC
            LIMIT 15"
        );

        $orcamento = $db->fetchAll(
            "SELECT 
                COALESCE(SUM(project_target_budget), 0) as previsto,
                COALESCE(SUM(project_actual_budget), 0) as pago
            FROM dotp_projects 
            WHERE project_status NOT IN (6, 7)"
        )[0] ?? [];

        return [
            'perfil' => 'prefeito',
            'source' => 'legacy_tables',
            'info' => 'Usando tabelas legadas (dotp_projects). Execute migrations para habilitar funcionalidades completas.',
            'ppa_execucao' => [
                'total_programas' => (int) ($projectStats['total'] ?? 0),
                'concluidos' => (int) ($projectStats['concluidos'] ?? 0),
                'criticos' => (int) ($projectStats['criticos'] ?? 0),
                'atencao' => 0,
                'percentual_medio' => round((float) ($projectStats['percentual_medio'] ?? 0), 2),
            ],
            'projetos_status' => $projetosStatus,
            'por_secretaria' => $porSecretaria,
            'obras_atrasadas' => $projetos,
            'convenios_vencer' => [],
            'emendas' => ['total' => 0, 'executadas' => 0, 'em_risco' => 0],
            'timeline_30dias' => $timeline,
            'orcamento' => [
                'previsto' => (float) ($orcamento['previsto'] ?? 0),
                'empenhado' => 0,
                'pago' => (float) ($orcamento['pago'] ?? 0),
                'percentual_executado' => ($orcamento['previsto'] ?? 0) > 0
                    ? round((($orcamento['pago'] ?? 0) / $orcamento['previsto']) * 100, 2)
                    : 0,
            ],
        ];
    }
}
