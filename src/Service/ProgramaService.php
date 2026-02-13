<?php
/**
 * Serviço de Programas do PPA.
 *
 * @package DotProject\Service
 */

declare(strict_types=1);

namespace DotProject\Service;

use DotProject\Repository\ProgramaRepository;

class ProgramaService
{
    private ProgramaRepository $repository;

    public function __construct(?ProgramaRepository $repository = null)
    {
        $this->repository = $repository ?? new ProgramaRepository();
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
        $id = $this->repository->create($payload);

        $created = $this->repository->find($id);
        if ($created === null) {
            throw new \RuntimeException('Programa criado, mas não foi possível recarregar o registro.');
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
                throw new \InvalidArgumentException('Nome do programa é obrigatório.');
            }
            if ($nome !== '') {
                $payload['nome'] = $nome;
            }
        }

        if (array_key_exists('unidade_id', $data) || $isCreate) {
            $unidadeId = (int) ($data['unidade_id'] ?? 0);
            if ($isCreate && $unidadeId <= 0) {
                throw new \InvalidArgumentException('Unidade responsável é obrigatória.');
            }
            if ($unidadeId > 0) {
                $payload['unidade_id'] = $unidadeId;
            }
        }

        if (array_key_exists('ppa_id', $data)) {
            $ppaId = (int) $data['ppa_id'];
            if ($ppaId > 0) {
                $payload['ppa_id'] = $ppaId;
            }
        }

        if (array_key_exists('codigo', $data)) {
            $codigo = strtoupper(trim((string) $data['codigo']));
            if ($codigo !== '') {
                $payload['codigo'] = $codigo;
            }
        }

        if (array_key_exists('objetivo', $data)) {
            $payload['objetivo'] = $this->normalizeNullableString($data['objetivo']);
            $payload['objetivo_estrategico'] = $this->normalizeNullableString($data['objetivo']);
        }

        if (array_key_exists('objetivo_estrategico', $data)) {
            $payload['objetivo_estrategico'] = $this->normalizeNullableString($data['objetivo_estrategico']);
        }

        if (array_key_exists('descricao', $data)) {
            $payload['descricao'] = $this->normalizeNullableString($data['descricao']);
        }

        if (array_key_exists('estado', $data)) {
            $estado = trim((string) $data['estado']);
            if ($estado !== '') {
                $payload['estado'] = $estado;
            }
        }

        if (array_key_exists('percent_execucao', $data)) {
            $percent = max(0, min(100, (float) $data['percent_execucao']));
            $payload['percent_execucao'] = $percent;
        }

        if (array_key_exists('prioridade', $data)) {
            $prioridade = trim((string) $data['prioridade']);
            if ($prioridade !== '') {
                $payload['prioridade'] = $prioridade;
            }
        }

        if (array_key_exists('eixo_ppa', $data)) {
            $payload['eixo_ppa'] = $this->normalizeNullableString($data['eixo_ppa']);
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

        if (array_key_exists('responsavel_politico_id', $data)) {
            $id = (int) $data['responsavel_politico_id'];
            $payload['responsavel_politico_id'] = $id > 0 ? $id : null;
        }

        if (array_key_exists('responsavel_tecnico_id', $data)) {
            $id = (int) $data['responsavel_tecnico_id'];
            $payload['responsavel_tecnico_id'] = $id > 0 ? $id : null;
        }

        if (array_key_exists('observacao_estrategica', $data)) {
            $payload['observacao_estrategica'] = $this->normalizeNullableString($data['observacao_estrategica']);
        }

        return $payload;
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
            throw new \InvalidArgumentException('Data inválida. Use o formato YYYY-MM-DD.');
        }

        return $parsed->format('Y-m-d');
    }
}

