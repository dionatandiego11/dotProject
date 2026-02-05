<?php
/**
 * Entidade Unidade Organizacional
 * Árvore hierárquica da prefeitura
 * 
 * @package DotProject\Entity
 */

declare(strict_types=1);

namespace DotProject\Entity;

class UnidadeOrganizacionalEntity
{
    private ?int $id = null;
    private ?int $paiId = null;
    private ?self $pai = null;
    private string $nome;
    private int $nivel; // 1=Prefeitura, 2=Secretaria, 3=Coordenação
    private ?string $sigla = null;
    private ?string $descricao = null;
    private ?string $endereco = null;
    private ?string $email = null;
    private ?string $telefone = null;
    private ?int $responsavelId = null;
    private ?string $responsavelNome = null;
    private ?string $responsavelEmail = null;
    private ?string $responsavelTelefone = null;
    private bool $ativa = true;
    private bool $podeCriarProjetos = true;
    private bool $podeCriarProgramas = false;
    private ?string $createdAt = null;
    private ?string $updatedAt = null;
    
    /** @var self[] */
    private array $filhas = [];
    
    /** @var array */
    private array $vinculos = [];
    
    /** @var NivelHierarquicoEntity|null */
    private ?NivelHierarquicoEntity $nivelHierarquico = null;
    
    public function getId(): ?int
    {
        return $this->id;
    }
    
    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }
    
    public function getPaiId(): ?int
    {
        return $this->paiId;
    }
    
    public function setPaiId(?int $id): self
    {
        $this->paiId = $id;
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
    
    public function getNivel(): int
    {
        return $this->nivel;
    }
    
    public function setNivel(int $nivel): self
    {
        $this->nivel = $nivel;
        return $this;
    }
    
    public function getSigla(): ?string
    {
        return $this->sigla;
    }
    
    public function setSigla(?string $sigla): self
    {
        $this->sigla = $sigla;
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
    
    public function getEndereco(): ?string
    {
        return $this->endereco;
    }
    
    public function setEndereco(?string $endereco): self
    {
        $this->endereco = $endereco;
        return $this;
    }
    
    public function getEmail(): ?string
    {
        return $this->email;
    }
    
    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }
    
    public function getTelefone(): ?string
    {
        return $this->telefone;
    }
    
    public function setTelefone(?string $telefone): self
    {
        $this->telefone = $telefone;
        return $this;
    }
    
    public function getResponsavelId(): ?int
    {
        return $this->responsavelId;
    }
    
    public function setResponsavelId(?int $responsavelId): self
    {
        $this->responsavelId = $responsavelId;
        return $this;
    }

    public function getResponsavelNome(): ?string
    {
        return $this->responsavelNome;
    }

    public function setResponsavelNome(?string $nome): self
    {
        $this->responsavelNome = $nome;
        return $this;
    }

    public function getResponsavelEmail(): ?string
    {
        return $this->responsavelEmail;
    }

    public function setResponsavelEmail(?string $email): self
    {
        $this->responsavelEmail = $email;
        return $this;
    }

    public function getResponsavelTelefone(): ?string
    {
        return $this->responsavelTelefone;
    }

    public function setResponsavelTelefone(?string $telefone): self
    {
        $this->responsavelTelefone = $telefone;
        return $this;
    }
    
    public function isAtiva(): bool
    {
        return $this->ativa;
    }
    
    public function setAtiva(bool $ativa): self
    {
        $this->ativa = $ativa;
        return $this;
    }
    
    public function podeCriarProjetos(): bool
    {
        return $this->podeCriarProjetos;
    }
    
    public function setPodeCriarProjetos(bool $pode): self
    {
        $this->podeCriarProjetos = $pode;
        return $this;
    }
    
    public function podeCriarProgramas(): bool
    {
        return $this->podeCriarProgramas;
    }
    
    public function setPodeCriarProgramas(bool $pode): self
    {
        $this->podeCriarProgramas = $pode;
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
     * Verifica se esta unidade é descendente de outra
     */
    public function isDescendantOf(int $ancestralId): bool
    {
        $atual = $this->pai;
        
        while ($atual !== null) {
            if ($atual->getId() === $ancestralId) {
                return true;
            }
            $atual = $atual->getPai();
        }
        
        return false;
    }
    
    public function getPai(): ?self
    {
        return $this->pai;
    }
    
    public function setPai(?self $pai): self
    {
        $this->pai = $pai;
        if ($pai) {
            $this->paiId = $pai->getId();
        }
        return $this;
    }
    
    public function addFilha(self $filha): void
    {
        $this->filhas[] = $filha;
    }
    
    public function getFilhas(): array
    {
        return $this->filhas;
    }
    
    public function setFilhas(array $filhas): self
    {
        $this->filhas = $filhas;
        return $this;
    }
    
    /**
     * Retorna todas as unidades descendentes (recursivo)
     * @return self[]
     */
    public function getTodosDescendentes(): array
    {
        $descendentes = [];
        
        foreach ($this->filhas as $filha) {
            $descendentes[] = $filha;
            $descendentes = array_merge($descendentes, $filha->getTodosDescendentes());
        }
        
        return $descendentes;
    }
    
    public function getVinculos(): array
    {
        return $this->vinculos;
    }
    
    public function setVinculos(array $vinculos): self
    {
        $this->vinculos = $vinculos;
        return $this;
    }
    
    public function getNivelHierarquico(): ?NivelHierarquicoEntity
    {
        return $this->nivelHierarquico;
    }
    
    public function setNivelHierarquico(?NivelHierarquicoEntity $nivel): self
    {
        $this->nivelHierarquico = $nivel;
        return $this;
    }
    
    /**
     * Retorna a secretaria desta unidade (nível 2)
     */
    public function getSecretaria(): ?self
    {
        if ($this->nivel === 2) {
            return $this;
        }
        
        $atual = $this->pai;
        while ($atual !== null) {
            if ($atual->getNivel() === 2) {
                return $atual;
            }
            $atual = $atual->getPai();
        }
        
        return null;
    }
    
    /**
     * Retorna a prefeitura (nível 1 - raiz)
     */
    public function getPrefeitura(): ?self
    {
        if ($this->nivel === 1) {
            return $this;
        }
        
        $atual = $this->pai;
        while ($atual !== null) {
            if ($atual->getNivel() === 1) {
                return $atual;
            }
            $atual = $atual->getPai();
        }
        
        return null;
    }
    
    /**
     * Retorna o caminho completo na árvore
     */
    public function getCaminho(): array
    {
        $caminho = [$this->nome];
        $atual = $this->pai;
        
        while ($atual !== null) {
            $caminho[] = $atual->getNome();
            $atual = $atual->getPai();
        }
        
        return array_reverse($caminho);
    }
    
    public function toArray(bool $includeFilhas = false): array
    {
        $data = [
            'id' => $this->id,
            'nome' => $this->nome,
            'nivel' => $this->nivel,
            'nivel_label' => $this->getNivelLabel(),
            'nivel_hierarquico' => $this->nivelHierarquico?->toArraySimple(),
            'sigla' => $this->sigla,
            'descricao' => $this->descricao,
            'endereco' => $this->endereco,
            'email' => $this->email,
            'telefone' => $this->telefone,
            'pai_id' => $this->paiId,
            'responsavel_id' => $this->responsavelId,
            'responsavel' => [
                'id' => $this->responsavelId,
                'nome' => $this->responsavelNome,
                'email' => $this->responsavelEmail,
                'telefone' => $this->responsavelTelefone,
            ],
            'ativa' => $this->ativa,
            'pode_criar_projetos' => $this->podeCriarProjetos,
            'pode_criar_programas' => $this->podeCriarProgramas,
            'caminho' => $this->getCaminho(),
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
        
        if ($includeFilhas) {
            $data['filhas'] = array_map(fn($f) => $f->toArray(true), $this->filhas);
        }
        
        return $data;
    }
    
    private function getNivelLabel(): string
    {
        return match($this->nivel) {
            1 => 'Prefeitura',
            2 => 'Secretaria',
            3 => 'Coordenação',
            4 => 'Equipe',
            default => 'Unidade',
        };
    }
}
