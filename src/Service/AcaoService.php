<?php
/**
 * Servico de Acoes do PPA.
 *
 * @package DotProject\Service
 */

declare(strict_types=1);

namespace DotProject\Service;

use DotProject\Repository\AcaoRepository;
use DotProject\Repository\ProgramaRepository;

class AcaoService
{
    private AcaoRepository $repository;
    private ?ProgramaRepository $programaRepository;

    public function __construct(
        ?AcaoRepository $repository = null,
        ?ProgramaRepository $programaRepository = null
    ) {
        $this->repository = $repository ?? new AcaoRepository();
        $this->programaRepository = $programaRepository;
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
    public function create(array $data): array
    {
        $payload = $this->normalizePayload($data, true);
        $this->assertProgramaExiste((int) ($payload['programa_id'] ?? 0));

        $id = $this->repository->create($payload);
        $created = $this->repository->find($id);
        if ($created === null) {
            throw new \RuntimeException('Acao criada, mas nao foi possivel recarregar o registro.');
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

        $payload = $this->normalizePayload($data, false);
        if ($payload === []) {
            return $existing;
        }

        if (array_key_exists('programa_id', $payload) && $payload['programa_id'] !== null) {
            $this->assertProgramaExiste((int) $payload['programa_id']);
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
    private function normalizePayload(array $data, bool $isCreate): array
    {
        $payload = [];

        if (array_key_exists('nome', $data) || $isCreate) {
            $nome = trim((string) ($data['nome'] ?? ''));
            if ($isCreate && $nome === '') {
                throw new \InvalidArgumentException('Nome da acao e obrigatorio.');
            }
            if ($nome !== '') {
                $payload['nome'] = $nome;
            }
        }

        if (array_key_exists('programa_id', $data) || $isCreate) {
            $programaId = (int) ($data['programa_id'] ?? 0);
            if ($isCreate && $programaId <= 0) {
                throw new \InvalidArgumentException('Programa vinculado e obrigatorio.');
            }
            if ($programaId > 0) {
                $payload['programa_id'] = $programaId;
            }
        }

        if (array_key_exists('codigo', $data)) {
            $codigo = strtoupper(trim((string) $data['codigo']));
            if ($codigo !== '') {
                $payload['codigo'] = $codigo;
            }
        }

        if (array_key_exists('estado', $data)) {
            $estado = trim((string) $data['estado']);
            if ($estado !== '') {
                $payload['estado'] = $estado;
            }
        } elseif ($isCreate) {
            $payload['estado'] = 'Planejamento';
        }

        if (array_key_exists('objetivo', $data)) {
            $payload['objetivo'] = $this->normalizeNullableString($data['objetivo']);
        }

        if (array_key_exists('descricao', $data)) {
            $payload['descricao'] = $this->normalizeNullableString($data['descricao']);
        }

        if (array_key_exists('percent_execucao', $data)) {
            $percent = max(0, min(100, (float) $data['percent_execucao']));
            $payload['percent_execucao'] = $percent;
        }

        if (array_key_exists('valor_orcamentario', $data)) {
            $payload['valor_orcamentario'] = (float) $data['valor_orcamentario'];
        }

        if (array_key_exists('data_inicio', $data)) {
            $payload['data_inicio'] = $this->normalizeDate($data['data_inicio']);
        }

        if (array_key_exists('data_fim', $data)) {
            $payload['data_fim'] = $this->normalizeDate($data['data_fim']);
        }

        $inicio = $payload['data_inicio'] ?? (array_key_exists('data_inicio', $data) ? $this->normalizeDate($data['data_inicio']) : null);
        $fim = $payload['data_fim'] ?? (array_key_exists('data_fim', $data) ? $this->normalizeDate($data['data_fim']) : null);
        if ($inicio !== null && $fim !== null && $fim < $inicio) {
            throw new \InvalidArgumentException('Data final nao pode ser menor que a data inicial.');
        }

        return $payload;
    }

    private function assertProgramaExiste(int $programaId): void
    {
        if ($programaId <= 0) {
            throw new \InvalidArgumentException('Programa vinculado e obrigatorio.');
        }

        try {
            $programa = $this->getProgramaRepository()->find($programaId);
        } catch (\Throwable) {
            // Se tabela de programas nao existir no ambiente, deixa o banco tratar.
            return;
        }

        if ($programa === null) {
            throw new \InvalidArgumentException('Programa informado nao encontrado.');
        }
    }

    private function getProgramaRepository(): ProgramaRepository
    {
        if ($this->programaRepository === null) {
            $this->programaRepository = new ProgramaRepository();
        }

        return $this->programaRepository;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);
        return $normalized === '' ? null : $normalized;
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
            throw new \InvalidArgumentException('Data invalida. Use o formato YYYY-MM-DD.');
        }

        return $parsed->format('Y-m-d');
    }
}
