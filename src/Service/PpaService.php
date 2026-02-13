<?php
/**
 * Serviço de PPA.
 *
 * @package DotProject\Service
 */

declare(strict_types=1);

namespace DotProject\Service;

use DotProject\Repository\PpaRepository;

class PpaService
{
    private PpaRepository $repository;

    public function __construct(?PpaRepository $repository = null)
    {
        $this->repository = $repository ?? new PpaRepository();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters = []): array
    {
        return $this->repository->list($filters);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->repository->find($id);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data, int $userId): array
    {
        $payload = $this->normalizePayload($data, true, $userId);
        $id = $this->repository->create($payload);

        $created = $this->repository->find($id);
        if ($created === null) {
            throw new \RuntimeException('PPA criado, mas não foi possível recarregar o registro.');
        }

        return $created;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function update(int $id, array $data): ?array
    {
        $existing = $this->repository->find($id);
        if ($existing === null) {
            return null;
        }

        $payload = $this->normalizePayload($data, false, null);
        if ($payload === []) {
            return $existing;
        }

        $this->repository->update($id, $payload);
        return $this->repository->find($id);
    }

    public function delete(int $id): bool
    {
        return $this->repository->delete($id);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizePayload(array $data, bool $isCreate, ?int $userId): array
    {
        $payload = [];

        if (array_key_exists('nome', $data) || $isCreate) {
            $nome = trim((string) ($data['nome'] ?? ''));
            if ($isCreate && $nome === '') {
                throw new \InvalidArgumentException('Nome do PPA é obrigatório.');
            }
            if ($nome !== '') {
                $payload['nome'] = $nome;
            }
        }

        if (array_key_exists('periodo_inicio', $data) || $isCreate) {
            $periodoInicio = (int) ($data['periodo_inicio'] ?? 0);
            if ($isCreate && $periodoInicio <= 0) {
                throw new \InvalidArgumentException('Período inicial é obrigatório.');
            }
            if ($periodoInicio > 0) {
                $payload['periodo_inicio'] = $periodoInicio;
            }
        }

        if (array_key_exists('periodo_fim', $data) || $isCreate) {
            $periodoFim = (int) ($data['periodo_fim'] ?? 0);
            if ($isCreate && $periodoFim <= 0) {
                throw new \InvalidArgumentException('Período final é obrigatório.');
            }
            if ($periodoFim > 0) {
                $payload['periodo_fim'] = $periodoFim;
            }
        }

        $inicio = $payload['periodo_inicio'] ?? (isset($data['periodo_inicio']) ? (int) $data['periodo_inicio'] : null);
        $fim = $payload['periodo_fim'] ?? (isset($data['periodo_fim']) ? (int) $data['periodo_fim'] : null);
        if ($inicio !== null && $fim !== null && $fim < $inicio) {
            throw new \InvalidArgumentException('Período final não pode ser menor que o período inicial.');
        }

        if (array_key_exists('estado', $data)) {
            $estado = trim((string) $data['estado']);
            if ($estado !== '') {
                $payload['estado'] = $estado;
            }
        } elseif ($isCreate) {
            $payload['estado'] = 'Rascunho';
        }

        if (array_key_exists('objetivo_geral', $data)) {
            $payload['objetivo_geral'] = $data['objetivo_geral'] !== null
                ? trim((string) $data['objetivo_geral'])
                : null;
        }

        if (array_key_exists('data_publicacao', $data)) {
            $payload['data_publicacao'] = $this->normalizeDate($data['data_publicacao']);
        }

        if (array_key_exists('prefeito_id', $data)) {
            $prefeitoId = (int) $data['prefeito_id'];
            if ($prefeitoId > 0) {
                $payload['prefeito_id'] = $prefeitoId;
            }
        } elseif ($isCreate && $userId !== null && $userId > 0) {
            $payload['prefeito_id'] = $userId;
        }

        return $payload;
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $date = trim((string) $value);
        if ($date === '') {
            return null;
        }

        $parsed = \DateTime::createFromFormat('Y-m-d', $date);
        if ($parsed === false) {
            throw new \InvalidArgumentException('Data inválida. Use o formato YYYY-MM-DD.');
        }

        return $parsed->format('Y-m-d');
    }
}

