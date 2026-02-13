<?php
/**
 * Entidade Acao
 * Nivel intermediario entre Programa e Projeto.
 *
 * @package DotProject\Entity
 */

declare(strict_types=1);

namespace DotProject\Entity;

class AcaoEntity
{
    private ?int $id = null;
    private int $programaId;
    private ?ProgramaEntity $programa = null;
    private string $nome;
    private ?string $codigo = null;
    private ?string $objetivo = null;
    private string $estado = 'Planejamento';
    private float $percentExecucao = 0.0;
    private string $tipo = 'Projeto';
    private string $statusSaude = 'em_dia';
    private float $valorOrcamentario = 0.0;
    private float $valorExecutado = 0.0;

    /** @var ProjetoEntity[] */
    private array $projetos = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getProgramaId(): int
    {
        return $this->programaId;
    }

    public function setProgramaId(int $programaId): self
    {
        $this->programaId = $programaId;
        return $this;
    }

    public function getPrograma(): ?ProgramaEntity
    {
        return $this->programa;
    }

    public function setPrograma(?ProgramaEntity $programa): self
    {
        $this->programa = $programa;
        if ($programa !== null && $programa->getId() !== null) {
            $this->programaId = (int) $programa->getId();
        }
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

    public function getCodigo(): ?string
    {
        return $this->codigo;
    }

    public function setCodigo(?string $codigo): self
    {
        $this->codigo = $codigo;
        return $this;
    }

    public function getObjetivo(): ?string
    {
        return $this->objetivo;
    }

    public function setObjetivo(?string $objetivo): self
    {
        $this->objetivo = $objetivo;
        return $this;
    }

    public function getEstado(): string
    {
        return $this->estado;
    }

    public function setEstado(string $estado): self
    {
        $this->estado = $estado;
        return $this;
    }

    public function getTipo(): string
    {
        return $this->tipo;
    }

    public function setTipo(string $tipo): self
    {
        $this->tipo = $tipo;
        return $this;
    }

    public function getStatusSaude(): string
    {
        return $this->statusSaude;
    }

    public function setStatusSaude(string $statusSaude): self
    {
        $this->statusSaude = $statusSaude;
        return $this;
    }

    public function getValorOrcamentario(): float
    {
        return $this->valorOrcamentario;
    }

    public function setValorOrcamentario(float $valor): self
    {
        $this->valorOrcamentario = $valor;
        return $this;
    }

    public function getValorExecutado(): float
    {
        return $this->valorExecutado;
    }

    public function setValorExecutado(float $valor): self
    {
        $this->valorExecutado = $valor;
        return $this;
    }

    public function getPercentExecucao(): float
    {
        return $this->percentExecucao;
    }

    public function setPercentExecucao(float $percentExecucao): self
    {
        $this->percentExecucao = max(0, min(100, $percentExecucao));
        return $this;
    }

    public function addProjeto(ProjetoEntity $projeto): void
    {
        $this->projetos[] = $projeto;
    }

    /**
     * @return ProjetoEntity[]
     */
    public function getProjetos(): array
    {
        return $this->projetos;
    }

    public function recalcularPercentualExecucao(): void
    {
        if ($this->projetos === []) {
            $this->percentExecucao = 0.0;
            return;
        }

        $soma = array_sum(array_map(
            static fn(ProjetoEntity $projeto): float => (float) $projeto->getPercentExecucao(),
            $this->projetos
        ));

        $this->percentExecucao = round($soma / count($this->projetos), 2);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'programa_id' => $this->programaId,
            'codigo' => $this->codigo,
            'nome' => $this->nome,
            'objetivo' => $this->objetivo,
            'estado' => $this->estado,
            'tipo' => $this->tipo,
            'status_saude' => $this->statusSaude,
            'valor_orcamentario' => $this->valorOrcamentario,
            'valor_executado' => $this->valorExecutado,
            'percent_execucao' => $this->percentExecucao,
            'total_projetos' => count($this->projetos),
        ];
    }
}
