<?php
/**
 * Repository para Unidades Organizacionais
 * 
 * @package DotProject\Repository
 */

declare(strict_types=1);

namespace DotProject\Repository;

use DotProject\Entity\UnidadeOrganizacionalEntity;

class UnidadeOrganizacionalRepository extends BaseRepository
{
    protected string $table = 'dotp_unidades_organizacionais';
    protected string $primaryKey = 'unidade_id';

    private ?string $nivelColumn = null;
    private ?string $statusColumn = null;
    
    /**
     * Hidrata a entidade com dados do banco
     */
    protected function hydrate(array $row): UnidadeOrganizacionalEntity
    {
        $nivelColumn = $this->resolveNivelColumn();
        $statusColumn = $this->resolveStatusColumn();

        $entity = new UnidadeOrganizacionalEntity();
        $entity->setId((int) $row['unidade_id']);
        $entity->setPaiId($row['unidade_pai_id'] ? (int) $row['unidade_pai_id'] : null);
        $entity->setNome($row['unidade_nome']);
        $entity->setNivel((int) $row[$nivelColumn]);
        $entity->setSigla($row['unidade_sigla'] ?? null);
        $entity->setDescricao($row['unidade_descricao'] ?? null);
        $entity->setEndereco($row['unidade_endereco'] ?? null);
        $entity->setEmail($row['unidade_email'] ?? null);
        $entity->setTelefone($row['unidade_telefone'] ?? null);
        $entity->setResponsavelId($row['unidade_responsavel_id'] ? (int) $row['unidade_responsavel_id'] : null);
        if (isset($row['responsavel_nome'])) {
            $entity->setResponsavelNome($row['responsavel_nome']);
        }
        if (isset($row['responsavel_email'])) {
            $entity->setResponsavelEmail($row['responsavel_email']);
        }
        if (isset($row['responsavel_telefone'])) {
            $entity->setResponsavelTelefone($row['responsavel_telefone']);
        }
        $entity->setAtiva($this->isRowAtiva($row, $statusColumn));
        $entity->setPodeCriarProjetos((bool) ($row['unidade_pode_criar_projetos'] ?? true));
        $entity->setPodeCriarProgramas((bool) ($row['unidade_pode_criar_programas'] ?? false));
        
        return $entity;
    }
    
    /**
     * Converte entidade para array para persistência
     */
    protected function toArray(object $entity): array
    {
        if (!$entity instanceof UnidadeOrganizacionalEntity) {
            throw new \InvalidArgumentException('Entity must be UnidadeOrganizacionalEntity');
        }

        $nivelColumn = $this->resolveNivelColumn();
        $statusColumn = $this->resolveStatusColumn();
        
        return [
            'unidade_pai_id' => $entity->getPaiId(),
            'unidade_nome' => $entity->getNome(),
            $nivelColumn => $entity->getNivel(),
            'unidade_sigla' => $entity->getSigla(),
            'unidade_descricao' => $entity->getDescricao(),
            'unidade_endereco' => $entity->getEndereco(),
            'unidade_email' => $entity->getEmail(),
            'unidade_telefone' => $entity->getTelefone(),
            'unidade_responsavel_id' => $entity->getResponsavelId(),
            $statusColumn => $this->statusValueForSql($entity->isAtiva(), $statusColumn),
            'unidade_pode_criar_projetos' => $entity->podeCriarProjetos() ? 1 : 0,
            'unidade_pode_criar_programas' => $entity->podeCriarProgramas() ? 1 : 0,
        ];
    }
    
    /**
     * Busca unidades em estrutura de árvore
     * @return UnidadeOrganizacionalEntity[]
     */
    public function findArvore(?int $raizId = null): array
    {
        $cacheKey = $this->cacheKey('arvore:' . ($raizId ?? 'all'));
        $cached = $this->cache->get($cacheKey);
        
        if ($cached) {
            return $cached;
        }
        
        $statusFilter = $this->getStatusFilterSql('u');
        $nivelColumn = $this->resolveNivelColumn();
        // Busca todas as unidades ativas com responsavel (se existir)
        $sql = "SELECT 
                    u.*,
                    COALESCE(c.contact_first_name, '') as responsavel_primeiro_nome,
                    COALESCE(c.contact_last_name, '') as responsavel_ultimo_nome,
                    c.contact_email as responsavel_email,
                    c.contact_phone as responsavel_telefone,
                    uo.user_username as responsavel_username
                FROM {$this->table} u
                LEFT JOIN dotp_users uo ON uo.user_id = u.unidade_responsavel_id
                LEFT JOIN dotp_contacts c ON c.contact_id = uo.user_contact
                WHERE {$statusFilter}{$this->tenantCondition('u')}
                ORDER BY u.{$nivelColumn}, u.unidade_nome";
        $rows = $this->db->fetchAll($sql);
        
        $unidades = [];
        foreach ($rows as $row) {
            $nomeCompleto = trim(($row['responsavel_primeiro_nome'] ?? '') . ' ' . ($row['responsavel_ultimo_nome'] ?? ''));
            $row['responsavel_nome'] = $nomeCompleto !== '' ? $nomeCompleto : ($row['responsavel_username'] ?? null);
            $unidades[$row['unidade_id']] = $this->hydrate($row);
        }
        
        // Monta as relacoes pai/filha
        $arvore = [];
        foreach ($unidades as $id => $unidade) {
            $paiId = $unidade->getPaiId();
            
            if ($paiId === null) {
                // É raiz global
                if ($raizId === null) {
                    $arvore[$id] = $unidade;
                }
            } else if (isset($unidades[$paiId])) {
                // Tem pai na lista
                $unidades[$paiId]->addFilha($unidade);
                $unidade->setPai($unidades[$paiId]);
            }
        }

        // Quando raizId é informado, retorna a subárvore da unidade alvo
        // mesmo que ela não seja raiz global.
        if ($raizId !== null) {
            $roots = isset($unidades[$raizId]) ? [$unidades[$raizId]] : [];
        } else {
            $roots = array_values($arvore);
        }

        $this->cache->set($cacheKey, $roots, 300);
        
        return $roots;
    }
    
    /**
     * Busca unidades por nível
     * @return UnidadeOrganizacionalEntity[]
     */
    public function findByNivel(int $nivel): array
    {
        $nivelColumn = $this->resolveNivelColumn();
        $statusColumn = $this->resolveStatusColumn();
        $statusValue = $this->statusValueForSql(true, $statusColumn);
        return $this->findBy([$nivelColumn => $nivel, $statusColumn => $statusValue], ['unidade_nome' => 'ASC']);
    }

    /**
     * Busca todas as unidades ativas
     * @return UnidadeOrganizacionalEntity[]
     */
    public function findAllAtivas(): array
    {
        $statusColumn = $this->resolveStatusColumn();
        $statusValue = $this->statusValueForSql(true, $statusColumn);
        return $this->findBy([$statusColumn => $statusValue], ['unidade_nome' => 'ASC']);
    }
    
    /**
     * Busca unidades filhas de uma unidade
     * @return UnidadeOrganizacionalEntity[]
     */
    public function findFilhas(int $paiId): array
    {
        $statusColumn = $this->resolveStatusColumn();
        $statusValue = $this->statusValueForSql(true, $statusColumn);
        return $this->findBy(['unidade_pai_id' => $paiId, $statusColumn => $statusValue], ['unidade_nome' => 'ASC']);
    }
    
    /**
     * Busca unidades por responsável
     * @return UnidadeOrganizacionalEntity[]
     */
    public function findByResponsavel(int $responsavelId): array
    {
        $statusColumn = $this->resolveStatusColumn();
        $statusValue = $this->statusValueForSql(true, $statusColumn);
        return $this->findBy(['unidade_responsavel_id' => $responsavelId, $statusColumn => $statusValue]);
    }
    
    /**
     * Busca todos os IDs descendentes (recursivo)
     * @return int[]
     */
    public function findTodosDescendentesIds(int $unidadeId): array
    {
        $descendentes = [];
        $filhas = $this->findFilhas($unidadeId);
        
        foreach ($filhas as $filha) {
            $descendentes[] = $filha->getId();
            $descendentes = array_merge($descendentes, $this->findTodosDescendentesIds($filha->getId()));
        }
        
        return $descendentes;
    }
    
    /**
     * Busca o caminho completo até a raiz
     * @return UnidadeOrganizacionalEntity[]
     */
    public function findCaminhoAteRaiz(int $unidadeId): array
    {
        $caminho = [];
        $atual = $this->find($unidadeId);
        
        while ($atual !== null) {
            $caminho[] = $atual;
            $paiId = $atual->getPaiId();
            $atual = $paiId ? $this->find($paiId) : null;
        }
        
        return array_reverse($caminho);
    }
    
    /**
     * Busca a secretaria de uma unidade (sobe na árvore até nível 2)
     */
    public function findSecretaria(int $unidadeId): ?UnidadeOrganizacionalEntity
    {
        $caminho = $this->findCaminhoAteRaiz($unidadeId);
        
        foreach ($caminho as $unidade) {
            if ($unidade->getNivel() === 2) {
                return $unidade;
            }
        }
        
        return null;
    }
    
    /**
     * Busca estatísticas para o dashboard admin
     */
    public function getEstatisticas(): array
    {
        $cacheKey = $this->cacheKey('stats');
        $cached = $this->cache->get($cacheKey);
        
        if ($cached) {
            return $cached;
        }
        
        $nivelColumn = $this->resolveNivelColumn();
        $statusFilter = $this->getStatusFilterSql();
        $tenantCondition = $this->tenantCondition();
        $stats = [
            'total_unidades' => (int) $this->db->fetchColumn("SELECT COUNT(*) FROM {$this->table} WHERE {$statusFilter}{$tenantCondition}"),
            'total_niveis' => (int) $this->db->fetchColumn("SELECT COUNT(DISTINCT {$nivelColumn}) FROM {$this->table} WHERE {$statusFilter}{$tenantCondition}"),
            'unidades_por_nivel' => $this->db->fetchAll("SELECT {$nivelColumn} as nivel, COUNT(*) as total FROM {$this->table} WHERE {$statusFilter}{$tenantCondition} GROUP BY {$nivelColumn} ORDER BY {$nivelColumn}"),
            'sem_responsavel' => (int) $this->db->fetchColumn("SELECT COUNT(*) FROM {$this->table} WHERE {$statusFilter}{$tenantCondition} AND unidade_responsavel_id IS NULL"),
            'unidades_raiz' => (int) $this->db->fetchColumn("SELECT COUNT(*) FROM {$this->table} WHERE {$statusFilter}{$tenantCondition} AND unidade_pai_id IS NULL"),
        ];
        
        $this->cache->set($cacheKey, $stats, 300);
        
        return $stats;
    }
    
    /**
     * Move uma unidade na árvore
     */
    public function mover(int $unidadeId, ?int $novoPaiId): void
    {
        // Validação: não pode mover para si mesma ou descendente
        if ($novoPaiId !== null) {
            $descendentes = $this->findTodosDescendentesIds($unidadeId);
            if (in_array($novoPaiId, $descendentes, true)) {
                throw new \InvalidArgumentException('Não é possível mover uma unidade para dentro de seus descendentes');
            }
        }
        
        $tenantCondition = $this->tenantCondition();
        $this->db->execute(
            "UPDATE {$this->table} SET unidade_pai_id = ? WHERE unidade_id = ?{$tenantCondition}",
            [$novoPaiId, $unidadeId]
        );
        
        $this->clearCache();
    }

    /**
     * Extrai dados da entidade para persistência (método abstrato da classe pai)
     * 
     * @return array<string, mixed>
     */
    protected function extract(object $entity): array
    {
        if (!$entity instanceof UnidadeOrganizacionalEntity) {
            throw new \InvalidArgumentException('Entity must be UnidadeOrganizacionalEntity');
        }

        $nivelColumn = $this->resolveNivelColumn();
        $statusColumn = $this->resolveStatusColumn();
        
        return [
            'unidade_pai_id' => $entity->getPaiId(),
            'unidade_nome' => $entity->getNome(),
            $nivelColumn => $entity->getNivel(),
            'unidade_sigla' => $entity->getSigla(),
            'unidade_descricao' => $entity->getDescricao(),
            'unidade_endereco' => $entity->getEndereco(),
            'unidade_email' => $entity->getEmail(),
            'unidade_telefone' => $entity->getTelefone(),
            'unidade_responsavel_id' => $entity->getResponsavelId(),
            $statusColumn => $this->statusValueForSql($entity->isAtiva(), $statusColumn),
            'unidade_pode_criar_projetos' => $entity->podeCriarProjetos() ? 1 : 0,
            'unidade_pode_criar_programas' => $entity->podeCriarProgramas() ? 1 : 0,
        ];
    }

    /**
     * Salva uma entidade (insert ou update)
     */
    public function save(object $entity): int
    {
        $data = $this->extract($entity);
        $tenantColumn = $this->getTenantColumn();
        $tenantId = $this->getTenantId();
        $applyTenant = $this->shouldApplyTenantScope() && $tenantColumn !== null && $tenantId !== null;
        if ($applyTenant && (!array_key_exists($tenantColumn, $data) || $data[$tenantColumn] === null || $data[$tenantColumn] === '')) {
            $data[$tenantColumn] = $tenantId;
        }
        
        if ($entity->getId()) {
            $fields = [];
            $values = [];
            foreach ($data as $key => $value) {
                $fields[] = "$key = ?";
                $values[] = $value;
            }
            $values[] = $entity->getId();
            
            $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE {$this->primaryKey} = ?";
            if ($applyTenant) {
                $sql .= " AND {$tenantColumn} = ?";
                $values[] = $tenantId;
            }
            $this->db->execute($sql, $values);
            $this->clearCache();
            return (int) $entity->getId();
        }
        
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $this->db->execute($sql, array_values($data));
        
        $newId = (int) $this->db->lastInsertId();
        $entity->setId($newId);
        $this->clearCache();
        return $newId;
    }

    /**
     * Remove uma entidade
     */
    public function delete(int $id): bool
    {
        $params = [$id];
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $sql = $this->appendTenantScopeToSql($sql, $params);
        $result = $this->db->execute($sql, $params);
        
        $this->clearCache();
        return $result;
    }

    private function resolveNivelColumn(): string
    {
        if ($this->nivelColumn !== null) {
            return $this->nivelColumn;
        }

        $exists = (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?",
            [$this->table, 'unidade_nivel_id']
        );

        $this->nivelColumn = $exists > 0 ? 'unidade_nivel_id' : 'unidade_nivel';
        return $this->nivelColumn;
    }

    private function resolveStatusColumn(): string
    {
        if ($this->statusColumn !== null) {
            return $this->statusColumn;
        }

        $exists = (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?",
            [$this->table, 'unidade_ativa']
        );

        $this->statusColumn = $exists > 0 ? 'unidade_ativa' : 'unidade_status';
        return $this->statusColumn;
    }

    private function statusValueForSql(bool $ativa, string $statusColumn): int|string
    {
        if ($statusColumn === 'unidade_ativa') {
            return $ativa ? 1 : 0;
        }

        return $ativa ? 'ativo' : 'inativo';
    }

    private function isRowAtiva(array $row, string $statusColumn): bool
    {
        if (!array_key_exists($statusColumn, $row)) {
            return true;
        }

        if ($statusColumn === 'unidade_ativa') {
            return (bool) $row[$statusColumn];
        }

        return (string) $row[$statusColumn] === 'ativo';
    }

    private function getStatusFilterSql(string $alias = null): string
    {
        $statusColumn = $this->resolveStatusColumn();
        $column = $alias ? "{$alias}.{$statusColumn}" : $statusColumn;
        if ($statusColumn === 'unidade_ativa') {
            return "{$column} = 1";
        }
        return "{$column} = 'ativo'";
    }

    private function tenantCondition(?string $alias = null): string
    {
        if (!$this->shouldApplyTenantScope()) {
            return '';
        }

        $tenantColumn = $this->getTenantColumn();
        $tenantId = $this->getTenantId();
        if ($tenantColumn === null || $tenantId === null) {
            return '';
        }

        $column = $alias !== null && $alias !== ''
            ? $alias . '.' . $tenantColumn
            : $tenantColumn;

        return " AND {$column} = {$tenantId}";
    }
}
