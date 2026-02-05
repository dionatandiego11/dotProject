<?php
/**
 * Entidade Nível Hierárquico
 * Define os níveis da estrutura organizacional da prefeitura
 * 
 * @package DotProject\Entity
 */

declare(strict_types=1);

namespace DotProject\Entity;

class NivelHierarquicoEntity
{
    private ?int $id = null;
    private int $ordem;
    private string $nome;
    private ?string $tituloResponsavel = null;
    private ?string $descricao = null;
    private string $cor = '#007bff';
    private bool $ativo = true;
    private ?string $createdAt = null;
    private ?string $updatedAt = null;
    
    /** @var PermissaoNivelEntity[] */
    private array $permissoes = [];
    
    public function getId(): ?int
    {
        return $this->id;
    }
    
    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }
    
    public function getOrdem(): int
    {
        return $this->ordem;
    }
    
    public function setOrdem(int $ordem): self
    {
        $this->ordem = $ordem;
        return $this;
    }
    
    public function getNome(): string
    {
        return $this->nome;
    }
    
    public function setNome(string $nome): self
    {
        $this->nome = $nome;
        return $this;
    }
    
    public function getTituloResponsavel(): ?string
    {
        return $this->tituloResponsavel;
    }
    
    public function setTituloResponsavel(?string $titulo): self
    {
        $this->tituloResponsavel = $titulo;
        return $this;
    }
    
    public function getDescricao(): ?string
    {
        return $this->descricao;
    }
    
    public function setDescricao(?string $descricao): self
    {
        $this->descricao = $descricao;
        return $this;
    }
    
    public function getCor(): string
    {
        return $this->cor;
    }
    
    public function setCor(string $cor): self
    {
        $this->cor = $cor;
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
    
    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }
    
    /**
     * @return PermissaoNivelEntity[]
     */
    public function getPermissoes(): array
    {
        return $this->permissoes;
    }
    
    public function setPermissoes(array $permissoes): self
    {
        $this->permissoes = $permissoes;
        return $this;
    }
    
    public function addPermissao(PermissaoNivelEntity $permissao): void
    {
        $this->permissoes[] = $permissao;
    }
    
    /**
     * Verifica se este nível pode realizar uma ação em um recurso
     */
    public function pode(string $recurso, string $acao): bool
    {
        foreach ($this->permissoes as $permissao) {
            if ($permissao->getRecurso() === $recurso && $permissao->getAcao() === $acao) {
                return $permissao->isAtivo();
            }
        }
        return false;
    }
    
    /**
     * Retorna o escopo de uma permissão
     */
    public function getEscopo(string $recurso, string $acao): ?string
    {
        foreach ($this->permissoes as $permissao) {
            if ($permissao->getRecurso() === $recurso && $permissao->getAcao() === $acao) {
                return $permissao->getEscopo();
            }
        }
        return null;
    }
    
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'ordem' => $this->ordem,
            'nome' => $this->nome,
            'titulo_responsavel' => $this->tituloResponsavel,
            'descricao' => $this->descricao,
            'cor' => $this->cor,
            'ativo' => $this->ativo,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'permissoes' => array_map(fn($p) => $p->toArray(), $this->permissoes),
        ];
    }
    
    public function toArraySimple(): array
    {
        return [
            'id' => $this->id,
            'ordem' => $this->ordem,
            'nome' => $this->nome,
            'titulo_responsavel' => $this->tituloResponsavel,
            'cor' => $this->cor,
            'ativo' => $this->ativo,
        ];
    }
}
