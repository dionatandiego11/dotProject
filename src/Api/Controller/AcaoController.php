<?php
/**
 * Acao Controller
 *
 * Controller para gerenciamento de acoes do PPA.
 *
 * @package DotProject\Api\Controller
 */

declare(strict_types=1);

namespace DotProject\Api\Controller;

use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Service\AcaoService;

class AcaoController extends BaseController
{
    private AcaoService $service;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->service = new AcaoService();
    }

    /**
     * Listar acoes
     */
    public function index(): Response
    {
        try {
            $filters = [
                'programa_id' => $this->request->getQueryParam('programa_id'),
                'ppa_id' => $this->request->getQueryParam('ppa_id'),
                'estado' => $this->request->getQueryParam('estado'),
                'search' => $this->request->getQueryParam('search'),
            ];

            return $this->json([
                'data' => $this->service->list($filters),
            ]);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), Response::HTTP_INTERNAL_ERROR);
        }
    }

    /**
     * Obter acao por ID
     */
    public function show(): Response
    {
        try {
            $id = (int) $this->request->getParam('id');
            $acao = $this->service->find($id);
            if ($acao === null) {
                return $this->notFound('Acao nao encontrada');
            }

            return $this->json(['data' => $acao]);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), Response::HTTP_INTERNAL_ERROR);
        }
    }

    /**
     * Criar acao
     */
    public function store(): Response
    {
        try {
            $created = $this->service->create($this->request->getJsonBody());

            return $this->created([
                'message' => 'Acao criada com sucesso',
                'data' => $created,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->validationError(['acao' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), Response::HTTP_INTERNAL_ERROR);
        }
    }

    /**
     * Atualizar acao
     */
    public function update(): Response
    {
        try {
            $id = (int) $this->request->getParam('id');
            $updated = $this->service->update($id, $this->request->getJsonBody());
            if ($updated === null) {
                return $this->notFound('Acao nao encontrada');
            }

            return $this->json([
                'message' => 'Acao atualizada com sucesso',
                'data' => $updated,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->validationError(['acao' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), Response::HTTP_INTERNAL_ERROR);
        }
    }

    /**
     * Excluir acao
     */
    public function destroy(): Response
    {
        try {
            $id = (int) $this->request->getParam('id');
            if (!$this->service->delete($id)) {
                return $this->notFound('Acao nao encontrada');
            }

            return $this->json(['message' => 'Acao removida com sucesso']);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), Response::HTTP_INTERNAL_ERROR);
        }
    }
}
