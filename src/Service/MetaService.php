<?php
/**
 * Serviço de Metas do PPA.
 *
 * Valida, normaliza e coordena operações de CRUD de Metas.
 * Metas são a FONTE oficial de valor_realizado para prestação de contas (TCE).
 *
 * @package DotProject\Service
 */

declare(strict_types=1);

namespace DotProject\Service;

use DotProject\Repository\MetaRepository;
use InvalidArgumentException;

class MetaService
{
    private MetaRepository $repository;

    public function __construct(MetaRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Lista metas com filtros opcionais.
     */
    public function listar(array $filters = []): array
    {
        return $this->repository->findAll($filters);
    }

    /**
     * Busca uma meta pelo ID.
     *
     * @throws InvalidArgumentException Se a meta não existir
     */
    public function buscarPorId(int $id): array
    {
        $meta = $this->repository->findById($id);

        if (!$meta) {
            throw new InvalidArgumentException("Meta #{$id} não encontrada.");
        }

        return $meta;
    }

    /**
     * Cria uma nova meta.
     *
     * @throws InvalidArgumentException Se dados inválidos
     */
    public function criar(array $data): array
    {
        $data = $this->normalizePayload($data);
        $this->validar($data);

        $id = $this->repository->create($data);

        return $this->repository->findById($id);
    }

    /**
     * Atualiza uma meta.
     *
     * @throws InvalidArgumentException Se a meta não existir ou dados inválidos
     */
    public function atualizar(int $id, array $data): array
    {
        // Verifica existência
        $this->buscarPorId($id);

        $data = $this->normalizePayload($data, true);
        $this->validar($data, true);

        $this->repository->update($id, $data);

        return $this->repository->findById($id);
    }

    /**
     * Exclui uma meta.
     *
     * @throws InvalidArgumentException Se a meta não existir
     */
    public function excluir(int $id): bool
    {
        $this->buscarPorId($id);
        return $this->repository->delete($id);
    }

    /**
     * Resumo de metas por ação agrupado por ano.
     */
    public function resumoPorAcao(int $acaoId): array
    {
        return $this->repository->resumoPorAcao($acaoId);
    }

    // ========================================================================
    // Validação & Normalização
    // ========================================================================

    /**
     * Normaliza payload de entrada.
     */
    private function normalizePayload(array $data, bool $isUpdate = false): array
    {
        $normalized = [];

        if (isset($data['acao_id'])) {
            $normalized['acao_id'] = (int) $data['acao_id'];
        }

        if (isset($data['descricao'])) {
            $normalized['descricao'] = trim((string) $data['descricao']);
        }

        if (isset($data['unidade_medida'])) {
            $normalized['unidade_medida'] = trim((string) $data['unidade_medida']);
        }

        if (isset($data['valor_previsto'])) {
            $normalized['valor_previsto'] = (float) $data['valor_previsto'];
        }

        if (isset($data['valor_realizado'])) {
            $normalized['valor_realizado'] = (float) $data['valor_realizado'];
        }

        if (isset($data['ano_referencia'])) {
            $normalized['ano_referencia'] = (int) $data['ano_referencia'];
        }

        if (array_key_exists('observacao', $data)) {
            $normalized['observacao'] = $data['observacao'] !== null ? trim((string) $data['observacao']) : null;
        }

        return $normalized;
    }

    /**
     * Valida dados de meta.
     *
     * @throws InvalidArgumentException
     */
    private function validar(array $data, bool $isUpdate = false): void
    {
        if (!$isUpdate) {
            if (empty($data['acao_id'])) {
                throw new InvalidArgumentException('O campo acao_id é obrigatório.');
            }

            if (empty($data['descricao'])) {
                throw new InvalidArgumentException('O campo descricao é obrigatório.');
            }

            if (empty($data['ano_referencia'])) {
                throw new InvalidArgumentException('O campo ano_referencia é obrigatório.');
            }
        }

        if (isset($data['descricao']) && strlen($data['descricao']) > 300) {
            throw new InvalidArgumentException('A descricao deve ter no máximo 300 caracteres.');
        }

        if (isset($data['valor_previsto']) && $data['valor_previsto'] < 0) {
            throw new InvalidArgumentException('O valor_previsto não pode ser negativo.');
        }

        if (isset($data['valor_realizado']) && $data['valor_realizado'] < 0) {
            throw new InvalidArgumentException('O valor_realizado não pode ser negativo.');
        }

        if (isset($data['ano_referencia'])) {
            $ano = $data['ano_referencia'];
            if ($ano < 2000 || $ano > 2100) {
                throw new InvalidArgumentException('O ano_referencia deve estar entre 2000 e 2100.');
            }
        }
    }
}
