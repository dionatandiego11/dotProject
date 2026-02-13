<?php
/**
 * Entidade Meta (Indicador/Meta do PPA)
 * 
 * Metas são indicadores vinculados a uma Ação com valores
 * previstos e realizados por ano. Fonte de dado para TCE.
 *
 * @package DotProject\Entity
 */

declare(strict_types=1);

namespace DotProject\Entity;

use DateTime;

class MetaEntity
{
    private ?int $id = null;
    private int $acaoId;
    private string $descricao;
    private string $unidadeMedida = 'unidades';
    private float $valorPrevisto = 0.0;
    private float $valorRealizado = 0.0;
    private int $anoReferencia;
    private ?string $observacao = null;
    private ?int $tenantId = null;
    private ?DateTime $createdAt = null;
    private ?DateTime $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new DateTime();
    }

    // Getters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAcaoId(): int
    {
        return $this->acaoId;
    }

    public function getDescricao(): string
    {
        return $this->descricao;
    }

    public function getUnidadeMedida(): string
    {
        return $this->unidadeMedida;
    }

    public function getValorPrevisto(): float
    {
        return $this->valorPrevisto;
    }

    public function getValorRealizado(): float
    {
        return $this->valorRealizado;
    }

    public function getAnoReferencia(): int
    {
        return $this->anoReferencia;
    }

    public function getObservacao(): ?string
    {
        return $this->observacao;
    }

    public function getTenantId(): ?int
    {
        return $this->tenantId;
    }

    /**
     * Calcula percentual de realização: (realizado / previsto) × 100
     */
    public function getPercentRealizado(): float
    {
        if ($this->valorPrevisto <= 0) {
            return 0.0;
        }

        return round(($this->valorRealizado / $this->valorPrevisto) * 100, 2);
    }

    /**
     * Verifica se a meta foi atingida (realizado >= previsto)
     */
    public function isAtingida(): bool
    {
        return $this->valorRealizado >= $this->valorPrevisto && $this->valorPrevisto > 0;
    }

    // Setters

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function setAcaoId(int $acaoId): self
    {
        $this->acaoId = $acaoId;
        return $this;
    }

    public function setDescricao(string $descricao): self
    {
        $this->descricao = $descricao;
        return $this;
    }

    public function setUnidadeMedida(string $unidade): self
    {
        $this->unidadeMedida = $unidade;
        return $this;
    }

    public function setValorPrevisto(float $valor): self
    {
        $this->valorPrevisto = $valor;
        return $this;
    }

    public function setValorRealizado(float $valor): self
    {
        $this->valorRealizado = $valor;
        return $this;
    }

    public function setAnoReferencia(int $ano): self
    {
        $this->anoReferencia = $ano;
        return $this;
    }

    public function setObservacao(?string $obs): self
    {
        $this->observacao = $obs;
        return $this;
    }

    public function setTenantId(?int $id): self
    {
        $this->tenantId = $id;
        return $this;
    }

    public function setCreatedAt(?DateTime $dt): self
    {
        $this->createdAt = $dt;
        return $this;
    }

    public function setUpdatedAt(?DateTime $dt): self
    {
        $this->updatedAt = $dt;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'acao_id' => $this->acaoId,
            'descricao' => $this->descricao,
            'unidade_medida' => $this->unidadeMedida,
            'valor_previsto' => $this->valorPrevisto,
            'valor_realizado' => $this->valorRealizado,
            'percent_realizado' => $this->getPercentRealizado(),
            'atingida' => $this->isAtingida(),
            'ano_referencia' => $this->anoReferencia,
            'observacao' => $this->observacao,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
