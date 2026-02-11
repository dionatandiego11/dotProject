<?php
/**
 * Repository para Projetos (PPA) unificados em dotp_projects
 *
 * @package DotProject\Repository
 */

declare(strict_types=1);

namespace DotProject\Repository;

use DateTime;
use DotProject\Entity\ProjetoEntity;

class ProjetoRepository extends BaseRepository
{
    protected string $table = 'dotp_projects';
    protected string $primaryKey = 'project_id';

    private EtapaRepository $etapaRepo;

    public function __construct(?\DotProject\Core\Database $db = null, ?\DotProject\Core\Cache $cache = null)
    {
        parent::__construct($db, $cache);
        $this->etapaRepo = new EtapaRepository($this->db, $this->cache);
    }

    /**
     * {@inheritdoc}
     */
    protected function hydrate(array $data): ProjetoEntity
    {
        $entity = new ProjetoEntity();
        $entity->setId((int) $data['project_id']);
        $entity->setNome($data['project_name'] ?? '');
        $entity->setTipo($data['project_tipo'] ?? 'Outro');
        $entity->setEstado($data['project_estado'] ?? 'Cadastrado');
        $entity->setEtapaAtualNumero((int) ($data['project_etapa_atual'] ?? 1));

        $percentExec = $data['project_percent_execucao'] ?? $data['project_percent_complete'] ?? 0;
        $entity->setPercentExecucao((float) $percentExec);

        $entity->setDescricao($data['project_description'] ?? null);
        $entity->setFonteRecurso($data['project_fonte_recurso'] ?? null);
        $entity->setValorPrevisto(isset($data['project_target_budget']) ? (float) $data['project_target_budget'] : null);
        $entity->setSituacaoOrcamentaria($data['project_situacao_orcamentaria'] ?? null);

        if (isset($data['project_company'])) {
            $entity->setUnidadeId((int) $data['project_company']);
        }

        if (!empty($data['project_programa_id'])) {
            $entity->setProgramaId((int) $data['project_programa_id']);
        }

        if (isset($data['project_coordenador_id'])) {
            $entity->setCoordenadorId($data['project_coordenador_id'] ? (int) $data['project_coordenador_id'] : 0);
        }

        if (!empty($data['project_start_date'])) {
            $entity->setDataPrevistaInicio(new DateTime($data['project_start_date']));
        }
        if (!empty($data['project_end_date'])) {
            $entity->setDataPrevistaFim(new DateTime($data['project_end_date']));
        }
        if (!empty($data['project_created_at'])) {
            $entity->setDataCriacao(new DateTime($data['project_created_at']));
        }
        if (!empty($data['project_updated_at'])) {
            $entity->setDataAtualizacao(new DateTime($data['project_updated_at']));
        }

        $entity->setJustificativaAtraso($data['project_justificativa_atraso'] ?? null);
        $entity->setImpedimentoDescricao($data['project_impedimento_descricao'] ?? null);

        // Carrega etapas
        $etapas = $this->etapaRepo->findByProjetoId($entity->getId() ?? 0);
        foreach ($etapas as $etapa) {
            $entity->addEtapa($etapa);
        }

        return $entity;
    }

    /**
     * {@inheritdoc}
     */
    protected function extract(object $entity): array
    {
        if (!$entity instanceof ProjetoEntity) {
            throw new \InvalidArgumentException('Entity must be ProjetoEntity');
        }

        return [
            'project_id' => $entity->getId(),
            'project_programa_id' => $entity->getProgramaId(),
            'project_name' => $entity->getNome(),
            'project_description' => $entity->getDescricao(),
            'project_tipo' => $entity->getTipo(),
            'project_estado' => $entity->getEstado(),
            'project_etapa_atual' => $entity->getEtapaAtualNumero(),
            'project_percent_execucao' => $entity->getPercentExecucao(),
            'project_situacao_orcamentaria' => $entity->getSituacaoOrcamentaria(),
            'project_company' => $entity->getUnidadeId(),
            'project_coordenador_id' => $entity->getCoordenadorId(),
            'project_start_date' => $entity->getDataPrevistaInicio()?->format('Y-m-d'),
            'project_end_date' => $entity->getDataPrevistaFim()?->format('Y-m-d'),
            'project_target_budget' => $entity->getValorPrevisto(),
            'project_percent_complete' => (int) round($entity->getPercentExecucao()),
            'project_justificativa_atraso' => $entity->getJustificativaAtraso(),
            'project_impedimento_descricao' => $entity->getImpedimentoDescricao(),
        ];
    }

    /**
     * Busca projetos com filtros simples.
     *
     * @return ProjetoEntity[]
     */
    public function findByFiltros(array $filtros): array
    {
        $where = [];
        $params = [];
        if ($this->shouldApplyTenantScope()) {
            $tenantColumn = $this->getTenantColumn();
            $tenantId = $this->getTenantId();
            if ($tenantColumn !== null && $tenantId !== null) {
                $where[] = "{$tenantColumn} = ?";
                $params[] = $tenantId;
            }
        }

        if (!empty($filtros['unidade_id'])) {
            $ids = is_array($filtros['unidade_id']) ? $filtros['unidade_id'] : [$filtros['unidade_id']];
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $where[] = "project_company IN ({$placeholders})";
            $params = array_merge($params, $ids);
        }

        if (!empty($filtros['programa_id'])) {
            $where[] = "project_programa_id = ?";
            $params[] = (int) $filtros['programa_id'];
        }

        if (!empty($filtros['estado'])) {
            $where[] = "project_estado = ?";
            $params[] = $filtros['estado'];
        }

        if (!empty($filtros['tipo'])) {
            $where[] = "project_tipo = ?";
            $params[] = $filtros['tipo'];
        }

        if (!empty($filtros['coordenador_id'])) {
            $where[] = "project_coordenador_id = ?";
            $params[] = (int) $filtros['coordenador_id'];
        }

        if (!empty($filtros['search'])) {
            $where[] = "(project_name LIKE ? OR project_description LIKE ?)";
            $like = '%' . $filtros['search'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $sql = "SELECT * FROM {$this->table}";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " ORDER BY project_name ASC";

        $rows = $this->db->fetchAllParams($sql, $params);
        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * Cria um novo projeto PPA.
     */
    public function create(array $dados): ProjetoEntity
    {
        $projeto = new ProjetoEntity();
        $projeto->setNome($dados['nome'] ?? '');
        $projeto->setTipo($dados['tipo'] ?? 'Outro');
        $projeto->setEstado($dados['estado'] ?? 'Cadastrado');
        $projeto->setEtapaAtualNumero((int) ($dados['etapa_atual'] ?? 1));
        $projeto->setPercentExecucao((float) ($dados['percent_execucao'] ?? 0));
        $projeto->setDescricao($dados['descricao'] ?? null);
        $projeto->setFonteRecurso($dados['fonte_recurso'] ?? null);
        $projeto->setValorPrevisto(isset($dados['valor_previsto']) ? (float) $dados['valor_previsto'] : null);
        $projeto->setSituacaoOrcamentaria($dados['situacao_orcamentaria'] ?? null);
        $projeto->setUnidadeId((int) ($dados['unidade_id'] ?? 0));
        $projeto->setCoordenadorId((int) ($dados['coordenador_id'] ?? 0));

        if (!empty($dados['programa_id'])) {
            $projeto->setProgramaId((int) $dados['programa_id']);
        }
        if (!empty($dados['data_prevista_inicio'])) {
            $projeto->setDataPrevistaInicio(new DateTime($dados['data_prevista_inicio']));
        }
        if (!empty($dados['data_prevista_fim'])) {
            $projeto->setDataPrevistaFim(new DateTime($dados['data_prevista_fim']));
        }

        $data = $this->extract($projeto);
        if ($this->shouldApplyTenantScope()) {
            $tenantColumn = $this->getTenantColumn();
            $tenantId = $this->getTenantId();
            if ($tenantColumn !== null && $tenantId !== null && (!array_key_exists($tenantColumn, $data) || $data[$tenantColumn] === null || $data[$tenantColumn] === '')) {
                $data[$tenantColumn] = $tenantId;
            }
        }
        unset($data['project_id']);

        $result = $this->db->insert($this->table, $data);
        if (!$result) {
            throw new \RuntimeException('Falha ao criar projeto');
        }

        $projeto->setId((int) $this->db->lastInsertId());

        // Cria etapas padrão se não existirem
        $this->criarEtapasPadrao($projeto->getId());

        return $projeto;
    }

    /**
     * Salva (atualiza) um projeto.
     */
    public function save(object $entity): int
    {
        if (!$entity instanceof ProjetoEntity) {
            throw new \InvalidArgumentException('Entity must be ProjetoEntity');
        }

        $data = $this->extract($entity);
        $tenantColumn = $this->getTenantColumn();
        $tenantId = $this->getTenantId();
        $applyTenant = $this->shouldApplyTenantScope() && $tenantColumn !== null && $tenantId !== null;
        if ($applyTenant && (!array_key_exists($tenantColumn, $data) || $data[$tenantColumn] === null || $data[$tenantColumn] === '')) {
            $data[$tenantColumn] = $tenantId;
        }
        $id = $data['project_id'] ?? null;
        $result = false;

        if (!$id) {
            unset($data['project_id']);
            $result = $this->db->insert($this->table, $data);
            if ($result) {
                $id = (int) $this->db->lastInsertId();
                $entity->setId($id);
                $this->criarEtapasPadrao($id);
            }
        } else {
            unset($data['project_id']);
            $result = $this->db->update(
                $this->table,
                $data,
                $applyTenant
                    ? "{$this->primaryKey} = {$id} AND {$tenantColumn} = {$tenantId}"
                    : "{$this->primaryKey} = {$id}"
            );
        }

        if ($result) {
            // Persiste etapas (se houver)
            foreach ($entity->getEtapas() as $etapa) {
                $this->etapaRepo->save($etapa);
            }
            $this->clearCache();
        }

        return $result ? (int) $entity->getId() : 0;
    }

    /**
     * Cria 5 etapas padrão para o projeto.
     */
    private function criarEtapasPadrao(int $projetoId): void
    {
        $nomes = [
            1 => 'Planejamento',
            2 => 'Licitação',
            3 => 'Execução',
            4 => 'Medição',
            5 => 'Pagamento',
        ];

        foreach ($nomes as $numero => $nome) {
            $etapa = new \DotProject\Entity\EtapaEntity();
            $etapa->setProjetoId($projetoId);
            $etapa->setNumero($numero);
            $etapa->setNome($nome);
            $etapa->setEstado('Nao_Iniciada');
            $this->etapaRepo->save($etapa);
        }
    }
}
