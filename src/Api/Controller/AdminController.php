<?php
/**
 * Controller for admin of organizational structure.
 *
 * @package DotProject\Api\Controller
 */

declare(strict_types=1);

namespace DotProject\Api\Controller;

use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Entity\NivelHierarquicoEntity;
use DotProject\Entity\UnidadeOrganizacionalEntity;
use DotProject\Entity\HistoricoMovimentacaoEntity;
use DotProject\Repository\NivelHierarquicoRepository;
use DotProject\Repository\UnidadeOrganizacionalRepository;
use DotProject\Repository\UsuarioUnidadeRepository;
use DotProject\Repository\HistoricoMovimentacaoRepository;
use DotProject\Repository\UserRepository;
use DotProject\Core\Logger;
use DotProject\Service\AuthorizationService;
use DotProject\Service\PermissionService;
use DotProject\Service\UserService;
use DotProject\Service\UnidadeCompanySyncService;
use DotProject\Service\OnboardingReadinessService;

class AdminController extends BaseController
{
    private NivelHierarquicoRepository $nivelRepo;
    private UnidadeOrganizacionalRepository $unidadeRepo;
    private UsuarioUnidadeRepository $vinculoRepo;
    private HistoricoMovimentacaoRepository $historicoRepo;
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
        $this->historicoRepo = new HistoricoMovimentacaoRepository();
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
        Logger::debug('AdminController inicializado');
    }

    // ===========================================
    // DASHBOARD ADMIN
    // ===========================================

    /**
     * GET /api/v1/admin/dashboard
     */
    public function dashboard(): Response
    {
        $cacheKey = $this->cacheKey('admin', 'dashboard');
        $cached = $this->cache->get($cacheKey);

        if ($cached) {
            return $this->json($cached);
        }

        $data = [
            'estrutura' => $this->unidadeRepo->getEstatisticas(),
            'vinculos' => $this->vinculoRepo->getEstatisticas(),
            'movimentacoes' => $this->historicoRepo->getEstatisticas(),
            'usuarios_sem_vinculo' => $this->vinculoRepo->findUsuariosSemVinculo(),
        ];

        $this->cache->set($cacheKey, $data, 300);

        return $this->json($data);
    }

    /**
     * GET /api/v1/admin/onboarding/readiness
     */
    public function onboardingReadiness(): Response
    {
        try {
            $data = $this->onboardingReadinessService->buildChecklist();
            return $this->json(['data' => $data]);
        } catch (\Throwable $e) {
            Logger::error('Erro ao gerar checklist de onboarding', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return $this->error('Falha ao carregar checklist de onboarding', 500);
        }
    }

    // ===========================================
    // NIVEIS HIERARQUICOS
    // ===========================================

    /**
     * GET /api/v1/admin/niveis
     */
    public function listNiveis(): Response
    {
        $niveis = $this->nivelRepo->findAllAtivos();

        return $this->json([
            'data' => array_map(fn($n) => $n->toArraySimple(), $niveis),
        ]);
    }

    /**
     * GET /api/v1/admin/niveis/{id}
     */
    public function getNivel(int $id): Response
    {
        $nivel = $this->nivelRepo->findWithPermissoes($id);

        if (!$nivel) {
            return $this->notFound('Nivel nao encontrado');
        }

        return $this->json([
            'data' => $nivel->toArray(),
        ]);
    }

    /**
     * POST /api/v1/admin/niveis
     */
    public function createNivel(): Response
    {
        try {
            $data = $this->request->getJsonBody();

            $errors = $this->validateNivel($data);
            if (!empty($errors)) {
                return $this->validationError($errors);
            }

            $nivel = new NivelHierarquicoEntity();
            $nivel->setOrdem((int) $data['ordem']);
            $nivel->setNome($data['nome']);
            $nivel->setTituloResponsavel($data['titulo_responsavel'] ?? null);
            $nivel->setDescricao($data['descricao'] ?? null);
            $nivel->setCor($data['cor'] ?? '#007bff');

            $id = $this->nivelRepo->save($nivel);
            $nivel->setId($id);

            if (!empty($data['permissoes'])) {
                $this->nivelRepo->atualizarPermissoes($id, $data['permissoes']);
            }

            return $this->json([
                'message' => 'Nivel criado com sucesso',
                'data' => $nivel->toArray(),
            ], 201);
        } catch (\Throwable $e) {
            Logger::error('Erro ao criar nivel', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return $this->json(['error' => 'Erro ao criar nivel'], 500);
        }
    }

    /**
     * PUT /api/v1/admin/niveis/{id}
     */
    public function updateNivel(int $id): Response
    {
        $nivel = $this->nivelRepo->find($id);

        if (!$nivel) {
            return $this->notFound('Nivel nao encontrado');
        }

        $data = $this->request->getJsonBody();

        if (isset($data['nome'])) {
            $nivel->setNome($data['nome']);
        }
        if (isset($data['titulo_responsavel'])) {
            $nivel->setTituloResponsavel($data['titulo_responsavel']);
        }
        if (isset($data['descricao'])) {
            $nivel->setDescricao($data['descricao']);
        }
        if (isset($data['cor'])) {
            $nivel->setCor($data['cor']);
        }
        if (isset($data['ativo'])) {
            $nivel->setAtivo((bool) $data['ativo']);
        }

        $this->nivelRepo->save($nivel);

        if (!empty($data['permissoes'])) {
            $this->nivelRepo->atualizarPermissoes($id, $data['permissoes']);
        }

        return $this->json([
            'message' => 'Nivel atualizado com sucesso',
            'data' => $nivel->toArray(),
        ]);
    }

    /**
     * PUT /api/v1/admin/niveis/reordenar
     */
    public function reordenarNiveis(): Response
    {
        $data = $this->request->getJsonBody();

        if (empty($data['ordens']) || !is_array($data['ordens'])) {
            return $this->validationError(['ordens' => 'Array de ordens e obrigatorio']);
        }

        $this->nivelRepo->reordenar($data['ordens']);

        return $this->json([
            'message' => 'Niveis reordenados com sucesso',
        ]);
    }

    /**
     * DELETE /api/v1/admin/niveis/{id}
     */
    public function deleteNivel(int $id): Response
    {
        $nivel = $this->nivelRepo->find($id);

        if (!$nivel) {
            return $this->notFound('Nivel nao encontrado');
        }

        $this->nivelRepo->delete($id);

        return $this->json([
            'message' => 'Nivel removido com sucesso',
        ]);
    }

    // ===========================================
    // UNIDADES ORGANIZACIONAIS
    // ===========================================

    /**
     * GET /api/v1/admin/unidades
     */
    public function listUnidades(): Response
    {
        $arvore = $this->normalizeBool($this->request->getQuery('arvore', false));
        $nivel = $this->request->getQuery('nivel');
        $escopo = $this->normalizeBool($this->request->getQuery('escopo', false));

        if ($arvore) {
            $unidades = $this->unidadeRepo->findArvore();
            if ($escopo) {
                $unidades = $this->filterUnidadesByEscopo($unidades);
            }
            return $this->json([
                'data' => array_map(fn($u) => $u->toArray(true), $unidades),
            ]);
        }

        if ($nivel) {
            $unidades = $this->unidadeRepo->findByNivel((int) $nivel);
        } else {
            $unidades = $this->unidadeRepo->findAllAtivas();
        }
        if ($escopo) {
            $unidades = $this->filterUnidadesByEscopo($unidades);
        }

        return $this->json([
            'data' => array_map(fn($u) => $u->toArray(), $unidades),
        ]);
    }

    /**
     * GET /api/v1/admin/unidades/arvore
     */
    public function getArvore(): Response
    {
        $raizId = $this->request->getQuery('raiz_id');
        $arvore = $this->unidadeRepo->findArvore($raizId ? (int) $raizId : null);

        return $this->json([
            'data' => array_map(fn($u) => $u->toArray(true), $arvore),
        ]);
    }

    /**
     * GET /api/v1/admin/unidades/{id}
     */
    public function getUnidade(int $id): Response
    {
        $unidade = $this->unidadeRepo->find($id);

        if (!$unidade) {
            return $this->notFound('Unidade nao encontrada');
        }

        $vinculos = $this->vinculoRepo->findByUnidade($id);

        $data = $unidade->toArray();
        $data['vinculos'] = $vinculos;
        $data['filhas'] = array_map(
            fn($f) => $f->toArray(),
            $this->unidadeRepo->findFilhas($id)
        );

        return $this->json([
            'data' => $data,
        ]);
    }

    /**
     * POST /api/v1/admin/unidades
     */
    public function createUnidade(): Response
    {
        $transactionStarted = false;
        try {
            $data = $this->request->getJsonBody();

            $errors = $this->validateUnidade($data);
            if (!empty($errors)) {
                return $this->validationError($errors);
            }

            $unidade = new UnidadeOrganizacionalEntity();
            $unidade->setNome($data['nome']);
            $unidade->setNivel((int) $data['nivel']);
            $unidade->setPaiId(isset($data['pai_id']) ? (int) $data['pai_id'] : null);
            $unidade->setSigla($data['sigla'] ?? null);
            $unidade->setDescricao($data['descricao'] ?? null);
            $unidade->setEndereco($data['endereco'] ?? null);
            $unidade->setEmail($data['email'] ?? null);
            $unidade->setTelefone($data['telefone'] ?? null);
            // O frontend envia 'responsavel' como string, mas o backend espera 'responsavel_id' como int
            // Vamos ignorar o campo 'responsavel' por enquanto
            $unidade->setResponsavelId($data['responsavel_id'] ?? null);
            $unidade->setPodeCriarProjetos($this->normalizeBool($data['pode_criar_projetos'] ?? null, true));
            $unidade->setPodeCriarProgramas($this->normalizeBool($data['pode_criar_programas'] ?? null, false));

            $this->db->beginTransaction();
            $transactionStarted = true;
            $id = $this->unidadeRepo->save($unidade);
            $unidade->setId($id);

            $synced = $this->unidadeCompanySync->ensureCompanyForUnidade($id, $unidade->getNome());
            if (!$synced) {
                $this->db->rollback();
                return $this->error('Falha ao sincronizar unidade com companies legadas', 500);
            }

            $this->db->commit();

            return $this->json([
                'message' => 'Unidade criada com sucesso',
                'data' => $unidade->toArray(),
            ], 201);
        } catch (\Throwable $e) {
            if ($transactionStarted) {
                $this->db->rollback();
            }
            Logger::error('Erro ao criar unidade', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return $this->json(['error' => 'Erro ao criar unidade'], 500);
        }
    }

    /**
     * PUT /api/v1/admin/unidades/{id}
     */
    public function updateUnidade(int $id): Response
    {
        $unidade = $this->unidadeRepo->find($id);

        if (!$unidade) {
            return $this->notFound('Unidade nao encontrada');
        }

        try {
            $transactionStarted = false;
            $data = $this->request->getJsonBody();

            if (isset($data['nome'])) {
                $unidade->setNome($data['nome']);
            }
            if (isset($data['sigla'])) {
                $unidade->setSigla($data['sigla']);
            }
            if (isset($data['descricao'])) {
                $unidade->setDescricao($data['descricao']);
            }
            if (isset($data['endereco'])) {
                $unidade->setEndereco($data['endereco']);
            }
            if (isset($data['email'])) {
                $unidade->setEmail($data['email']);
            }
            if (isset($data['telefone'])) {
                $unidade->setTelefone($data['telefone']);
            }
            if (isset($data['responsavel_id'])) {
                $unidade->setResponsavelId($data['responsavel_id']);
            }
            if (isset($data['ativa'])) {
                $unidade->setAtiva($this->normalizeBool($data['ativa']));
            }
            if (isset($data['pode_criar_projetos'])) {
                $unidade->setPodeCriarProjetos($this->normalizeBool($data['pode_criar_projetos']));
            }
            if (isset($data['pode_criar_programas'])) {
                $unidade->setPodeCriarProgramas($this->normalizeBool($data['pode_criar_programas']));
            }

            $this->db->beginTransaction();
            $transactionStarted = true;
            $this->unidadeRepo->save($unidade);
            $synced = $this->unidadeCompanySync->ensureCompanyForUnidade($id, $unidade->getNome());
            if (!$synced) {
                $this->db->rollback();
                return $this->error('Falha ao sincronizar unidade com companies legadas', 500);
            }
            $this->db->commit();

            return $this->json([
                'message' => 'Unidade atualizada com sucesso',
                'data' => $unidade->toArray(),
            ]);
        } catch (\Throwable $e) {
            if ($transactionStarted) {
                $this->db->rollback();
            }
            Logger::error('Erro ao atualizar unidade', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'unidade_id' => $id,
            ]);
            return $this->json(['error' => 'Erro ao atualizar unidade'], 500);
        }
    }

    /**
     * DELETE /api/v1/admin/unidades/{id}
     */
    public function deleteUnidade(int $id): Response
    {
        try {
            $unidade = $this->unidadeRepo->find($id);

            if (!$unidade) {
                return $this->notFound('Unidade nao encontrada');
            }

            $filhas = $this->unidadeRepo->findFilhas($id);
            if (!empty($filhas)) {
                return $this->validationError([
                    'unidade' => 'Unidade possui sub-unidades. Remova ou mova as filhas antes de excluir.',
                ]);
            }

            $unidade->setAtiva(false);
            $this->unidadeRepo->save($unidade);

            return $this->json([
                'message' => 'Unidade removida com sucesso',
            ]);
        } catch (\Throwable $e) {
            Logger::error('Erro ao remover unidade', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            // Se a unidade ja nao existe, considera a remocao bem-sucedida
            try {
                $exists = $this->unidadeRepo->find($id);
                if (!$exists) {
                    return $this->json([
                        'message' => 'Unidade removida com sucesso',
                    ]);
                }
            } catch (\Throwable $inner) {
                // Ignora erro adicional e retorna falha generica
            }

            return $this->error('Falha ao remover unidade', 500);
        }
    }

    /**
     * PUT /api/v1/admin/unidades/{id}/mover
     */
    public function moverUnidade(int $id): Response
    {
        $data = $this->request->getJsonBody();

        if (!isset($data['pai_id'])) {
            return $this->validationError(['pai_id' => 'Novo pai e obrigatorio (ou null para raiz)']);
        }

        try {
            $paiId = $data['pai_id'];
            if ($paiId === null || $paiId === '') {
                $paiId = null;
            } else {
                $paiId = (int) $paiId;
            }

            $this->unidadeRepo->mover($id, $paiId);

            return $this->json([
                'message' => 'Unidade movida com sucesso',
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->validationError(['pai_id' => $e->getMessage()]);
        }
    }

    /**
     * GET /api/v1/admin/unidades/{id}/subordinadas
     */
    public function getSubordinadas(int $id): Response
    {
        $descendentes = $this->unidadeRepo->findTodosDescendentesIds($id);

        return $this->json([
            'unidade_id' => $id,
            'total' => count($descendentes),
            'ids' => $descendentes,
        ]);
    }

    // ===========================================
    // VINCULOS USUARIO-UNIDADE
    // ===========================================

    /**
     * GET /api/v1/admin/vinculos
     */
    public function listVinculos(): Response
    {
        $userId = $this->request->getQuery('user_id');
        $unidadeId = $this->request->getQuery('unidade_id');

        if ($userId) {
            $vinculos = $this->vinculoRepo->findByUsuario((int) $userId);
        } elseif ($unidadeId) {
            $vinculos = $this->vinculoRepo->findByUnidade((int) $unidadeId);
        } else {
            return $this->validationError(['user_id' => 'user_id ou unidade_id e obrigatorio']);
        }

        return $this->json([
            'data' => $vinculos,
        ]);
    }

    /**
     * POST /api/v1/admin/vinculos
     */
    public function createVinculo(): Response
    {
        $data = $this->request->getJsonBody();

        if (empty($data['user_id'])) {
            return $this->validationError(['user_id' => 'Usuario e obrigatorio']);
        }
        if (empty($data['unidade_id'])) {
            return $this->validationError(['unidade_id' => 'Unidade e obrigatoria']);
        }
        if (empty($data['role'])) {
            return $this->validationError(['role' => 'Role e obrigatoria']);
        }

        $vinculoId = $this->vinculoRepo->criarVinculo($data);
        if ($vinculoId <= 0) {
            $dbError = $this->db->getError();
            $fallback = $this->vinculoRepo->findByUsuarioEUnidade((int) $data['user_id'], (int) $data['unidade_id']);
            if ($fallback) {
                $vinculoId = (int) $fallback['vinculo_id'];
            } else {
                return $this->error('Falha ao criar vinculo' . ($dbError ? ': ' . $dbError : ''), 500);
            }
        }

        $historico = new HistoricoMovimentacaoEntity();
        $historico->setUserId($data['user_id']);
        $historico->setUnidadeDestinoId($data['unidade_id']);
        $historico->setCargoNovo($data['cargo'] ?? null);
        $historico->setTipoMovimentacao(HistoricoMovimentacaoEntity::TIPO_TRANSFERENCIA);
        $historico->setDataMovimentacao($data['data_inicio'] ?? date('Y-m-d'));
        $historico->setObservacao('Vinculo criado via admin');
        $this->historicoRepo->save($historico);
        $this->syncUserCompanyFromPrincipalVinculo((int) $data['user_id']);

        return $this->json([
            'message' => 'Vinculo criado com sucesso',
            'data' => $this->vinculoRepo->findById($vinculoId),
        ], 201);
    }

    /**
     * PUT /api/v1/admin/vinculos/{id}
     */
    public function updateVinculo(int $id): Response
    {
        $vinculo = $this->vinculoRepo->findById($id);

        if (!$vinculo) {
            return $this->notFound('Vinculo nao encontrado');
        }

        $data = $this->request->getJsonBody();
        $this->vinculoRepo->atualizarVinculo($id, $data);
        $updated = $this->vinculoRepo->findById($id);
        if (!empty($updated['vinculo_user_id'])) {
            $this->syncUserCompanyFromPrincipalVinculo((int) $updated['vinculo_user_id']);
        }

        return $this->json([
            'message' => 'Vinculo atualizado com sucesso',
            'data' => $this->vinculoRepo->findById($id),
        ]);
    }

    /**
     * DELETE /api/v1/admin/vinculos/{id}
     */
    public function deleteVinculo(int $id): Response
    {
        $vinculo = $this->vinculoRepo->findById($id);

        if (!$vinculo) {
            return $this->notFound('Vinculo nao encontrado');
        }

        $this->vinculoRepo->desativarVinculo($id);

        $historico = new HistoricoMovimentacaoEntity();
        $historico->setUserId($vinculo['vinculo_user_id']);
        $historico->setUnidadeOrigemId($vinculo['vinculo_unidade_id']);
        $historico->setCargoAnterior($vinculo['vinculo_cargo']);
        $historico->setTipoMovimentacao(HistoricoMovimentacaoEntity::TIPO_EXONERACAO);
        $historico->setDataMovimentacao(date('Y-m-d'));
        $historico->setObservacao('Vinculo desativado via admin');
        $this->historicoRepo->save($historico);
        $this->syncUserCompanyFromPrincipalVinculo((int) $vinculo['vinculo_user_id']);

        return $this->json([
            'message' => 'Vinculo desativado com sucesso',
        ]);
    }

    /**
     * PUT /api/v1/admin/vinculos/{id}/principal
     */
    public function definirPrincipal(int $id): Response
    {
        $vinculo = $this->vinculoRepo->findById($id);
        if (!$vinculo) {
            return $this->notFound('Vinculo nao encontrado');
        }

        $this->vinculoRepo->definirPrincipal($id);
        $this->syncUserCompanyFromPrincipalVinculo((int) $vinculo['vinculo_user_id']);

        return $this->json([
            'message' => 'Vinculo definido como principal',
        ]);
    }

    // ===========================================
    // USUARIOS
    // ===========================================

    /**
     * GET /api/v1/admin/usuarios
     */
    public function listUsuarios(): Response
    {
        $query = (string) ($this->request->getQuery('q') ?? '');
        $includeInactive = $this->normalizeBool($this->request->getQuery('include_inactive', false));

        if ($query !== '') {
            $users = $this->userService->searchUsers($query);
        } else {
            if ($includeInactive) {
                $table = $this->db->table('users');
                $sql = "SELECT u.*, c.contact_first_name, c.contact_last_name, c.contact_email\n                        FROM {$table} u\n                        LEFT JOIN dotp_contacts c ON c.contact_id = u.user_contact\n                        ORDER BY u.user_username";
                $rows = $this->db->fetchAll($sql);
                $users = array_map(fn($row) => $this->userRepo->hydrateRow($row), $rows);
            } else {
                $users = $this->userRepo->findActive();
            }
        }

        return $this->json([
            'data' => array_map(fn($u) => $u->toArray(), $users),
        ]);
    }

    /**
     * GET /api/v1/admin/usuarios/{id}
     */
    public function getUsuario(int $id): Response
    {
        $user = $this->userRepo->find($id);

        if (!$user) {
            return $this->notFound('Usuario nao encontrado');
        }

        $data = $user->toArray();
        $data['vinculos'] = $this->vinculoRepo->findByUsuario($id);

        return $this->json([
            'data' => $data,
        ]);
    }

    /**
     * POST /api/v1/admin/usuarios
     */
    public function createUsuario(): Response
    {
        $data = $this->request->getJsonBody();

        if (empty($data['user_username'])) {
            return $this->validationError(['user_username' => 'Username e obrigatorio']);
        }
        if (!array_key_exists('user_password', $data) || trim((string) $data['user_password']) === '') {
            return $this->validationError(['user_password' => 'Senha e obrigatoria']);
        }

        $currentUserId = $this->getUserId() ?? 0;

        try {
            $user = $this->userService->createUser($data, $currentUserId);
        } catch (\InvalidArgumentException $e) {
            return $this->validationError(['user' => $e->getMessage()]);
        }

        if (!$user) {
            $dbError = $this->db->getError();
            $message = 'Falha ao criar usuario';
            if (!empty($dbError)) {
                $message .= ': ' . $dbError;
            }
            return $this->error($message, 500);
        }

        return $this->json([
            'message' => 'Usuario criado com sucesso',
            'data' => $user->toArray(),
        ], 201);
    }

    /**
     * PUT /api/v1/admin/usuarios/{id}
     */
    public function updateUsuario(int $id): Response
    {
        $data = $this->request->getJsonBody();
        $data['id'] = $id;

        try {
            $user = $this->userService->updateUser($id, $data);
        } catch (\InvalidArgumentException $e) {
            return $this->validationError(['user' => $e->getMessage()]);
        }

        if (!$user) {
            $dbError = $this->db->getError();
            $message = 'Usuario nao encontrado';
            if (!empty($dbError)) {
                $message .= ': ' . $dbError;
            }
            return $this->notFound($message);
        }

        return $this->json([
            'message' => 'Usuario atualizado com sucesso',
            'data' => $user->toArray(),
        ]);
    }

    /**
     * DELETE /api/v1/admin/usuarios/{id}
     */
    public function deleteUsuario(int $id): Response
    {
        $user = $this->userRepo->find($id);
        $currentUserId = $this->getUserId() ?? 0;

        if (!$user) {
            return $this->notFound('Usuario nao encontrado');
        }

        if ($id === $currentUserId) {
            return $this->validationError(['user' => 'Nao e permitido desativar o proprio usuario']);
        }

        // Soft-delete: try to ensure user_status exists to avoid hard-delete FK failures.
        if ($this->ensureUserStatusColumn()) {
            $updated = $this->db->update('users', ['user_status' => 1], "user_id = {$id}");
            if (!$updated) {
                $dbError = $this->db->getError();
                $message = 'Falha ao desativar usuario';
                if (!empty($dbError)) {
                    $message .= ': ' . $dbError;
                }
                return $this->error($message, 500);
            }

            // Deactivate active vínculos to keep scope and dashboards consistent.
            $this->vinculoRepo->removerTodosVinculos($id);

            // Invalidate both legacy and repository cache namespaces.
            $this->cache->invalidate('users:*');
            $this->cache->invalidate('DotProject.Repository.UserRepository:dotp_users:*');

            return $this->json([
                'message' => 'Usuario desativado com sucesso',
            ]);
        }

        try {
            $deleted = $this->userService->deleteUser($id, $currentUserId);
        } catch (\InvalidArgumentException $e) {
            return $this->validationError(['user' => $e->getMessage()]);
        }

        if (!$deleted) {
            $dbError = $this->db->getError();
            $message = 'Falha ao excluir usuario';
            if (!empty($dbError)) {
                $message .= ': ' . $dbError;
            }
            return $this->error($message, 500);
        }

        return $this->json([
            'message' => 'Usuario excluido com sucesso',
        ]);
    }

    // ===========================================
    // MATRIZ DE PERMISSOES
    // ===========================================

    /**
     * GET /api/v1/admin/permissoes/matriz
     */
    public function getMatrizPermissoes(): Response
    {
        $matriz = $this->nivelRepo->getMatrizPermissoes();

        return $this->json([
            'data' => $matriz,
        ]);
    }

    /**
     * PUT /api/v1/admin/permissoes/matriz
     */
    public function updateMatrizPermissoes(): Response
    {
        $data = $this->request->getJsonBody();

        if (empty($data['nivel_id']) || empty($data['permissoes'])) {
            return $this->validationError(['nivel_id' => 'nivel_id e permissoes sao obrigatorios']);
        }

        $this->nivelRepo->atualizarPermissoes($data['nivel_id'], $data['permissoes']);

        return $this->json([
            'message' => 'Permissoes atualizadas com sucesso',
        ]);
    }

    /**
     * @param UnidadeOrganizacionalEntity[] $unidades
     * @return UnidadeOrganizacionalEntity[]
     */
    private function filterUnidadesByEscopo(array $unidades): array
    {
        $userId = $this->getUserId();
        if ($userId === null) {
            return [];
        }

        $auth = AuthorizationService::getInstance();
        if ($auth->isAdmin($userId)) {
            return $unidades;
        }

        $perm = new PermissionService();
        $escopo = $perm->getEscopoDados($userId);
        if (!$escopo) {
            return [];
        }

        if (in_array(
            $escopo['role'] ?? '',
            [PermissionService::ROLE_PREFEITO, PermissionService::ROLE_CONTROLADOR],
            true
        )) {
            return $unidades;
        }

        $allowedIds = array_map('intval', (array) ($escopo['unidades_escopo'] ?? []));
        if (empty($allowedIds)) {
            return [];
        }

        $allowedLookup = array_fill_keys($allowedIds, true);
        return array_values(array_filter($unidades, static function (UnidadeOrganizacionalEntity $unidade) use ($allowedLookup): bool {
            $id = (int) ($unidade->getId() ?? 0);
            return isset($allowedLookup[$id]);
        }));
    }

    private function syncUserCompanyFromPrincipalVinculo(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $principal = $this->vinculoRepo->findPrincipal($userId);
        $companyId = null;
        if ($principal && !empty($principal['vinculo_unidade_id'])) {
            $companyId = (int) $principal['vinculo_unidade_id'];
        }

        $this->db->update(
            'users',
            ['user_company' => $companyId],
            sprintf('user_id = %d', $userId)
        );
    }

    // ===========================================
    // PRIVATE VALIDATION
    // ===========================================

    private function validateNivel(array $data): array
    {
        $errors = [];

        if (empty($data['ordem']) || !is_numeric($data['ordem'])) {
            $errors['ordem'] = 'Ordem e obrigatoria e deve ser um numero';
        }

        if (empty($data['nome']) || !is_string($data['nome'])) {
            $errors['nome'] = 'Nome e obrigatorio';
        }

        return $errors;
    }

    private function validateUnidade(array $data): array
    {
        $errors = [];

        if (empty($data['nome'])) {
            $errors['nome'] = 'Nome e obrigatorio';
        }

        if (!isset($data['nivel']) || $data['nivel'] === '' || !is_numeric($data['nivel'])) {
            $errors['nivel'] = 'Nivel e obrigatorio e deve ser um numero inteiro';
        }

        return $errors;
    }

    private function normalizeBool(mixed $value, bool $default = false): bool
    {
        if ($value === null) {
            return $default;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        return $parsed ?? $default;
    }

    protected function ensureUserStatusColumn(): bool
    {
        $usersTable = $this->db->table('users');
        $exists = (int) ($this->db->fetchValue(
            "SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND column_name = 'user_status'",
            [$usersTable]
        ) ?? 0);

        if ($exists > 0) {
            return true;
        }

        try {
            $this->db->execute(
                sprintf(
                    "ALTER TABLE `%s` ADD COLUMN user_status TINYINT(1) NOT NULL DEFAULT 0 AFTER user_department",
                    $usersTable
                )
            );
        } catch (\Throwable $e) {
            return false;
        }

        try {
            $this->db->execute(sprintf(
                "UPDATE `%s` SET user_status = 0 WHERE user_status IS NULL",
                $usersTable
            ));

            $idxExists = (int) ($this->db->fetchValue(
                "SELECT COUNT(*)
                 FROM information_schema.statistics
                 WHERE table_schema = DATABASE()
                   AND table_name = ?
                   AND index_name = 'idx_user_status'",
                [$usersTable]
            ) ?? 0);

            if ($idxExists === 0) {
                $this->db->execute(sprintf(
                    "ALTER TABLE `%s` ADD INDEX idx_user_status (user_status)",
                    $usersTable
                ));
            }
        } catch (\Throwable $e) {
            // Column was created; index/backfill can be handled by migration later.
        }

        $existsAfter = (int) ($this->db->fetchValue(
            "SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND column_name = 'user_status'",
            [$usersTable]
        ) ?? 0);

        return $existsAfter > 0;
    }
}
