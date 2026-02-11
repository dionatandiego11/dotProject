<?php
/**
 * Dashboard do Coordenador — Visão de Projetos.
 *
 * @package DotProject\Api\Controller\Dashboard
 */

declare(strict_types=1);

namespace DotProject\Api\Controller\Dashboard;

use DotProject\Api\Controller\BaseController;
use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Service\PermissionService;

class CoordenadorDashboardController extends BaseController
{
    use DashboardHelperTrait;
    private ?PermissionService $permissionService = null;

    private function getPermissionService(): PermissionService
    {
        if ($this->permissionService === null) {
            $this->permissionService = new PermissionService();
        }
        return $this->permissionService;
    }

    /**
     * GET /api/v1/dashboard/coordenador
     */
    public function coordenador(): Response
    {
        $userId = $this->getUserId();
        $escopo = $this->getPermissionService()->getEscopoDados($userId);

        if (!$escopo) {
            return $this->error('Escopo não encontrado', Response::HTTP_FORBIDDEN);
        }

        $unidadesEscopo = $escopo['unidades_escopo'];

        $cacheKey = $this->cacheKey('dashboard', 'coordenador', $userId);
        $cached = $this->cache->get($cacheKey);

        if ($cached) {
            return $this->json($cached);
        }

        if ($this->checkModernTables()) {
            $data = $this->getModern($userId, $unidadesEscopo);
        } else {
            $data = $this->getLegacy($userId);
        }

        $this->cache->set($cacheKey, $data, 300);

        return $this->json(['data' => $data]);
    }

    private function getModern(int $userId, array $unidadesEscopo): array
    {
        $db = \DotProject\Core\Database::getInstance();
        $tenantProjetos = $this->tenantAndCondition('dotp_projetos_prefeitura');
        $tenantProjetosAlias = $this->tenantAndCondition('dotp_projetos_prefeitura', 'p');
        $tenantProgramasAlias = $this->tenantAndCondition('dotp_programas', 'prog');
        $tenantUsersAlias = $this->tenantAndCondition('dotp_users', 'u');
        $tenantVinculosAlias = $this->tenantAndCondition('dotp_usuario_unidades', 'v');
        $tenantTasksAlias = $this->tenantAndCondition('dotp_tasks', 't');
        $placeholders = implode(',', array_fill(0, count($unidadesEscopo), '?'));

        $resumo = $db->fetchAll(
            "SELECT
                COUNT(*) as total,
                COUNT(CASE WHEN estado = 'Concluido' THEN 1 END) as concluidos,
                COUNT(CASE WHEN estado = 'Atrasado' THEN 1 END) as atrasados,
                COUNT(CASE WHEN estado NOT IN ('Concluido', 'Cancelado') THEN 1 END) as em_andamento,
                AVG(percent_execucao) as percentual_medio
            FROM dotp_projetos_prefeitura
            WHERE unidade_id IN ($placeholders)
            AND estado != 'Cancelado'{$tenantProjetos}",
            $unidadesEscopo
        )[0] ?? [];

        $projetos = $db->fetchAll(
            "SELECT
                p.id,
                p.nome,
                p.tipo,
                p.estado,
                p.percent_execucao,
                p.data_prevista_inicio,
                p.data_prevista_fim,
                prog.nome as programa_nome
            FROM dotp_projetos_prefeitura p
            LEFT JOIN dotp_programas prog ON prog.id = p.programa_id{$tenantProgramasAlias}
            WHERE p.unidade_id IN ($placeholders)
            AND p.estado != 'Cancelado'{$tenantProjetosAlias}
            ORDER BY
                CASE p.estado WHEN 'Atrasado' THEN 0 ELSE 1 END,
                p.percent_execucao ASC",
            $unidadesEscopo
        );

        $equipe = $db->fetchAll(
            "SELECT
                u.user_id,
                u.user_username as nome,
                COUNT(t.task_id) as tarefas_ativas
            FROM dotp_users u
            JOIN dotp_usuario_unidades v ON v.vinculo_user_id = u.user_id AND v.vinculo_status = 'ativo'{$tenantVinculosAlias}
            LEFT JOIN dotp_tasks t ON t.task_owner = u.user_id AND t.task_percent_complete < 100{$tenantTasksAlias}
            WHERE v.vinculo_unidade_id IN ($placeholders)
            {$tenantUsersAlias}
            GROUP BY u.user_id
            ORDER BY tarefas_ativas DESC",
            $unidadesEscopo
        );

        return [
            'perfil' => 'coordenador',
            'source' => 'modern_tables',
            'resumo' => [
                'total_projetos' => (int) ($resumo['total'] ?? 0),
                'concluidos' => (int) ($resumo['concluidos'] ?? 0),
                'atrasados' => (int) ($resumo['atrasados'] ?? 0),
                'em_andamento' => (int) ($resumo['em_andamento'] ?? 0),
                'percentual_medio' => round((float) ($resumo['percentual_medio'] ?? 0), 2),
            ],
            'projetos' => $projetos,
            'etapas_atencao' => [],
            'proximas_etapas' => [],
            'equipe' => $equipe,
        ];
    }

    private function getLegacy(int $userId): array
    {
        $db = \DotProject\Core\Database::getInstance();
        $tenantProjects = $this->tenantAndCondition('dotp_projects');

        $resumo = $db->fetchAll(
            "SELECT
                COUNT(*) as total,
                COUNT(CASE WHEN project_status = 5 THEN 1 END) as concluidos,
                COUNT(CASE WHEN project_status = 4 THEN 1 END) as atrasados,
                COUNT(CASE WHEN project_status = 3 THEN 1 END) as em_andamento,
                AVG(project_percent_complete) as percentual_medio
            FROM dotp_projects
            WHERE project_owner = ?{$tenantProjects}",
            [$userId]
        )[0] ?? [];

        $projetos = $db->fetchAll(
            "SELECT
                project_id as id,
                project_name as nome,
                '' as tipo,
                CASE project_status
                    WHEN 3 THEN 'Em_Andamento'
                    WHEN 4 THEN 'Em_Espera'
                    WHEN 5 THEN 'Concluido'
                    ELSE 'Outro'
                END as estado,
                project_percent_complete as percent_execucao,
                project_start_date as data_prevista_inicio,
                project_end_date as data_prevista_fim
            FROM dotp_projects
            WHERE project_owner = ?
            AND project_status NOT IN (6, 7)
            {$tenantProjects}
            ORDER BY project_percent_complete ASC",
            [$userId]
        );

        return [
            'perfil' => 'coordenador',
            'source' => 'legacy_tables',
            'resumo' => [
                'total_projetos' => (int) ($resumo['total'] ?? 0),
                'concluidos' => (int) ($resumo['concluidos'] ?? 0),
                'atrasados' => (int) ($resumo['atrasados'] ?? 0),
                'em_andamento' => (int) ($resumo['em_andamento'] ?? 0),
                'percentual_medio' => round((float) ($resumo['percentual_medio'] ?? 0), 2),
            ],
            'projetos' => $projetos,
            'etapas_atencao' => [],
            'proximas_etapas' => [],
            'equipe' => [],
        ];
    }
}
