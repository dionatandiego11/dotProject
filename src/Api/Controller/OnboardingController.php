<?php
/**
 * Controller para o wizard de onboarding da prefeitura.
 *
 * Recebe todos os dados do wizard de configuração inicial e cria
 * níveis hierárquicos, unidades organizacionais, primeiro usuário
 * e vínculos em uma única transação.
 *
 * @package DotProject\Api\Controller
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Api\Controller;

use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Core\Logger;
use DotProject\Entity\NivelHierarquicoEntity;
use DotProject\Entity\UnidadeOrganizacionalEntity;
use DotProject\Repository\NivelHierarquicoRepository;
use DotProject\Repository\UnidadeOrganizacionalRepository;
use DotProject\Repository\UsuarioUnidadeRepository;
use DotProject\Repository\UserRepository;
use DotProject\Service\OnboardingReadinessService;
use DotProject\Service\UserService;
use DotProject\Service\UnidadeCompanySyncService;

class OnboardingController extends BaseController
{
    private NivelHierarquicoRepository $nivelRepo;
    private UnidadeOrganizacionalRepository $unidadeRepo;
    private UsuarioUnidadeRepository $vinculoRepo;
    private UserRepository $userRepo;
    private UserService $userService;
    private UnidadeCompanySyncService $unidadeCompanySync;
    private OnboardingReadinessService $onboardingReadinessService;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->nivelRepo = new NivelHierarquicoRepository();
        $this->unidadeRepo = new UnidadeOrganizacionalRepository();
        $this->vinculoRepo = new UsuarioUnidadeRepository();
        $this->userRepo = new UserRepository();
        $this->userService = new UserService();
        $this->unidadeCompanySync = new UnidadeCompanySyncService($this->db);
        $this->onboardingReadinessService = new OnboardingReadinessService(
            $this->db,
            $this->nivelRepo,
            $this->unidadeRepo,
            $this->vinculoRepo,
            $this->userRepo
        );
    }

    /**
     * GET /api/v1/admin/setup/templates
     *
     * Retorna templates de níveis hierárquicos pré-configurados.
     */
    public function templates(): Response
    {
        $templates = [
            'padrao' => [
                'nome' => 'Padrão (5 níveis)',
                'descricao' => 'Estrutura completa para prefeituras de médio porte',
                'niveis' => [
                    ['ordem' => 1, 'nome' => 'Prefeitura', 'titulo_responsavel' => 'Prefeito(a)', 'cor' => '#1e3a8a'],
                    ['ordem' => 2, 'nome' => 'Secretaria', 'titulo_responsavel' => 'Secretário(a)', 'cor' => '#2563eb'],
                    ['ordem' => 3, 'nome' => 'Coordenação', 'titulo_responsavel' => 'Coordenador(a)', 'cor' => '#3b82f6'],
                    ['ordem' => 4, 'nome' => 'Departamento', 'titulo_responsavel' => 'Chefe de Depto.', 'cor' => '#60a5fa'],
                    ['ordem' => 5, 'nome' => 'Setor', 'titulo_responsavel' => 'Responsável', 'cor' => '#93c5fd'],
                ],
            ],
            'simplificado' => [
                'nome' => 'Simplificado (3 níveis)',
                'descricao' => 'Para prefeituras pequenas ou início rápido',
                'niveis' => [
                    ['ordem' => 1, 'nome' => 'Prefeitura', 'titulo_responsavel' => 'Prefeito(a)', 'cor' => '#1e3a8a'],
                    ['ordem' => 2, 'nome' => 'Secretaria', 'titulo_responsavel' => 'Secretário(a)', 'cor' => '#2563eb'],
                    ['ordem' => 3, 'nome' => 'Departamento', 'titulo_responsavel' => 'Responsável', 'cor' => '#3b82f6'],
                ],
            ],
            'completo' => [
                'nome' => 'Completo (6 níveis)',
                'descricao' => 'Para capitais e prefeituras de grande porte',
                'niveis' => [
                    ['ordem' => 1, 'nome' => 'Prefeitura', 'titulo_responsavel' => 'Prefeito(a)', 'cor' => '#1e3a8a'],
                    ['ordem' => 2, 'nome' => 'Secretaria', 'titulo_responsavel' => 'Secretário(a)', 'cor' => '#2563eb'],
                    ['ordem' => 3, 'nome' => 'Subsecretaria', 'titulo_responsavel' => 'Subsecretário(a)', 'cor' => '#3b82f6'],
                    ['ordem' => 4, 'nome' => 'Coordenação', 'titulo_responsavel' => 'Coordenador(a)', 'cor' => '#60a5fa'],
                    ['ordem' => 5, 'nome' => 'Departamento', 'titulo_responsavel' => 'Chefe de Depto.', 'cor' => '#93c5fd'],
                    ['ordem' => 6, 'nome' => 'Setor', 'titulo_responsavel' => 'Responsável', 'cor' => '#bfdbfe'],
                ],
            ],
        ];

        return $this->json(['data' => $templates]);
    }

    /**
     * POST /api/v1/admin/setup
     *
     * Executa o setup completo da prefeitura em uma transação.
     *
     * Payload esperado:
     * {
     *   "prefeitura": { "nome": "...", "cnpj": "...", "estado": "...", "cidade": "..." },
     *   "niveis": [ { "ordem": 1, "nome": "...", "titulo_responsavel": "...", "cor": "#..." }, ... ],
     *   "secretarias": [ { "nome": "...", "sigla": "..." }, ... ],
     *   "departamentos": { "Secretaria X": [ { "nome": "...", "sigla": "..." }, ... ], ... },
     *   "usuario": { "nome": "...", "sobrenome": "...", "email": "...", "username": "...", "password": "..." },
     *   "convites": [ "email1@...", "email2@..." ]
     * }
     */
    public function setup(): Response
    {
        $data = $this->request->getJsonBody();
        $forceReconfigure = (bool) ($data['force_reconfigure'] ?? false);

        $readiness = $this->onboardingReadinessService->buildChecklist();
        $summary = is_array($readiness['summary'] ?? null) ? $readiness['summary'] : [];
        $hasExistingStructure = $this->hasExistingStructure($summary);

        if ($hasExistingStructure && !$forceReconfigure) {
            return $this->json([
                'error' => true,
                'message' => 'Setup bloqueado: já existe estrutura ativa. Envie force_reconfigure=true para confirmar reexecução.',
                'data' => [
                    'summary' => $summary,
                ],
            ], 409);
        }

        // --- Validação ---
        $errors = $this->validateSetupData($data);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        $transactionStarted = false;
        try {
            $this->db->beginTransaction();
            $transactionStarted = true;

            $created = [
                'niveis' => [],
                'unidades' => [],
                'usuario' => null,
                'vinculo' => null,
                'convites' => [],
            ];

            // 1) Criar níveis hierárquicos
            $nivelIds = $this->createNiveis($data['niveis']);
            $created['niveis'] = $nivelIds;

            // 2) Criar unidade raiz (Prefeitura)
            $prefData = $data['prefeitura'];
            $raizId = $this->createUnidade(
                $prefData['nome'],
                1, // nível 1
                null, // sem pai
                $prefData['sigla'] ?? null,
                $prefData['cnpj'] ?? null
            );
            $created['unidades'][] = [
                'id' => $raizId,
                'nome' => $prefData['nome'],
                'tipo' => 'raiz',
            ];

            // 3) Criar secretarias (nível 2)
            $secretariaMap = []; // nome => id
            $secretarias = $data['secretarias'] ?? [];
            foreach ($secretarias as $sec) {
                $secId = $this->createUnidade(
                    $sec['nome'],
                    2, // nível 2
                    $raizId,
                    $sec['sigla'] ?? null
                );
                $secretariaMap[$sec['nome']] = $secId;
                $created['unidades'][] = [
                    'id' => $secId,
                    'nome' => $sec['nome'],
                    'tipo' => 'secretaria',
                ];
            }

            // 4) Criar departamentos/coordenações (nível 3)
            $departamentos = $data['departamentos'] ?? [];
            foreach ($departamentos as $secNome => $deptos) {
                $parentId = $secretariaMap[$secNome] ?? null;
                if ($parentId === null) {
                    continue; // secretaria inválida, pular
                }
                foreach ($deptos as $dept) {
                    $deptId = $this->createUnidade(
                        $dept['nome'],
                        3, // nível 3
                        $parentId,
                        $dept['sigla'] ?? null
                    );
                    $created['unidades'][] = [
                        'id' => $deptId,
                        'nome' => $dept['nome'],
                        'tipo' => 'departamento',
                        'secretaria' => $secNome,
                    ];
                }
            }

            // 5) Criar primeiro usuário (Prefeito)
            $userData = $data['usuario'];
            $user = $this->userService->createUser([
                'user_username' => $userData['username'],
                'user_password' => $userData['password'],
                'contact_first_name' => $userData['nome'],
                'contact_last_name' => $userData['sobrenome'] ?? '',
                'contact_email' => $userData['email'],
                'user_company' => $raizId,
            ], 0); // criado pelo sistema (user_id = 0)

            if (!$user) {
                $this->db->rollback();
                return $this->error('Falha ao criar usuário administrador', 500);
            }

            $userId = $user->getId();
            $created['usuario'] = [
                'id' => $userId,
                'username' => $userData['username'],
                'email' => $userData['email'],
            ];

            // 6) Criar vínculo do prefeito com a unidade raiz
            $vinculoId = $this->createVinculo($userId, $raizId, true);
            $created['vinculo'] = ['id' => $vinculoId, 'unidade_id' => $raizId];

            // 7) Salvar convites (apenas armazena — envio real será futuro)
            $convites = $data['convites'] ?? [];
            if (!empty($convites)) {
                $created['convites'] = $convites;
                Logger::log('info', 'Convites registrados no setup', [
                    'emails' => $convites,
                    'prefeitura' => $prefData['nome'],
                ]);
            }

            $this->db->commit();

            Logger::log('info', 'Setup completo da prefeitura', [
                'prefeitura' => $prefData['nome'],
                'niveis_criados' => count($nivelIds),
                'unidades_criadas' => count($created['unidades']),
                'usuario_id' => $userId,
                'forced_reconfigure' => $forceReconfigure,
            ]);

            return $this->json([
                'message' => 'Prefeitura configurada com sucesso!',
                'data' => $created,
            ], 201);

        } catch (\Throwable $e) {
            if ($transactionStarted) {
                $this->db->rollback();
            }
            Logger::error('Erro no setup da prefeitura', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return $this->error('Erro ao configurar prefeitura: ' . $e->getMessage(), 500);
        }
    }

    // =========================================================================
    // Métodos privados auxiliares
    // =========================================================================

    /**
     * Detecta se já existe estrutura organizacional ativa no ambiente.
     *
     * @param array<string, mixed> $summary
     */
    private function hasExistingStructure(array $summary): bool
    {
        return (
            (int) ($summary['niveis_ativos'] ?? 0) > 0 ||
            (int) ($summary['unidades_ativas'] ?? 0) > 0 ||
            (int) ($summary['vinculos_ativos'] ?? 0) > 0
        );
    }

    /**
     * Valida os dados de entrada do setup.
     *
     * @return array<string, string> Erros de validação (vazio se ok)
     */
    private function validateSetupData(array $data): array
    {
        $errors = [];

        // Prefeitura
        if (empty($data['prefeitura']['nome'])) {
            $errors['prefeitura.nome'] = 'Nome da prefeitura é obrigatório';
        }

        // Níveis
        if (empty($data['niveis']) || !is_array($data['niveis'])) {
            $errors['niveis'] = 'Pelo menos um nível hierárquico é obrigatório';
        } else {
            foreach ($data['niveis'] as $i => $nivel) {
                if (empty($nivel['nome'])) {
                    $errors["niveis.{$i}.nome"] = "Nome do nível {$i} é obrigatório";
                }
                if (!isset($nivel['ordem'])) {
                    $errors["niveis.{$i}.ordem"] = "Ordem do nível {$i} é obrigatória";
                }
            }
        }

        // Usuário
        if (empty($data['usuario']['username'])) {
            $errors['usuario.username'] = 'Username é obrigatório';
        }
        if (empty($data['usuario']['password'])) {
            $errors['usuario.password'] = 'Senha é obrigatória';
        }
        if (empty($data['usuario']['nome'])) {
            $errors['usuario.nome'] = 'Nome do usuário é obrigatório';
        }
        if (empty($data['usuario']['email'])) {
            $errors['usuario.email'] = 'Email é obrigatório';
        }

        return $errors;
    }

    /**
     * Cria os níveis hierárquicos.
     *
     * @return array<array{id: int, nome: string}>
     */
    private function createNiveis(array $niveis): array
    {
        $result = [];
        foreach ($niveis as $nivelData) {
            $nivel = new NivelHierarquicoEntity();
            $nivel->setOrdem((int) $nivelData['ordem']);
            $nivel->setNome($nivelData['nome']);
            $nivel->setTituloResponsavel($nivelData['titulo_responsavel'] ?? null);
            $nivel->setDescricao($nivelData['descricao'] ?? null);
            $nivel->setCor($nivelData['cor'] ?? '#007bff');

            $id = $this->nivelRepo->save($nivel);
            $result[] = ['id' => $id, 'nome' => $nivelData['nome']];
        }
        return $result;
    }

    /**
     * Cria uma unidade organizacional e sincroniza com a tabela companies legada.
     */
    private function createUnidade(
        string $nome,
        int $nivel,
        ?int $paiId,
        ?string $sigla = null,
        ?string $descricao = null
    ): int {
        $unidade = new UnidadeOrganizacionalEntity();
        $unidade->setNome($nome);
        $unidade->setNivel($nivel);
        $unidade->setPaiId($paiId);
        $unidade->setSigla($sigla);
        $unidade->setDescricao($descricao);
        $unidade->setPodeCriarProjetos(true);

        $id = $this->unidadeRepo->save($unidade);

        // Sincroniza com tabela companies legada
        $this->unidadeCompanySync->ensureCompanyForUnidade($id, $nome);

        return $id;
    }

    /**
     * Cria vínculo entre usuário e unidade organizacional.
     */
    private function createVinculo(int $userId, int $unidadeId, bool $principal = false): int
    {
        $table = $this->db->table('usuario_unidades');

        $this->db->insert('usuario_unidades', [
            'vinculo_user_id' => $userId,
            'vinculo_unidade_id' => $unidadeId,
            'vinculo_cargo' => 'Prefeito(a)',
            'vinculo_is_principal' => $principal ? 1 : 0,
            'vinculo_ativo' => 1,
        ]);

        return (int) $this->db->lastInsertId();
    }
}
