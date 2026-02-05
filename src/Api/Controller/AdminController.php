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
use DotProject\Service\UserService;

class AdminController extends BaseController
{
    private NivelHierarquicoRepository $nivelRepo;
    private UnidadeOrganizacionalRepository $unidadeRepo;
    private UsuarioUnidadeRepository $vinculoRepo;
    private HistoricoMovimentacaoRepository $historicoRepo;
    private UserRepository $userRepo;
    private UserService $userService;

    public function __construct(Request $request, Response $response)
    {
        error_log('DEBUG AdminController - Construtor chamado');
        parent::__construct($request, $response);
        error_log('DEBUG AdminController - Criando NivelHierarquicoRepository...');
        $this->nivelRepo = new NivelHierarquicoRepository();
        error_log('DEBUG AdminController - Criando UnidadeOrganizacionalRepository...');
        $this->unidadeRepo = new UnidadeOrganizacionalRepository();
        error_log('DEBUG AdminController - Criando UsuarioUnidadeRepository...');
        $this->vinculoRepo = new UsuarioUnidadeRepository();
        error_log('DEBUG AdminController - Criando HistoricoMovimentacaoRepository...');
        $this->historicoRepo = new HistoricoMovimentacaoRepository();
        error_log('DEBUG AdminController - Criando UserRepository...');
        $this->userRepo = new UserRepository();
        error_log('DEBUG AdminController - Criando UserService...');
        $this->userService = new UserService();
        error_log('DEBUG AdminController - Construtor concluido');
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
            error_log('ERRO createNivel: ' . $e->getMessage());
            error_log('Trace: ' . $e->getTraceAsString());
            return $this->json([
                'error' => 'Erro ao criar nivel: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
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
        $arvore = $this->request->getQuery('arvore', false);
        $nivel = $this->request->getQuery('nivel');

        if ($arvore) {
            $unidades = $this->unidadeRepo->findArvore();
            return $this->json([
                'data' => array_map(fn($u) => $u->toArray(true), $unidades),
            ]);
        }

        if ($nivel) {
            $unidades = $this->unidadeRepo->findByNivel((int) $nivel);
        } else {
            $unidades = $this->unidadeRepo->findAllAtivas();
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
        try {
            $data = $this->request->getJsonBody();

            error_log('DEBUG createUnidade - Dados recebidos: ' . json_encode($data));

            $errors = $this->validateUnidade($data);
            if (!empty($errors)) {
                error_log('DEBUG createUnidade - Erros de validacao: ' . json_encode($errors));
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
            $unidade->setPodeCriarProjetos($data['pode_criar_projetos'] ?? true);
            $unidade->setPodeCriarProgramas($data['pode_criar_programas'] ?? false);

            error_log('DEBUG createUnidade - Salvando unidade: ' . $unidade->getNome());

            $id = $this->unidadeRepo->save($unidade);
            $unidade->setId($id);

            error_log('DEBUG createUnidade - Unidade criada com ID: ' . $id);

            return $this->json([
                'message' => 'Unidade criada com sucesso',
                'data' => $unidade->toArray(),
            ], 201);
        } catch (\Throwable $e) {
            error_log('ERRO createUnidade: ' . $e->getMessage());
            error_log('Trace: ' . $e->getTraceAsString());
            return $this->json([
                'error' => 'Erro ao criar unidade: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
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
            $unidade->setAtiva((bool) $data['ativa']);
        }
        if (isset($data['pode_criar_projetos'])) {
            $unidade->setPodeCriarProjetos((bool) $data['pode_criar_projetos']);
        }
        if (isset($data['pode_criar_programas'])) {
            $unidade->setPodeCriarProgramas((bool) $data['pode_criar_programas']);
        }

        $this->unidadeRepo->save($unidade);

        return $this->json([
            'message' => 'Unidade atualizada com sucesso',
            'data' => $unidade->toArray(),
        ]);
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
            error_log('ERRO deleteUnidade: ' . $e->getMessage());
            error_log('Trace: ' . $e->getTraceAsString());

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
            $this->unidadeRepo->mover($id, $data['pai_id']);

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

        return $this->json([
            'message' => 'Vinculo desativado com sucesso',
        ]);
    }

    /**
     * PUT /api/v1/admin/vinculos/{id}/principal
     */
    public function definirPrincipal(int $id): Response
    {
        $this->vinculoRepo->definirPrincipal($id);

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
        $includeInactive = (bool) $this->request->getQuery('include_inactive', false);

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

        if (!$user) {
            return $this->notFound('Usuario nao encontrado');
        }

        // Soft-delete compatible with legacy schema
        if ($this->userRepo->supportsUserStatus()) {
            $updated = $this->db->update('users', ['user_status' => 1], "user_id = {$id}");
            if (!$updated) {
                $dbError = $this->db->getError();
                $message = 'Falha ao desativar usuario';
                if (!empty($dbError)) {
                    $message .= ': ' . $dbError;
                }
                return $this->error($message, 500);
            }
            return $this->json([
                'message' => 'Usuario desativado com sucesso',
            ]);
        }

        try {
            $deleted = $this->userService->deleteUser($id, $this->getUserId() ?? 0);
        } catch (\InvalidArgumentException $e) {
            return $this->validationError(['user' => $e->getMessage()]);
        }

        if (!$deleted) {
            return $this->error('Falha ao excluir usuario', 500);
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

        if (empty($data['nivel']) || !is_int($data['nivel'])) {
            $errors['nivel'] = 'Nivel e obrigatorio e deve ser um numero inteiro';
        }

        return $errors;
    }
}
