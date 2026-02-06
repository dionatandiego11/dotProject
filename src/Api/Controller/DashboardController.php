<?php
/**
 * Controller para Dashboards por Perfil
 * 
 * @package DotProject\Api\Controller
 * 
 * ATUALIZADO: 30/01/2026 - Compatibilidade com tabelas legadas
 * Implementa fallback graceful para dotp_projects/dotp_tasks quando
 * tabelas modernas (programas/projetos/etapas) não existem.
 */

declare(strict_types=1);

namespace DotProject\Api\Controller;

use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Service\PermissionService;
use DotProject\Repository\AlertaRepository;
use DotProject\Repository\UnidadeOrganizacionalRepository;
use DotProject\Repository\UsuarioUnidadeRepository;

class DashboardController extends BaseController
{
    private ?PermissionService $permissionService = null;
    private ?AlertaRepository $alertaRepo = null;
    private ?UnidadeOrganizacionalRepository $unidadeRepo = null;
    private ?UsuarioUnidadeRepository $vinculoRepo = null;
    private ?string $unidadeNivelColumn = null;
    private ?string $unidadeStatusColumn = null;

    /** @var bool Cache para verificar se tabelas modernas existem */
    private ?bool $hasModernTables = null;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        // Eager init to avoid null access in legacy code paths.
        $this->permissionService = new PermissionService();
    }

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

    private function getUnidadeRepo(): UnidadeOrganizacionalRepository
    {
        if ($this->unidadeRepo === null) {
            $this->unidadeRepo = new UnidadeOrganizacionalRepository();
        }
        return $this->unidadeRepo;
    }

    private function getVinculoRepo(): UsuarioUnidadeRepository
    {
        if ($this->vinculoRepo === null) {
            $this->vinculoRepo = new UsuarioUnidadeRepository();
        }
        return $this->vinculoRepo;
    }

    private function getUnidadeNivelColumn(): string
    {
        if ($this->unidadeNivelColumn !== null) {
            return $this->unidadeNivelColumn;
        }

        $db = \DotProject\Core\Database::getInstance();
        $exists = $db->fetchValue(
            "SELECT COUNT(*) FROM information_schema.columns 
             WHERE table_schema = DATABASE() 
               AND table_name = 'dotp_unidades_organizacionais' 
               AND column_name = 'unidade_nivel_id'"
        );

        $this->unidadeNivelColumn = ((int) $exists > 0) ? 'unidade_nivel_id' : 'unidade_nivel';
        return $this->unidadeNivelColumn;
    }

    private function getUnidadeStatusColumn(): ?string
    {
        if ($this->unidadeStatusColumn !== null) {
            return $this->unidadeStatusColumn;
        }

        $db = \DotProject\Core\Database::getInstance();
        $hasStatus = $db->fetchValue(
            "SELECT COUNT(*) FROM information_schema.columns 
             WHERE table_schema = DATABASE() 
               AND table_name = 'dotp_unidades_organizacionais' 
               AND column_name = 'unidade_status'"
        );

        if ((int) $hasStatus > 0) {
            $this->unidadeStatusColumn = 'unidade_status';
            return $this->unidadeStatusColumn;
        }

        $hasAtiva = $db->fetchValue(
            "SELECT COUNT(*) FROM information_schema.columns 
             WHERE table_schema = DATABASE() 
               AND table_name = 'dotp_unidades_organizacionais' 
               AND column_name = 'unidade_ativa'"
        );

        $this->unidadeStatusColumn = ((int) $hasAtiva > 0) ? 'unidade_ativa' : null;
        return $this->unidadeStatusColumn;
    }

    private function getUnidadeStatusFilter(string $alias): string
    {
        $column = $this->getUnidadeStatusColumn();
        if ($column === 'unidade_status') {
            return "{$alias}.unidade_status = 'ativo'";
        }
        if ($column === 'unidade_ativa') {
            return "{$alias}.unidade_ativa = 1";
        }
        return '1=1';
    }

    /**
     * Verifica se as tabelas modernas (programas, projetos, etapas) existem
     */
    private function checkModernTables(): bool
    {
        if ($this->hasModernTables !== null) {
            return $this->hasModernTables;
        }

        try {
            $db = \DotProject\Core\Database::getInstance();
            $result = $db->fetchValue("SELECT COUNT(*) FROM information_schema.tables 
                WHERE table_schema = DATABASE() 
                AND table_name IN ('dotp_programas', 'dotp_projects', 'dotp_etapas')");
            $this->hasModernTables = ((int) $result) >= 3;
        } catch (\Exception $e) {
            $this->hasModernTables = false;
        }

        return $this->hasModernTables;
    }

    /**
     * Retorna o dashboard apropriado baseado na role do usuário
     * GET /api/v1/dashboard
     */
    public function index(): Response
    {
        $userId = $this->getUserId();
        if ($userId === null) {
            return $this->response->unauthorized();
        }

        $perfil = $this->getPermissionService()->getDashboardType($userId);
        return match ($perfil) {
            'prefeito' => $this->prefeito(),
            'secretario' => $this->secretario(),
            'coordenador' => $this->coordenador(),
            'controlador' => $this->controlador(),
            default => $this->tecnico(),
        };
    }

    /**
     * Dashboard do Prefeito - Visão Executiva Geral
     * GET /api/v1/dashboard/prefeito
     */
    public function prefeito(): Response
    {
        $cacheKey = $this->cacheKey('dashboard', 'prefeito');
        $cached = $this->cache->get($cacheKey);

        if ($cached) {
            return $this->json($cached);
        }

        // Verificar se tabelas modernas existem
        if ($this->checkModernTables()) {
            $data = $this->getPrefeitoDashboardModern();
        } else {
            $data = $this->getPrefeitoDashboardLegacy();
        }

        $this->cache->set($cacheKey, $data, 300);

        return $this->json(['data' => $data]);
    }

    /**
     * Dashboard Prefeito usando tabelas modernas (programas, projetos, etapas)
     */
    private function getPrefeitoDashboardModern(): array
    {
        $db = \DotProject\Core\Database::getInstance();

        // Execução do PPA
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

        // Status dos projetos
        $projetosStatus = $db->fetchAll(
            "SELECT 
                estado,
                COUNT(*) as total,
                SUM(valor_previsto) as valor_total
            FROM dotp_projetos_prefeitura 
            WHERE estado != 'Cancelado'
            GROUP BY estado"
        );

        // Projetos por secretaria
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

        // Obras em destaque (atrasadas)
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

        // Convênios a vencer (próximos 60 dias)
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

        // Emendas parlamentares
        $emendas = $db->fetchAll(
            "SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN percent_execucao >= 80 THEN 1 END) as executadas,
                COUNT(CASE WHEN percent_execucao < 30 AND DATEDIFF(CURDATE(), data_prevista_fim) < 120 THEN 1 END) as em_risco
            FROM dotp_projetos_prefeitura 
            WHERE tipo = 'Emenda' AND estado != 'Cancelado'"
        )[0] ?? [];

        // Timeline dos próximos 30 dias
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

        // Execução orçamentária (resumo)
        $orcamento = $db->fetchAll(
            "SELECT 
                COALESCE(SUM(valor_previsto), 0) as previsto,
                COALESCE(SUM(CASE WHEN situacao_orcamentaria = 'empenhado' THEN valor_previsto * 0.3 END), 0) as empenhado,
                COALESCE(SUM(CASE WHEN situacao_orcamentaria = 'pago' THEN valor_previsto END), 0) as pago
            FROM dotp_projetos_prefeitura 
            WHERE estado != 'Cancelado'"
        )[0] ?? [];

        $data = [
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

        return $data;
    }

    /**
     * Dashboard Prefeito usando tabelas legadas (dotp_projects, dotp_tasks)
     * Mapeia campos legados para estrutura moderna do dashboard
     */
    private function getPrefeitoDashboardLegacy(): array
    {
        $db = \DotProject\Core\Database::getInstance();

        // Usar tabela dotp_projects como fonte
        $projectStats = $db->fetchAll(
            "SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN project_status = 5 THEN 1 END) as concluidos,
                COUNT(CASE WHEN project_status IN (4, 7) THEN 1 END) as criticos,
                COUNT(CASE WHEN project_status = 3 THEN 1 END) as em_andamento,
                AVG(project_percent_complete) as percentual_medio
            FROM dotp_projects"
        )[0] ?? [];

        // Projetos por status
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

        // Projetos por departamento (company como proxy de secretaria)
        $porSecretaria = $db->fetchAll(
            "SELECT 
                c.company_id as unidade_id,
                c.company_name as unidade_nome,
                SUBSTRING(c.company_name, 1, 5) as unidade_sigla,
                COUNT(p.project_id) as total_projetos,
                COUNT(CASE WHEN p.project_status = 4 THEN 1 END) as atrasados,
                COUNT(CASE WHEN p.project_status = 5 THEN 1 END) as concluidos,
                AVG(p.project_percent_complete) as percentual_execucao
            FROM dotp_companies c
            LEFT JOIN dotp_projects p ON p.project_company = c.company_id
            GROUP BY c.company_id
            ORDER BY c.company_name"
        );

        // Projetos recentes (substituindo obras atrasadas)
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

        // Tarefas pendentes como timeline
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

        // Orçamento dos projetos
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

    /**
     * Dashboard do Secretário - Visão da Secretaria
     * GET /api/v1/dashboard/secretario
     */
    public function secretario(): Response
    {
        try {
            $userId = $this->getUserId();
            $escopo = $this->getPermissionService()->getEscopoDados($userId);

            if (!$escopo) {
                return $this->json(['error' => 'Escopo não encontrado'], 403);
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
                    $data = $this->getSecretarioDashboardModern($unidadeId, $unidadesEscopo);
                } else {
                    $data = $this->getSecretarioDashboardLegacy($userId);
                }
            } catch (\Throwable $e) {
                \DotProject\Core\Logger::error('Dashboard secretario falhou, aplicando fallback legado', [
                    'user_id' => $userId,
                    'unidade_id' => $unidadeId,
                    'error' => $e->getMessage(),
                ]);
                $data = $this->getSecretarioDashboardLegacy($userId);
            }

            $this->cache->set($cacheKey, $data, 300);

            return $this->json(['data' => $data]);
        } catch (\Throwable $e) {
            \DotProject\Core\Logger::error('Erro ao carregar dashboard do secretario', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return $this->json(['error' => 'Erro ao carregar dashboard'], 500);
        }
    }

    /**
     * Dashboard Secretário usando tabelas modernas
     */
    private function getSecretarioDashboardModern(int $unidadeId, array $unidadesEscopo): array
    {
        $db = \DotProject\Core\Database::getInstance();

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

        // Programas da secretaria
        try {
            $programas = $db->fetchAll(
                "SELECT 
                    id,
                    nome,
                    estado,
                    percent_execucao,
                    (SELECT COUNT(*) FROM dotp_projetos_prefeitura WHERE programa_id = p.id AND estado != 'Cancelado') as total_projetos
                FROM dotp_programas p
                WHERE unidade_id IN ($placeholders)
                ORDER BY percent_execucao ASC",
                $unidadesEscopo
            );
        } catch (\Throwable $e) {
            throw $e;
        }

        // Projetos da secretaria
        $projetos = $db->fetchAll(
            "SELECT 
                estado,
                COUNT(*) as total
            FROM dotp_projetos_prefeitura 
            WHERE unidade_id IN ($placeholders)
            AND estado != 'Cancelado'
            GROUP BY estado",
            $unidadesEscopo
        );

        // Projetos que precisam de atenção
        $atencao = $db->fetchAll(
            "SELECT 
                p.id,
                p.nome,
                p.estado,
                p.percent_execucao,
                prog.nome as programa_nome,
                p.data_prevista_fim
            FROM dotp_projetos_prefeitura p
            LEFT JOIN dotp_programas prog ON prog.id = p.programa_id
            WHERE p.unidade_id IN ($placeholders)
            AND p.estado NOT IN ('Concluido', 'Cancelado')
            AND p.estado = 'Atrasado'
            ORDER BY p.data_prevista_fim ASC
            LIMIT 15",
            $unidadesEscopo
        );

        // Alertas da secretaria
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

    /**
     * Dashboard Secretário usando tabelas legadas
     */
    private function getSecretarioDashboardLegacy(int $userId): array
    {
        $db = \DotProject\Core\Database::getInstance();

        // Projetos onde o usuário é owner
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
            GROUP BY project_status",
            [$userId]
        );

        // Lista de projetos
        $listaProjectos = $db->fetchAll(
            "SELECT 
                project_id as id,
                project_name as nome,
                project_percent_complete as percent_execucao,
                project_end_date as data_prevista_fim
            FROM dotp_projects 
            WHERE project_owner = ?
            AND project_status NOT IN (5, 7)
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

    /**
     * Dashboard do Coordenador - Visão de Projetos
     * GET /api/v1/dashboard/coordenador
     */
    public function coordenador(): Response
    {
        $userId = $this->getUserId();
        $escopo = $this->getPermissionService()->getEscopoDados($userId);

        if (!$escopo) {
            return $this->json(['error' => 'Escopo não encontrado'], 403);
        }

        $unidadesEscopo = $escopo['unidades_escopo'];

        $cacheKey = $this->cacheKey('dashboard', 'coordenador', $userId);
        $cached = $this->cache->get($cacheKey);

        if ($cached) {
            return $this->json($cached);
        }

        if ($this->checkModernTables()) {
            $data = $this->getCoordenadorDashboardModern($userId, $unidadesEscopo);
        } else {
            $data = $this->getCoordenadorDashboardLegacy($userId);
        }

        $this->cache->set($cacheKey, $data, 300);

        return $this->json(['data' => $data]);
    }

    /**
     * Dashboard Coordenador usando tabelas modernas
     */
    private function getCoordenadorDashboardModern(int $userId, array $unidadesEscopo): array
    {
        $db = \DotProject\Core\Database::getInstance();
        $placeholders = implode(',', array_fill(0, count($unidadesEscopo), '?'));

        // Resumo dos projetos
        $resumo = $db->fetchAll(
            "SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN estado = 'Concluido' THEN 1 END) as concluidos,
                COUNT(CASE WHEN estado = 'Atrasado' THEN 1 END) as atrasados,
                COUNT(CASE WHEN estado NOT IN ('Concluido', 'Cancelado') THEN 1 END) as em_andamento,
                AVG(percent_execucao) as percentual_medio
            FROM dotp_projetos_prefeitura 
            WHERE unidade_id IN ($placeholders)
            AND estado != 'Cancelado'",
            $unidadesEscopo
        )[0] ?? [];

        // Meus projetos com detalhes
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
            LEFT JOIN dotp_programas prog ON prog.id = p.programa_id
            WHERE p.unidade_id IN ($placeholders)
            AND p.estado != 'Cancelado'
            ORDER BY 
                CASE p.estado WHEN 'Atrasado' THEN 0 ELSE 1 END,
                p.percent_execucao ASC",
            $unidadesEscopo
        );

        // Equipe (técnicos vinculados)
        $equipe = $db->fetchAll(
            "SELECT 
                u.user_id,
                u.user_username as nome,
                COUNT(t.task_id) as tarefas_ativas
            FROM dotp_users u
            JOIN dotp_usuario_unidades v ON v.vinculo_user_id = u.user_id AND v.vinculo_status = 'ativo'
            LEFT JOIN dotp_tasks t ON t.task_owner = u.user_id AND t.task_percent_complete < 100
            WHERE v.vinculo_unidade_id IN ($placeholders)
            GROUP BY u.user_id
            ORDER BY tarefas_ativas DESC",
            $unidadesEscopo
        );

        $data = [
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

        return $data;
    }

    /**
     * Dashboard Coordenador usando tabelas legadas
     */
    private function getCoordenadorDashboardLegacy(int $userId): array
    {
        $db = \DotProject\Core\Database::getInstance();

        // Resumo dos projetos
        $resumo = $db->fetchAll(
            "SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN project_status = 5 THEN 1 END) as concluidos,
                COUNT(CASE WHEN project_status = 4 THEN 1 END) as atrasados,
                COUNT(CASE WHEN project_status = 3 THEN 1 END) as em_andamento,
                AVG(project_percent_complete) as percentual_medio
            FROM dotp_projects 
            WHERE project_owner = ?",
            [$userId]
        )[0] ?? [];

        // Meus projetos
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

    /**
     * Dashboard do Técnico - Visão de Tarefas
     * GET /api/v1/dashboard/tecnico
     */
    public function tecnico(): Response
    {
        $userId = $this->getUserId();

        $cacheKey = $this->cacheKey('dashboard', 'tecnico', (string) $userId);
        $cached = $this->cache->get($cacheKey);

        if ($cached) {
            return $this->json($cached);
        }

        // Técnico sempre usa dotp_tasks (tabela base do sistema)
        $data = $this->getTecnicoDashboard($userId);

        $this->cache->set($cacheKey, $data, 300);

        return $this->json(['data' => $data]);
    }

    /**
     * Dashboard Técnico - funciona com qualquer schema
     */
    private function getTecnicoDashboard(int $userId): array
    {
        $db = \DotProject\Core\Database::getInstance();

        // Resumo das minhas tarefas
        $resumo = $db->fetchAll(
            "SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN task_percent_complete >= 100 THEN 1 END) as concluidas,
                COUNT(CASE WHEN task_percent_complete < 100 THEN 1 END) as pendentes,
                COUNT(CASE WHEN task_end_date < CURDATE() AND task_percent_complete < 100 THEN 1 END) as atrasadas
            FROM dotp_tasks 
            WHERE task_owner = ?",
            [$userId]
        )[0] ?? [];

        // Tarefas priorizadas
        $tarefas = $db->fetchAll(
            "SELECT 
                t.task_id as id,
                t.task_name as nome,
                t.task_priority as prioridade,
                t.task_percent_complete as progresso,
                t.task_end_date as prazo,
                p.project_name as projeto,
                CASE 
                    WHEN t.task_end_date < CURDATE() AND t.task_percent_complete < 100 THEN 'atrasada'
                    WHEN DATEDIFF(t.task_end_date, CURDATE()) <= 3 THEN 'urgente'
                    ELSE 'normal'
                END as urgencia
            FROM dotp_tasks t
            LEFT JOIN dotp_projects p ON p.project_id = t.task_project
            WHERE t.task_owner = ?
            AND t.task_percent_complete < 100
            ORDER BY 
                CASE WHEN t.task_end_date < CURDATE() THEN 0 
                     WHEN DATEDIFF(t.task_end_date, CURDATE()) <= 3 THEN 1 
                     ELSE 2 END,
                t.task_priority DESC,
                t.task_end_date ASC
            LIMIT 20",
            [$userId]
        );

        // Tarefas concluídas esta semana
        $concluidasSemana = $db->fetchAll(
            "SELECT 
                task_id as id,
                task_name as nome,
                task_end_date as data_conclusao
            FROM dotp_tasks 
            WHERE task_owner = ?
            AND task_percent_complete >= 100
            AND task_end_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            ORDER BY task_end_date DESC
            LIMIT 10",
            [$userId]
        );

        // Projetos que participo
        $projetos = $db->fetchAll(
            "SELECT DISTINCT
                p.project_id as id,
                p.project_name as nome,
                p.project_percent_complete as percent_execucao,
                COUNT(t.task_id) as minhas_tarefas,
                COUNT(CASE WHEN t.task_percent_complete >= 100 THEN 1 END) as tarefas_concluidas
            FROM dotp_projects p
            JOIN dotp_tasks t ON t.task_project = p.project_id
            WHERE t.task_owner = ?
            GROUP BY p.project_id
            ORDER BY p.project_name",
            [$userId]
        );

        // Minha produtividade
        $produtividade = $db->fetchAll(
            "SELECT 
                DATE(task_end_date) as data,
                COUNT(*) as tarefas_concluidas
            FROM dotp_tasks 
            WHERE task_owner = ?
            AND task_percent_complete >= 100
            AND task_end_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(task_end_date)
            ORDER BY data DESC",
            [$userId]
        );

        return [
            'perfil' => 'tecnico',
            'source' => 'base_tables',
            'resumo' => [
                'total' => (int) ($resumo['total'] ?? 0),
                'concluidas' => (int) ($resumo['concluidas'] ?? 0),
                'pendentes' => (int) ($resumo['pendentes'] ?? 0),
                'bloqueadas' => 0,
                'atrasadas' => (int) ($resumo['atrasadas'] ?? 0),
            ],
            'tarefas_prioritarias' => $tarefas,
            'concluidas_semana' => $concluidasSemana,
            'projetos' => $projetos,
            'produtividade_30d' => $produtividade,
        ];
    }

    /**
     * Dashboard do Controlador - Visão de Fiscalização
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
            $data = $this->getControladorDashboardModern();
        } else {
            $data = $this->getControladorDashboardLegacy();
        }

        $this->cache->set($cacheKey, $data, 300);

        return $this->json(['data' => $data]);
    }

    /**
     * Dashboard Controlador usando tabelas modernas
     */
    private function getControladorDashboardModern(): array
    {
        $db = \DotProject\Core\Database::getInstance();

        // Alertas de conformidade
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

        // Panorama por secretaria
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

        // Irregularidades
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

    /**
     * Dashboard Controlador usando tabelas legadas
     */
    private function getControladorDashboardLegacy(): array
    {
        $db = \DotProject\Core\Database::getInstance();

        // Stats gerais
        $stats = $db->fetchAll(
            "SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN project_percent_complete > 100 THEN 1 END) as acima_100,
                COUNT(CASE WHEN project_status = 4 THEN 1 END) as em_espera
            FROM dotp_projects"
        )[0] ?? [];

        // Panorama por company
        $panorama = $db->fetchAll(
            "SELECT 
                c.company_id as unidade_id,
                c.company_name as unidade_nome,
                COUNT(p.project_id) as total_projetos,
                COUNT(CASE WHEN p.project_status = 4 THEN 1 END) as alertas,
                AVG(p.project_percent_complete) as execucao_media
            FROM dotp_companies c
            LEFT JOIN dotp_projects p ON p.project_company = c.company_id
            GROUP BY c.company_id
            ORDER BY c.company_name"
        );

        $data = [
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

        return $data;
    }

    /**
     * GET /api/v1/dashboard/alertas
     * Alertas do usuário logado
     */
    public function alertas(): Response
    {
        $userId = $this->getUserId();
        $apenasNaoLidos = filter_var(
            $this->request->getQuery('nao_lidos', false),
            FILTER_VALIDATE_BOOL
        );
        $limit = (int) $this->request->getQuery('limit', 50);
        if ($limit <= 0) {
            $limit = 50;
        } elseif ($limit > 200) {
            $limit = 200;
        }

        try {
            $alertas = $this->getAlertaRepo()->findComDetalhes($userId, $apenasNaoLidos, $limit);
            $estatisticas = $this->getAlertaRepo()->getEstatisticas($userId);
        } catch (\Exception $e) {
            $alertas = [];
            $estatisticas = ['total' => 0, 'nao_lidos' => 0];
        }

        return $this->json([
            'data' => $alertas,
            'estatisticas' => $estatisticas,
        ]);
    }

    /**
     * PUT /api/v1/dashboard/alertas/:id/lido
     * Marca alerta como lido
     */
    public function marcarAlertaLido(int $id): Response
    {
        $userId = $this->getUserId();

        try {
            $alerta = $this->getAlertaRepo()->find($id);

            if (!$alerta || $alerta->getDestinatarioId() !== $userId) {
                return $this->notFound('Alerta não encontrado');
            }

            $this->getAlertaRepo()->marcarComoLido($id);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erro ao processar alerta'], 500);
        }

        return $this->json([
            'message' => 'Alerta marcado como lido',
        ]);
    }

    /**
     * PUT /api/v1/dashboard/alertas/lidos
     * Marca todos os alertas como lidos
     */
    public function marcarTodosLidos(): Response
    {
        $userId = $this->getUserId();

        try {
            $this->getAlertaRepo()->marcarTodosComoLidos($userId);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erro ao processar alertas'], 500);
        }

        return $this->json([
            'message' => 'Todos os alertas marcados como lidos',
        ]);
    }

    /**
     * GET /api/v1/dashboard/status
     * Retorna status do sistema (para debug)
     */
    public function status(): Response
    {
        return $this->json([
            'status' => 'ok',
            'modern_tables' => $this->checkModernTables(),
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }
}
