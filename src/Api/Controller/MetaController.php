<?php
/**
 * Meta Controller
 *
 * Controller para gerenciamento de Metas/Indicadores do PPA.
 * Metas são a FONTE oficial de valor_realizado (TCE).
 *
 * @package DotProject\Api\Controller
 */

declare(strict_types=1);

namespace DotProject\Api\Controller;

use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Service\MetaService;
use DotProject\Repository\MetaRepository;
use DotProject\Core\TenantContext;

class MetaController extends BaseController
{
    private MetaService $service;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->service = new MetaService(
            new MetaRepository($this->db, new TenantContext())
        );
    }

    /**
     * GET /v1/metas
     */
    public function index(): Response
    {
        try {
            $filters = [
                'acao_id' => $this->request->getQueryParam('acao_id'),
                'ano_referencia' => $this->request->getQueryParam('ano_referencia'),
                'search' => $this->request->getQueryParam('search'),
            ];

            return $this->json([
                'data' => $this->service->listar(array_filter($filters)),
            ]);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), Response::HTTP_INTERNAL_ERROR);
        }
    }

    /**
     * GET /v1/metas/{id}
     */
    public function show(): Response
    {
        try {
            $id = (int) $this->request->getParam('id');
            $meta = $this->service->buscarPorId($id);
            return $this->json(['data' => $meta]);
        } catch (\InvalidArgumentException $e) {
            return $this->notFound($e->getMessage());
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), Response::HTTP_INTERNAL_ERROR);
        }
    }

    /**
     * POST /v1/metas
     */
    public function store(): Response
    {
        try {
            $meta = $this->service->criar($this->request->getJsonBody());
            return $this->created([
                'message' => 'Meta criada com sucesso',
                'data' => $meta,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->validationError(['meta' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), Response::HTTP_INTERNAL_ERROR);
        }
    }

    /**
     * PUT /v1/metas/{id}
     */
    public function update(): Response
    {
        try {
            $id = (int) $this->request->getParam('id');
            $meta = $this->service->atualizar($id, $this->request->getJsonBody());
            return $this->json([
                'message' => 'Meta atualizada com sucesso',
                'data' => $meta,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->validationError(['meta' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), Response::HTTP_INTERNAL_ERROR);
        }
    }

    /**
     * DELETE /v1/metas/{id}
     */
    public function destroy(): Response
    {
        try {
            $id = (int) $this->request->getParam('id');
            $this->service->excluir($id);
            return $this->json(['message' => 'Meta removida com sucesso']);
        } catch (\InvalidArgumentException $e) {
            return $this->notFound($e->getMessage());
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), Response::HTTP_INTERNAL_ERROR);
        }
    }

    /**
     * GET /v1/metas/resumo/{acaoId}
     */
    public function resumo(): Response
    {
        try {
            $acaoId = (int) $this->request->getParam('acaoId');
            $resumo = $this->service->resumoPorAcao($acaoId);
            return $this->json(['data' => $resumo]);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), Response::HTTP_INTERNAL_ERROR);
        }
    }
}
