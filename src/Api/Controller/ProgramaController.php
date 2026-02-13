<?php
/**
 * Programa Controller
 *
 * Controller para gerenciamento de programas do PPA.
 *
 * @package DotProject\Api\Controller
 */

declare(strict_types=1);

namespace DotProject\Api\Controller;

use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Service\ProgramaService;

class ProgramaController extends BaseController
{
    private ProgramaService $service;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->service = new ProgramaService();
    }

    /**
     * Listar programas
     */
    public function index(): Response
    {
        try {
            $filters = [
                'estado' => $this->request->getQueryParam('estado'),
                'unidade_id' => $this->request->getQueryParam('unidade_id'),
                'ppa_id' => $this->request->getQueryParam('ppa_id'),
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
     * Obter programa por ID
     */
    public function show(): Response
    {
        try {
            $id = (int) $this->request->getParam('id');
            $programa = $this->service->find($id);
            if ($programa === null) {
                return $this->notFound('Programa não encontrado');
            }

            return $this->json(['data' => $programa]);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), Response::HTTP_INTERNAL_ERROR);
        }
    }

    /**
     * Criar programa
     */
    public function store(): Response
    {
        try {
            $created = $this->service->create($this->request->getJsonBody());

            return $this->created([
                'message' => 'Programa criado com sucesso',
                'data' => $created,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->validationError(['programa' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), Response::HTTP_INTERNAL_ERROR);
        }
    }

    /**
     * Atualizar programa
     */
    public function update(): Response
    {
        try {
            $id = (int) $this->request->getParam('id');
            $updated = $this->service->update($id, $this->request->getJsonBody());
            if ($updated === null) {
                return $this->notFound('Programa não encontrado');
            }

            return $this->json([
                'message' => 'Programa atualizado com sucesso',
                'data' => $updated,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->validationError(['programa' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), Response::HTTP_INTERNAL_ERROR);
        }
    }

    /**
     * Excluir programa
     */
    public function destroy(): Response
    {
        try {
            $id = (int) $this->request->getParam('id');
            if (!$this->service->delete($id)) {
                return $this->notFound('Programa não encontrado');
            }

            return $this->json(['message' => 'Programa removido com sucesso']);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), Response::HTTP_INTERNAL_ERROR);
        }
    }
}

