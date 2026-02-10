<?php
/**
 * Dashboard do Controlador — Visão de Fiscalização.
 *
 * @package DotProject\Api\Controller\Dashboard
 */

declare(strict_types=1);

namespace DotProject\Api\Controller\Dashboard;

use DotProject\Api\Controller\BaseController;
use DotProject\Api\Request;
use DotProject\Api\Response;

class ControladorDashboardController extends BaseController
{
    use DashboardHelperTrait;

    /**
     * GET /api/v1/dashboard/controlador
     */
    public function controlador(): Response
    {
        $cacheKey = $this->cacheKey('dashboard', 'controlador');
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

        $alertasConformidade = [
            'sem_prestacao_contas' => (int) $db->fetchValue(
                "SELECT COUNT(*) FROM dotp_projetos_prefeitura WHERE tipo = 'Convenio' 
                 AND estado = 'Concluido' 
                 AND id NOT IN (SELECT DISTINCT file_project FROM dotp_files WHERE file_project IS NOT NULL)"
            ),
            'execucao_acima_cronograma' => (int) $db->fetchValue(
                "SELECT COUNT(*) FROM dotp_projetos_prefeitura WHERE percent_execucao > 100 AND estado != 'Concluido'"
            ),
            'diferenca_empenho_execucao' => 0,
        ];

        $nivelColumn = $this->getUnidadeNivelColumn();
        $statusFilter = $this->getUnidadeStatusFilter('u');
        $panorama = $db->fetchAll(
            "SELECT 
                u.unidade_id,
                u.unidade_nome,
                COUNT(DISTINCT p.id) as total_projetos,
                COUNT(CASE WHEN p.estado = 'Atrasado' THEN 1 END) as alertas,
                AVG(p.percent_execucao) as execucao_media
            FROM dotp_unidades_organizacionais u
            LEFT JOIN dotp_projetos_prefeitura p ON p.unidade_id = u.unidade_id AND p.estado != 'Cancelado'
            WHERE u.{$nivelColumn} = 2 AND {$statusFilter}
            GROUP BY u.unidade_id
            ORDER BY u.unidade_nome"
        );

        $irregularidades = $db->fetchAll(
            "SELECT 
                p.id,
                p.nome,
                p.estado,
                p.percent_execucao,
                u.unidade_nome as secretaria,
                'Execução acima do cronograma' as irregularidade
            FROM dotp_projetos_prefeitura p
            JOIN dotp_unidades_organizacionais u ON u.unidade_id = p.unidade_id
            WHERE p.percent_execucao > 100 AND p.estado != 'Concluido'
            LIMIT 20"
        );

        return [
            'perfil' => 'controlador',
            'source' => 'modern_tables',
            'alertas_conformidade' => $alertasConformidade,
            'panorama_secretarias' => $panorama,
            'irregularidades' => $irregularidades,
            'convenios_prestacao_pendente' => [],
        ];
    }

    private function getLegacy(): array
    {
        $db = \DotProject\Core\Database::getInstance();

        $stats = $db->fetchAll(
            "SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN project_percent_complete > 100 THEN 1 END) as acima_100,
                COUNT(CASE WHEN project_status = 4 THEN 1 END) as em_espera
            FROM dotp_projects"
        )[0] ?? [];

        $panorama = $db->fetchAll(
            "SELECT 
                c.company_id as unidade_id,
                COALESCE(NULLIF(TRIM(u.unidade_nome), ''), c.company_name) as unidade_nome,
                COUNT(p.project_id) as total_projetos,
                COUNT(CASE WHEN p.project_status = 4 THEN 1 END) as alertas,
                AVG(p.project_percent_complete) as execucao_media
            FROM dotp_companies c
            LEFT JOIN dotp_projects p ON p.project_company = c.company_id
            LEFT JOIN dotp_unidades_organizacionais u ON u.unidade_id = c.company_id
            GROUP BY c.company_id
            ORDER BY unidade_nome"
        );

        return [
            'perfil' => 'controlador',
            'source' => 'legacy_tables',
            'alertas_conformidade' => [
                'sem_prestacao_contas' => 0,
                'execucao_acima_cronograma' => (int) ($stats['acima_100'] ?? 0),
                'diferenca_empenho_execucao' => 0,
            ],
            'panorama_secretarias' => $panorama,
            'irregularidades' => [],
            'convenios_prestacao_pendente' => [],
        ];
    }
}
