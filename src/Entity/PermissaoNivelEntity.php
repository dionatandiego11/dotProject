<?php
/**
 * Entidade Permissão por Nível
 * Define as permissões de cada nível hierárquico
 * 
 * @package DotProject\Entity
 */

declare(strict_types=1);

namespace DotProject\Entity;

class PermissaoNivelEntity
{
    private ?int $id = null;
    private int $nivelId;
    private string $recurso;
    private string $acao;
    private string $escopo;
    private bool $ativo = true;
    private ?string $createdAt = null;
    
    // Constantes para recursos
    public const RECURSO_PPA = 'ppa';
    public const RECURSO_PROGRAMA = 'programa';
    public const RECURSO_PROJETO = 'projeto';
    public const RECURSO_ETAPA = 'etapa';
    public const RECURSO_TAREFA = 'tarefa';
    public const RECURSO_RELATORIO = 'relatorio';
    public const RECURSO_ALERTA = 'alerta';
    public const RECURSO_ADMIN = 'admin';
    public const RECURSO_DASHBOARD = 'dashboard';
    
    // Constantes para ações
    public const ACAO_VISUALIZAR = 'visualizar';
    public const ACAO_CRIAR = 'criar';
    public const ACAO_EDITAR = 'editar';
    public const ACAO_EXCLUIR = 'excluir';
    public const ACAO_APROVAR = 'aprovar';
    public const ACAO_ATRIBUIR = 'atribuir';
    public const ACAO_ACESSAR = 'acessar';
    
    // Constantes para escopos
    public const ESCOPO_TODOS = 'todos';
    public const ESCOPO_SUBORDINADOS = 'subordinados';
    public const ESCOPO_UNIDADE = 'unidade';
    public const ESCOPO_PROPRIO = 'proprio';
    public const ESCOPO_EQUIPE = 'equipe';
    
    public function getId(): ?int
    {
        return $this->id;
    }
    
    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }
    
    public function getNivelId(): int
    {
        return $this->nivelId;
    }
    
    public function setNivelId(int $nivelId): self
    {
        $this->nivelId = $nivelId;
        return $this;
    }
    
    public function getRecurso(): string
    {
        return $this->recurso;
    }
    
    public function setRecurso(string $recurso): self
    {
        $this->recurso = $recurso;
        return $this;
    }
    
    public function getAcao(): string
    {
        return $this->acao;
    }
    
    public function setAcao(string $acao): self
    {
        $this->acao = $acao;
        return $this;
    }
    
    public function getEscopo(): string
    {
        return $this->escopo;
    }
    
    public function setEscopo(string $escopo): self
    {
        $this->escopo = $escopo;
        return $this;
    }
    
    public function isAtivo(): bool
    {
        return $this->ativo;
    }
    
    public function setAtivo(bool $ativo): self
    {
        $this->ativo = $ativo;
        return $this;
    }
    
    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }
    
    /**
     * Retorna label legível para o escopo
     */
    public function getEscopoLabel(): string
    {
        return match($this->escopo) {
            self::ESCOPO_TODOS => '✅ Todos',
            self::ESCOPO_SUBORDINADOS => '🔶 Subordinados',
            self::ESCOPO_UNIDADE => '🏢 Unidade',
            self::ESCOPO_PROPRIO => '👤 Próprio',
            self::ESCOPO_EQUIPE => '👥 Equipe',
            default => $this->escopo,
        };
    }
    
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'nivel_id' => $this->nivelId,
            'recurso' => $this->recurso,
            'acao' => $this->acao,
            'escopo' => $this->escopo,
            'escopo_label' => $this->getEscopoLabel(),
            'ativo' => $this->ativo,
            'created_at' => $this->createdAt,
        ];
    }
}
