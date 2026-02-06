<?php
/**
 * Repository para Histórico de Movimentações
 * 
 * @package DotProject\Repository
 */

declare(strict_types=1);

namespace DotProject\Repository;

use DotProject\Entity\HistoricoMovimentacaoEntity;

class HistoricoMovimentacaoRepository extends BaseRepository
{
    protected string $table = 'dotp_historico_movimentacoes';
    protected string $primaryKey = 'historico_id';
    
    /**
     * Hidrata a entidade com dados do banco
     */
    protected function hydrate(array $row): HistoricoMovimentacaoEntity
    {
        $entity = new HistoricoMovimentacaoEntity();
        $entity->setId((int) $row['historico_id']);
        $entity->setUserId((int) $row['historico_user_id']);
        $entity->setUnidadeOrigemId($row['historico_unidade_origem_id'] ? (int) $row['historico_unidade_origem_id'] : null);
        $entity->setUnidadeDestinoId((int) $row['historico_unidade_destino_id']);
        $entity->setCargoAnterior($row['historico_cargo_anterior'] ?? null);
        $entity->setCargoNovo($row['historico_cargo_novo'] ?? null);
        $entity->setTipoMovimentacao($row['historico_tipo_movimentacao']);
        $entity->setDataMovimentacao($row['historico_data_movimentacao']);
        $entity->setObservacao($row['historico_observacao'] ?? null);
        $entity->setResponsavelId($row['historico_responsavel_id'] ? (int) $row['historico_responsavel_id'] : null);
        
        return $entity;
    }
    
    /**
     * Converte entidade para array para persistência
     */
    protected function extract(object $entity): array
    {
        if (!$entity instanceof HistoricoMovimentacaoEntity) {
            throw new \InvalidArgumentException('Entity must be HistoricoMovimentacaoEntity');
        }
        
        return [
            'historico_user_id' => $entity->getUserId(),
            'historico_unidade_origem_id' => $entity->getUnidadeOrigemId(),
            'historico_unidade_destino_id' => $entity->getUnidadeDestinoId(),
            'historico_cargo_anterior' => $entity->getCargoAnterior(),
            'historico_cargo_novo' => $entity->getCargoNovo(),
            'historico_tipo_movimentacao' => $entity->getTipoMovimentacao(),
            'historico_data_movimentacao' => $entity->getDataMovimentacao(),
            'historico_observacao' => $entity->getObservacao(),
            'historico_responsavel_id' => $entity->getResponsavelId(),
        ];
    }
    
    /**
     * Busca histórico por usuário
     * @return HistoricoMovimentacaoEntity[]
     */
    public function findByUsuario(int $userId, int $limit = 50): array
    {
        return $this->findBy(
            ['historico_user_id' => $userId],
            ['historico_data_movimentacao' => 'DESC', 'historico_id' => 'DESC'],
            $limit
        );
    }
    
    /**
     * Busca histórico por unidade (origem ou destino)
     * @return HistoricoMovimentacaoEntity[]
     */
    public function findByUnidade(int $unidadeId, int $limit = 50): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE historico_unidade_origem_id = ? OR historico_unidade_destino_id = ?
                ORDER BY historico_data_movimentacao DESC, historico_id DESC
                LIMIT ?";
        
        $rows = $this->db->fetchAll($sql, [$unidadeId, $unidadeId, $limit]);
        
        return array_map([$this, 'hydrate'], $rows);
    }
    
    /**
     * Busca histórico por período
     * @return HistoricoMovimentacaoEntity[]
     */
    public function findByPeriodo(string $dataInicio, string $dataFim): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE historico_data_movimentacao BETWEEN ? AND ?
                ORDER BY historico_data_movimentacao DESC, historico_id DESC";
        
        $rows = $this->db->fetchAll($sql, [$dataInicio, $dataFim]);
        
        return array_map([$this, 'hydrate'], $rows);
    }
    
    /**
     * Busca estatísticas de movimentações
     */
    public function getEstatisticas(): array
    {
        $cacheKey = $this->cacheKey('stats');
        $cached = $this->cache->get($cacheKey);
        
        if ($cached) {
            return $cached;
        }
        
        $stats = [
            'total' => (int) $this->db->fetchColumn("SELECT COUNT(*) FROM {$this->table}"),
            'ultimos_30_dias' => (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM {$this->table} WHERE historico_created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
            ),
            'por_tipo' => $this->db->fetchAll(
                "SELECT historico_tipo_movimentacao as tipo, COUNT(*) as total FROM {$this->table} GROUP BY historico_tipo_movimentacao"
            ),
        ];
        
        $this->cache->set($cacheKey, $stats, 300);
        
        return $stats;
    }
    
    /**
     * Registra uma nova movimentação
     */
    public function registrar(HistoricoMovimentacaoEntity $movimentacao): void
    {
        $this->save($movimentacao);
        $this->clearCache();
    }
    
    /**
     * Busca movimentações recentes com detalhes
     */
    public function findRecentesComDetalhes(int $limit = 20): array
    {
        $sql = "SELECT 
                    h.*,
                    u.user_first_name as usuario_nome,
                    u.user_last_name as usuario_sobrenome,
                    uo.unidade_nome as unidade_origem_nome,
                    ud.unidade_nome as unidade_destino_nome,
                    r.user_first_name as responsavel_nome,
                    r.user_last_name as responsavel_sobrenome
                FROM {$this->table} h
                LEFT JOIN dotp_users u ON u.user_id = h.historico_user_id
                LEFT JOIN dotp_unidades_organizacionais uo ON uo.unidade_id = h.historico_unidade_origem_id
                LEFT JOIN dotp_unidades_organizacionais ud ON ud.unidade_id = h.historico_unidade_destino_id
                LEFT JOIN dotp_users r ON r.user_id = h.historico_responsavel_id
                ORDER BY h.historico_data_movimentacao DESC, h.historico_id DESC
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$limit]);
    }
}
