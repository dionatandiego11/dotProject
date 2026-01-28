<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Entidade de Configuração - Configurações do sistema
 * 
 * @package App\Entity
 */
class ConfigEntity
{
    private ?int $id = null;
    private string $name = '';
    private string $value = '';
    private string $group = '';
    private string $type = 'text';
    
    /**
     * Criar entidade a partir de array
     */
    public static function fromArray(array $data): self
    {
        $entity = new self();
        
        if (isset($data['config_id'])) {
            $entity->id = (int) $data['config_id'];
        }
        
        $entity->name = $data['config_name'] ?? '';
        $entity->value = $data['config_value'] ?? '';
        $entity->group = $data['config_group'] ?? '';
        $entity->type = $data['config_type'] ?? 'text';
        
        return $entity;
    }
    
    // Getters
    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getValue(): string { return $this->value; }
    public function getGroup(): string { return $this->group; }
    public function getType(): string { return $this->type; }
    
    // Setters
    public function setName(string $name): void { $this->name = $name; }
    public function setValue(string $value): void { $this->value = $value; }
    public function setGroup(string $group): void { $this->group = $group; }
    public function setType(string $type): void { $this->type = $type; }
    
    /**
     * Converter para array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'value' => $this->value,
            'group' => $this->group,
            'type' => $this->type,
            'typed_value' => $this->getTypedValue(),
        ];
    }
    
    /**
     * Obter valor convertido para o tipo correto
     */
    public function getTypedValue(): mixed
    {
        return match ($this->type) {
            'checkbox' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'int', 'integer' => (int) $this->value,
            'float', 'decimal' => (float) $this->value,
            'array' => explode(',', $this->value),
            'json' => json_decode($this->value, true),
            default => $this->value,
        };
    }
    
    /**
     * Verificar se é checkbox
     */
    public function isCheckbox(): bool
    {
        return $this->type === 'checkbox';
    }
    
    /**
     * Verificar se é texto
     */
    public function isText(): bool
    {
        return $this->type === 'text';
    }
    
    /**
     * Verificar se valor booleano é true
     */
    public function isTrue(): bool
    {
        if ($this->type === 'checkbox') {
            return $this->getTypedValue() === true;
        }
        return in_array(strtolower($this->value), ['true', '1', 'yes', 'on']);
    }
    
    /**
     * Obter grupo formatado
     */
    public function getGroupLabel(): string
    {
        $labels = [
            'ui' => 'Interface',
            'tasks' => 'Tarefas',
            'projects' => 'Projetos',
            'calendar' => 'Calendário',
            'files' => 'Arquivos',
            'system' => 'Sistema',
            'security' => 'Segurança',
        ];
        
        return $labels[$this->group] ?? ucfirst($this->group);
    }
    
    /**
     * Obter nome formatado
     */
    public function getDisplayName(): string
    {
        return ucwords(str_replace('_', ' ', $this->name));
    }
}
