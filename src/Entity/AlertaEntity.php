<?php
/**
 * Entidade Alerta
 * Sistema de alertas automáticos para gestão pública
 * 
 * @package DotProject\Entity
 */

declare(strict_types=1);

namespace DotProject\Entity;

class AlertaEntity
{
    // Tipos de alerta
    public const TIPO_CONVENIO_VENCER = 'convenio_vencer';
    public const TIPO_OBRA_PARADA = 'obra_parada';
    public const TIPO_PROJETO_PARADO = 'projeto_parado';
    public const TIPO_RECURSO_NAO_PAGO = 'recurso_nao_pago';
    public const TIPO_EMENDA_SEM_EXECUCAO = 'emenda_sem_execucao';
    public const TIPO_PRAZO_ETAPA_PROXIMO = 'prazo_etapa_proximo';
    public const TIPO_INDICADOR_ABAIXO_META = 'indicador_abaixo_meta';
    public const TIPO_ETAPA_ATRASADA = 'etapa_atrasada';
    public const TIPO_PAGAMENTO_PENDENTE = 'pagamento_pendente';
    
    // Prioridades
    public const PRIORIDADE_BAIXA = 'baixa';
    public const PRIORIDADE_MEDIA = 'media';
    public const PRIORIDADE_ALTA = 'alta';
    public const PRIORIDADE_CRITICA = 'critica';
    
    private ?int $id = null;
    private string $tipo;
    private string $titulo;
    private ?string $descricao = null;
    private ?int $projetoId = null;
    private ?int $etapaId = null;
    private ?int $programaId = null;
    private int $destinatarioId;
    private ?int $unidadeId = null;
    private string $prioridade = self::PRIORIDADE_MEDIA;
    private bool $lido = false;
    private ?string $dataLeitura = null;
    private ?string $dataCriacao = null;
    private ?string $acaoRequerida = null;
    private ?string $linkAcao = null;
    
    public function getId(): ?int
    {
        return $this->id;
    }
    
    public function setId(int $id): self
    {
        $this->id = $id;
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
    
    public function getTitulo(): string
    {
        return $this->titulo;
    }
    
    public function setTitulo(string $titulo): self
    {
        $this->titulo = $titulo;
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
    
    public function getProjetoId(): ?int
    {
        return $this->projetoId;
    }
    
    public function setProjetoId(?int $projetoId): self
    {
        $this->projetoId = $projetoId;
        return $this;
    }
    
    public function getEtapaId(): ?int
    {
        return $this->etapaId;
    }
    
    public function setEtapaId(?int $etapaId): self
    {
        $this->etapaId = $etapaId;
        return $this;
    }
    
    public function getProgramaId(): ?int
    {
        return $this->programaId;
    }
    
    public function setProgramaId(?int $programaId): self
    {
        $this->programaId = $programaId;
        return $this;
    }
    
    public function getDestinatarioId(): int
    {
        return $this->destinatarioId;
    }
    
    public function setDestinatarioId(int $destinatarioId): self
    {
        $this->destinatarioId = $destinatarioId;
        return $this;
    }
    
    public function getUnidadeId(): ?int
    {
        return $this->unidadeId;
    }
    
    public function setUnidadeId(?int $unidadeId): self
    {
        $this->unidadeId = $unidadeId;
        return $this;
    }
    
    public function getPrioridade(): string
    {
        return $this->prioridade;
    }
    
    public function setPrioridade(string $prioridade): self
    {
        $this->prioridade = $prioridade;
        return $this;
    }
    
    public function isLido(): bool
    {
        return $this->lido;
    }
    
    public function setLido(bool $lido): self
    {
        $this->lido = $lido;
        return $this;
    }
    
    public function getDataLeitura(): ?string
    {
        return $this->dataLeitura;
    }
    
    public function setDataLeitura(?string $data): self
    {
        $this->dataLeitura = $data;
        return $this;
    }
    
    public function getDataCriacao(): ?string
    {
        return $this->dataCriacao;
    }
    
    public function getAcaoRequerida(): ?string
    {
        return $this->acaoRequerida;
    }
    
    public function setAcaoRequerida(?string $acao): self
    {
        $this->acaoRequerida = $acao;
        return $this;
    }
    
    public function getLinkAcao(): ?string
    {
        return $this->linkAcao;
    }
    
    public function setLinkAcao(?string $link): self
    {
        $this->linkAcao = $link;
        return $this;
    }
    
    /**
     * Marca como lido
     */
    public function marcarComoLido(): void
    {
        $this->lido = true;
        $this->dataLeitura = date('Y-m-d H:i:s');
    }
    
    /**
     * Retorna ícone baseado no tipo
     */
    public function getIcone(): string
    {
        return match($this->tipo) {
            self::TIPO_CONVENIO_VENCER => '📅',
            self::TIPO_OBRA_PARADA => '🚧',
            self::TIPO_PROJETO_PARADO => '⏸️',
            self::TIPO_RECURSO_NAO_PAGO => '💰',
            self::TIPO_EMENDA_SEM_EXECUCAO => '🏛️',
            self::TIPO_PRAZO_ETAPA_PROXIMO => '⏰',
            self::TIPO_INDICADOR_ABAIXO_META => '📉',
            self::TIPO_ETAPA_ATRASADA => '⚠️',
            self::TIPO_PAGAMENTO_PENDENTE => '💳',
            default => '📢',
        };
    }
    
    /**
     * Retorna cor baseada na prioridade
     */
    public function getCor(): string
    {
        return match($this->prioridade) {
            self::PRIORIDADE_CRITICA => '#dc2626',
            self::PRIORIDADE_ALTA => '#ef4444',
            self::PRIORIDADE_MEDIA => '#f59e0b',
            self::PRIORIDADE_BAIXA => '#3b82f6',
            default => '#6b7280',
        };
    }
    
    /**
     * Retorna label do tipo
     */
    public function getTipoLabel(): string
    {
        return match($this->tipo) {
            self::TIPO_CONVENIO_VENCER => 'Convênio a Vencer',
            self::TIPO_OBRA_PARADA => 'Obra Parada',
            self::TIPO_PROJETO_PARADO => 'Projeto Parado',
            self::TIPO_RECURSO_NAO_PAGO => 'Recurso Não Pago',
            self::TIPO_EMENDA_SEM_EXECUCAO => 'Emenda sem Execução',
            self::TIPO_PRAZO_ETAPA_PROXIMO => 'Prazo de Etapa Próximo',
            self::TIPO_INDICADOR_ABAIXO_META => 'Indicador Abaixo da Meta',
            self::TIPO_ETAPA_ATRASADA => 'Etapa Atrasada',
            self::TIPO_PAGAMENTO_PENDENTE => 'Pagamento Pendente',
            default => 'Alerta',
        };
    }
    
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->tipo,
            'tipo_label' => $this->getTipoLabel(),
            'icone' => $this->getIcone(),
            'titulo' => $this->titulo,
            'descricao' => $this->descricao,
            'projeto_id' => $this->projetoId,
            'etapa_id' => $this->etapaId,
            'programa_id' => $this->programaId,
            'destinatario_id' => $this->destinatarioId,
            'unidade_id' => $this->unidadeId,
            'prioridade' => $this->prioridade,
            'cor' => $this->getCor(),
            'lido' => $this->lido,
            'data_leitura' => $this->dataLeitura,
            'data_criacao' => $this->dataCriacao,
            'acao_requerida' => $this->acaoRequerida,
            'link_acao' => $this->linkAcao,
        ];
    }
}
