<?php
/**
 * Entidade Programa
 * Nível 1 - Carteira do Secretário
 * 
 * @package DotProject\Entity
 */

declare(strict_types=1);

namespace DotProject\Entity;

class ProgramaEntity
{
    private ?int $id = null;
    private int $ppaId;
    private ?PpaEntity $ppa = null;
    private string $nome;
    private ?string $objetivo = null;
    private int $unidadeId; // Secretaria responsável
    private ?UnidadeOrganizacionalEntity $unidade = null;
    private string $estado = 'Cadastrado';
    private float $percentExecucao = 0.0;
    private string $prioridade = 'Media'; // Baixa, Media, Alta
    private string $statusSaude = 'em_dia';
    private ?\DateTime $dataUltimaAtualizacao = null;
    private ?string $observacaoEstrategica = null;

    /** @var ProjetoEntity[] */
    private array $projetos = [];

    // Getters e Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getPpaId(): int
    {
        return $this->ppaId;
    }

    public function setPpaId(int $id): self
    {
        $this->ppaId = $id;
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

    public function getUnidadeId(): int
    {
        return $this->unidadeId;
    }

    public function setUnidadeId(int $id): self
    {
        $this->unidadeId = $id;
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

    public function getPercentExecucao(): float
    {
        return $this->percentExecucao;
    }

    public function setPercentExecucao(float $percent): self
    {
        $this->percentExecucao = $percent;
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

    /**
     * Recalcula percentual baseado nos projetos
     */
    public function recalcularPercentualExecucao(): void
    {
        if (empty($this->projetos)) {
            $this->percentExecucao = 0.0;
            return;
        }

        // Média ponderada (pode ser ajustada para pesos diferentes)
        $soma = array_sum(array_map(
            fn($p) => $p->getPercentExecucao(),
            $this->projetos
        ));

        $this->percentExecucao = round($soma / count($this->projetos), 2);
    }

    /**
     * Atualiza status automático baseado nos projetos
     */
    public function atualizarStatusAutomatico(): void
    {
        if (empty($this->projetos)) {
            $this->estado = 'Cadastrado';
            return;
        }

        $total = count($this->projetos);
        $concluidos = count(array_filter($this->projetos, fn($p) => $p->getEstado() === 'Concluido'));
        $atrasados = count(array_filter($this->projetos, fn($p) => $p->getEstado() === 'Atrasado'));
        $parados = count(array_filter($this->projetos, fn($p) => $p->getEstado() === 'Impedido'));

        // Todos concluídos
        if ($concluidos === $total) {
            $this->estado = 'Concluido';
            return;
        }

        // Mais da metade atrasados
        if ($atrasados > $total / 2) {
            $this->estado = 'Critico';
            return;
        }

        // Alguns atrasados
        if ($atrasados > 0) {
            $this->estado = 'Atencao';
            return;
        }

        // Todos parados
        if ($parados === ($total - $concluidos)) {
            $this->estado = 'Parado';
            return;
        }

        // Percentual determina
        if ($this->percentExecucao >= 80) {
            $this->estado = 'Em_Dia';
        } elseif ($this->percentExecucao >= 50) {
            $this->estado = 'Atencao';
        } else {
            $this->estado = 'Em_Andamento';
        }
    }

    public function addProjeto(ProjetoEntity $projeto): void
    {
        $this->projetos[] = $projeto;
    }

    public function getProjetos(): array
    {
        return $this->projetos;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'objetivo' => $this->objetivo,
            'estado' => $this->estado,
            'status_saude' => $this->statusSaude,
            'percent_execucao' => $this->percentExecucao,
            'prioridade' => $this->prioridade,
            'total_projetos' => count($this->projetos),
            'projetos_concluidos' => count(array_filter($this->projetos, fn($p) => $p->getEstado() === 'Concluido')),
        ];
    }
}
