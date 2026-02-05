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
use DotProject\Dto\ApiResponse;

class ProgramaController extends BaseController
{
    /**
     * Listar programas
     */
    public function index(): void
    {
        try {
            $programas = [
                ['id' => 1, 'nome' => 'Programa de Mobilidade Urbana', 'status' => 'ativo'],
                ['id' => 2, 'nome' => 'Programa de Saúde Pública', 'status' => 'ativo'],
            ];
            
            $this->response->json(ApiResponse::success($programas)->toArray())->send();
        } catch (\Exception $e) {
            $this->response->error($e->getMessage(), 500)->send();
        }
    }
    
    /**
     * Obter programa por ID
     */
    public function show(): void
    {
        try {
            $id = (int) $this->request->getParam('id');
            $programa = ['id' => $id, 'nome' => 'Programa Exemplo', 'status' => 'ativo'];
            
            $this->response->json(ApiResponse::success($programa)->toArray())->send();
        } catch (\Exception $e) {
            $this->response->error($e->getMessage(), 500)->send();
        }
    }
    
    /**
     * Criar programa
     */
    public function store(): void
    {
        try {
            $data = $this->request->getJsonBody();
            
            $this->response->json(ApiResponse::success(['id' => 1, 'message' => 'Programa criado'])->toArray(), 201)->send();
        } catch (\Exception $e) {
            $this->response->error($e->getMessage(), 500)->send();
        }
    }
    
    /**
     * Atualizar programa
     */
    public function update(): void
    {
        try {
            $id = (int) $this->request->getParam('id');
            $data = $this->request->getJsonBody();
            
            $this->response->json(ApiResponse::success(['id' => $id, 'message' => 'Programa atualizado'])->toArray())->send();
        } catch (\Exception $e) {
            $this->response->error($e->getMessage(), 500)->send();
        }
    }
}
