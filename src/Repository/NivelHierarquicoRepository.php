<?php
/**
 * Repository para Níveis Hierárquicos
 * 
 * @package DotProject\Repository
 */

declare(strict_types=1);

namespace DotProject\Repository;

use DotProject\Core\Database;
use DotProject\Entity\NivelHierarquicoEntity;
use DotProject\Entity\PermissaoNivelEntity;

class NivelHierarquicoRepository extends BaseRepository
{
    protected string $table = 'dotp_niveis_hierarquicos';
    protected string $primaryKey = 'nivel_id';
    
    /**
     * Hidrata a entidade com dados do banco
     */
    protected function hydrate(array $row): NivelHierarquicoEntity
    {
        $entity = new NivelHierarquicoEntity();
        $entity->setId((int) $row['nivel_id']);
        $entity->setOrdem((int) $row['nivel_ordem']);
        $entity->setNome($row['nivel_nome']);
        $entity->setTituloResponsavel($row['nivel_titulo_responsavel'] ?? null);
        $entity->setDescricao($row['nivel_descricao'] ?? null);
        $entity->setCor($row['nivel_cor'] ?? '#007bff');
        $entity->setAtivo((bool) $row['nivel_ativo']);
        
        return $entity;
    }
    
    /**
     * Converte entidade para array para persistência
     */
    protected function toArray(object $entity): array
    {
        if (!$entity instanceof NivelHierarquicoEntity) {
            throw new \InvalidArgumentException('Entity must be NivelHierarquicoEntity');
        }
        
        return [
            'nivel_ordem' => $entity->getOrdem(),
            'nivel_nome' => $entity->getNome(),
            'nivel_titulo_responsavel' => $entity->getTituloResponsavel(),
            'nivel_descricao' => $entity->getDescricao(),
            'nivel_cor' => $entity->getCor(),
            'nivel_ativo' => $entity->isAtivo() ? 1 : 0,
        ];
    }
    
    /**
     * Busca todos os níveis ativos ordenados
     * @return NivelHierarquicoEntity[]
     */
    public function findAllAtivos(): array
    {
        return $this->findBy(['nivel_ativo' => 1], ['nivel_ordem' => 'ASC']);
    }
    
    /**
     * Busca nível por ordem
     */
    public function findByOrdem(int $ordem): ?NivelHierarquicoEntity
    {
        $result = $this->findBy(['nivel_ordem' => $ordem], [], 1);
        return $result[0] ?? null;
    }
    
    /**
     * Busca nível com suas permissões
     */
    public function findWithPermissoes(int $id): ?NivelHierarquicoEntity
    {
        $entity = $this->find($id);
        
        if (!$entity) {
            return null;
        }
        
        $permissoes = $this->buscarPermissoesDoNivel($id);
        $entity->setPermissoes($permissoes);
        
        return $entity;
    }
    
    /**
     * Busca todas as permissões de um nível
     * @return PermissaoNivelEntity[]
     */
    public function buscarPermissoesDoNivel(int $nivelId): array
    {
        $sql = "SELECT * FROM dotp_permissoes_nivel 
                WHERE permissao_nivel_id = ? AND permissao_ativo = 1
                ORDER BY permissao_recurso, permissao_acao";
        
        $rows = $this->db->fetchAll($sql, [$nivelId]);
        
        return array_map([$this, 'hydratePermissao'], $rows);
    }
    
    /**
     * Atualiza as permissões de um nível
     */
    public function atualizarPermissoes(int $nivelId, array $permissoes): void
    {
        // Desativa todas as permissões atuais
        $this->db->execute(
            "UPDATE dotp_permissoes_nivel SET permissao_ativo = 0 WHERE permissao_nivel_id = ?",
            [$nivelId]
        );
        
        // Insere ou atualiza as novas permissões
        foreach ($permissoes as $perm) {
            $sql = "INSERT INTO dotp_permissoes_nivel 
                    (permissao_nivel_id, permissao_recurso, permissao_acao, permissao_escopo, permissao_ativo)
                    VALUES (?, ?, ?, ?, 1)
                    ON DUPLICATE KEY UPDATE 
                    permissao_escopo = VALUES(permissao_escopo),
                    permissao_ativo = 1";
            
            $this->db->execute($sql, [
                $nivelId,
                $perm['recurso'],
                $perm['acao'],
                $perm['escopo'],
            ]);
        }
        
        $this->clearCache();
    }
    
    /**
     * Reordena os níveis
     */
    public function reordenar(array $ordens): void
    {
        // ordens = [id => nova_ordem, ...]
        foreach ($ordens as $id => $ordem) {
            $this->db->execute(
                "UPDATE {$this->table} SET nivel_ordem = ? WHERE nivel_id = ?",
                [$ordem, $id]
            );
        }
        
        $this->clearCache();
    }
    
    /**
     * Retorna a matriz de permissões completa
     */
    public function getMatrizPermissoes(): array
    {
        $cacheKey = $this->cacheKey('matriz');
        $cached = $this->cache->get($cacheKey);
        
        if ($cached) {
            return $cached;
        }
        
        $sql = "SELECT 
                    n.nivel_id,
                    n.nivel_nome,
                    n.nivel_titulo_responsavel,
                    pn.permissao_recurso,
                    pn.permissao_acao,
                    pn.permissao_escopo
                FROM dotp_niveis_hierarquicos n
                LEFT JOIN dotp_permissoes_nivel pn 
                    ON pn.permissao_nivel_id = n.nivel_id AND pn.permissao_ativo = 1
                WHERE n.nivel_ativo = 1
                ORDER BY n.nivel_ordem, pn.permissao_recurso, pn.permissao_acao";
        
        $result = $this->db->fetchAll($sql);
        
        $this->cache->set($cacheKey, $result, 3600);
        
        return $result;
    }
    
    /**
     * Extrai dados da entidade para persistência
     * 
     * @return array<string, mixed>
     */
    protected function extract(object $entity): array
    {
        if (!$entity instanceof NivelHierarquicoEntity) {
            throw new \InvalidArgumentException('Entity must be NivelHierarquicoEntity');
        }
        
        return [
            'nivel_ordem' => $entity->getOrdem(),
            'nivel_nome' => $entity->getNome(),
            'nivel_titulo_responsavel' => $entity->getTituloResponsavel(),
            'nivel_descricao' => $entity->getDescricao(),
            'nivel_cor' => $entity->getCor(),
            'nivel_ativo' => $entity->isAtivo() ? 1 : 0,
        ];
    }

    /**
     * Salva uma entidade (insert ou update)
     * Sobrescreve o método do BaseRepository
     */
    public function save(object $entity): int
    {
        $data = $this->extract($entity);
        
        if ($entity->getId()) {
            // Update
            $fields = [];
            $values = [];
            foreach ($data as $key => $value) {
                $fields[] = "$key = ?";
                $values[] = $value;
            }
            $values[] = $entity->getId();
            
            $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE {$this->primaryKey} = ?";
            $this->db->execute($sql, $values);
            $this->clearCache();
            return $entity->getId();
        } else {
            // Insert
            $columns = array_keys($data);
            $placeholders = array_fill(0, count($columns), '?');
            
            $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $this->db->execute($sql, array_values($data));
            
            $newId = (int) $this->db->lastInsertId();
            $entity->setId($newId);
            $this->clearCache();
            return $newId;
        }
    }

    /**
     * Remove uma entidade
     * Sobrescreve o método do BaseRepository
     */
    public function delete(int $id): bool
    {
        $this->db->execute(
            "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?",
            [$id]
        );
        
        $this->clearCache();
        return $this->db->rowCount() > 0;
    }

    /**
     * Hidrata uma permissão
     */
    private function hydratePermissao(array $row): PermissaoNivelEntity
    {
        $entity = new PermissaoNivelEntity();
        $entity->setId((int) $row['permissao_id']);
        $entity->setNivelId((int) $row['permissao_nivel_id']);
        $entity->setRecurso($row['permissao_recurso']);
        $entity->setAcao($row['permissao_acao']);
        $entity->setEscopo($row['permissao_escopo']);
        $entity->setAtivo((bool) $row['permissao_ativo']);
        
        return $entity;
    }
}
