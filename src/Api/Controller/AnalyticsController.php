<?php
/**
 * Analytics Controller
 * 
 * Controller para endpoints de analytics e métricas.
 * 
 * @package DotProject\Api\Controller
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Api\Controller;

use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Dto\ApiResponse;
use DotProject\Service\AnalyticsService;

/**
 * Controller de Analytics
 */
class AnalyticsController extends BaseController
{
    private AnalyticsService $analyticsService;
    
    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->analyticsService = new AnalyticsService();
    }
    
    /**
     * GET /v1/analytics/dashboard
     * Resumo do dashboard
     */
    public function dashboard(): void
    {
        try {
            $userId = $this->getCurrentUserId();
            
            $summary = $this->analyticsService->getDashboardSummary($userId);
            
            $this->response->json(ApiResponse::success($summary)->toArray())->send();
            
        } catch (\Exception $e) {
            $this->response->error($e->getMessage(), 500)->send();
        }
    }
    
    /**
     * GET /v1/analytics/productivity
     * Gráfico de produtividade
     */
    public function productivity(): void
    {
        try {
            $days = (int) $this->request->getQueryParam('days', 7);
            $days = min(30, max(7, $days));
            
            $data = $this->analyticsService->getProductivityTrend($days);
            
            $this->response->json(ApiResponse::success(['data' => $data])->toArray())->send();
            
        } catch (\Exception $e) {
            $this->response->error($e->getMessage(), 500)->send();
        }
    }
    
    private function getCurrentUserId(): ?int
    {
        return $this->request->getParam('_user_id');
    }
}
