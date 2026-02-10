<?php
/**
 * Controller for Alertas (notifications).
 *
 * @package DotProject\Api\Controller\Dashboard
 */

declare(strict_types=1);

namespace DotProject\Api\Controller\Dashboard;

use DotProject\Api\Controller\BaseController;
use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Repository\AlertaRepository;

class AlertaDashboardController extends BaseController
{
    private ?AlertaRepository $alertaRepo = null;

    private function getAlertaRepo(): AlertaRepository
    {
        if ($this->alertaRepo === null) {
            $this->alertaRepo = new AlertaRepository();
        }
        return $this->alertaRepo;
    }

    /**
     * GET /api/v1/dashboard/alertas
     */
    public function alertas(): Response
    {
        $userId = $this->getUserId();
        $apenasNaoLidos = filter_var(
            $this->request->getQuery('nao_lidos', false),
            FILTER_VALIDATE_BOOL
        );
        $limit = (int) $this->request->getQuery('limit', 50);
        if ($limit <= 0) {
            $limit = 50;
        } elseif ($limit > 200) {
            $limit = 200;
        }

        try {
            $alertas = $this->getAlertaRepo()->findComDetalhes($userId, $apenasNaoLidos, $limit);
            $estatisticas = $this->getAlertaRepo()->getEstatisticas($userId);
        } catch (\Exception $e) {
            $alertas = [];
            $estatisticas = ['total' => 0, 'nao_lidos' => 0];
        }

        return $this->json([
            'data' => $alertas,
            'estatisticas' => $estatisticas,
        ]);
    }

    /**
     * PUT /api/v1/dashboard/alertas/:id/lido
     */
    public function marcarAlertaLido(int $id): Response
    {
        $userId = $this->getUserId();

        try {
            $alerta = $this->getAlertaRepo()->find($id);

            if (!$alerta || $alerta->getDestinatarioId() !== $userId) {
                return $this->notFound('Alerta não encontrado');
            }

            $this->getAlertaRepo()->marcarComoLido($id);
        } catch (\Exception $e) {
            return $this->error('Erro ao processar alerta', Response::HTTP_INTERNAL_ERROR);
        }

        return $this->json([
            'message' => 'Alerta marcado como lido',
        ]);
    }

    /**
     * PUT /api/v1/dashboard/alertas/lidos
     */
    public function marcarTodosLidos(): Response
    {
        $userId = $this->getUserId();

        try {
            $this->getAlertaRepo()->marcarTodosComoLidos($userId);
        } catch (\Exception $e) {
            return $this->error('Erro ao processar alertas', Response::HTTP_INTERNAL_ERROR);
        }

        return $this->json([
            'message' => 'Todos os alertas marcados como lidos',
        ]);
    }
}
