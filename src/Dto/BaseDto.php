<?php
/**
 * Base DTO
 * 
 * Classe base para Data Transfer Objects.
 * 
 * @package DotProject\Dto
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Dto;

/**
 * Base para todos os DTOs
 * 
 * Fornece metodos comuns para conversao e validacao.
 */
abstract class BaseDto
{
    /**
     * Cria DTO a partir de array
     */
    public static function fromArray(array $data): static
    {
        $dto = new static();
        
        foreach ($data as $key => $value) {
            $property = self::snakeToCamel($key);
            if (property_exists($dto, $property)) {
                $dto->$property = $value;
            }
        }
        
        return $dto;
    }
    
    /**
     * Converte DTO para array
     */
    public function toArray(): array
    {
        $data = [];
        
        foreach (get_object_vars($this) as $property => $value) {
            $key = self::camelToSnake($property);
            $data[$key] = $this->serializeValue($value);
        }
        
        return $data;
    }
    
    /**
     * Serializa valor para array
     */
    protected function serializeValue(mixed $value): mixed
    {
        if ($value instanceof \DateTime) {
            return $value->format('Y-m-d H:i:s');
        }
        
        if ($value instanceof \DateTimeImmutable) {
            return $value->format('Y-m-d H:i:s');
        }
        
        if (is_array($value)) {
            return array_map([$this, 'serializeValue'], $value);
        }
        
        if ($value instanceof BaseDto) {
            return $value->toArray();
        }
        
        return $value;
    }
    
    /**
     * Valida o DTO
     * 
     * @return array<string, string> Erros de validacao
     */
    abstract public function validate(): array;
    
    /**
     * Verifica se DTO e valido
     */
    public function isValid(): bool
    {
        return empty($this->validate());
    }
    
    /**
     * Converte snake_case para camelCase
     */
    protected static function snakeToCamel(string $str): string
    {
        return lcfirst(str_replace('_', '', ucwords($str, '_')));
    }
    
    /**
     * Converte camelCase para snake_case
     */
    protected static function camelToSnake(string $str): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $str));
    }
}
