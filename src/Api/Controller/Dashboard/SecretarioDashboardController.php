<?php
/**
 * Dashboard do Secretário — Visão da Secretaria.
 *
 * @package DotProject\Api\Controller\Dashboard
 */

declare(strict_types=1);

namespace DotProject\Api\Controller\Dashboard;

use DotProject\Api\Controller\BaseController;
use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Service\PermissionService;
use DotProject\Repository\AlertaRepository;

class SecretarioDashboardController extends BaseController
{
    use DashboardHelperTrait;

    private ?PermissionService $permissionService = null;
    private ?AlertaRepository $alertaRepo = null;

    private function getPermissionService(): PermissionService
    {
        if ($this->permissionService === null) {
            $this->permissionService = new PermissionService();
        }
        return $this->permissionService;
    }

    private function getAlertaRepo(): AlertaRepository
    {
        if ($this->alertaRepo === null) {
            $this->alertaRepo = new AlertaRepository();
        }
        return $this->alertaRepo;
    }

    /**
     * GET /api/v1/dashboard/secretario
     */
    public function secretario(): Response
    {
        try {
            $userId = $this->getUserId();
            $escopo = $this->getPermissionService()->getEscopoDados($userId);

            if (!$escopo) {
                return $this->error('Escopo não encontrado', Response::HTTP_FORBIDDEN);
            }

            $unidadeId = $escopo['unidade_id'];
            $unidadesEscopo = $escopo['unidades_escopo'];

            if (empty($unidadesEscopo) && $unidadeId > 0) {
                $unidadesEscopo = [$unidadeId];
            }

            $cacheKey = $this->cacheKey('dashboard', 'secretario', $unidadeId);
            $cached = $this->cache->get($cacheKey);

            if ($cached) {
                return $this->json($cached);
            }

            try {
                if ($this->checkModernTables()) {
                    $data = $this->getModern($unidadeId, $unidadesEscopo);
                } else {
                    $data = $this->getLegacy($userId);
                }
            } catch (\Throwable $e) {
                \DotProject\Core\Logger::error('Dashboard secretario falhou, aplicando fallback legado', [
                    'user_id' => $userId,
                    'unidade_id' => $unidadeId,
                    'error' => $e->getMessage(),
                ]);
                $data = $this->getLegacy($userId);
            }

            $this->cache->set($cacheKey, $data, 300);

            return $this->json(['data' => $data]);
        } catch (\Throwable $e) {
            \DotProject\Core\Logger::error('Erro ao carregar dashboard do secretario', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return $this->error('Erro ao carregar dashboard', Response::HTTP_INTERNAL_ERROR);
        }
    }

    private function getModern(int $unidadeId, array $unidadesEscopo): array
    {
        $db = \DotProject\Core\Database::getInstance();
        $tenantProgramas = $this->tenantAndCondition('dotp_programas', 'p');
        $tenantProgramasJoin = $this->tenantAndCondition('dotp_programas', 'prog');
        $tenantProjetos = $this->tenantAndCondition('dotp_projetos_prefeitura');
        $tenantProjetosAlias = $this->tenantAndCondition('dotp_projetos_prefeitura', 'p');
        $tenantProjetosSubquery = $this->tenantAndCondition('dotp_projetos_prefeitura', 'pp');

        if (empty($unidadesEscopo)) {
            $alertas = ['total' => 0, 'nao_lidos' => 0];
            try {
                $userId = $this->getUserId();
                if ($userId !== null) {
                    $alertas = $this->getAlertaRepo()->getEstatisticas($userId);
                }
            } catch (\Exception $e) {
                $alertas = ['total' => 0, 'nao_lidos' => 0];
            }

            return [
                'perfil' => 'secretario',
                'source' => 'modern_tables',
                'unidade_id' => $unidadeId,
                'programas' => [],
                'projetos_resumo' => [],
                'projetos_atencao' => [],
                'coordenadores' => [],
                'alertas' => $alertas,
            ];
        }
        $placeholders = implode(',', array_fill(0, count($unidadesEscopo), '?'));

        try {
            $programas = $db->fetchAll(
                "SELECT
                    id,
                    nome,
                    estado,
                    percent_execucao,
                    (SELECT COUNT(*) FROM dotp_projetos_prefeitura pp WHERE pp.programa_id = p.id AND pp.estado != 'Cancelado'{$tenantProjetosSubquery}) as total_projetos
                FROM dotp_programas p
                WHERE unidade_id IN ($placeholders)
                {$tenantProgramas}
                ORDER BY percent_execucao ASC",
                $unidadesEscopo
            );
        } catch (\Throwable $e) {
            throw $e;
        }

        $projetos = $db->fetchAll(
            "SELECT
                estado,
                COUNT(*) as total
            FROM dotp_projetos_prefeitura
            WHERE unidade_id IN ($placeholders)
            AND estado != 'Cancelado'
            {$tenantProjetos}
            GROUP BY estado",
            $unidadesEscopo
        );

        $atencao = $db->fetchAll(
            "SELECT
                p.id,
                p.nome,
                p.estado,
                p.percent_execucao,
                prog.nome as programa_nome,
                p.data_prevista_fim
            FROM dotp_projetos_prefeitura p
            LEFT JOIN dotp_programas prog ON prog.id = p.programa_id{$tenantProgramasJoin}
            WHERE p.unidade_id IN ($placeholders)
            AND p.estado NOT IN ('Concluido', 'Cancelado')
            AND p.estado = 'Atrasado'
            {$tenantProjetosAlias}
            ORDER BY p.data_prevista_fim ASC
            LIMIT 15",
            $unidadesEscopo
        );

        $userId = $this->getUserId();
        try {
            $alertas = $this->getAlertaRepo()->getEstatisticas($userId);
        } catch (\Exception $e) {
            $alertas = ['total' => 0, 'nao_lidos' => 0];
        }

        return [
            'perfil' => 'secretario',
            'source' => 'modern_tables',
            'unidade_id' => $unidadeId,
            'programas' => $programas,
            'projetos_resumo' => $projetos,
            'projetos_atencao' => $atencao,
            'coordenadores' => [],
            'alertas' => $alertas,
        ];
    }

    private function getLegacy(int $userId): array
    {
        $db = \DotProject\Core\Database::getInstance();
        $tenantProjects = $this->tenantAndCondition('dotp_projects');

        $projetos = $db->fetchAll(
            "SELECT
                CASE project_status
                    WHEN 3 THEN 'Em_Andamento'
                    WHEN 4 THEN 'Em_Espera'
                    WHEN 5 THEN 'Concluido'
                    ELSE 'Outro'
                END as estado,
                COUNT(*) as total
            FROM dotp_projects
            WHERE project_owner = ?
            {$tenantProjects}
            GROUP BY project_status",
            [$userId]
        );

        $listaProjectos = $db->fetchAll(
            "SELECT
                project_id as id,
                project_name as nome,
                project_percent_complete as percent_execucao,
                project_end_date as data_prevista_fim
            FROM dotp_projects
            WHERE project_owner = ?
            AND project_status NOT IN (5, 7)
            {$tenantProjects}
            ORDER BY project_end_date ASC
            LIMIT 15",
            [$userId]
        );

        return [
            'perfil' => 'secretario',
            'source' => 'legacy_tables',
            'unidade_id' => 0,
            'programas' => [],
            'projetos_resumo' => $projetos,
            'projetos_atencao' => $listaProjectos,
            'coordenadores' => [],
            'alertas' => ['total' => 0, 'nao_lidos' => 0],
        ];
    }
}
