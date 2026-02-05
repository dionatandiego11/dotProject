<?php
/**
 * DotProject Validation Service
 * 
 * Provides centralized validation for entity data.
 * Can be used by both legacy and modern code.
 * 
 * @package DotProject\Service
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Service;

/**
 * Validation Service
 * 
 * Handles data validation with customizable rules.
 */
class ValidationService
{
    /** @var array<string, string> Validation errors */
    private array $errors = [];

    /** @var array<string, mixed> Data being validated */
    private array $data = [];

    /**
     * Start validating data
     * 
     * @param array<string, mixed> $data Data to validate
     * @return static
     */
    public function validate(array $data): static
    {
        $this->data = $data;
        $this->errors = [];
        return $this;
    }

    /**
     * Check if field is required (not empty)
     * 
     * @param string $field Field name
     * @param string|null $message Custom error message
     * @return static
     */
    public function required(string $field, ?string $message = null): static
    {
        $value = $this->data[$field] ?? null;

        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            $this->errors[$field] = $message ?? "O campo '{$field}' é obrigatório.";
        }

        return $this;
    }

    /**
     * Check minimum string length
     * 
     * @param string $field Field name
     * @param int $min Minimum length
     * @param string|null $message Custom error message
     * @return static
     */
    public function minLength(string $field, int $min, ?string $message = null): static
    {
        $value = $this->data[$field] ?? '';

        if (is_string($value) && mb_strlen($value) < $min) {
            $this->errors[$field] = $message ?? "O campo '{$field}' deve ter pelo menos {$min} caracteres.";
        }

        return $this;
    }

    /**
     * Check maximum string length
     * 
     * @param string $field Field name
     * @param int $max Maximum length
     * @param string|null $message Custom error message
     * @return static
     */
    public function maxLength(string $field, int $max, ?string $message = null): static
    {
        $value = $this->data[$field] ?? '';

        if (is_string($value) && mb_strlen($value) > $max) {
            $this->errors[$field] = $message ?? "O campo '{$field}' deve ter no máximo {$max} caracteres.";
        }

        return $this;
    }

    /**
     * Check if value is a valid email
     * 
     * @param string $field Field name
     * @param string|null $message Custom error message
     * @return static
     */
    public function email(string $field, ?string $message = null): static
    {
        $value = $this->data[$field] ?? '';

        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = $message ?? "O campo '{$field}' deve ser um email válido.";
        }

        return $this;
    }

    /**
     * Check if value is numeric
     * 
     * @param string $field Field name
     * @param string|null $message Custom error message
     * @return static
     */
    public function numeric(string $field, ?string $message = null): static
    {
        $value = $this->data[$field] ?? null;

        if ($value !== null && $value !== '' && !is_numeric($value)) {
            $this->errors[$field] = $message ?? "O campo '{$field}' deve ser numérico.";
        }

        return $this;
    }

    /**
     * Check if value is an integer
     * 
     * @param string $field Field name
     * @param string|null $message Custom error message
     * @return static
     */
    public function integer(string $field, ?string $message = null): static
    {
        $value = $this->data[$field] ?? null;

        if ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false) {
            $this->errors[$field] = $message ?? "O campo '{$field}' deve ser um número inteiro.";
        }

        return $this;
    }

    /**
     * Check if value is within a range
     * 
     * @param string $field Field name
     * @param int|float $min Minimum value
     * @param int|float $max Maximum value
     * @param string|null $message Custom error message
     * @return static
     */
    public function between(string $field, int|float $min, int|float $max, ?string $message = null): static
    {
        $value = $this->data[$field] ?? null;

        if (is_numeric($value) && ($value < $min || $value > $max)) {
            $this->errors[$field] = $message ?? "O campo '{$field}' deve estar entre {$min} e {$max}.";
        }

        return $this;
    }

    /**
     * Check if value is a valid date
     * 
     * @param string $field Field name
     * @param string $format Expected date format
     * @param string|null $message Custom error message
     * @return static
     */
    public function date(string $field, string $format = 'Y-m-d', ?string $message = null): static
    {
        $value = $this->data[$field] ?? '';

        if (!empty($value)) {
            $date = \DateTime::createFromFormat($format, $value);
            if (!$date || $date->format($format) !== $value) {
                $this->errors[$field] = $message ?? "O campo '{$field}' deve ser uma data válida no formato {$format}.";
            }
        }

        return $this;
    }

    /**
     * Check if value is in a list of allowed values
     * 
     * @param string $field Field name
     * @param array<int, mixed> $allowed Allowed values
     * @param string|null $message Custom error message
     * @return static
     */
    public function in(string $field, array $allowed, ?string $message = null): static
    {
        $value = $this->data[$field] ?? null;

        if ($value !== null && !in_array($value, $allowed, true)) {
            $this->errors[$field] = $message ?? "O valor do campo '{$field}' não é válido.";
        }

        return $this;
    }

    /**
     * Check if value matches a regex pattern
     * 
     * @param string $field Field name
     * @param string $pattern Regex pattern
     * @param string|null $message Custom error message
     * @return static
     */
    public function regex(string $field, string $pattern, ?string $message = null): static
    {
        $value = $this->data[$field] ?? '';

        if (!empty($value) && !preg_match($pattern, (string) $value)) {
            $this->errors[$field] = $message ?? "O campo '{$field}' possui um formato inválido.";
        }

        return $this;
    }

    /**
     * Custom validation with callback
     * 
     * @param string $field Field name
     * @param callable $callback Callback that returns bool
     * @param string $message Error message if validation fails
     * @return static
     */
    public function custom(string $field, callable $callback, string $message): static
    {
        $value = $this->data[$field] ?? null;

        if (!$callback($value, $this->data)) {
            $this->errors[$field] = $message;
        }

        return $this;
    }

    /**
     * Check if validation passed
     * 
     * @return bool
     */
    public function passes(): bool
    {
        return empty($this->errors);
    }

    /**
     * Check if validation failed
     * 
     * @return bool
     */
    public function fails(): bool
    {
        return !$this->passes();
    }

    /**
     * Get all validation errors
     * 
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get first error message
     * 
     * @return string|null
     */
    public function firstError(): ?string
    {
        return !empty($this->errors) ? reset($this->errors) : null;
    }

    /**
     * Get error for a specific field
     * 
     * @param string $field Field name
     * @return string|null
     */
    public function error(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }

    /**
     * Validate project data
     * 
     * @param array<string, mixed> $data Project data
     * @return static
     */
    public function validateProject(array $data): static
    {
        return $this->validate($data)
            ->required('project_name', 'O nome do projeto é obrigatório.')
            ->minLength('project_name', 3, 'O nome do projeto deve ter pelo menos 3 caracteres.')
            ->maxLength('project_name', 255, 'O nome do projeto deve ter no máximo 255 caracteres.')
            ->maxLength('project_short_name', 10, 'O nome curto deve ter no máximo 10 caracteres.')
            ->numeric('project_company', 'A empresa deve ser um valor numérico.')
            ->between('project_status', 0, 7, 'Status inválido.')
            ->between('project_priority', -1, 5, 'Prioridade inválida.');
    }

    /**
     * Validate task data
     * 
     * @param array<string, mixed> $data Task data
     * @return static
     */
    public function validateTask(array $data): static
    {
        return $this->validate($data)
            ->required('task_name', 'O nome da tarefa é obrigatório.')
            ->minLength('task_name', 2, 'O nome da tarefa deve ter pelo menos 2 caracteres.')
            ->maxLength('task_name', 255, 'O nome da tarefa deve ter no máximo 255 caracteres.')
            ->required('task_project', 'O projeto é obrigatório.')
            ->integer('task_project', 'O projeto deve ser um ID válido.')
            ->between('task_percent_complete', 0, 100, 'A porcentagem deve estar entre 0 e 100.')
            ->numeric('task_duration', 'A duração deve ser um valor numérico.');
    }

    /**
     * Validate user data
     * 
     * @param array<string, mixed> $data User data
     * @return static
     */
    public function validateUser(array $data): static
    {
        return $this->validate($data)
            ->required('user_username', 'O nome de usuário é obrigatório.')
            ->minLength('user_username', 3, 'O nome de usuário deve ter pelo menos 3 caracteres.')
            ->maxLength('user_username', 50, 'O nome de usuário deve ter no máximo 50 caracteres.')
            ->regex('user_username', '/^[a-zA-Z0-9_.-]+$/', 'O nome de usuário só pode conter letras, números, pontos, hífens e underscores.');
    }
}
