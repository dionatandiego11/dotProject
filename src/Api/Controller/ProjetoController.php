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
use DotProject\Repository\ProjetoRepository;
use DotProject\Service\PermissionService;
use DotProject\Service\KpiCalculationService;

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
        $user = $this->getCurrentUser();
        $filtros = $this->request->getQueryParams();
        
        // Aplica filtro de escopo
        $unidades = $this->permission->getUserUnidadesIds($user->getId());
        if (!empty($unidades)) {
            $filtros['unidade_id'] = $unidades;
        }
        
        $projetos = $this->repository->findByFiltros($filtros);
        
        $this->response->json([
            'data' => array_map(fn($p) => $p->toArray(), $projetos),
            'total' => count($projetos),
        ]);
    }
    
    /**
     * GET /v1/projetos/{id}
     */
    public function show(): void
    {
        $id = (int) $this->request->getParam('id');
        $projeto = $this->repository->find($id);
        
        if (!$projeto) {
            return $this->response->json(['error' => 'Projeto não encontrado'], 404);
        }
        
        // Verifica permissão
        if (!$this->permission->can($this->getCurrentUser(), 'projeto.read', $projeto)) {
            return $this->response->json(['error' => 'Sem permissão'], 403);
        }
        
        $this->response->json([
            'data' => $projeto->toArray(),
            'etapas' => array_map(fn($e) => $e->toArray(), $projeto->getEtapas()),
            'kpi' => $this->kpiService->getEstatisticasTarefas($id),
        ]);
    }
    
    /**
     * POST /v1/projetos
     */
    public function store(): void
    {
        if (!$this->permission->can($this->getCurrentUser(), 'projeto.create')) {
            return $this->response->json(['error' => 'Sem permissão'], 403);
        }
        
        $dados = $this->request->getBody();
        
        // Validação básica
        $erros = $this->validarDados($dados);
        if (!empty($erros)) {
            return $this->response->json(['error' => 'Dados inválidos', 'details' => $erros], 422);
        }
        
        try {
            $projeto = $this->repository->create($dados);
            
            $this->response->json([
                'message' => 'Projeto criado com sucesso',
                'data' => $projeto->toArray(),
            ], 201);
            
        } catch (\Exception $e) {
            $this->response->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * PUT /v1/projetos/{id}/transicionar
     * Transiciona estado do projeto
     */
    public function transicionarEstado(): void
    {
        $id = (int) $this->request->getParam('id');
        $body = $this->request->getBody();
        
        $novoEstado = $body['estado'] ?? null;
        $justificativa = $body['justificativa'] ?? null;
        
        if (!$novoEstado) {
            return $this->response->json(['error' => 'Estado obrigatório'], 400);
        }
        
        $projeto = $this->repository->find($id);
        
        if (!$projeto) {
            return $this->response->json(['error' => 'Projeto não encontrado'], 404);
        }
        
        // Verifica permissão
        if (!$this->permission->can($this->getCurrentUser(), 'projeto.update', $projeto)) {
            return $this->response->json(['error' => 'Sem permissão'], 403);
        }
        
        try {
            $estadoAnterior = $projeto->getEstado();
            
            $projeto->transicionarEstado($novoEstado, [
                'justificativa' => $justificativa,
                'usuario_id' => $this->getCurrentUser()->getId(),
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
            ]);
            
        } catch (\InvalidArgumentException $e) {
            $this->response->json([
                'error' => 'Transição inválida',
                'message' => $e->getMessage(),
                'transicoes_permitidas' => $projeto->getStateObject()->getAllowedTransitions(),
            ], 422);
        }
    }
    
    /**
     * POST /v1/projetos/{id}/avancar-etapa
     */
    public function avancarEtapa(): void
    {
        $id = (int) $this->request->getParam('id');
        $projeto = $this->repository->find($id);
        
        if (!$projeto) {
            return $this->response->json(['error' => 'Projeto não encontrado'], 404);
        }
        
        if (!$this->permission->can($this->getCurrentUser(), 'projeto.update', $projeto)) {
            return $this->response->json(['error' => 'Sem permissão'], 403);
        }
        
        try {
            $projeto->avancarEtapa();
            $this->repository->save($projeto);
            
            $this->response->json([
                'message' => 'Etapa avançada com sucesso',
                'data' => [
                    'etapa_anterior' => $projeto->getEtapaAtualNumero() - 1,
                    'etapa_atual' => $projeto->getEtapaAtualNumero(),
                    'etapa_nome' => $projeto->getEtapaAtual()->getNome(),
                    'estado' => $projeto->getEstado(),
                    'percent_execucao' => $projeto->getPercentExecucao(),
                ],
            ]);
            
        } catch (\InvalidArgumentException $e) {
            $this->response->json([
                'error' => 'Não é possível avançar',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
    
    /**
     * GET /v1/projetos/{id}/etapas
     */
    public function etapas(): void
    {
        $id = (int) $this->request->getParam('id');
        $projeto = $this->repository->find($id);
        
        if (!$projeto) {
            return $this->response->json(['error' => 'Projeto não encontrado'], 404);
        }
        
        $this->response->json([
            'data' => array_map(fn($e) => $e->toArray(), $projeto->getEtapas()),
        ]);
    }
    
    /**
     * PUT /v1/projetos/{id}/etapas/{etapaId}
     * Atualiza dados da etapa
     */
    public function atualizarEtapa(): void
    {
        $projetoId = (int) $this->request->getParam('id');
        $etapaId = (int) $this->request->getParam('etapaId');
        $dados = $this->request->getBody();
        
        $projeto = $this->repository->find($projetoId);
        
        if (!$projeto) {
            return $this->response->json(['error' => 'Projeto não encontrado'], 404);
        }
        
        if (!$this->permission->can($this->getCurrentUser(), 'etapa.update', $projeto)) {
            return $this->response->json(['error' => 'Sem permissão'], 403);
        }
        
        // Busca etapa
        $etapas = $projeto->getEtapas();
        $etapa = $etapas[$etapaId] ?? null;
        
        if (!$etapa) {
            return $this->response->json(['error' => 'Etapa não encontrada'], 404);
        }
        
        // Atualiza dados
        if (isset($dados['data_prevista_fim'])) {
            $etapa->setDataPrevistaFim(new \DateTime($dados['data_prevista_fim']));
        }
        
        if (isset($dados['responsavel_id'])) {
            $etapa->setResponsavelId($dados['responsavel_id']);
        }
        
        $this->repository->save($projeto);
        
        $this->response->json([
            'message' => 'Etapa atualizada',
            'data' => $etapa->toArray(),
        ]);
    }
    
    /**
     * POST /v1/projetos/{id}/etapas/{etapaId}/concluir
     */
    public function concluirEtapa(): void
    {
        $projetoId = (int) $this->request->getParam('id');
        $numeroEtapa = (int) $this->request->getParam('etapaId');
        $dados = $this->request->getBody();
        
        $projeto = $this->repository->find($projetoId);
        
        if (!$projeto) {
            return $this->response->json(['error' => 'Projeto não encontrado'], 404);
        }
        
        if (!$this->permission->can($this->getCurrentUser(), 'etapa.update', $projeto)) {
            return $this->response->json(['error' => 'Sem permissão'], 403);
        }
        
        $etapa = $projeto->getEtapas()[$numeroEtapa] ?? null;
        
        if (!$etapa) {
            return $this->response->json(['error' => 'Etapa não encontrada'], 404);
        }
        
        // Se está atrasada, exige justificativa
        if ($etapa->estaAtrasada() && empty($dados['justificativa_atraso'])) {
            return $this->response->json([
                'error' => 'Justificativa de atraso é obrigatória',
            ], 422);
        }
        
        try {
            if (!empty($dados['justificativa_atraso'])) {
                $etapa->setJustificativaAtraso($dados['justificativa_atraso']);
            }
            
            if (!empty($dados['evidencia_url'])) {
                $etapa->setEvidenciaUrl($dados['evidencia_url']);
            }
            
            $etapa->finalizar();
            $this->repository->save($projeto);
            
            $this->response->json([
                'message' => 'Etapa concluída',
                'data' => $etapa->toArray(),
                'proxima_acao' => $projeto->podeAvancarEtapa() ? 'avancar_etapa' : null,
            ]);
            
        } catch (\DomainException $e) {
            $this->response->json(['error' => $e->getMessage()], 422);
        }
    }
    
    private function validarDados(array $dados): array
    {
        $erros = [];
        
        if (empty($dados['nome'])) {
            $erros['nome'] = 'Nome é obrigatório';
        }
        
        if (empty($dados['tipo'])) {
            $erros['tipo'] = 'Tipo é obrigatório';
        }
        
        if (empty($dados['coordenador_id'])) {
            $erros['coordenador_id'] = 'Coordenador é obrigatório';
        }
        
        return $erros;
    }
}
