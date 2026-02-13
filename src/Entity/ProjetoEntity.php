<?php
/**
 * Entidade Projeto Público
 * Nível 2 - 5 Etapas Fixas
 * 
 * @package DotProject\Entity
 */

declare(strict_types=1);

namespace DotProject\Entity;

use DateTime;
use DotProject\State\StateFactory;
use DotProject\State\Projeto\ProjetoStateInterface;
use InvalidArgumentException;

class ProjetoEntity
{
    private ?int $id = null;
    private ?int $programaId = null;
    private ?ProgramaEntity $programa = null;
    private string $nome;
    private string $tipo; // Obra, Politica_Publica, Convenio, Emenda, Outro
    private string $estado = 'Cadastrado';
    private ?string $estadoAnterior = null;
    private int $etapaAtualNumero = 1;
    private float $percentExecucao = 0.0;
    private ?string $descricao = null;
    private ?string $fonteRecurso = null;
    private ?float $valorPrevisto = null;
    private ?string $situacaoOrcamentaria = null;
    private int $unidadeId;
    private int $coordenadorId;
    private ?DateTime $dataPrevistaInicio = null;
    private ?DateTime $dataPrevistaFim = null;
    private ?DateTime $dataConclusao = null;
    private ?DateTime $dataCriacao = null;
    private ?DateTime $dataAtualizacao = null;
    private ?string $justificativaAtraso = null;
    private ?string $impedimentoDescricao = null;
    private string $statusFluxo = 'Planejamento';
    private string $statusSaude = 'em_dia';
    private float $valorExecutado = 0.0;

    /** @var EtapaEntity[] */
    private array $etapas = [];

    /** @var TaskEntity[] */
    private array $tarefas = [];

    public function __construct()
    {
        $this->dataCriacao = new DateTime();
        $this->criarEtapasPadrao();
    }

    /**
     * Cria as 5 etapas padrão do projeto
     */
    private function criarEtapasPadrao(): void
    {
        $nomes = [
            1 => 'Planejamento',
            2 => 'Licitação',
            3 => 'Execução',
            4 => 'Medição',
            5 => 'Pagamento',
        ];

        for ($i = 1; $i <= 5; $i++) {
            $etapa = new EtapaEntity();
            $etapa->setNumero($i);
            $etapa->setNome($nomes[$i]);
            $etapa->setEstado('Nao_Iniciada');
            $this->etapas[$i] = $etapa;
        }
    }

    // Getters e Setters principais

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
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

    public function getTipo(): string
    {
        return $this->tipo;
    }

    public function setTipo(string $tipo): self
    {
        $this->tipo = $tipo;
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

    public function setEtapaAtualNumero(int $numero): self
    {
        if ($numero < 1) {
            $numero = 1;
        }
        $this->etapaAtualNumero = $numero;
        return $this;
    }

    public function getEstadoAnterior(): ?string
    {
        return $this->estadoAnterior;
    }

    public function getEtapaAtualNumero(): int
    {
        return $this->etapaAtualNumero;
    }

    public function getEtapaAtual(): EtapaEntity
    {
        return $this->etapas[$this->etapaAtualNumero];
    }

    public function getPercentExecucao(): float
    {
        return $this->percentExecucao;
    }

    public function getStatusFluxo(): string
    {
        return $this->statusFluxo;
    }

    public function setStatusFluxo(string $status): self
    {
        $this->statusFluxo = $status;
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

    public function getValorExecutado(): float
    {
        return $this->valorExecutado;
    }

    public function setValorExecutado(float $valor): self
    {
        $this->valorExecutado = $valor;
        return $this;
    }

    public function getDescricao(): ?string
    {
        return $this->descricao;
    }

    public function getFonteRecurso(): ?string
    {
        return $this->fonteRecurso;
    }

    public function getValorPrevisto(): ?float
    {
        return $this->valorPrevisto;
    }

    public function getSituacaoOrcamentaria(): ?string
    {
        return $this->situacaoOrcamentaria;
    }

    public function getDataPrevistaInicio(): ?DateTime
    {
        return $this->dataPrevistaInicio;
    }

    public function getDataPrevistaFim(): ?DateTime
    {
        return $this->dataPrevistaFim;
    }

    public function getJustificativaAtraso(): ?string
    {
        return $this->justificativaAtraso;
    }

    public function getImpedimentoDescricao(): ?string
    {
        return $this->impedimentoDescricao;
    }

    public function setPercentExecucao(float $percent): self
    {
        $this->percentExecucao = $percent;
        return $this;
    }

    public function setProgramaId(?int $id): self
    {
        $this->programaId = $id;
        return $this;
    }

    public function setDescricao(?string $descricao): self
    {
        $this->descricao = $descricao;
        return $this;
    }

    public function setFonteRecurso(?string $fonte): self
    {
        $this->fonteRecurso = $fonte;
        return $this;
    }

    public function setValorPrevisto(?float $valor): self
    {
        $this->valorPrevisto = $valor;
        return $this;
    }

    public function setSituacaoOrcamentaria(?string $situacao): self
    {
        $this->situacaoOrcamentaria = $situacao;
        return $this;
    }

    public function setDataPrevistaInicio(?DateTime $data): self
    {
        $this->dataPrevistaInicio = $data;
        return $this;
    }

    public function setDataPrevistaFim(?DateTime $data): self
    {
        $this->dataPrevistaFim = $data;
        return $this;
    }

    public function setDataCriacao(?DateTime $data): self
    {
        $this->dataCriacao = $data;
        return $this;
    }

    public function setDataAtualizacao(?DateTime $data): self
    {
        $this->dataAtualizacao = $data;
        return $this;
    }

    public function setJustificativaAtraso(?string $justificativa): self
    {
        $this->justificativaAtraso = $justificativa;
        return $this;
    }

    public function setImpedimentoDescricao(?string $descricao): self
    {
        $this->impedimentoDescricao = $descricao;
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

    public function getCoordenadorId(): int
    {
        return $this->coordenadorId;
    }

    public function setCoordenadorId(int $id): self
    {
        $this->coordenadorId = $id;
        return $this;
    }

    public function getPrograma(): ?ProgramaEntity
    {
        return $this->programa;
    }

    public function getProgramaId(): ?int
    {
        return $this->programaId;
    }

    public function setPrograma(?ProgramaEntity $programa): self
    {
        $this->programa = $programa;
        if ($programa) {
            $this->programaId = $programa->getId();
        }
        return $this;
    }

    public function getDiasAtraso(): int
    {
        $etapa = $this->getEtapaAtual();
        return $etapa->getDiasAtraso();
    }

    /**
     * Transiciona para novo estado
     * 
     * @throws InvalidArgumentException Se transição não for permitida
     */
    public function transicionarEstado(string $novoEstado, ?array $contexto = null): void
    {
        $estadoAtual = $this->getStateObject();

        // Valida transição
        if (!$estadoAtual->canTransitionTo($novoEstado)) {
            throw new InvalidArgumentException(
                "Transição de '{$this->estado}' para '{$novoEstado}' não permitida. " .
                "Transições permitidas: " . implode(', ', $estadoAtual->getAllowedTransitions())
            );
        }

        // Executa onExit
        $estadoAtual->onExit($this);

        // Armazena estado anterior
        $this->estadoAnterior = $this->estado;
        $this->estado = $novoEstado;

        // Executa onEnter do novo estado
        $novoEstadoObj = $this->getStateObject();
        $novoEstadoObj->onEnter($this);

        // Recalcula percentual
        $this->percentExecucao = $novoEstadoObj->calcularPercentualExecucao($this);

        // Atualiza timestamp
        $this->dataAtualizacao = new DateTime();

        // Se é estado de etapa, atualiza número
        $numeroEtapa = $novoEstadoObj->getEtapaNumero();
        if ($numeroEtapa > 0) {
            $this->etapaAtualNumero = $numeroEtapa;
        }
    }

    /**
     * Avança para próxima etapa
     */
    public function avancarEtapa(): void
    {
        $estadoAtual = $this->getStateObject();

        if (!$estadoAtual->podeAvancarEtapa($this)) {
            throw new InvalidArgumentException(
                'Não é possível avançar etapa. Etapa atual não está concluída.'
            );
        }

        if ($this->etapaAtualNumero >= 5) {
            // Última etapa - conclui projeto
            $this->transicionarEstado('Concluido');
            return;
        }

        // Finaliza etapa atual
        $etapaAtual = $this->getEtapaAtual();
        $etapaAtual->finalizar();

        // Avança número
        $this->etapaAtualNumero++;

        // Transiciona estado baseado na nova etapa
        $novoEstado = match ($this->etapaAtualNumero) {
            1 => 'Planejamento',
            2 => 'Licitacao',
            3 => 'Execucao',
            4 => 'Medicao',
            5 => 'Pagamento',
            default => 'Execucao',
        };

        $this->transicionarEstado($novoEstado);

        // Inicia nova etapa
        $novaEtapa = $this->getEtapaAtual();
        $novaEtapa->iniciar();
    }

    /**
     * Retorna objeto de estado atual
     */
    public function getStateObject(): ProjetoStateInterface
    {
        return StateFactory::createProjetoState($this->estado);
    }

    /**
     * Verifica e atualiza estado automaticamente (chamado por job)
     */
    public function verificarEstadoAutomatico(): void
    {
        // Verifica atraso na etapa atual
        $etapa = $this->getEtapaAtual();
        $etapa->verificarPrazo();

        if (in_array($etapa->getEstado(), ['Atrasada', 'Critica'], true)) {
            if ($this->estado !== 'Atrasado') {
                $this->transicionarEstado('Atrasado');
            }
        }
    }

    /**
     * Atualiza last_update (chamado quando tarefa é modificada)
     */
    public function touch(): void
    {
        $this->dataAtualizacao = new DateTime();
    }

    /**
     * Conta tarefas pendentes do projeto
     */
    public function countTarefasPendentas(): int
    {
        return count(array_filter($this->tarefas, fn($t) => !$t->isConcluida()));
    }

    public function addEtapa(EtapaEntity $etapa): void
    {
        $this->etapas[$etapa->getNumero()] = $etapa;
    }

    public function getEtapas(): array
    {
        return $this->etapas;
    }

    public function addTarefa(TaskEntity $tarefa): void
    {
        $this->tarefas[] = $tarefa;
    }

    public function getTarefas(): array
    {
        return $this->tarefas;
    }

    public function getTarefasPorEtapa(int $etapaNumero): array
    {
        return array_filter($this->tarefas, fn($t) => $t->getEtapaId() === $etapaNumero);
    }

    public function setDataConclusao(?DateTime $data): self
    {
        $this->dataConclusao = $data;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'tipo' => $this->tipo,
            'estado' => $this->estado,
            'etapa_atual' => $this->etapaAtualNumero,
            'etapa_nome' => $this->getEtapaAtual()->getNome(),
            'percent_execucao' => $this->percentExecucao,
            'valor_previsto' => $this->valorPrevisto,
            'situacao_orcamentaria' => $this->situacaoOrcamentaria,
            'dias_atraso' => $this->getDiasAtraso(),
            'data_prevista_fim' => $this->dataPrevistaFim?->format('Y-m-d'),
            'total_etapas' => count($this->etapas),
            'total_tarefas' => count($this->tarefas),
        ];
    }
}
