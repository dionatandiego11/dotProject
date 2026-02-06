<?php
/**
 * Entidade Etapa
 * Nível 3 - Timeline Macro (5 etapas fixas)
 * 
 * @package DotProject\Entity
 */

declare(strict_types=1);

namespace DotProject\Entity;

use DateTime;
use DotProject\State\StateFactory;
use DotProject\State\Etapa\EtapaStateInterface;

class EtapaEntity
{
    private ?int $id = null;
    private int $projetoId;
    private ?ProjetoEntity $projeto = null;
    private int $numero; // 1-5
    private string $nome;
    private string $estado = 'Nao_Iniciada';
    private ?DateTime $dataPrevistaInicio = null;
    private ?DateTime $dataPrevistaFim = null;
    private ?DateTime $dataRealInicio = null;
    private ?DateTime $dataRealFim = null;
    private ?int $responsavelId = null;
    private int $diasAtraso = 0;
    private ?string $justificativaAtraso = null;
    private ?string $evidenciaUrl = null;
    private float $percentConclusao = 0.0;
    
    public function getId(): ?int
    {
        return $this->id;
    }
    
    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }
    
    public function getProjetoId(): int
    {
        return $this->projetoId;
    }
    
    public function setProjetoId(int $id): self
    {
        $this->projetoId = $id;
        return $this;
    }
    
    public function getProjeto(): ?ProjetoEntity
    {
        return $this->projeto;
    }
    
    public function setProjeto(?ProjetoEntity $projeto): self
    {
        $this->projeto = $projeto;
        if ($projeto) {
            $this->projetoId = $projeto->getId() ?? 0;
        }
        return $this;
    }
    
    public function getNumero(): int
    {
        return $this->numero;
    }
    
    public function setNumero(int $numero): self
    {
        $this->numero = $numero;
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
    
    public function getEstado(): string
    {
        return $this->estado;
    }
    
    public function setEstado(string $estado): self
    {
        $this->estado = $estado;
        return $this;
    }

    public function setDataPrevistaInicio(?DateTime $data): self
    {
        $this->dataPrevistaInicio = $data;
        return $this;
    }
    
    public function getDataPrevistaFim(): ?DateTime
    {
        return $this->dataPrevistaFim;
    }

    public function getDataPrevistaInicio(): ?DateTime
    {
        return $this->dataPrevistaInicio;
    }
    
    public function setDataPrevistaFim(?DateTime $data): self
    {
        $this->dataPrevistaFim = $data;
        return $this;
    }
    
    public function getDataRealFim(): ?DateTime
    {
        return $this->dataRealFim;
    }
    
    public function setDataRealFim(?DateTime $data): self
    {
        $this->dataRealFim = $data;
        return $this;
    }
    
    public function getDataRealInicio(): ?DateTime
    {
        return $this->dataRealInicio;
    }

    public function getResponsavelId(): ?int
    {
        return $this->responsavelId;
    }
    
    public function setDataRealInicio(?DateTime $data): self
    {
        $this->dataRealInicio = $data;
        return $this;
    }

    public function setResponsavelId(?int $id): self
    {
        $this->responsavelId = $id;
        return $this;
    }
    
    public function getDiasAtraso(): int
    {
        return $this->diasAtraso;
    }
    
    public function setDiasAtraso(int $dias): self
    {
        $this->diasAtraso = $dias;
        return $this;
    }
    
    public function getJustificativaAtraso(): ?string
    {
        return $this->justificativaAtraso;
    }

    public function getEvidenciaUrl(): ?string
    {
        return $this->evidenciaUrl;
    }
    
    public function setJustificativaAtraso(?string $justificativa): self
    {
        $this->justificativaAtraso = $justificativa;
        return $this;
    }

    public function setEvidenciaUrl(?string $url): self
    {
        $this->evidenciaUrl = $url;
        return $this;
    }
    
    public function getPercentConclusao(): float
    {
        return $this->percentConclusao;
    }
    
    public function setPercentConclusao(float $percent): self
    {
        $this->percentConclusao = max(0, min(100, $percent));
        return $this;
    }
    
    /**
     * Inicia a etapa
     */
    public function iniciar(): void
    {
        $this->dataRealInicio = new DateTime();
        $this->estado = 'Em_Andamento';
        $this->getStateObject()->onEnter($this);
    }
    
    /**
     * Finaliza a etapa
     */
    public function finalizar(): void
    {
        if (!$this->justificativaAtraso && $this->estaAtrasada()) {
            throw new \DomainException('Justificativa de atraso é obrigatória');
        }
        
        $this->dataRealFim = new DateTime();
        
        if ($this->estaAtrasada()) {
            $this->estado = 'Concluida_Com_Atraso';
        } else {
            $this->estado = 'Concluida';
        }
        
        $this->percentConclusao = 100.0;
        $this->getStateObject()->onEnter($this);
    }
    
    /**
     * Verifica automaticamente o prazo e atualiza estado
     */
    public function verificarPrazo(): void
    {
        $estado = $this->getStateObject();
        $diasAtraso = $estado->verificarAtraso($this);
        
        if ($diasAtraso !== null) {
            $this->diasAtraso = $diasAtraso;
            $this->estado = $diasAtraso > 30 ? 'Critica' : 'Atrasada';
        } else {
            // Verifica se está próximo do prazo
            if ($this->dataPrevistaFim) {
                $hoje = new DateTime();
                $limite = (clone $hoje)->modify('+7 days');
                
                if ($this->dataPrevistaFim <= $limite && $this->dataPrevistaFim >= $hoje) {
                    $this->estado = 'Proximo_Prazo';
                } elseif ($this->dataPrevistaFim > $limite) {
                    $this->estado = 'Dentro_Prazo';
                }
            }
        }
    }
    
    /**
     * Transiciona para novo estado
     */
    public function transicionarEstado(string $novoEstado): void
    {
        $estadoAtual = $this->getStateObject();
        
        if (!$estadoAtual->canTransitionTo($novoEstado)) {
            throw new \InvalidArgumentException(
                "Transição de '{$this->estado}' para '{$novoEstado}' não permitida"
            );
        }
        
        $estadoAtual->onExit($this);
        $this->estado = $novoEstado;
        $this->getStateObject()->onEnter($this);
    }
    
    public function isConcluida(): bool
    {
        return $this->getStateObject()->isConcluida();
    }
    
    public function estaAtrasada(): bool
    {
        if (!$this->dataPrevistaFim || $this->isConcluida()) {
            return false;
        }
        
        $hoje = new DateTime();
        return $hoje > $this->dataPrevistaFim;
    }
    
    /**
     * Retorna cor para UI
     */
    public function getCorStatus(): string
    {
        return $this->getStateObject()->getColor();
    }
    
    public function getStateObject(): EtapaStateInterface
    {
        return StateFactory::createEtapaState($this->estado);
    }
    
    /**
     * Conta tarefas vinculadas a esta etapa
     */
    public function countTarefas(): int
    {
        if (!$this->projeto) {
            return 0;
        }
        return count($this->projeto->getTarefasPorEtapa($this->numero));
    }
    
    /**
     * Conta tarefas concluídas
     */
    public function countTarefasConcluidas(): int
    {
        if (!$this->projeto) {
            return 0;
        }
        $tarefas = $this->projeto->getTarefasPorEtapa($this->numero);
        return count(array_filter($tarefas, fn($t) => $t->isConcluida()));
    }
    
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'nome' => $this->nome,
            'estado' => $this->estado,
            'cor_status' => $this->getCorStatus(),
            'data_prevista_fim' => $this->dataPrevistaFim?->format('Y-m-d'),
            'data_real_fim' => $this->dataRealFim?->format('Y-m-d'),
            'dias_atraso' => $this->diasAtraso,
            'percent_conclusao' => $this->percentConclusao,
            'total_tarefas' => $this->countTarefas(),
            'tarefas_concluidas' => $this->countTarefasConcluidas(),
        ];
    }
}
