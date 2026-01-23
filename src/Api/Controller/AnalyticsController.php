<?php
/**
 * DotProject Analytics Controller
 * 
 * Controller for analytics endpoints.
 * 
 * @package DotProject\Api\Controller
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Api\Controller;

use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Service\AnalyticsService;
use DotProject\Service\ProjectService;

/**
 * Controller de analytics
 */
class AnalyticsController extends BaseController
{
    private AnalyticsService $analytics;
    private ProjectService $projectService;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->analytics = new AnalyticsService();
        $this->projectService = new ProjectService();
    }

    /**
     * GET /v1/analytics/dashboard
     * 
     * Returns dashboard summary with KPIs
     */
    public function dashboard(): Response
    {
        $userId = $this->request->getQueryParam('user_id');

        $summary = $this->analytics->getDashboardSummary(
            $userId ? (int) $userId : null
        );

        return $this->json($summary);
    }

    /**
     * GET /v1/analytics/projects-health
     * 
     * Returns health status of all active projects
     */
    public function projectsHealth(): Response
    {
        $projects = $this->analytics->getProjectsHealth();

        return $this->json([
            'projects' => $projects,
            'summary' => [
                'total' => count($projects),
                'healthy' => count(array_filter($projects, fn($p) => $p['health'] === 'healthy')),
                'at_risk' => count(array_filter($projects, fn($p) => $p['health'] === 'at_risk')),
                'critical' => count(array_filter($projects, fn($p) => $p['health'] === 'critical')),
            ],
        ]);
    }

    /**
     * GET /v1/analytics/completion-trend
     * 
     * Returns task completion trend over time
     */
    public function completionTrend(): Response
    {
        $days = (int) $this->request->getQueryParam('days', 30);
        $days = min(90, max(7, $days)); // Limit between 7-90 days

        $trend = $this->analytics->getCompletionTrend($days);

        return $this->json([
            'days' => $days,
            'trend' => $trend,
        ]);
    }

    /**
     * GET /v1/analytics/team-performance
     * 
     * Returns team performance metrics
     */
    public function teamPerformance(): Response
    {
        $team = $this->analytics->getTeamPerformance();

        return $this->json([
            'members' => $team,
            'total_members' => count($team),
        ]);
    }

    /**
     * GET /v1/analytics/velocity
     * 
     * Returns velocity (tasks per week)
     */
    public function velocity(): Response
    {
        $weeks = (int) $this->request->getQueryParam('weeks', 8);
        $weeks = min(26, max(4, $weeks)); // Limit between 4-26 weeks

        $velocity = $this->analytics->getVelocity($weeks);

        // Calculate average
        $totalCompleted = array_sum(array_column($velocity, 'completed'));
        $avgVelocity = count($velocity) > 0 ? round($totalCompleted / count($velocity), 1) : 0;

        return $this->json([
            'weeks' => $weeks,
            'velocity' => $velocity,
            'average' => $avgVelocity,
        ]);
    }

    /**
     * GET /v1/analytics/projects/{id}/burndown
     * 
     * Returns burndown chart data for a project
     */
    public function projectBurndown(): Response
    {
        $projectId = (int) $this->request->getParam('id');

        $burndown = $this->analytics->getProjectBurndown($projectId);

        if (empty($burndown)) {
            return $this->notFound('Project not found');
        }

        return $this->json($burndown);
    }

    /**
     * GET /v1/analytics/projects/{id}/statistics
     * 
     * Returns detailed statistics for a project
     */
    public function projectStatistics(): Response
    {
        $projectId = (int) $this->request->getParam('id');

        $stats = $this->projectService->getProjectStatistics($projectId);

        return $this->json($stats);
    }
}
