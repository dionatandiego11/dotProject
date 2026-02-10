<?php
/**
 * Controller for Hierarchical Levels (Níveis Hierárquicos).
 *
 * @package DotProject\Api\Controller\Admin
 */

declare(strict_types=1);

namespace DotProject\Api\Controller\Admin;

use DotProject\Api\Controller\BaseController;
use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Entity\NivelHierarquicoEntity;
use DotProject\Repository\NivelHierarquicoRepository;
use DotProject\Core\Logger;

class NivelController extends BaseController
{
    private NivelHierarquicoRepository $nivelRepo;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->nivelRepo = new NivelHierarquicoRepository();
        Logger::debug('NivelController inicializado');
    }

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
}
