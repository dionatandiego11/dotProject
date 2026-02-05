<?php
/**
 * Service de Permissões Hierárquicas
 * Verifica acesso baseado na estrutura organizacional
 * 
 * @package DotProject\Service
 */

declare(strict_types=1);

namespace DotProject\Service;

use DotProject\Core\Cache;
use DotProject\Repository\NivelHierarquicoRepository;
use DotProject\Repository\UnidadeOrganizacionalRepository;
use DotProject\Repository\UsuarioUnidadeRepository;

class PermissionService
{
    private NivelHierarquicoRepository $nivelRepo;
    private UnidadeOrganizacionalRepository $unidadeRepo;
    private UsuarioUnidadeRepository $vinculoRepo;
    private Cache $cache;
    
    // Roles do sistema
    public const ROLE_PREFEITO = 'PREFEITO';
    public const ROLE_SECRETARIO = 'SECRETARIO';
    public const ROLE_COORDENADOR = 'COORDENADOR';
    public const ROLE_TECNICO = 'TECNICO';
    public const ROLE_CONTROLADOR = 'CONTROLADOR';
    public const ROLE_CHEFE = 'CHEFE';
    
    public function __construct()
    {
        $this->nivelRepo = new NivelHierarquicoRepository();
        $this->unidadeRepo = new UnidadeOrganizacionalRepository();
        $this->vinculoRepo = new UsuarioUnidadeRepository();
        $this->cache = new Cache();
    }
    
    /**
     * Verifica se o usuário pode realizar uma ação em um recurso
     */
    public function can(int $userId, string $recurso, string $acao, ?int $recursoId = null): bool
    {
        $vinculoPrincipal = $this->vinculoRepo->findPrincipal($userId);
        
        if (!$vinculoPrincipal) {
            return false;
        }
        
        $role = $vinculoPrincipal['vinculo_role'];
        $unidadeId = $vinculoPrincipal['vinculo_unidade_id'];
        $nivelId = $vinculoPrincipal['unidade_nivel'];
        
        // Prefeito e Controlador podem tudo
        if (in_array($role, [self::ROLE_PREFEITO, self::ROLE_CONTROLADOR], true)) {
            return true;
        }
        
        // Busca permissões do nível
        $permissoes = $this->getPermissoesDoNivel($nivelId);
        
        // Verifica se tem a permissão específica
        $temPermissao = false;
        $escopo = null;
        
        foreach ($permissoes as $perm) {
            if ($perm['recurso'] === $recurso && $perm['acao'] === $acao) {
                $temPermissao = true;
                $escopo = $perm['escopo'];
                break;
            }
        }
        
        if (!$temPermissao) {
            return false;
        }
        
        // Se não tem recursoId específico, apenas verificou a permissão
        if ($recursoId === null) {
            return true;
        }
        
        // Verifica escopo para o recurso específico
        return $this->verificarEscopo($userId, $unidadeId, $role, $escopo, $recurso, $recursoId);
    }
    
    /**
     * Retorna as permissões de um nível (com cache)
     */
    public function getPermissoesDoNivel(int $nivelId): array
    {
        $cacheKey = "permissoes_nivel_{$nivelId}";
        $cached = $this->cache->get($cacheKey);
        
        if ($cached) {
            return $cached;
        }
        
        $permissoes = $this->nivelRepo->buscarPermissoesDoNivel($nivelId);
        $result = array_map(fn($p) => $p->toArray(), $permissoes);
        
        $this->cache->set($cacheKey, $result, 3600);
        
        return $result;
    }
    
    /**
     * Retorna o escopo de dados do usuário
     */
    public function getEscopoDados(int $userId): ?array
    {
        $cacheKey = "escopo_dados_{$userId}";
        $cached = $this->cache->get($cacheKey);
        
        if ($cached) {
            return $cached;
        }

        [$role, $unidadeId, $nivel] = $this->resolveRoleAndUnit($userId);

        if ($unidadeId === null && $role !== self::ROLE_PREFEITO) {
            return null;
        }

        if ($role === self::ROLE_PREFEITO) {
            $unidadesEscopo = $this->getTodasUnidadesAtivas();
        } else {
            $unidadesEscopo = $this->getUnidadesNoEscopo((int) $unidadeId, (int) $nivel, $role);
        }

        $escopo = [
            'user_id' => $userId,
            'role' => $role,
            'unidade_id' => $unidadeId,
            'nivel' => $nivel,
            'unidades_escopo' => $unidadesEscopo,
            'eh_gestor' => in_array($role, [self::ROLE_PREFEITO, self::ROLE_SECRETARIO, self::ROLE_COORDENADOR, self::ROLE_CHEFE], true),
        ];
        
        $this->cache->set($cacheKey, $escopo, 300);
        
        return $escopo;
    }
    
    /**
     * Verifica se o usuário pode acessar uma unidade específica
     */
    public function podeAcessarUnidade(int $userId, int $unidadeId): bool
    {
        $escopo = $this->getEscopoDados($userId);
        
        if (!$escopo) {
            return false;
        }
        
        // Prefeito e Controlador acessam tudo
        if (in_array($escopo['role'], [self::ROLE_PREFEITO, self::ROLE_CONTROLADOR], true)) {
            return true;
        }
        
        // Verifica se a unidade está no escopo
        return in_array($unidadeId, $escopo['unidades_escopo'], true);
    }
    
    /**
     * Retorna filtro SQL para unidades no escopo do usuário
     */
    public function getFiltroUnidades(int $userId, string $tabelaAlias = 'u'): ?string
    {
        $escopo = $this->getEscopoDados($userId);
        
        if (!$escopo) {
            return null;
        }
        
        // Prefeito e Controlador não precisam de filtro
        if (in_array($escopo['role'], [self::ROLE_PREFEITO, self::ROLE_CONTROLADOR], true)) {
            return null;
        }
        
        $unidades = $escopo['unidades_escopo'];
        
        if (empty($unidades)) {
            return "{$tabelaAlias}.unidade_id = -1"; // Nenhuma unidade
        }
        
        $ids = implode(',', $unidades);
        return "{$tabelaAlias}.unidade_id IN ({$ids})";
    }
    
    /**
     * Retorna IDs de unidades no escopo do usuário
     */
    private function getUnidadesNoEscopo(int $unidadeId, int $nivel, string $role): array
    {
        $unidades = [$unidadeId];
        
        // Secret?rio v? sua secretaria + todas subordinadas
        if ($role === self::ROLE_SECRETARIO && $nivel <= 2) {
            $descendentes = $this->unidadeRepo->findTodosDescendentesIds($unidadeId);
            $unidades = array_merge($unidades, $descendentes);
        }

        // Chefe/coordenador v? apenas sua unidade
        // Para t?cnicos e outros, retorna apenas sua unidade
        
        return array_unique($unidades);
    }
    
    /**
     * Verifica escopo para um recurso específico
     */
    private function verificarEscopo(int $userId, int $unidadeId, string $role, ?string $escopo, string $recurso, int $recursoId): bool
    {
        if (!$escopo) {
            return false;
        }
        
        // Escopo todos: sempre pode
        if ($escopo === 'todos') {
            return true;
        }
        
        // Escopo próprio: verifica se é o responsável pelo recurso
        if ($escopo === 'proprio') {
            return $this->isResponsavelDoRecurso($userId, $recurso, $recursoId);
        }
        
        // Escopo unidade: verifica se o recurso está na mesma unidade
        if ($escopo === 'unidade') {
            return $this->isRecursoNaUnidade($unidadeId, $recurso, $recursoId);
        }
        
        // Escopo subordinados: verifica se o recurso está em unidade subordinada
        if ($escopo === 'subordinados') {
            $descendentes = $this->unidadeRepo->findTodosDescendentesIds($unidadeId);
            $unidades = array_merge([$unidadeId], $descendentes);
            return $this->isRecursoEmUnidades($unidades, $recurso, $recursoId);
        }
        
        // Escopo equipe: verifica se o recurso pertence à equipe
        if ($escopo === 'equipe') {
            return $this->isRecursoNaEquipe($userId, $recurso, $recursoId);
        }
        
        return false;
    }
    
    /**
     * Verifica se o usuário é responsável direto por um recurso
     */
    private function isResponsavelDoRecurso(int $userId, string $recurso, int $recursoId): bool
    {
        $db = \DotProject\Core\Database::getInstance();
        
        return match($recurso) {
            'projeto' => (bool) $db->fetchColumn(
                "SELECT 1 FROM dotp_projetos_prefeitura WHERE id = ? AND coordenador_id = ?",
                [$recursoId, $userId]
            ),
            'programa' => (bool) $db->fetchColumn(
                "SELECT 1 FROM dotp_programas WHERE id = ? AND unidade_id IN (
                    SELECT vinculo_unidade_id FROM dotp_usuario_unidades 
                    WHERE vinculo_user_id = ? AND vinculo_status = 'ativo'
                )",
                [$recursoId, $userId]
            ),
            'tarefa' => (bool) $db->fetchColumn(
                "SELECT 1 FROM dotp_tasks WHERE task_id = ? AND task_owner = ?",
                [$recursoId, $userId]
            ),
            default => false,
        };
    }
    
    /**
     * Verifica se um recurso está em uma unidade específica
     */
    private function isRecursoNaUnidade(int $unidadeId, string $recurso, int $recursoId): bool
    {
        $db = \DotProject\Core\Database::getInstance();
        
        return match($recurso) {
            'projeto' => (bool) $db->fetchColumn(
                "SELECT 1 FROM dotp_projetos_prefeitura WHERE id = ? AND unidade_id = ?",
                [$recursoId, $unidadeId]
            ),
            'programa' => (bool) $db->fetchColumn(
                "SELECT 1 FROM dotp_programas WHERE id = ? AND unidade_id = ?",
                [$recursoId, $unidadeId]
            ),
            default => false,
        };
    }
    
    /**
     * Verifica se um recurso está em alguma das unidades
     */
    private function isRecursoEmUnidades(array $unidades, string $recurso, int $recursoId): bool
    {
        if (empty($unidades)) {
            return false;
        }
        
        $db = \DotProject\Core\Database::getInstance();
        $placeholders = implode(',', array_fill(0, count($unidades), '?'));
        
        return match($recurso) {
            'projeto' => (bool) $db->fetchColumn(
                "SELECT 1 FROM dotp_projetos_prefeitura WHERE id = ? AND unidade_id IN ({$placeholders})",
                array_merge([$recursoId], $unidades)
            ),
            'programa' => (bool) $db->fetchColumn(
                "SELECT 1 FROM dotp_programas WHERE id = ? AND unidade_id IN ({$placeholders})",
                array_merge([$recursoId], $unidades)
            ),
            default => false,
        };
    }
    
    /**
     * Verifica se um recurso pertence à equipe do usuário
     */
    private function isRecursoNaEquipe(int $userId, string $recurso, int $recursoId): bool
    {
        $db = \DotProject\Core\Database::getInstance();
        
        // Busca unidades onde o usuário é gestor
        $unidadesGestor = $db->fetchAll(
            "SELECT vinculo_unidade_id FROM dotp_usuario_unidades 
             WHERE vinculo_user_id = ? AND vinculo_status = 'ativo' 
             AND vinculo_role IN (?, ?)",
            [$userId, self::ROLE_COORDENADOR, self::ROLE_SECRETARIO]
        );
        
        if (empty($unidadesGestor)) {
            return false;
        }
        
        $unidades = array_column($unidadesGestor, 'vinculo_unidade_id');
        return $this->isRecursoEmUnidades($unidades, $recurso, $recursoId);
    }

    private function resolveRoleAndUnit(int $userId): array
    {
        $db = \DotProject\Core\Database::getInstance();

        $responsaveis = $db->fetchAll(
            "SELECT unidade_id, unidade_nivel_id, unidade_pai_id
             FROM dotp_unidades_organizacionais
             WHERE unidade_responsavel_id = ?
               AND unidade_status = 'ativo'",
            [$userId]
        );

        // Prefeito = responsavel da raiz (sem pai ou nivel 1)
        foreach ($responsaveis as $row) {
            if ($row['unidade_pai_id'] === null || (int) $row['unidade_nivel_id'] === 1) {
                return [self::ROLE_PREFEITO, (int) $row['unidade_id'], (int) $row['unidade_nivel_id']];
            }
        }

        // Secretario = responsavel por secretaria (nivel 2)
        foreach ($responsaveis as $row) {
            if ((int) $row['unidade_nivel_id'] === 2) {
                return [self::ROLE_SECRETARIO, (int) $row['unidade_id'], (int) $row['unidade_nivel_id']];
            }
        }

        // Chefe = responsavel por qualquer outra unidade
        if (!empty($responsaveis)) {
            $row = $responsaveis[0];
            return [self::ROLE_CHEFE, (int) $row['unidade_id'], (int) $row['unidade_nivel_id']];
        }

        // Sem responsabilidade: usa vinculo principal como tecnico
        $vinculo = $this->vinculoRepo->findPrincipal($userId);
        if ($vinculo) {
            return [self::ROLE_TECNICO, (int) $vinculo['vinculo_unidade_id'], (int) $vinculo['unidade_nivel']];
        }

        return [self::ROLE_TECNICO, null, null];
    }

    private function getTodasUnidadesAtivas(): array
    {
        $db = \DotProject\Core\Database::getInstance();
        $rows = $db->fetchAll("SELECT unidade_id FROM dotp_unidades_organizacionais WHERE unidade_status = 'ativo'");
        return array_map(fn($row) => (int) $row['unidade_id'], $rows);
    }
    
    /**
     * Limpa o cache de permissões de um usuário
     */
    public function clearUserCache(int $userId): void
    {
        $this->cache->delete("escopo_dados_{$userId}");
    }
    
    /**
     * Retorna o dashboard apropriado para o usuário baseado na role
     */
    public function getDashboardType(int $userId): string
    {
        [$role] = $this->resolveRoleAndUnit($userId);

        return match($role) {
            self::ROLE_PREFEITO => 'prefeito',
            self::ROLE_SECRETARIO => 'secretario',
            self::ROLE_COORDENADOR, self::ROLE_CHEFE => 'coordenador',
            self::ROLE_CONTROLADOR => 'controlador',
            default => 'tecnico',
        };
    }
}
