<?php
/**
 * Controller for Organizational Units (Unidades Organizacionais).
 *
 * @package DotProject\Api\Controller\Admin
 */

declare(strict_types=1);

namespace DotProject\Api\Controller\Admin;

use DotProject\Api\Controller\BaseController;
use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Entity\UnidadeOrganizacionalEntity;
use DotProject\Repository\UnidadeOrganizacionalRepository;
use DotProject\Repository\UsuarioUnidadeRepository;
use DotProject\Core\Logger;
use DotProject\Service\AuthorizationService;
use DotProject\Service\PermissionService;
use DotProject\Service\UnidadeCompanySyncService;

class UnidadeController extends BaseController
{
    private UnidadeOrganizacionalRepository $unidadeRepo;
    private UsuarioUnidadeRepository $vinculoRepo;
    private UnidadeCompanySyncService $unidadeCompanySync;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->unidadeRepo = new UnidadeOrganizacionalRepository();
        $this->vinculoRepo = new UsuarioUnidadeRepository();
        $this->unidadeCompanySync = new UnidadeCompanySyncService($this->db);
        Logger::debug('UnidadeController inicializado');
    }

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

            try {
                $exists = $this->unidadeRepo->find($id);
                if (!$exists) {
                    return $this->json([
                        'message' => 'Unidade removida com sucesso',
                    ]);
                }
            } catch (\Throwable $inner) {
                // Ignora erro adicional
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
    // PRIVATE HELPERS
    // ===========================================

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

        if (
            in_array(
                $escopo['role'] ?? '',
                [PermissionService::ROLE_PREFEITO, PermissionService::ROLE_CONTROLADOR],
                true
            )
        ) {
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
}
