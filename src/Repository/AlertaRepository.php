<?php
/**
 * Repository para Alertas
 * 
 * @package DotProject\Repository
 */

declare(strict_types=1);

namespace DotProject\Repository;

use DotProject\Entity\AlertaEntity;

class AlertaRepository extends BaseRepository
{
        /** @var string */
    protected $table = 'dotp_alertas';
    
        /** @var string */
    protected $primaryKey = 'id';
    
    protected function hydrate(array $row): AlertaEntity
    {
        $entity = new AlertaEntity();
        $entity->setId((int) $row['id']);
        $entity->setTipo($row['tipo']);
        $entity->setTitulo($row['titulo']);
        $entity->setDescricao($row['mensagem'] ?? null);
        $entity->setProjetoId($row['projeto_id'] ? (int) $row['projeto_id'] : null);
        $entity->setEtapaId($row['etapa_id'] ? (int) $row['etapa_id'] : null);
        $entity->setProgramaId($row['programa_id'] ? (int) $row['programa_id'] : null);
        $entity->setDestinatarioId((int) $row['destinatario_id']);
        $entity->setUnidadeId(null); // Coluna não existe no banco
        $entity->setPrioridade(strtolower($row['prioridade'] ?? 'media'));
        $entity->setLido((bool) ($row['lido'] ?? 0));
        $entity->setDataLeitura($row['data_leitura'] ?? null);
        $entity->setAcaoRequerida(null);
        $entity->setLinkAcao(null);
        
        return $entity;
    }
    
    protected function toArray(object $entity): array
    {
        if (!$entity instanceof AlertaEntity) {
            throw new \InvalidArgumentException('Entity must be AlertaEntity');
        }
        
        return [
            'tipo' => $entity->getTipo(),
            'titulo' => $entity->getTitulo(),
            'mensagem' => $entity->getDescricao(),
            'projeto_id' => $entity->getProjetoId(),
            'etapa_id' => $entity->getEtapaId(),
            'programa_id' => $entity->getProgramaId(),
            'destinatario_id' => $entity->getDestinatarioId(),
            'prioridade' => ucfirst($entity->getPrioridade()),
            'lido' => $entity->isLido() ? 1 : 0,
            'data_leitura' => $entity->getDataLeitura(),
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    protected function extract(object $entity): array
    {
        return $this->toArray($entity);
    }
    
    /**
     * {@inheritdoc}
     */
    public function save(object $entity): bool
    {
        if (!$entity instanceof AlertaEntity) {
            throw new \InvalidArgumentException('Entity must be AlertaEntity');
        }
        
        $data = $this->toArray($entity);
        
        if ($entity->getId()) {
            // Update
            $fields = [];
            $values = [];
            foreach ($data as $key => $value) {
                $fields[] = "{$key} = ?";
                $values[] = $value;
            }
            $values[] = $entity->getId();
            
            $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = ?";
            $this->db->execute($sql, $values);
            $this->clearCache();
            return true;
        } else {
            // Insert
            $columns = array_keys($data);
            $placeholders = array_fill(0, count($columns), '?');
            
            $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") 
                    VALUES (" . implode(', ', $placeholders) . ")";
            $this->db->execute($sql, array_values($data));
            $newId = (int) $this->db->lastInsertId();
            $entity->setId($newId);
            $this->clearCache();
            return true;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        $this->db->execute($sql, [$id]);
        $this->clearCache();
        return $this->db->rowCount() > 0;
    }
    
    /**
     * Busca alertas do usuário
     * @return AlertaEntity[]
     */
    public function findByUsuario(int $userId, bool $apenasNaoLidos = false, int $limit = 50): array
    {
        $where = ['destinatario_id' => $userId];
        if ($apenasNaoLidos) {
            $where['lido'] = 0;
        }
        
        return $this->findBy($where, ['created_at' => 'DESC'], $limit);
    }
    
    /**
     * Busca alertas por unidade
     * @return AlertaEntity[]
     */
    public function findByUnidade(int $unidadeId, int $limit = 50): array
    {
        // Coluna unidade_id não existe na tabela
        return [];
    }
    
    /**
     * Conta alertas não lidos do usuário
     */
    public function countNaoLidos(int $userId): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE destinatario_id = ? AND lido = 0";
        return (int) $this->db->fetchColumn($sql, [$userId]);
    }
    
    /**
     * Conta alertas não lidos por prioridade
     */
    public function countNaoLidosPorPrioridade(int $userId): array
    {
        $sql = "SELECT prioridade, COUNT(*) as total 
                FROM {$this->table} 
                WHERE destinatario_id = ? AND lido = 0 
                GROUP BY prioridade";
        
        return $this->db->fetchAllParams($sql, [$userId]);
    }
    
    /**
     * Marca alerta como lido
     */
    public function marcarComoLido(int $alertaId): void
    {
        $sql = "UPDATE {$this->table} 
                SET lido = 1, data_leitura = NOW() 
                WHERE id = ?";
        
        $this->db->execute($sql, [$alertaId]);
        $this->clearCache();
    }
    
    /**
     * Marca todos os alertas do usuário como lidos
     */
    public function marcarTodosComoLidos(int $userId): void
    {
        $sql = "UPDATE {$this->table} 
                SET lido = 1, data_leitura = NOW() 
                WHERE destinatario_id = ? AND lido = 0";
        
        $this->db->execute($sql, [$userId]);
        $this->clearCache();
    }
    
    /**
     * Busca alertas por tipo
     * @return AlertaEntity[]
     */
    public function findByTipo(string $tipo, int $limit = 50): array
    {
        return $this->findBy(
            ['tipo' => $tipo],
            ['created_at' => 'DESC'],
            $limit
        );
    }
    
    /**
     * Busca alertas críticos não lidos
     * @return AlertaEntity[]
     */
    public function findCriticosNaoLidos(int $userId): array
    {
        return $this->findBy([
            'destinatario_id' => $userId,
            'prioridade' => 'Critica',
            'lido' => 0,
        ], ['created_at' => 'DESC']);
    }
    
    /**
     * Verifica se já existe alerta similar não lido
     */
    public function existeAlertaSimilar(string $tipo, int $destinatarioId, ?int $projetoId = null, ?int $etapaId = null): bool
    {
        $sql = "SELECT 1 FROM {$this->table} 
                WHERE tipo = ? 
                AND destinatario_id = ? 
                AND lido = 0";
        
        $params = [$tipo, $destinatarioId];
        
        if ($projetoId) {
            $sql .= " AND projeto_id = ?";
            $params[] = $projetoId;
        }
        
        if ($etapaId) {
            $sql .= " AND etapa_id = ?";
            $params[] = $etapaId;
        }
        
        $sql .= " LIMIT 1";
        
        return (bool) $this->db->fetchColumn($sql, $params);
    }
    
    /**
     * Cria alerta se não existir similar
     */
    public function criarSeNaoExistir(AlertaEntity $alerta): ?int
    {
        if ($this->existeAlertaSimilar(
            $alerta->getTipo(),
            $alerta->getDestinatarioId(),
            $alerta->getProjetoId(),
            $alerta->getEtapaId()
        )) {
            return null;
        }
        
        return $this->save($alerta);
    }
    
    /**
     * Busca alertas com detalhes (join com projetos, unidades)
     */
    public function findComDetalhes(int $userId, bool $apenasNaoLidos = false, int $limit = 50): array
    {
        $sql = "SELECT 
                    a.*,
                    p.nome as projeto_nome,
                    p.estado as projeto_estado,
                    pr.nome as programa_nome
                FROM {$this->table} a
                LEFT JOIN dotp_projetos_prefeitura p ON p.id = a.projeto_id
                LEFT JOIN dotp_programas pr ON pr.id = a.programa_id
                WHERE a.destinatario_id = ?";
        
        $params = [$userId];
        
        if ($apenasNaoLidos) {
            $sql .= " AND a.lido = 0";
        }
        
        $sql .= " ORDER BY a.created_at DESC LIMIT ?";
        $params[] = $limit;
        
        return $this->db->fetchAllParams($sql, $params);
    }
    
    /**
     * Estatísticas de alertas do usuário
     */
    public function getEstatisticas(int $userId): array
    {
        error_log('[DEBUG] AlertaRepository::getEstatisticas() - userId: ' . $userId);
        try {
            $sql = "SELECT 
                        COUNT(*) as total,
                        COUNT(CASE WHEN lido = 0 THEN 1 END) as nao_lidos,
                        COUNT(CASE WHEN lido = 0 AND prioridade = 'Critica' THEN 1 END) as criticos,
                        COUNT(CASE WHEN lido = 0 AND prioridade = 'Alta' THEN 1 END) as altas,
                        COUNT(CASE WHEN lido = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as ultimas_24h
                    FROM {$this->table}
                    WHERE destinatario_id = ?";
            
            error_log('[DEBUG] Executando SQL...');
            $result = $this->db->fetchAllParams($sql, [$userId]);
            error_log('[DEBUG] Resultado: ' . json_encode($result));
            
            return $result[0] ?? [
                'total' => 0,
                'nao_lidos' => 0,
                'criticos' => 0,
                'altas' => 0,
                'ultimas_24h' => 0,
            ];
        } catch (\Throwable $e) {
            error_log('[DEBUG] ERRO em getEstatisticas: ' . $e->getMessage());
            error_log('[DEBUG] Arquivo: ' . $e->getFile() . ':' . $e->getLine());
            return [
                'total' => 0,
                'nao_lidos' => 0,
                'criticos' => 0,
                'altas' => 0,
                'ultimas_24h' => 0,
            ];
        }
    }
    
    /**
     * Remove alertas antigos (mais de X dias)
     */
    public function limparAlertasAntigos(int $dias = 90): int
    {
        $sql = "DELETE FROM {$this->table} WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        $this->db->execute($sql, [$dias]);
        $this->clearCache();
        
        return $this->db->rowCount();
    }
}
