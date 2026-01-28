<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Entidade de Valor de Sistema (SysVal) - Configurações dinâmicas
 * 
 * @package App\Entity
 */
class SysValEntity
{
    private ?int $id = null;
    private int $keyId = 0;
    private string $title = '';
    private string $value = '';
    
    // Parsed values (for select lists)
    private array $parsedValues = [];
    
    /**
     * Criar entidade a partir de array
     */
    public static function fromArray(array $data): self
    {
        $entity = new self();
        
        if (isset($data['sysval_id'])) {
            $entity->id = (int) $data['sysval_id'];
        }
        
        $entity->keyId = (int) ($data['sysval_key_id'] ?? 0);
        $entity->title = $data['sysval_title'] ?? '';
        $entity->value = $data['sysval_value'] ?? '';
        
        // Parse valores de lista
        $entity->parsedValues = $entity->parseValue();
        
        return $entity;
    }
    
    // Getters
    public function getId(): ?int { return $this->id; }
    public function getKeyId(): int { return $this->keyId; }
    public function getTitle(): string { return $this->title; }
    public function getValue(): string { return $this->value; }
    public function getParsedValues(): array { return $this->parsedValues; }
    
    // Setters
    public function setKeyId(int $keyId): void { $this->keyId = $keyId; }
    public function setTitle(string $title): void { $this->title = $title; }
    public function setValue(string $value): void { 
        $this->value = $value; 
        $this->parsedValues = $this->parseValue();
    }
    
    /**
     * Converter para array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'key_id' => $this->keyId,
            'title' => $this->title,
            'value' => $this->value,
            'parsed_values' => $this->parsedValues,
        ];
    }
    
    /**
     * Parsear valor (formato: key|label\nkey2|label2)
     */
    private function parseValue(): array
    {
        $result = [];
        $lines = explode("\n", $this->value);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            $parts = explode('|', $line, 2);
            if (count($parts) === 2) {
                $result[trim($parts[0])] = trim($parts[1]);
            } else {
                $result[trim($parts[0])] = trim($parts[0]);
            }
        }
        
        return $result;
    }
    
    /**
     * Obter label para um valor
     */
    public function getLabel(string $key): ?string
    {
        return $this->parsedValues[$key] ?? null;
    }
    
    /**
     * Verificar se é uma lista de seleção
     */
    public function isSelectList(): bool
    {
        return $this->keyId === 1;
    }
    
    /**
     * Verificar se é um campo customizado
     */
    public function isCustomField(): bool
    {
        return $this->keyId === 2;
    }
    
    /**
     * Verificar se é seleção de cor
     */
    public function isColorSelection(): bool
    {
        return $this->keyId === 3;
    }
    
    /**
     * Obter valor como array
     */
    public function getValueAsArray(): array
    {
        return $this->parsedValues;
    }
    
    /**
     * Serializar valores para salvar
     */
    public static function serializeValues(array $values): string
    {
        $lines = [];
        foreach ($values as $key => $label) {
            $lines[] = $key . '|' . $label;
        }
        return implode("\n", $lines);
    }
}
