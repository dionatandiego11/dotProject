<?php
/**
 * Entidade PPA (Programa de Metas)
 * Nível 0 - Mola mestra do sistema
 * 
 * @package DotProject\Entity
 */

declare(strict_types=1);

namespace DotProject\Entity;

use DateTime;

class PpaEntity
{
    private ?int $id = null;
    private string $nome;
    private int $periodoInicio;
    private int $periodoFim;
    private string $estado = 'Rascunho';
    private ?string $objetivoGeral = null;
    private int $prefeitoId;
    private ?DateTime $dataPublicacao = null;
    private ?DateTime $dataCriacao = null;
    private ?DateTime $dataAtualizacao = null;
    
    /** @var ProgramaEntity[] */
    private array $programas = [];
    
    public function __construct()
    {
        $this->dataCriacao = new DateTime();
    }
    
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
    
    public function getNome(): string
    {
        return $this->nome;
    }
    
    public function setNome(string $nome): self
    {
        $this->nome = $nome;
        return $this;
    }
    
    public function getPeriodoInicio(): int
    {
        return $this->periodoInicio;
    }
    
    public function setPeriodoInicio(int $ano): self
    {
        $this->periodoInicio = $ano;
        return $this;
    }
    
    public function getPeriodoFim(): int
    {
        return $this->periodoFim;
    }
    
    public function setPeriodoFim(int $ano): self
    {
        $this->periodoFim = $ano;
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
    
    public function getObjetivoGeral(): ?string
    {
        return $this->objetivoGeral;
    }
    
    public function setObjetivoGeral(?string $objetivo): self
    {
        $this->objetivoGeral = $objetivo;
        return $this;
    }
    
    public function getPrefeitoId(): int
    {
        return $this->prefeitoId;
    }
    
    public function setPrefeitoId(int $id): self
    {
        $this->prefeitoId = $id;
        return $this;
    }
    
    /**
     * Calcula progresso geral do PPA baseado nos programas
     */
    public function calcularPercentExecucao(): float
    {
        if (empty($this->programas)) {
            return 0.0;
        }
        
        $soma = array_sum(array_map(
            fn($p) => $p->getPercentExecucao(),
            $this->programas
        ));
        
        return round($soma / count($this->programas), 2);
    }
    
    /**
     * Contagem de programas por estado
     */
    public function countProgramasPorEstado(): array
    {
        $contagem = [];
        foreach ($this->programas as $programa) {
            $estado = $programa->getEstado();
            $contagem[$estado] = ($contagem[$estado] ?? 0) + 1;
        }
        return $contagem;
    }
    
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'periodo_inicio' => $this->periodoInicio,
            'periodo_fim' => $this->periodoFim,
            'estado' => $this->estado,
            'objetivo_geral' => $this->objetivoGeral,
            'percent_execucao' => $this->calcularPercentExecucao(),
            'total_programas' => count($this->programas),
            'data_publicacao' => $this->dataPublicacao?->format('Y-m-d'),
        ];
    }
}
