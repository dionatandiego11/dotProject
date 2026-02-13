<?php
/**
 * Dashboard Router controller — determines the user profile and dispatches.
 *
 * @package DotProject\Api\Controller\Dashboard
 */

declare(strict_types=1);

namespace DotProject\Api\Controller\Dashboard;

use DotProject\Api\Controller\BaseController;
use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Service\PermissionService;

class DashboardRouterController extends BaseController
{
    private ?PermissionService $permissionService = null;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->permissionService = new PermissionService();
    }

    /**
     * GET /api/v1/dashboard
     * Routes to the appropriate profile dashboard.
     */
    public function index(): Response
    {
        $userId = $this->getUserId();
        if ($userId === null) {
            return $this->response->unauthorized();
        }

        $perfil = $this->permissionService->getDashboardType($userId);
        return match ($perfil) {
            'prefeito' => (new PrefeitoDashboardController($this->request, $this->response))->prefeito(),
            'secretario' => (new SecretarioDashboardController($this->request, $this->response))->secretario(),
            'coordenador' => (new CoordenadorDashboardController($this->request, $this->response))->coordenador(),
            'controlador' => (new ControladorDashboardController($this->request, $this->response))->controlador(),
            default => (new TecnicoDashboardController($this->request, $this->response))->tecnico(),
        };
    }

    /**
     * GET /api/v1/dashboard/status
     */
    public function status(): Response
    {
        $userId = $this->getUserId();
        $perfil = $userId !== null
            ? $this->permissionService->getDashboardType($userId)
            : null;

        return $this->json([
            'status' => 'ok',
            'perfil' => $perfil,
            'modern_tables' => (new class ($this->request, $this->response) extends BaseController{
            use DashboardHelperTrait;
            public function check(): bool
            {
                return $this->checkModernTables(); }
            })->check(),
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }
}
