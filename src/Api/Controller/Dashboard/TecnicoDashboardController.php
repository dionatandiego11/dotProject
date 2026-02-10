<?php
/**
 * Dashboard do Técnico — Visão de Tarefas.
 *
 * @package DotProject\Api\Controller\Dashboard
 */

declare(strict_types=1);

namespace DotProject\Api\Controller\Dashboard;

use DotProject\Api\Controller\BaseController;
use DotProject\Api\Request;
use DotProject\Api\Response;

class TecnicoDashboardController extends BaseController
{
    /**
     * GET /api/v1/dashboard/tecnico
     */
    public function tecnico(): Response
    {
        $userId = $this->getUserId();

        $cacheKey = $this->cacheKey('dashboard', 'tecnico', (string) $userId);
        $cached = $this->cache->get($cacheKey);

        if ($cached) {
            return $this->json($cached);
        }

        $data = $this->getData($userId);

        $this->cache->set($cacheKey, $data, 300);

        return $this->json(['data' => $data]);
    }

    private function getData(int $userId): array
    {
        $db = \DotProject\Core\Database::getInstance();

        $resumo = $db->fetchAll(
            "SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN task_percent_complete >= 100 THEN 1 END) as concluidas,
                COUNT(CASE WHEN task_percent_complete < 100 THEN 1 END) as pendentes,
                COUNT(CASE WHEN task_end_date < CURDATE() AND task_percent_complete < 100 THEN 1 END) as atrasadas
            FROM dotp_tasks 
            WHERE task_owner = ?",
            [$userId]
        )[0] ?? [];

        $tarefas = $db->fetchAll(
            "SELECT 
                t.task_id as id,
                t.task_name as nome,
                t.task_priority as prioridade,
                t.task_percent_complete as progresso,
                t.task_end_date as prazo,
                p.project_name as projeto,
                CASE 
                    WHEN t.task_end_date < CURDATE() AND t.task_percent_complete < 100 THEN 'atrasada'
                    WHEN DATEDIFF(t.task_end_date, CURDATE()) <= 3 THEN 'urgente'
                    ELSE 'normal'
                END as urgencia
            FROM dotp_tasks t
            LEFT JOIN dotp_projects p ON p.project_id = t.task_project
            WHERE t.task_owner = ?
            AND t.task_percent_complete < 100
            ORDER BY 
                CASE WHEN t.task_end_date < CURDATE() THEN 0 
                     WHEN DATEDIFF(t.task_end_date, CURDATE()) <= 3 THEN 1 
                     ELSE 2 END,
                t.task_priority DESC,
                t.task_end_date ASC
            LIMIT 20",
            [$userId]
        );

        $concluidasSemana = $db->fetchAll(
            "SELECT 
                task_id as id,
                task_name as nome,
                task_end_date as data_conclusao
            FROM dotp_tasks 
            WHERE task_owner = ?
            AND task_percent_complete >= 100
            AND task_end_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            ORDER BY task_end_date DESC
            LIMIT 10",
            [$userId]
        );

        $projetos = $db->fetchAll(
            "SELECT DISTINCT
                p.project_id as id,
                p.project_name as nome,
                p.project_percent_complete as percent_execucao,
                COUNT(t.task_id) as minhas_tarefas,
                COUNT(CASE WHEN t.task_percent_complete >= 100 THEN 1 END) as tarefas_concluidas
            FROM dotp_projects p
            JOIN dotp_tasks t ON t.task_project = p.project_id
            WHERE t.task_owner = ?
            GROUP BY p.project_id
            ORDER BY p.project_name",
            [$userId]
        );

        $produtividade = $db->fetchAll(
            "SELECT 
                DATE(task_end_date) as data,
                COUNT(*) as tarefas_concluidas
            FROM dotp_tasks 
            WHERE task_owner = ?
            AND task_percent_complete >= 100
            AND task_end_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(task_end_date)
            ORDER BY data DESC",
            [$userId]
        );

        return [
            'perfil' => 'tecnico',
            'source' => 'base_tables',
            'resumo' => [
                'total' => (int) ($resumo['total'] ?? 0),
                'concluidas' => (int) ($resumo['concluidas'] ?? 0),
                'pendentes' => (int) ($resumo['pendentes'] ?? 0),
                'bloqueadas' => 0,
                'atrasadas' => (int) ($resumo['atrasadas'] ?? 0),
            ],
            'tarefas_prioritarias' => $tarefas,
            'concluidas_semana' => $concluidasSemana,
            'projetos' => $projetos,
            'produtividade_30d' => $produtividade,
        ];
    }
}
