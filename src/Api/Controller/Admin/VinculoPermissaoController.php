<?php
/**
 * Controller for User-Unit Links (Vínculos) and Permission Matrix.
 *
 * @package DotProject\Api\Controller\Admin
 */

declare(strict_types=1);

namespace DotProject\Api\Controller\Admin;

use DotProject\Api\Controller\BaseController;
use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Entity\HistoricoMovimentacaoEntity;
use DotProject\Repository\UsuarioUnidadeRepository;
use DotProject\Repository\HistoricoMovimentacaoRepository;
use DotProject\Core\Logger;
use DotProject\Core\TenantContext;

class VinculoPermissaoController extends BaseController
{
    private UsuarioUnidadeRepository $vinculoRepo;
    private HistoricoMovimentacaoRepository $historicoRepo;
    /** @var array<string, bool> */
    private array $columnPresenceCache = [];

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->vinculoRepo = new UsuarioUnidadeRepository();
        $this->historicoRepo = new HistoricoMovimentacaoRepository();
        Logger::debug('VinculoPermissaoController inicializado');
    }

    // ===========================================
    // VINCULOS USUARIO-UNIDADE
    // ===========================================

    /**
     * GET /api/v1/admin/vinculos
     */
    public function listVinculos(): Response
    {
        $userId = $this->request->getQuery('user_id');
        $unidadeId = $this->request->getQuery('unidade_id');

        if ($userId) {
            $vinculos = $this->vinculoRepo->findByUsuario((int) $userId);
        } elseif ($unidadeId) {
            $vinculos = $this->vinculoRepo->findByUnidade((int) $unidadeId);
        } else {
            return $this->validationError(['user_id' => 'user_id ou unidade_id e obrigatorio']);
        }

        return $this->json([
            'data' => $vinculos,
        ]);
    }

    /**
     * POST /api/v1/admin/vinculos
     */
    public function createVinculo(): Response
    {
        $data = $this->request->getJsonBody();

        if (empty($data['user_id'])) {
            return $this->validationError(['user_id' => 'Usuario e obrigatorio']);
        }
        if (empty($data['unidade_id'])) {
            return $this->validationError(['unidade_id' => 'Unidade e obrigatoria']);
        }
        if (empty($data['role'])) {
            return $this->validationError(['role' => 'Role e obrigatoria']);
        }

        $vinculoId = $this->vinculoRepo->criarVinculo($data);
        if ($vinculoId <= 0) {
            $dbError = $this->db->getError();
            $fallback = $this->vinculoRepo->findByUsuarioEUnidade((int) $data['user_id'], (int) $data['unidade_id']);
            if ($fallback) {
                $vinculoId = (int) $fallback['vinculo_id'];
            } else {
                return $this->error('Falha ao criar vinculo' . ($dbError ? ': ' . $dbError : ''), 500);
            }
        }

        $historico = new HistoricoMovimentacaoEntity();
        $historico->setUserId($data['user_id']);
        $historico->setUnidadeDestinoId($data['unidade_id']);
        $historico->setCargoNovo($data['cargo'] ?? null);
        $historico->setTipoMovimentacao(HistoricoMovimentacaoEntity::TIPO_TRANSFERENCIA);
        $historico->setDataMovimentacao($data['data_inicio'] ?? date('Y-m-d'));
        $historico->setObservacao('Vinculo criado via admin');
        $this->historicoRepo->save($historico);
        $this->syncUserCompanyFromPrincipalVinculo((int) $data['user_id']);

        return $this->json([
            'message' => 'Vinculo criado com sucesso',
            'data' => $this->vinculoRepo->findById($vinculoId),
        ], 201);
    }

    /**
     * PUT /api/v1/admin/vinculos/{id}
     */
    public function updateVinculo(int $id): Response
    {
        $vinculo = $this->vinculoRepo->findById($id);

        if (!$vinculo) {
            return $this->notFound('Vinculo nao encontrado');
        }

        $data = $this->request->getJsonBody();
        $this->vinculoRepo->atualizarVinculo($id, $data);
        $updated = $this->vinculoRepo->findById($id);
        if (!empty($updated['vinculo_user_id'])) {
            $this->syncUserCompanyFromPrincipalVinculo((int) $updated['vinculo_user_id']);
        }

        return $this->json([
            'message' => 'Vinculo atualizado com sucesso',
            'data' => $this->vinculoRepo->findById($id),
        ]);
    }

    /**
     * DELETE /api/v1/admin/vinculos/{id}
     */
    public function deleteVinculo(int $id): Response
    {
        $vinculo = $this->vinculoRepo->findById($id);

        if (!$vinculo) {
            return $this->notFound('Vinculo nao encontrado');
        }

        $this->vinculoRepo->desativarVinculo($id);

        $historico = new HistoricoMovimentacaoEntity();
        $historico->setUserId($vinculo['vinculo_user_id']);
        $historico->setUnidadeOrigemId($vinculo['vinculo_unidade_id']);
        $historico->setCargoAnterior($vinculo['vinculo_cargo']);
        $historico->setTipoMovimentacao(HistoricoMovimentacaoEntity::TIPO_EXONERACAO);
        $historico->setDataMovimentacao(date('Y-m-d'));
        $historico->setObservacao('Vinculo desativado via admin');
        $this->historicoRepo->save($historico);
        $this->syncUserCompanyFromPrincipalVinculo((int) $vinculo['vinculo_user_id']);

        return $this->json([
            'message' => 'Vinculo desativado com sucesso',
        ]);
    }

    /**
     * PUT /api/v1/admin/vinculos/{id}/principal
     */
    public function definirPrincipal(int $id): Response
    {
        $vinculo = $this->vinculoRepo->findById($id);
        if (!$vinculo) {
            return $this->notFound('Vinculo nao encontrado');
        }

        $this->vinculoRepo->definirPrincipal($id);
        $this->syncUserCompanyFromPrincipalVinculo((int) $vinculo['vinculo_user_id']);

        return $this->json([
            'message' => 'Vinculo definido como principal',
        ]);
    }

    // ===========================================
    // PRIVATE HELPERS
    // ===========================================

    private function syncUserCompanyFromPrincipalVinculo(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $principal = $this->vinculoRepo->findPrincipal($userId);
        $companyId = null;
        if ($principal && !empty($principal['vinculo_unidade_id'])) {
            $companyId = (int) $principal['vinculo_unidade_id'];
        }

        $this->db->update(
            'users',
            ['user_company' => $companyId],
            sprintf('user_id = %d', $userId) . $this->tenantAndCondition($this->db->table('users'))
        );
    }

    private function tenantAndCondition(string $table, ?string $alias = null): string
    {
        $tenantId = $this->getTenantId();
        if ($tenantId === null || !$this->hasTableColumn($table, 'tenant_id')) {
            return '';
        }

        $column = $alias !== null && $alias !== ''
            ? $alias . '.tenant_id'
            : 'tenant_id';

        return " AND {$column} = {$tenantId}";
    }

    private function hasTableColumn(string $table, string $column): bool
    {
        $tableName = trim($table, '`');
        $cacheKey = $tableName . ':' . $column;
        if (array_key_exists($cacheKey, $this->columnPresenceCache)) {
            return $this->columnPresenceCache[$cacheKey];
        }

        $exists = (int) ($this->db->fetchValue(
            "SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND column_name = ?",
            [$tableName, $column]
        ) ?? 0);

        $this->columnPresenceCache[$cacheKey] = $exists > 0;
        return $this->columnPresenceCache[$cacheKey];
    }

    private function getTenantId(): ?int
    {
        if (!TenantContext::isEnabled()) {
            return null;
        }

        $tenantId = TenantContext::getTenantId();
        if ($tenantId === null || $tenantId <= 0) {
            return null;
        }

        return $tenantId;
    }
}
