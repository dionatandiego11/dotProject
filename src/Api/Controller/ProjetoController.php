<?php
/**
 * Controller de Projetos - API REST
 *
 * @package DotProject\Api\Controller
 */

declare(strict_types=1);

namespace DotProject\Api\Controller;

use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Entity\PermissaoNivelEntity;
use DotProject\Entity\ProjetoEntity;
use DotProject\Repository\ProjetoRepository;
use DotProject\Service\KpiCalculationService;
use DotProject\Service\PermissionService;

class ProjetoController extends BaseController
{
    private ProjetoRepository $repository;
    private PermissionService $permission;
    private KpiCalculationService $kpiService;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->repository = new ProjetoRepository();
        $this->permission = new PermissionService();
        $this->kpiService = new KpiCalculationService();
    }

    /**
     * GET /v1/projetos
     */
    public function index(): void
    {
        try {
            $userId = $this->requireUserId();
            if ($userId === null) {
                return;
            }

            if (!$this->hasProjetoPermission($userId, PermissaoNivelEntity::ACAO_VISUALIZAR)) {
                $this->response->forbidden('Sem permissao')->send();
                return;
            }

            $filtros = $this->request->getQueryParams();
            $unidades = $this->permission->getUserUnidadesIds($userId);
            if (!empty($unidades)) {
                $filtros['unidade_id'] = $unidades;
            }

            $projetos = $this->repository->findByFiltros($filtros);

            $this->response->json([
                'data' => array_map(fn($p) => $p->toArray(), $projetos),
                'total' => count($projetos),
            ])->send();
        } catch (\Throwable $e) {
            $this->response->error($e->getMessage(), 500)->send();
        }
    }

    /**
     * GET /v1/projetos/{id}
     */
    public function show(): void
    {
        try {
            $userId = $this->requireUserId();
            if ($userId === null) {
                return;
            }

            $id = (int) $this->request->getParam('id');
            $projeto = $this->repository->find($id);

            if (!$projeto) {
                $this->response->json(['error' => 'Projeto nao encontrado'], 404)->send();
                return;
            }

            if (!$this->hasProjetoPermission($userId, PermissaoNivelEntity::ACAO_VISUALIZAR, $id)) {
                $this->response->forbidden('Sem permissao')->send();
                return;
            }

            $this->response->json([
                'data' => $projeto->toArray(),
                'etapas' => array_map(fn($e) => $e->toArray(), $projeto->getEtapas()),
                'kpi' => $this->kpiService->getEstatisticasTarefas($id),
            ])->send();
        } catch (\Throwable $e) {
            $this->response->error($e->getMessage(), 500)->send();
        }
    }

    /**
     * POST /v1/projetos
     */
    public function store(): void
    {
        try {
            $userId = $this->requireUserId();
            if ($userId === null) {
                return;
            }

            if (!$this->hasProjetoPermission($userId, PermissaoNivelEntity::ACAO_CRIAR)) {
                $this->response->forbidden('Sem permissao')->send();
                return;
            }

            $dados = $this->request->getBody();
            $erros = $this->validarDados($dados);
            if (!empty($erros)) {
                $this->response->json(['error' => 'Dados invalidos', 'details' => $erros], 422)->send();
                return;
            }

            $projeto = $this->repository->create($dados);
            $this->response->json([
                'message' => 'Projeto criado com sucesso',
                'data' => $projeto->toArray(),
            ], 201)->send();
        } catch (\Throwable $e) {
            $this->response->error($e->getMessage(), 500)->send();
        }
    }

    /**
     * PUT /v1/projetos/{id}
     */
    public function update(): void
    {
        try {
            $userId = $this->requireUserId();
            if ($userId === null) {
                return;
            }

            $id = (int) $this->request->getParam('id');
            $projeto = $this->repository->find($id);
            if (!$projeto) {
                $this->response->json(['error' => 'Projeto nao encontrado'], 404)->send();
                return;
            }

            if (!$this->hasProjetoPermission($userId, PermissaoNivelEntity::ACAO_EDITAR, $id)) {
                $this->response->forbidden('Sem permissao')->send();
                return;
            }

            $dados = $this->request->getBody();
            $this->applyProjetoUpdates($projeto, $dados);
            $this->repository->save($projeto);

            $this->response->json([
                'message' => 'Projeto atualizado com sucesso',
                'data' => $projeto->toArray(),
            ])->send();
        } catch (\InvalidArgumentException $e) {
            $this->response->json(['error' => 'Dados invalidos', 'message' => $e->getMessage()], 422)->send();
        } catch (\Throwable $e) {
            $this->response->error($e->getMessage(), 500)->send();
        }
    }

    /**
     * POST /v1/projetos/{id}/transicionar
     */
    public function transicionarEstado(): void
    {
        try {
            $userId = $this->requireUserId();
            if ($userId === null) {
                return;
            }

            $id = (int) $this->request->getParam('id');
            $body = $this->request->getBody();
            $novoEstado = $body['estado'] ?? null;
            $justificativa = $body['justificativa'] ?? null;

            if (!$novoEstado) {
                $this->response->json(['error' => 'Estado obrigatorio'], 400)->send();
                return;
            }

            $projeto = $this->repository->find($id);
            if (!$projeto) {
                $this->response->json(['error' => 'Projeto nao encontrado'], 404)->send();
                return;
            }

            if (!$this->hasProjetoPermission($userId, PermissaoNivelEntity::ACAO_EDITAR, $id)) {
                $this->response->forbidden('Sem permissao')->send();
                return;
            }

            $estadoAnterior = $projeto->getEstado();
            $projeto->transicionarEstado((string) $novoEstado, [
                'justificativa' => $justificativa,
                'usuario_id' => $userId,
            ]);

            $this->repository->save($projeto);

            $this->response->json([
                'message' => 'Estado atualizado',
                'data' => [
                    'id' => $projeto->getId(),
                    'estado_anterior' => $estadoAnterior,
                    'estado_novo' => $novoEstado,
                    'percent_execucao' => $projeto->getPercentExecucao(),
                ],
            ])->send();
        } catch (\InvalidArgumentException $e) {
            $this->response->json([
                'error' => 'Transicao invalida',
                'message' => $e->getMessage(),
            ], 422)->send();
        } catch (\Throwable $e) {
            $this->response->error($e->getMessage(), 500)->send();
        }
    }

    /**
     * POST /v1/projetos/{id}/avancar-etapa
     */
    public function avancarEtapa(): void
    {
        try {
            $userId = $this->requireUserId();
            if ($userId === null) {
                return;
            }

            $id = (int) $this->request->getParam('id');
            $projeto = $this->repository->find($id);

            if (!$projeto) {
                $this->response->json(['error' => 'Projeto nao encontrado'], 404)->send();
                return;
            }

            if (!$this->hasProjetoPermission($userId, PermissaoNivelEntity::ACAO_EDITAR, $id)) {
                $this->response->forbidden('Sem permissao')->send();
                return;
            }

            $projeto->avancarEtapa();
            $this->repository->save($projeto);

            $this->response->json([
                'message' => 'Etapa avancada com sucesso',
                'data' => [
                    'etapa_anterior' => $projeto->getEtapaAtualNumero() - 1,
                    'etapa_atual' => $projeto->getEtapaAtualNumero(),
                    'etapa_nome' => $projeto->getEtapaAtual()->getNome(),
                    'estado' => $projeto->getEstado(),
                    'percent_execucao' => $projeto->getPercentExecucao(),
                ],
            ])->send();
        } catch (\InvalidArgumentException $e) {
            $this->response->json([
                'error' => 'Nao e possivel avancar',
                'message' => $e->getMessage(),
            ], 422)->send();
        } catch (\Throwable $e) {
            $this->response->error($e->getMessage(), 500)->send();
        }
    }

    /**
     * GET /v1/projetos/{id}/etapas
     */
    public function etapas(): void
    {
        try {
            $userId = $this->requireUserId();
            if ($userId === null) {
                return;
            }

            $id = (int) $this->request->getParam('id');
            $projeto = $this->repository->find($id);

            if (!$projeto) {
                $this->response->json(['error' => 'Projeto nao encontrado'], 404)->send();
                return;
            }

            if (!$this->hasProjetoPermission($userId, PermissaoNivelEntity::ACAO_VISUALIZAR, $id)) {
                $this->response->forbidden('Sem permissao')->send();
                return;
            }

            $this->response->json([
                'data' => array_map(fn($e) => $e->toArray(), $projeto->getEtapas()),
            ])->send();
        } catch (\Throwable $e) {
            $this->response->error($e->getMessage(), 500)->send();
        }
    }

    /**
     * PUT /v1/projetos/{id}/etapas/{etapaId}
     */
    public function atualizarEtapa(): void
    {
        try {
            $userId = $this->requireUserId();
            if ($userId === null) {
                return;
            }

            $projetoId = (int) $this->request->getParam('id');
            $etapaId = (int) $this->request->getParam('etapaId');
            $dados = $this->request->getBody();

            $projeto = $this->repository->find($projetoId);
            if (!$projeto) {
                $this->response->json(['error' => 'Projeto nao encontrado'], 404)->send();
                return;
            }

            if (!$this->hasProjetoPermission($userId, PermissaoNivelEntity::ACAO_EDITAR, $projetoId)) {
                $this->response->forbidden('Sem permissao')->send();
                return;
            }

            $etapas = $projeto->getEtapas();
            $etapa = $etapas[$etapaId] ?? null;
            if (!$etapa) {
                $this->response->json(['error' => 'Etapa nao encontrada'], 404)->send();
                return;
            }

            if (isset($dados['data_prevista_fim'])) {
                $etapa->setDataPrevistaFim($this->parseNullableDate($dados['data_prevista_fim'], 'data_prevista_fim'));
            }
            if (isset($dados['responsavel_id'])) {
                $etapa->setResponsavelId($dados['responsavel_id'] !== null ? (int) $dados['responsavel_id'] : null);
            }

            $this->repository->save($projeto);

            $this->response->json([
                'message' => 'Etapa atualizada',
                'data' => $etapa->toArray(),
            ])->send();
        } catch (\InvalidArgumentException $e) {
            $this->response->json(['error' => 'Dados invalidos', 'message' => $e->getMessage()], 422)->send();
        } catch (\Throwable $e) {
            $this->response->error($e->getMessage(), 500)->send();
        }
    }

    /**
     * POST /v1/projetos/{id}/etapas/{etapaId}/concluir
     */
    public function concluirEtapa(): void
    {
        try {
            $userId = $this->requireUserId();
            if ($userId === null) {
                return;
            }

            $projetoId = (int) $this->request->getParam('id');
            $numeroEtapa = (int) $this->request->getParam('etapaId');
            $dados = $this->request->getBody();

            $projeto = $this->repository->find($projetoId);
            if (!$projeto) {
                $this->response->json(['error' => 'Projeto nao encontrado'], 404)->send();
                return;
            }

            if (!$this->hasProjetoPermission($userId, PermissaoNivelEntity::ACAO_EDITAR, $projetoId)) {
                $this->response->forbidden('Sem permissao')->send();
                return;
            }

            $etapa = $projeto->getEtapas()[$numeroEtapa] ?? null;
            if (!$etapa) {
                $this->response->json(['error' => 'Etapa nao encontrada'], 404)->send();
                return;
            }

            if ($etapa->estaAtrasada() && empty($dados['justificativa_atraso'])) {
                $this->response->json([
                    'error' => 'Justificativa de atraso e obrigatoria',
                ], 422)->send();
                return;
            }

            if (!empty($dados['justificativa_atraso'])) {
                $etapa->setJustificativaAtraso($dados['justificativa_atraso']);
            }
            if (!empty($dados['evidencia_url'])) {
                $etapa->setEvidenciaUrl($dados['evidencia_url']);
            }

            $etapa->finalizar();
            $this->repository->save($projeto);

            $this->response->json([
                'message' => 'Etapa concluida',
                'data' => $etapa->toArray(),
                'proxima_acao' => $projeto->getEtapaAtualNumero() < 5 ? 'avancar_etapa' : null,
            ])->send();
        } catch (\DomainException $e) {
            $this->response->json(['error' => $e->getMessage()], 422)->send();
        } catch (\Throwable $e) {
            $this->response->error($e->getMessage(), 500)->send();
        }
    }

    private function requireUserId(): ?int
    {
        $userId = $this->getUserId();
        if ($userId === null || $userId <= 0) {
            $this->response->unauthorized('Usuario nao autenticado')->send();
            return null;
        }

        return $userId;
    }

    private function hasProjetoPermission(int $userId, string $acao, ?int $projetoId = null): bool
    {
        return $this->permission->can($userId, PermissaoNivelEntity::RECURSO_PROJETO, $acao, $projetoId);
    }

    private function applyProjetoUpdates(ProjetoEntity $projeto, array $dados): void
    {
        if (array_key_exists('nome', $dados) && $dados['nome'] !== '') {
            $projeto->setNome((string) $dados['nome']);
        }
        if (array_key_exists('tipo', $dados) && $dados['tipo'] !== '') {
            $projeto->setTipo((string) $dados['tipo']);
        }
        if (array_key_exists('descricao', $dados)) {
            $projeto->setDescricao($dados['descricao'] !== null ? (string) $dados['descricao'] : null);
        }
        if (array_key_exists('fonte_recurso', $dados)) {
            $projeto->setFonteRecurso($dados['fonte_recurso'] !== null ? (string) $dados['fonte_recurso'] : null);
        }
        if (array_key_exists('valor_previsto', $dados)) {
            $projeto->setValorPrevisto($dados['valor_previsto'] !== null && $dados['valor_previsto'] !== '' ? (float) $dados['valor_previsto'] : null);
        }
        if (array_key_exists('situacao_orcamentaria', $dados)) {
            $projeto->setSituacaoOrcamentaria($dados['situacao_orcamentaria'] !== null ? (string) $dados['situacao_orcamentaria'] : null);
        }
        if (array_key_exists('unidade_id', $dados) && $dados['unidade_id'] !== null && $dados['unidade_id'] !== '') {
            $projeto->setUnidadeId((int) $dados['unidade_id']);
        }
        if (array_key_exists('coordenador_id', $dados) && $dados['coordenador_id'] !== null && $dados['coordenador_id'] !== '') {
            $projeto->setCoordenadorId((int) $dados['coordenador_id']);
        }
        if (array_key_exists('programa_id', $dados)) {
            $projeto->setProgramaId($dados['programa_id'] !== null && $dados['programa_id'] !== '' ? (int) $dados['programa_id'] : null);
        }
        if (array_key_exists('estado', $dados) && $dados['estado'] !== '') {
            $projeto->setEstado((string) $dados['estado']);
        }
        if (array_key_exists('percent_execucao', $dados) && $dados['percent_execucao'] !== null && $dados['percent_execucao'] !== '') {
            $projeto->setPercentExecucao((float) $dados['percent_execucao']);
        }
        if (array_key_exists('data_prevista_inicio', $dados)) {
            $projeto->setDataPrevistaInicio($this->parseNullableDate($dados['data_prevista_inicio'], 'data_prevista_inicio'));
        }
        if (array_key_exists('data_prevista_fim', $dados)) {
            $projeto->setDataPrevistaFim($this->parseNullableDate($dados['data_prevista_fim'], 'data_prevista_fim'));
        }
    }

    private function parseNullableDate(mixed $value, string $field): ?\DateTime
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new \DateTime((string) $value);
        } catch (\Throwable) {
            throw new \InvalidArgumentException("Campo {$field} possui data invalida");
        }
    }

    private function validarDados(array $dados): array
    {
        $erros = [];

        if (empty($dados['nome'])) {
            $erros['nome'] = 'Nome e obrigatorio';
        }
        if (empty($dados['tipo'])) {
            $erros['tipo'] = 'Tipo e obrigatorio';
        }
        if (empty($dados['coordenador_id'])) {
            $erros['coordenador_id'] = 'Coordenador e obrigatorio';
        }
        if (!isset($dados['unidade_id']) || $dados['unidade_id'] === '' || $dados['unidade_id'] === null) {
            $erros['unidade_id'] = 'Unidade e obrigatoria';
        }

        return $erros;
    }
}
