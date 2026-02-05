<?php
/**
 * PPA Controller
 * 
 * Controller para gerenciamento do PPA (Plano Plurianual).
 * 
 * @package DotProject\Api\Controller
 */

declare(strict_types=1);

namespace DotProject\Api\Controller;

use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Dto\ApiResponse;

class PpaController extends BaseController
{
    /**
     * Listar PPAs
     */
    public function index(): void
    {
        try {
            $ppas = [
                ['id' => 1, 'nome' => 'PPA 2024-2027', 'periodo' => '2024-2027', 'status' => 'Vigente'],
            ];
            
            $this->response->json(ApiResponse::success($ppas)->toArray())->send();
        } catch (\Exception $e) {
            $this->response->error($e->getMessage(), 500)->send();
        }
    }
    
    /**
     * Obter PPA por ID
     */
    public function show(): void
    {
        try {
            $id = (int) $this->request->getParam('id');
            $ppa = ['id' => $id, 'nome' => 'PPA 2024-2027', 'periodo' => '2024-2027', 'status' => 'Vigente'];
            
            $this->response->json(ApiResponse::success($ppa)->toArray())->send();
        } catch (\Exception $e) {
            $this->response->error($e->getMessage(), 500)->send();
        }
    }
    
    /**
     * Criar PPA
     */
    public function store(): void
    {
        try {
            $data = $this->request->getJsonBody();
            
            $this->response->json(ApiResponse::success(['id' => 1, 'message' => 'PPA criado'])->toArray(), 201)->send();
        } catch (\Exception $e) {
            $this->response->error($e->getMessage(), 500)->send();
        }
    }
}
