<?php

declare(strict_types=1);

namespace DotProject\Tests\Integration;

use DotProject\Api\Controller\Dashboard\ControladorDashboardController;
use DotProject\Api\Controller\Dashboard\CoordenadorDashboardController;
use DotProject\Api\Controller\Dashboard\PrefeitoDashboardController;
use DotProject\Api\Controller\Dashboard\SecretarioDashboardController;
use DotProject\Api\Controller\Dashboard\TecnicoDashboardController;
use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Core\Cache;
use DotProject\Core\Database;
use PHPUnit\Framework\TestCase;

class DashboardProfilesIntegrationTest extends TestCase
{
    private static ?bool $dbReady = null;
    private Database $db;

    /** @var array<string, int[]> */
    private array $created = [
        'contacts' => [],
        'users' => [],
        'niveis' => [],
        'unidades' => [],
        'vinculos' => [],
        'companies' => [],
        'programas' => [],
        'projetos_prefeitura' => [],
        'projects' => [],
        'tasks' => [],
    ];

    /** @var array<string, int> */
    private array $fixture = [];

    protected function setUp(): void
    {
        if (self::$dbReady === false) {
            $this->markTestSkipped('Database connection is not initialized for dashboard integration tests.');
        }

        try {
            $this->db = Database::getInstance();
            self::$dbReady = true;
        } catch (\RuntimeException $e) {
            self::$dbReady = false;
            $this->markTestSkipped('Database connection is not initialized for dashboard integration tests.');
        }

        (new Cache())->clear();

        $this->ensureRequiredSchema();
        $this->fixture = $this->seedFixture();
    }

    protected function tearDown(): void
    {
        $this->cleanupFixture();
        (new Cache())->clear();
    }

    public function testPrefeitoDashboardReadsFixtureFromModernTables(): void
    {
        $controller = new PrefeitoDashboardController(
            $this->buildRequest($this->fixture['prefeito_user_id'], '/api/v1/dashboard/prefeito'),
            new Response()
        );

        $result = $controller->prefeito();
        $body = $this->responseBody($result);
        $data = $body['data'] ?? [];

        $this->assertSame(200, $this->responseStatus($result), var_export($body, true));
        $this->assertSame('prefeito', $data['perfil'] ?? null);
        $this->assertSame('modern_tables', $data['source'] ?? null);
        $this->assertGreaterThanOrEqual(1, (int) ($data['ppa_execucao']['total_programas'] ?? 0));
        $this->assertTrue($this->hasRowWithId($data['por_secretaria'] ?? [], $this->fixture['secretaria_unidade_id'], 'unidade_id'));
        $this->assertTrue($this->hasRowWithId($data['obras_atrasadas'] ?? [], $this->fixture['projeto_atrasado_id']));
    }

    public function testSecretarioDashboardReadsScopedFixtureData(): void
    {
        $controller = new SecretarioDashboardController(
            $this->buildRequest($this->fixture['secretario_user_id'], '/api/v1/dashboard/secretario'),
            new Response()
        );

        $result = $controller->secretario();
        $body = $this->responseBody($result);
        $data = $body['data'] ?? [];

        $this->assertSame(200, $this->responseStatus($result), var_export($body, true));
        $this->assertSame('secretario', $data['perfil'] ?? null);
        $this->assertSame($this->fixture['secretaria_unidade_id'], (int) ($data['unidade_id'] ?? 0));
        $this->assertNotEmpty($data['programas'] ?? []);
        $this->assertTrue($this->hasRowWithId($data['projetos_atencao'] ?? [], $this->fixture['projeto_atrasado_id']));
        $this->assertTrue($this->hasRowWithValue($data['projetos_resumo'] ?? [], 'estado', 'Atrasado'));
    }

    public function testCoordenadorDashboardReadsScopedFixtureData(): void
    {
        $controller = new CoordenadorDashboardController(
            $this->buildRequest($this->fixture['coordenador_user_id'], '/api/v1/dashboard/coordenador'),
            new Response()
        );

        $result = $controller->coordenador();
        $body = $this->responseBody($result);
        $data = $body['data'] ?? [];

        $this->assertSame(200, $this->responseStatus($result), var_export($body, true));
        $this->assertSame('coordenador', $data['perfil'] ?? null);
        $this->assertGreaterThanOrEqual(1, (int) ($data['resumo']['total_projetos'] ?? 0));
        $this->assertTrue($this->hasRowWithId($data['projetos'] ?? [], $this->fixture['projeto_irregular_id']));
        $this->assertTrue($this->hasRowWithId($data['equipe'] ?? [], $this->fixture['tecnico_user_id'], 'user_id'));
    }

    public function testTecnicoDashboardReadsTaskFixtureData(): void
    {
        $controller = new TecnicoDashboardController(
            $this->buildRequest($this->fixture['tecnico_user_id'], '/api/v1/dashboard/tecnico'),
            new Response()
        );

        $result = $controller->tecnico();
        $body = $this->responseBody($result);
        $data = $body['data'] ?? [];

        $this->assertSame(200, $this->responseStatus($result), var_export($body, true));
        $this->assertSame('tecnico', $data['perfil'] ?? null);
        $this->assertGreaterThanOrEqual(2, (int) ($data['resumo']['total'] ?? 0));
        $this->assertGreaterThanOrEqual(1, (int) ($data['resumo']['concluidas'] ?? 0));
        $this->assertGreaterThanOrEqual(1, (int) ($data['resumo']['pendentes'] ?? 0));
        $this->assertTrue($this->hasRowWithId($data['tarefas_prioritarias'] ?? [], $this->fixture['task_pendente_id']));
        $this->assertTrue($this->hasRowWithId($data['concluidas_semana'] ?? [], $this->fixture['task_concluida_id']));
    }

    public function testControladorDashboardReadsComplianceFixtureData(): void
    {
        $controller = new ControladorDashboardController(
            $this->buildRequest($this->fixture['prefeito_user_id'], '/api/v1/dashboard/controlador'),
            new Response()
        );

        $result = $controller->controlador();
        $body = $this->responseBody($result);
        $data = $body['data'] ?? [];

        $this->assertSame(200, $this->responseStatus($result), var_export($body, true));
        $this->assertSame('controlador', $data['perfil'] ?? null);
        $this->assertSame('modern_tables', $data['source'] ?? null);
        $this->assertGreaterThanOrEqual(1, (int) ($data['alertas_conformidade']['sem_prestacao_contas'] ?? 0));
        $this->assertGreaterThanOrEqual(1, (int) ($data['alertas_conformidade']['execucao_acima_cronograma'] ?? 0));
        $this->assertTrue($this->hasRowWithId($data['irregularidades'] ?? [], $this->fixture['projeto_irregular_id']));
        $this->assertTrue($this->hasRowWithId($data['panorama_secretarias'] ?? [], $this->fixture['secretaria_unidade_id'], 'unidade_id'));
    }

    private function ensureRequiredSchema(): void
    {
        $requiredTables = [
            'dotp_contacts',
            'dotp_users',
            'dotp_unidades_organizacionais',
            'dotp_usuario_unidades',
            'dotp_companies',
            'dotp_programas',
            'dotp_projetos_prefeitura',
            'dotp_projects',
            'dotp_tasks',
            'dotp_etapas',
            'dotp_files',
        ];

        foreach ($requiredTables as $table) {
            if (!$this->tableExists($table)) {
                $this->markTestSkipped("Tabela obrigatoria ausente para teste de dashboard: {$table}");
            }
        }

        if (!$this->hasColumn('dotp_unidades_organizacionais', 'unidade_nivel_id')) {
            $this->markTestSkipped('Coluna obrigatoria ausente: dotp_unidades_organizacionais.unidade_nivel_id');
        }
        if (!$this->hasColumn('dotp_unidades_organizacionais', 'unidade_status')) {
            $this->markTestSkipped('Coluna obrigatoria ausente: dotp_unidades_organizacionais.unidade_status');
        }
        if (!$this->hasColumn('dotp_usuario_unidades', 'vinculo_status')) {
            $this->markTestSkipped('Coluna obrigatoria ausente: dotp_usuario_unidades.vinculo_status');
        }
    }

    /**
     * @return array<string, int>
     */
    private function seedFixture(): array
    {
        $suffix = strtoupper(bin2hex(random_bytes(3)));

        $prefeitoUserId = $this->createUser("it_pref_{$suffix}");
        $secretarioUserId = $this->createUser("it_sec_{$suffix}");
        $coordenadorUserId = $this->createUser("it_coord_{$suffix}");
        $tecnicoUserId = $this->createUser("it_tec_{$suffix}");

        $this->ensureNivelExists(1, 'Prefeitura');
        $this->ensureNivelExists(2, 'Secretaria');
        $this->ensureNivelExists(3, 'Coordenacao');

        $prefeituraUnidadeId = $this->createUnidade(
            "Prefeitura IT {$suffix}",
            1,
            null,
            $prefeitoUserId
        );
        $secretariaUnidadeId = $this->createUnidade(
            "Secretaria IT {$suffix}",
            2,
            $prefeituraUnidadeId,
            $secretarioUserId
        );
        $coordenacaoUnidadeId = $this->createUnidade(
            "Coordenacao IT {$suffix}",
            3,
            $secretariaUnidadeId,
            $coordenadorUserId
        );

        $this->ensureCompanyForUnidade($secretariaUnidadeId);
        $this->ensureCompanyForUnidade($coordenacaoUnidadeId);

        $this->createVinculo($secretarioUserId, $secretariaUnidadeId, 'SECRETARIO', true);
        $this->createVinculo($coordenadorUserId, $coordenacaoUnidadeId, 'COORDENADOR', true);
        $this->createVinculo($tecnicoUserId, $coordenacaoUnidadeId, 'TECNICO', true);

        $programaId = $this->createPrograma(
            $secretariaUnidadeId,
            $secretarioUserId,
            $coordenadorUserId,
            "Programa Integracao {$suffix}"
        );

        $projetoAtrasadoId = $this->createProjetoPrefeitura(
            $programaId,
            $secretariaUnidadeId,
            $coordenadorUserId,
            "Obra Atrasada {$suffix}",
            'Obra',
            'Atrasado',
            42,
            date('Y-m-d', strtotime('-4 days')),
            900000.0
        );

        $projetoIrregularId = $this->createProjetoPrefeitura(
            $programaId,
            $coordenacaoUnidadeId,
            $coordenadorUserId,
            "Projeto Irregular {$suffix}",
            'Servico',
            'Em_Andamento',
            130,
            date('Y-m-d', strtotime('+12 days')),
            650000.0
        );

        $this->createProjetoPrefeitura(
            $programaId,
            $secretariaUnidadeId,
            $coordenadorUserId,
            "Convenio Concluido {$suffix}",
            'Convenio',
            'Concluido',
            100,
            date('Y-m-d', strtotime('-1 day')),
            450000.0
        );

        $this->createProjetoPrefeitura(
            $programaId,
            $secretariaUnidadeId,
            $coordenadorUserId,
            "Convenio a Vencer {$suffix}",
            'Convenio',
            'Em_Andamento',
            64,
            date('Y-m-d', strtotime('+20 days')),
            380000.0
        );

        $this->createProjetoPrefeitura(
            $programaId,
            $secretariaUnidadeId,
            $coordenadorUserId,
            "Emenda em Risco {$suffix}",
            'Emenda',
            'Em_Andamento',
            18,
            date('Y-m-d', strtotime('+90 days')),
            220000.0
        );

        $legacyProjectId = $this->createLegacyProject(
            $coordenacaoUnidadeId,
            $tecnicoUserId,
            $programaId,
            "Projeto Tecnico {$suffix}"
        );

        $taskPendenteId = $this->createTask(
            $legacyProjectId,
            $tecnicoUserId,
            "Tarefa Pendente {$suffix}",
            65,
            date('Y-m-d H:i:s', strtotime('+2 days'))
        );

        $taskConcluidaId = $this->createTask(
            $legacyProjectId,
            $tecnicoUserId,
            "Tarefa Concluida {$suffix}",
            100,
            date('Y-m-d H:i:s', strtotime('-1 day'))
        );

        return [
            'prefeito_user_id' => $prefeitoUserId,
            'secretario_user_id' => $secretarioUserId,
            'coordenador_user_id' => $coordenadorUserId,
            'tecnico_user_id' => $tecnicoUserId,
            'secretaria_unidade_id' => $secretariaUnidadeId,
            'projeto_atrasado_id' => $projetoAtrasadoId,
            'projeto_irregular_id' => $projetoIrregularId,
            'task_pendente_id' => $taskPendenteId,
            'task_concluida_id' => $taskConcluidaId,
        ];
    }

    private function createUser(string $baseUsername): int
    {
        $username = strtolower($baseUsername . '_' . bin2hex(random_bytes(3)));
        $contactId = $this->db->insert('contacts', [
            'contact_first_name' => 'IT',
            'contact_last_name' => strtoupper(substr($baseUsername, -12)),
            'contact_order_by' => 'IT',
            'contact_company' => 'Integration',
            'contact_email' => $username . '@integration.test',
            'contact_owner' => 1,
        ]);
        $this->assertNotFalse($contactId, 'Falha ao criar contato do fixture de dashboard');
        $this->created['contacts'][] = (int) $contactId;

        $userPayload = [
            'user_contact' => (int) $contactId,
            'user_username' => $username,
            'user_password' => md5('dotproject123'),
            'user_parent' => 0,
            'user_type' => 1,
            'user_company' => 0,
            'user_department' => 0,
            'user_owner' => 1,
        ];
        if ($this->hasColumn('dotp_users', 'user_status')) {
            $userPayload['user_status'] = 0;
        }

        $userId = $this->db->insert('users', $userPayload);
        $this->assertNotFalse($userId, 'Falha ao criar usuario do fixture de dashboard');
        $this->assertGreaterThan(0, (int) $userId);
        $this->created['users'][] = (int) $userId;

        return (int) $userId;
    }

    private function ensureNivelExists(int $nivelId, string $nome): void
    {
        if (!$this->tableExists('dotp_niveis_hierarquicos')) {
            return;
        }

        $exists = (int) ($this->db->fetchValue(
            'SELECT COUNT(*) FROM dotp_niveis_hierarquicos WHERE nivel_id = ?',
            [$nivelId]
        ) ?? 0);

        if ($exists > 0) {
            return;
        }

        $inserted = $this->db->insert('niveis_hierarquicos', [
            'nivel_id' => $nivelId,
            'nivel_ordem' => $nivelId,
            'nivel_nome' => $nome,
            'nivel_titulo_responsavel' => $nome,
            'nivel_descricao' => "Fixture {$nome}",
            'nivel_cor' => '#3b82f6',
            'nivel_ativo' => 1,
        ]);

        $this->assertNotFalse($inserted, "Falha ao criar nivel {$nivelId} para fixture");
        $this->created['niveis'][] = $nivelId;
    }

    private function createUnidade(
        string $nome,
        int $nivel,
        ?int $unidadePaiId,
        int $responsavelId
    ): int {
        $payload = [
            'unidade_nome' => $nome,
        ];

        if ($this->hasColumn('dotp_unidades_organizacionais', 'unidade_nivel_id')) {
            $payload['unidade_nivel_id'] = $nivel;
        }
        if ($this->hasColumn('dotp_unidades_organizacionais', 'unidade_nivel')) {
            $payload['unidade_nivel'] = $nivel;
        }
        if ($this->hasColumn('dotp_unidades_organizacionais', 'unidade_pai_id')) {
            $payload['unidade_pai_id'] = $unidadePaiId;
        }
        if ($this->hasColumn('dotp_unidades_organizacionais', 'unidade_sigla')) {
            $payload['unidade_sigla'] = substr(strtoupper(preg_replace('/[^A-Z]/', '', $nome) ?: 'UNID'), 0, 10);
        }
        if ($this->hasColumn('dotp_unidades_organizacionais', 'unidade_status')) {
            $payload['unidade_status'] = 'ativo';
        }
        if ($this->hasColumn('dotp_unidades_organizacionais', 'unidade_ativa')) {
            $payload['unidade_ativa'] = 1;
        }
        if ($this->hasColumn('dotp_unidades_organizacionais', 'unidade_responsavel_id')) {
            $payload['unidade_responsavel_id'] = $responsavelId;
        }
        if ($this->hasColumn('dotp_unidades_organizacionais', 'unidade_pode_criar_projetos')) {
            $payload['unidade_pode_criar_projetos'] = 1;
        }
        if ($this->hasColumn('dotp_unidades_organizacionais', 'unidade_pode_criar_programas')) {
            $payload['unidade_pode_criar_programas'] = $nivel <= 2 ? 1 : 0;
        }

        $unidadeId = $this->db->insert('unidades_organizacionais', $payload);
        $this->assertNotFalse($unidadeId, "Falha ao criar unidade {$nome} para fixture");
        $this->assertGreaterThan(0, (int) $unidadeId);
        $this->created['unidades'][] = (int) $unidadeId;

        return (int) $unidadeId;
    }

    private function createVinculo(
        int $userId,
        int $unidadeId,
        string $role,
        bool $isPrincipal
    ): int {
        $payload = [
            'vinculo_user_id' => $userId,
            'vinculo_unidade_id' => $unidadeId,
        ];

        if ($this->hasColumn('dotp_usuario_unidades', 'vinculo_role')) {
            $payload['vinculo_role'] = $role;
        }
        if ($this->hasColumn('dotp_usuario_unidades', 'vinculo_status')) {
            $payload['vinculo_status'] = 'ativo';
        }
        if ($this->hasColumn('dotp_usuario_unidades', 'vinculo_ativo')) {
            $payload['vinculo_ativo'] = 1;
        }
        if ($this->hasColumn('dotp_usuario_unidades', 'vinculo_is_principal')) {
            $payload['vinculo_is_principal'] = $isPrincipal ? 1 : 0;
        }
        if ($this->hasColumn('dotp_usuario_unidades', 'vinculo_cargo')) {
            $payload['vinculo_cargo'] = $role;
        }
        if ($this->hasColumn('dotp_usuario_unidades', 'vinculo_nivel_acesso')) {
            $payload['vinculo_nivel_acesso'] = 2;
        }
        if ($this->hasColumn('dotp_usuario_unidades', 'vinculo_data_inicio')) {
            $payload['vinculo_data_inicio'] = date('Y-m-d');
        }

        $vinculoId = $this->db->insert('usuario_unidades', $payload);
        $this->assertNotFalse($vinculoId, "Falha ao criar vinculo {$role} para fixture");
        $this->assertGreaterThan(0, (int) $vinculoId);
        $this->created['vinculos'][] = (int) $vinculoId;

        return (int) $vinculoId;
    }

    private function ensureCompanyForUnidade(int $unidadeId): void
    {
        $exists = (int) ($this->db->fetchValue(
            'SELECT COUNT(*) FROM dotp_companies WHERE company_id = ?',
            [$unidadeId]
        ) ?? 0);

        if ($exists > 0) {
            return;
        }

        $inserted = $this->db->insert('companies', [
            'company_id' => $unidadeId,
            'company_module' => 0,
            'company_name' => "Company {$unidadeId}",
            'company_owner' => 0,
            'company_type' => 0,
            'company_email' => "company{$unidadeId}@integration.test",
        ]);

        $this->assertNotFalse($inserted, "Falha ao criar company para unidade {$unidadeId}");
        $this->created['companies'][] = $unidadeId;
    }

    private function createPrograma(
        int $unidadeId,
        int $responsavelPoliticoId,
        int $responsavelTecnicoId,
        string $nome
    ): int {
        $payload = [
            'codigo' => 'IT-' . strtoupper(bin2hex(random_bytes(2))),
            'nome' => $nome,
            'unidade_id' => $unidadeId,
        ];

        if ($this->hasColumn('dotp_programas', 'estado')) {
            $payload['estado'] = 'Execucao';
        }
        if ($this->hasColumn('dotp_programas', 'percent_execucao')) {
            $payload['percent_execucao'] = 55;
        }
        if ($this->hasColumn('dotp_programas', 'status_saude')) {
            $payload['status_saude'] = 'atencao';
        }
        if ($this->hasColumn('dotp_programas', 'valor_orcamentario')) {
            $payload['valor_orcamentario'] = 3200000.0;
        }
        if ($this->hasColumn('dotp_programas', 'responsavel_politico_id')) {
            $payload['responsavel_politico_id'] = $responsavelPoliticoId;
        }
        if ($this->hasColumn('dotp_programas', 'responsavel_tecnico_id')) {
            $payload['responsavel_tecnico_id'] = $responsavelTecnicoId;
        }
        if ($this->hasColumn('dotp_programas', 'data_inicio')) {
            $payload['data_inicio'] = date('Y-m-d', strtotime('-20 days'));
        }
        if ($this->hasColumn('dotp_programas', 'data_fim')) {
            $payload['data_fim'] = date('Y-m-d', strtotime('+320 days'));
        }
        if ($this->hasColumn('dotp_programas', 'objetivo_estrategico')) {
            $payload['objetivo_estrategico'] = 'Fixture de integracao de dashboard';
        }
        if ($this->hasColumn('dotp_programas', 'descricao')) {
            $payload['descricao'] = 'Programa criado para validar dashboards por perfil.';
        }

        $programaId = $this->db->insert('programas', $payload);
        $this->assertNotFalse($programaId, "Falha ao criar programa {$nome} para fixture");
        $this->assertGreaterThan(0, (int) $programaId);
        $this->created['programas'][] = (int) $programaId;

        return (int) $programaId;
    }

    private function createProjetoPrefeitura(
        int $programaId,
        int $unidadeId,
        int $coordenadorId,
        string $nome,
        string $tipo,
        string $estado,
        int $percentExecucao,
        string $dataPrevistaFim,
        float $valorPrevisto
    ): int {
        $payload = [
            'programa_id' => $programaId,
            'nome' => $nome,
            'unidade_id' => $unidadeId,
        ];

        if ($this->hasColumn('dotp_projetos_prefeitura', 'tipo')) {
            $payload['tipo'] = $tipo;
        }
        if ($this->hasColumn('dotp_projetos_prefeitura', 'coordenador_id')) {
            $payload['coordenador_id'] = $coordenadorId;
        }
        if ($this->hasColumn('dotp_projetos_prefeitura', 'estado')) {
            $payload['estado'] = $estado;
        }
        if ($this->hasColumn('dotp_projetos_prefeitura', 'percent_execucao')) {
            $payload['percent_execucao'] = $percentExecucao;
        }
        if ($this->hasColumn('dotp_projetos_prefeitura', 'valor_previsto')) {
            $payload['valor_previsto'] = $valorPrevisto;
        }
        if ($this->hasColumn('dotp_projetos_prefeitura', 'valor_executado')) {
            $payload['valor_executado'] = round($valorPrevisto * ($percentExecucao / 100), 2);
        }
        if ($this->hasColumn('dotp_projetos_prefeitura', 'etapa_atual')) {
            $payload['etapa_atual'] = 1;
        }
        if ($this->hasColumn('dotp_projetos_prefeitura', 'situacao_orcamentaria')) {
            $payload['situacao_orcamentaria'] = $percentExecucao >= 100 ? 'pago' : 'empenhado';
        }
        if ($this->hasColumn('dotp_projetos_prefeitura', 'data_prevista_inicio')) {
            $payload['data_prevista_inicio'] = date('Y-m-d', strtotime('-25 days'));
        }
        if ($this->hasColumn('dotp_projetos_prefeitura', 'data_prevista_fim')) {
            $payload['data_prevista_fim'] = $dataPrevistaFim;
        }
        if ($this->hasColumn('dotp_projetos_prefeitura', 'status_saude')) {
            $payload['status_saude'] = $percentExecucao > 100 ? 'critico' : ($estado === 'Atrasado' ? 'atencao' : 'em_dia');
        }

        $projetoId = $this->db->insert('projetos_prefeitura', $payload);
        $this->assertNotFalse($projetoId, "Falha ao criar projeto {$nome} para fixture");
        $this->assertGreaterThan(0, (int) $projetoId);
        $this->created['projetos_prefeitura'][] = (int) $projetoId;

        return (int) $projetoId;
    }

    private function createLegacyProject(
        int $unidadeId,
        int $ownerId,
        int $programaId,
        string $nome
    ): int {
        $payload = [
            'project_company' => $unidadeId,
            'project_company_internal' => 0,
            'project_department' => 0,
            'project_name' => $nome,
            'project_short_name' => substr(preg_replace('/[^A-Za-z0-9]/', '', $nome) ?: 'ITPROJ', 0, 10),
            'project_owner' => $ownerId,
            'project_creator' => $ownerId,
            'project_status' => 3,
            'project_percent_complete' => 52,
            'project_color_identifier' => '#4A90D9',
            'project_priority' => 1,
            'project_type' => 0,
            'project_start_date' => date('Y-m-d H:i:s', strtotime('-20 days')),
            'project_end_date' => date('Y-m-d H:i:s', strtotime('+30 days')),
            'project_target_budget' => 150000.0,
            'project_actual_budget' => 80000.0,
        ];

        if ($this->hasColumn('dotp_projects', 'project_programa_id')) {
            $payload['project_programa_id'] = $programaId;
        }
        if ($this->hasColumn('dotp_projects', 'project_tipo')) {
            $payload['project_tipo'] = 'Outro';
        }
        if ($this->hasColumn('dotp_projects', 'project_estado')) {
            $payload['project_estado'] = 'Em_Andamento';
        }
        if ($this->hasColumn('dotp_projects', 'project_etapa_atual')) {
            $payload['project_etapa_atual'] = 1;
        }
        if ($this->hasColumn('dotp_projects', 'project_percent_execucao')) {
            $payload['project_percent_execucao'] = 52;
        }
        if ($this->hasColumn('dotp_projects', 'project_coordenador_id')) {
            $payload['project_coordenador_id'] = $ownerId;
        }
        if ($this->hasColumn('dotp_projects', 'status_saude')) {
            $payload['status_saude'] = 'atencao';
        }
        if ($this->hasColumn('dotp_projects', 'status_fluxo')) {
            $payload['status_fluxo'] = 'Execucao';
        }
        if ($this->hasColumn('dotp_projects', 'valor_executado')) {
            $payload['valor_executado'] = 80000.0;
        }

        $projectId = $this->db->insert('projects', $payload);
        $this->assertNotFalse($projectId, "Falha ao criar projeto legado {$nome} para fixture");
        $this->assertGreaterThan(0, (int) $projectId);
        $this->created['projects'][] = (int) $projectId;

        return (int) $projectId;
    }

    private function createTask(
        int $projectId,
        int $ownerId,
        string $nome,
        int $percentComplete,
        string $endDate
    ): int {
        $payload = [
            'task_name' => $nome,
            'task_project' => $projectId,
            'task_owner' => $ownerId,
            'task_start_date' => date('Y-m-d H:i:s', strtotime('-3 days')),
            'task_end_date' => $endDate,
            'task_status' => 0,
            'task_priority' => 1,
            'task_percent_complete' => $percentComplete,
            'task_creator' => $ownerId,
            'task_duration' => 4,
            'task_duration_type' => 1,
            'task_order' => 0,
            'task_notify' => 0,
        ];

        if ($this->hasColumn('dotp_tasks', 'task_assigned_to')) {
            $payload['task_assigned_to'] = $ownerId;
        }
        if ($this->hasColumn('dotp_tasks', 'estado')) {
            $payload['estado'] = $percentComplete >= 100 ? 'Concluida' : 'Em_Andamento';
        }
        if ($this->hasColumn('dotp_tasks', 'peso')) {
            $payload['peso'] = 1.0;
        }

        $taskId = $this->db->insert('tasks', $payload);
        $this->assertNotFalse($taskId, "Falha ao criar tarefa {$nome} para fixture");
        $this->assertGreaterThan(0, (int) $taskId);
        $this->created['tasks'][] = (int) $taskId;

        return (int) $taskId;
    }

    private function cleanupFixture(): void
    {
        $this->deleteByIds('dotp_tasks', 'task_id', $this->created['tasks']);
        $this->deleteByIds('dotp_projects', 'project_id', $this->created['projects']);
        $this->deleteByIds('dotp_projetos_prefeitura', 'id', $this->created['projetos_prefeitura']);
        $this->deleteByIds('dotp_programas', 'id', $this->created['programas']);
        $this->deleteByIds('dotp_usuario_unidades', 'vinculo_id', $this->created['vinculos']);
        $this->deleteByIds('dotp_unidades_organizacionais', 'unidade_id', $this->created['unidades']);
        $this->deleteByIds('dotp_companies', 'company_id', $this->created['companies']);
        $this->deleteByIds('dotp_users', 'user_id', $this->created['users']);
        $this->deleteByIds('dotp_contacts', 'contact_id', $this->created['contacts']);
        $this->deleteByIds('dotp_niveis_hierarquicos', 'nivel_id', $this->created['niveis']);

        foreach ($this->created as $key => $ids) {
            $this->created[$key] = [];
        }
        $this->fixture = [];
    }

    private function tableExists(string $table): bool
    {
        return (int) ($this->db->fetchValue(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$table]
        ) ?? 0) > 0;
    }

    private function hasColumn(string $table, string $column): bool
    {
        return (int) ($this->db->fetchValue(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [$table, $column]
        ) ?? 0) > 0;
    }

    /**
     * @param int[] $ids
     */
    private function deleteByIds(string $table, string $pk, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $ordered = array_values(array_unique(array_map('intval', $ids)));
        rsort($ordered);

        foreach ($ordered as $id) {
            try {
                $this->db->execute("DELETE FROM {$table} WHERE {$pk} = ?", [$id]);
            } catch (\Throwable) {
                // Cleanup should not fail the test.
            }
        }
    }

    private function buildRequest(int $userId, string $uri, array $query = []): Request
    {
        $request = $this->createMock(Request::class);
        $request->method('getParam')
            ->willReturnCallback(
                static fn(string $key, mixed $default = null): mixed => match ($key) {
                    '_user_id' => $userId,
                    '_user_data' => [],
                    default => $default,
                }
            );
        $request->method('getQuery')
            ->willReturnCallback(
                static fn(string $key, mixed $default = null): mixed => $query[$key] ?? $default
            );
        $request->method('getQueryParam')
            ->willReturnCallback(
                static fn(string $key, mixed $default = null): mixed => $query[$key] ?? $default
            );
        $request->method('getUri')->willReturn($uri);

        return $request;
    }

    private function responseStatus(Response $response): int
    {
        $statusProp = new \ReflectionProperty(Response::class, 'statusCode');
        $statusProp->setAccessible(true);
        return (int) $statusProp->getValue($response);
    }

    /**
     * @return array<string, mixed>
     */
    private function responseBody(Response $response): array
    {
        $bodyProp = new \ReflectionProperty(Response::class, 'body');
        $bodyProp->setAccessible(true);
        $body = $bodyProp->getValue($response);

        return is_array($body) ? $body : [];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function hasRowWithId(array $rows, int $id, string $key = 'id'): bool
    {
        foreach ($rows as $row) {
            if ((int) ($row[$key] ?? 0) === $id) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function hasRowWithValue(array $rows, string $key, string $value): bool
    {
        foreach ($rows as $row) {
            if ((string) ($row[$key] ?? '') === $value) {
                return true;
            }
        }

        return false;
    }
}
