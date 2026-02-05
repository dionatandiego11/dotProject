<?php
/**
 * Entidade Histórico de Movimentação
 * Registra mudanças de unidade/cargo dos usuários
 * 
 * @package DotProject\Entity
 */

declare(strict_types=1);

namespace DotProject\Entity;

class HistoricoMovimentacaoEntity
{
    public const TIPO_PROMOCAO = 'promocao';
    public const TIPO_REMOCAO = 'remocao';
    public const TIPO_EXONERACAO = 'exoneracao';
    public const TIPO_REINTEGRACAO = 'reintegracao';
    public const TIPO_TRANSFERENCIA = 'transferencia';
    
    private ?int $id = null;
    private int $userId;
    private ?int $unidadeOrigemId = null;
    private ?UnidadeOrganizacionalEntity $unidadeOrigem = null;
    private int $unidadeDestinoId;
    private ?UnidadeOrganizacionalEntity $unidadeDestino = null;
    private ?string $cargoAnterior = null;
    private ?string $cargoNovo = null;
    private string $tipoMovimentacao;
    private string $dataMovimentacao;
    private ?string $observacao = null;
    private ?int $responsavelId = null;
    private ?string $createdAt = null;
    
    public function getId(): ?int
    {
        return $this->id;
    }
    
    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }
    
    public function getUserId(): int
    {
        return $this->userId;
    }
    
    public function setUserId(int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }
    
    public function getUnidadeOrigemId(): ?int
    {
        return $this->unidadeOrigemId;
    }
    
    public function setUnidadeOrigemId(?int $unidadeOrigemId): self
    {
        $this->unidadeOrigemId = $unidadeOrigemId;
        return $this;
    }
    
    public function getUnidadeOrigem(): ?UnidadeOrganizacionalEntity
    {
        return $this->unidadeOrigem;
    }
    
    public function setUnidadeOrigem(?UnidadeOrganizacionalEntity $unidade): self
    {
        $this->unidadeOrigem = $unidade;
        if ($unidade) {
            $this->unidadeOrigemId = $unidade->getId();
        }
        return $this;
    }
    
    public function getUnidadeDestinoId(): int
    {
        return $this->unidadeDestinoId;
    }
    
    public function setUnidadeDestinoId(int $unidadeDestinoId): self
    {
        $this->unidadeDestinoId = $unidadeDestinoId;
        return $this;
    }
    
    public function getUnidadeDestino(): ?UnidadeOrganizacionalEntity
    {
        return $this->unidadeDestino;
    }
    
    public function setUnidadeDestino(?UnidadeOrganizacionalEntity $unidade): self
    {
        $this->unidadeDestino = $unidade;
        if ($unidade) {
            $this->unidadeDestinoId = $unidade->getId();
        }
        return $this;
    }
    
    public function getCargoAnterior(): ?string
    {
        return $this->cargoAnterior;
    }
    
    public function setCargoAnterior(?string $cargo): self
    {
        $this->cargoAnterior = $cargo;
        return $this;
    }
    
    public function getCargoNovo(): ?string
    {
        return $this->cargoNovo;
    }
    
    public function setCargoNovo(?string $cargo): self
    {
        $this->cargoNovo = $cargo;
        return $this;
    }
    
    public function getTipoMovimentacao(): string
    {
        return $this->tipoMovimentacao;
    }
    
    public function setTipoMovimentacao(string $tipo): self
    {
        $this->tipoMovimentacao = $tipo;
        return $this;
    }
    
    public function getDataMovimentacao(): string
    {
        return $this->dataMovimentacao;
    }
    
    public function setDataMovimentacao(string $data): self
    {
        $this->dataMovimentacao = $data;
        return $this;
    }
    
    public function getObservacao(): ?string
    {
        return $this->observacao;
    }
    
    public function setObservacao(?string $observacao): self
    {
        $this->observacao = $observacao;
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
    
    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }
    
    /**
     * Retorna label legível para o tipo de movimentação
     */
    public function getTipoLabel(): string
    {
        return match($this->tipoMovimentacao) {
            self::TIPO_PROMOCAO => '📈 Promoção',
            self::TIPO_REMOCAO => '📉 Remoção',
            self::TIPO_EXONERACAO => '🚪 Exoneração',
            self::TIPO_REINTEGRACAO => '🔄 Reintegração',
            self::TIPO_TRANSFERENCIA => '➡️ Transferência',
            default => $this->tipoMovimentacao,
        };
    }
    
    /**
     * Verifica se foi uma movimentação de entrada na organização
     */
    public function isEntrada(): bool
    {
        return in_array($this->tipoMovimentacao, [self::TIPO_REINTEGRACAO, self::TIPO_TRANSFERENCIA], true) 
            && $this->unidadeOrigemId === null;
    }
    
    /**
     * Verifica se foi uma movimentação de saída
     */
    public function isSaida(): bool
    {
        return $this->tipoMovimentacao === self::TIPO_EXONERACAO;
    }
    
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'unidade_origem_id' => $this->unidadeOrigemId,
            'unidade_origem' => $this->unidadeOrigem?->toArray(),
            'unidade_destino_id' => $this->unidadeDestinoId,
            'unidade_destino' => $this->unidadeDestino?->toArray(),
            'cargo_anterior' => $this->cargoAnterior,
            'cargo_novo' => $this->cargoNovo,
            'tipo_movimentacao' => $this->tipoMovimentacao,
            'tipo_label' => $this->getTipoLabel(),
            'data_movimentacao' => $this->dataMovimentacao,
            'observacao' => $this->observacao,
            'responsavel_id' => $this->responsavelId,
            'created_at' => $this->createdAt,
        ];
    }
}
