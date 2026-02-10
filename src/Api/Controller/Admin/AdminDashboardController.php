<?php
/**
 * Controller for Admin Dashboard and Onboarding Readiness.
 *
 * @package DotProject\Api\Controller\Admin
 */

declare(strict_types=1);

namespace DotProject\Api\Controller\Admin;

use DotProject\Api\Controller\BaseController;
use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Repository\UnidadeOrganizacionalRepository;
use DotProject\Repository\UsuarioUnidadeRepository;
use DotProject\Repository\HistoricoMovimentacaoRepository;
use DotProject\Repository\UserRepository;
use DotProject\Repository\NivelHierarquicoRepository;
use DotProject\Core\Logger;
use DotProject\Service\OnboardingReadinessService;

class AdminDashboardController extends BaseController
{
    private UnidadeOrganizacionalRepository $unidadeRepo;
    private UsuarioUnidadeRepository $vinculoRepo;
    private HistoricoMovimentacaoRepository $historicoRepo;
    private OnboardingReadinessService $onboardingReadinessService;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->unidadeRepo = new UnidadeOrganizacionalRepository();
        $this->vinculoRepo = new UsuarioUnidadeRepository();
        $this->historicoRepo = new HistoricoMovimentacaoRepository();
        $nivelRepo = new NivelHierarquicoRepository();
        $userRepo = new UserRepository();
        $this->onboardingReadinessService = new OnboardingReadinessService(
            $this->db,
            $nivelRepo,
            $this->unidadeRepo,
            $this->vinculoRepo,
            $userRepo
        );
        Logger::debug('AdminDashboardController inicializado');
    }

    /**
     * GET /api/v1/admin/dashboard
     */
    public function dashboard(): Response
    {
        $cacheKey = $this->cacheKey('admin', 'dashboard');
        $cached = $this->cache->get($cacheKey);

        if ($cached) {
            return $this->json($cached);
        }

        $data = [
            'estrutura' => $this->unidadeRepo->getEstatisticas(),
            'vinculos' => $this->vinculoRepo->getEstatisticas(),
            'movimentacoes' => $this->historicoRepo->getEstatisticas(),
            'usuarios_sem_vinculo' => $this->vinculoRepo->findUsuariosSemVinculo(),
        ];

        $this->cache->set($cacheKey, $data, 300);

        return $this->json($data);
    }

    /**
     * GET /api/v1/admin/onboarding/readiness
     */
    public function onboardingReadiness(): Response
    {
        try {
            $data = $this->onboardingReadinessService->buildChecklist();
            return $this->json(['data' => $data]);
        } catch (\Throwable $e) {
            Logger::error('Erro ao gerar checklist de onboarding', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return $this->error('Falha ao carregar checklist de onboarding', 500);
        }
    }
}
