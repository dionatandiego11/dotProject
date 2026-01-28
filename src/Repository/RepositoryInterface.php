<?php
/**
 * Interface base para repositories
 * 
 * @package DotProject\Repository
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Repository;

interface RepositoryInterface
{
    /**
     * Encontra uma entidade pelo ID
     */
    public function find(int $id): ?object;

    /**
     * Encontra todas as entidades
     * 
     * @return array<object>
     */
    public function findAll(): array;

    /**
     * Encontra entidades por critérios
     * 
     * @param array<string, mixed> $criteria
     * @param array<string, string>|null $orderBy
     * @param int|null $limit
     * @param int|null $offset
     * @return array<object>
     */
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;

    /**
     * Encontra uma entidade por critérios
     * 
     * @param array<string, mixed> $criteria
     */
    public function findOneBy(array $criteria): ?object;

    /**
     * Salva uma entidade
     */
    public function save(object $entity): bool;

    /**
     * Remove uma entidade
     */
    public function delete(int $id): bool;

    /**
     * Conta entidades por critérios
     * 
     * @param array<string, mixed> $criteria
     */
    public function count(array $criteria = []): int;
}
