<?php
/**
 * Job de Verificação de Alertas Automáticos
 * Executado periodicamente para gerar alertas baseado em regras de negócio
 * 
 * @package DotProject\Job
 */

declare(strict_types=1);

namespace DotProject\Job;

use DotProject\Core\Database;
use DotProject\Core\TenantContext;
use DotProject\Entity\AlertaEntity;
use DotProject\Repository\AlertaRepository;
use DotProject\Repository\UsuarioUnidadeRepository;
use DotProject\Service\PermissionService;

class VerificadorAlertasJob
{
    private Database $db;
    private AlertaRepository $alertaRepo;
    private UsuarioUnidadeRepository $vinculoRepo;
    private ?string $unidadeNivelColumn = null;
    private ?string $unidadeStatusColumn = null;
    /**
     * @var array<string, bool>
     */
    private array $tableHasTenantColumn = [];
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->alertaRepo = new AlertaRepository();
        $this->vinculoRepo = new UsuarioUnidadeRepository();
    }
    
    /**
     * Executa todas as verificações
     */
    public function executar(): array
    {
        $resultado = [
            'inicio' => date('Y-m-d H:i:s'),
            'alertas_criados' => 0,
            'verificacoes' => [],
        ];
        
        // 1. Convênios a vencer (60 dias)
        $count = $this->verificarConveniosVencer();
        $resultado['verificacoes']['convenios_vencer'] = $count;
        $resultado['alertas_criados'] += $count;
        
        // 2. Obras paradas (sem atualização > 15 dias)
        $count = $this->verificarObrasParadas();
        $resultado['verificacoes']['obras_paradas'] = $count;
        $resultado['alertas_criados'] += $count;
        
        // 3. Projetos parados (sem movimentação > 30 dias)
        $count = $this->verificarProjetosParados();
        $resultado['verificacoes']['projetos_parados'] = $count;
        $resultado['alertas_criados'] += $count;
        
        // 4. Recursos não pagos (empenhado há 90 dias)
        $count = $this->verificarRecursosNaoPagos();
        $resultado['verificacoes']['recursos_nao_pagos'] = $count;
        $resultado['alertas_criados'] += $count;
        
        // 5. Emendas com execução baixa (< 30% a 4 meses do fim)
        $count = $this->verificarEmendasSemExecucao();
        $resultado['verificacoes']['emendas_sem_execucao'] = $count;
        $resultado['alertas_criados'] += $count;
        
        // 6. Prazo de etapa próximo (< 7 dias)
        $count = $this->verificarPrazoEtapaProximo();
        $resultado['verificacoes']['prazo_etapa_proximo'] = $count;
        $resultado['alertas_criados'] += $count;
        
        // 7. Etpas atrasadas
        $count = $this->verificarEtapasAtrasadas();
        $resultado['verificacoes']['etapas_atrasadas'] = $count;
        $resultado['alertas_criados'] += $count;
        
        $resultado['fim'] = date('Y-m-d H:i:s');
        
        return $resultado;
    }
    
    /**
     * Verifica convênios próximos do vencimento (60 dias)
     */
    private function verificarConveniosVencer(): int
    {
        $tenantProjectsAlias = $this->tenantAndCondition('dotp_projects', 'p');
        $tenantUnidadesAlias = $this->tenantAndCondition('dotp_unidades_organizacionais', 'u');

        $sql = "SELECT 
                    p.project_id as id,
                    p.project_name as nome,
                    p.project_end_date as data_prevista_fim,
                    p.project_company as unidade_id,
                    u.unidade_responsavel_id as responsavel_id,
                    DATEDIFF(p.project_end_date, CURDATE()) as dias_restantes
                FROM dotp_projects p
                JOIN dotp_unidades_organizacionais u ON u.unidade_id = p.project_company{$tenantUnidadesAlias}
                WHERE p.project_tipo = 'Convenio'
                AND p.project_estado NOT IN ('Concluido', 'Cancelado')
                AND p.project_end_date IS NOT NULL
                AND DATEDIFF(p.project_end_date, CURDATE()) BETWEEN 0 AND 60
                {$tenantProjectsAlias}";
        
        $convenios = $this->db->fetchAll($sql);
        $count = 0;
        
        foreach ($convenios as $conv) {
            $alerta = new AlertaEntity();
            $alerta->setTipo(AlertaEntity::TIPO_CONVENIO_VENCER);
            $alerta->setTitulo("Convênio #{$conv['id']} vence em {$conv['dias_restantes']} dias");
            $alerta->setDescricao("O convênio '{$conv['nome']}' está próximo do prazo final de vigência.");
            $alerta->setProjetoId($conv['id']);
            $alerta->setUnidadeId($conv['unidade_id']);
            $alerta->setPrioridade($conv['dias_restantes'] <= 15 ? AlertaEntity::PRIORIDADE_CRITICA : AlertaEntity::PRIORIDADE_ALTA);
            $alerta->setAcaoRequerida('Verificar possibilidade de prorrogação ou acelerar execução');
            
            // Envia para o coordenador
            if ($conv['responsavel_id']) {
                $alerta->setDestinatarioId($conv['responsavel_id']);
                if ($this->alertaRepo->criarSeNaoExistir($alerta)) {
                    $count++;
                }
            }
            
            // Também envia para o secretário
            $secretario = $this->buscarSecretario($conv['unidade_id']);
            if ($secretario) {
                $alerta2 = clone $alerta;
                $alerta2->setDestinatarioId($secretario);
                if ($this->alertaRepo->criarSeNaoExistir($alerta2)) {
                    $count++;
                }
            }
        }
        
        return $count;
    }
    
    /**
     * Verifica obras paradas (sem atualização > 15 dias)
     */
    private function verificarObrasParadas(): int
    {
        $tenantProjectsAlias = $this->tenantAndCondition('dotp_projects', 'p');

        $sql = "SELECT 
                    p.project_id as id,
                    p.project_name as nome,
                    p.project_company as unidade_id,
                    p.project_coordenador_id as responsavel_id,
                    DATEDIFF(CURDATE(), p.project_updated_at) as dias_sem_atualizacao
                FROM dotp_projects p
                WHERE p.project_tipo = 'Obra'
                AND p.project_estado = 'Execucao'
                AND DATEDIFF(CURDATE(), p.project_updated_at) > 15
                {$tenantProjectsAlias}";
        
        $obras = $this->db->fetchAll($sql);
        $count = 0;
        
        foreach ($obras as $obra) {
            $alerta = new AlertaEntity();
            $alerta->setTipo(AlertaEntity::TIPO_OBRA_PARADA);
            $alerta->setTitulo("Obra '{$obra['nome']}' parada há {$obra['dias_sem_atualizacao']} dias");
            $alerta->setDescricao("A obra não recebe atualizações há mais de 15 dias. Verificar situação no local.");
            $alerta->setProjetoId($obra['id']);
            $alerta->setUnidadeId($obra['unidade_id']);
            $alerta->setPrioridade(AlertaEntity::PRIORIDADE_ALTA);
            $alerta->setAcaoRequerida('Atualizar andamento da obra ou justificar parada');
            $alerta->setDestinatarioId($obra['responsavel_id']);
            
            if ($this->alertaRepo->criarSeNaoExistir($alerta)) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Verifica projetos parados (sem movimentação > 30 dias)
     */
    private function verificarProjetosParados(): int
    {
        $tenantProjectsAlias = $this->tenantAndCondition('dotp_projects', 'p');

        $sql = "SELECT 
                    p.project_id as id,
                    p.project_name as nome,
                    p.project_company as unidade_id,
                    p.project_coordenador_id as responsavel_id,
                    DATEDIFF(CURDATE(), p.project_updated_at) as dias_parado
                FROM dotp_projects p
                WHERE p.project_estado NOT IN ('Concluido', 'Cancelado')
                AND DATEDIFF(CURDATE(), p.project_updated_at) > 30
                {$tenantProjectsAlias}";
        
        $projetos = $this->db->fetchAll($sql);
        $count = 0;
        
        foreach ($projetos as $proj) {
            $alerta = new AlertaEntity();
            $alerta->setTipo(AlertaEntity::TIPO_PROJETO_PARADO);
            $alerta->setTitulo("Projeto '{$proj['nome']}' sem movimentação há {$proj['dias_parado']} dias");
            $alerta->setDescricao("O projeto não tem atualizações há mais de 30 dias.");
            $alerta->setProjetoId($proj['id']);
            $alerta->setUnidadeId($proj['unidade_id']);
            $alerta->setPrioridade(AlertaEntity::PRIORIDADE_MEDIA);
            $alerta->setAcaoRequerida('Atualizar status do projeto ou arquivar');
            $alerta->setDestinatarioId($proj['responsavel_id']);
            
            if ($this->alertaRepo->criarSeNaoExistir($alerta)) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Verifica recursos empenhados mas não pagos (> 90 dias)
     */
    private function verificarRecursosNaoPagos(): int
    {
        $tenantProjectsAlias = $this->tenantAndCondition('dotp_projects', 'p');

        // Esta verificação depende da integração com o sistema contábil
        // Simulação baseada na data de atualização
        $sql = "SELECT 
                    p.project_id as id,
                    p.project_name as nome,
                    p.project_company as unidade_id,
                    p.project_situacao_orcamentaria as situacao_orcamentaria,
                    p.project_coordenador_id as responsavel_id
                FROM dotp_projects p
                WHERE p.project_situacao_orcamentaria = 'empenhado'
                AND DATEDIFF(CURDATE(), p.project_updated_at) > 90
                {$tenantProjectsAlias}";
        
        $projetos = $this->db->fetchAll($sql);
        $count = 0;
        
        foreach ($projetos as $proj) {
            $alerta = new AlertaEntity();
            $alerta->setTipo(AlertaEntity::TIPO_RECURSO_NAO_PAGO);
            $alerta->setTitulo("Recurso empenhado não pago - Projeto #{$proj['id']}");
            $alerta->setDescricao("O recurso foi empenhado há mais de 90 dias mas ainda não foi pago.");
            $alerta->setProjetoId($proj['id']);
            $alerta->setUnidadeId($proj['unidade_id']);
            $alerta->setPrioridade(AlertaEntity::PRIORIDADE_ALTA);
            $alerta->setAcaoRequerida('Regularizar pagamento ou verificar pendência documental');
            $alerta->setDestinatarioId($proj['responsavel_id']);
            
            if ($this->alertaRepo->criarSeNaoExistir($alerta)) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Verifica emendas com execução abaixo de 30% faltando 4 meses
     */
    private function verificarEmendasSemExecucao(): int
    {
        $tenantProjectsAlias = $this->tenantAndCondition('dotp_projects', 'p');

        $sql = "SELECT 
                    p.project_id as id,
                    p.project_name as nome,
                    p.project_percent_execucao as percent_execucao,
                    p.project_company as unidade_id,
                    p.project_coordenador_id as responsavel_id,
                    DATEDIFF(p.project_end_date, CURDATE()) as dias_restantes
                FROM dotp_projects p
                WHERE p.project_tipo = 'Emenda'
                AND p.project_estado NOT IN ('Concluido', 'Cancelado')
                AND p.project_percent_execucao < 30
                AND DATEDIFF(p.project_end_date, CURDATE()) BETWEEN 0 AND 120
                {$tenantProjectsAlias}";
        
        $emendas = $this->db->fetchAll($sql);
        $count = 0;
        
        foreach ($emendas as $emenda) {
            $alerta = new AlertaEntity();
            $alerta->setTipo(AlertaEntity::TIPO_EMENDA_SEM_EXECUCAO);
            $alerta->setTitulo("Emenda - Execução em {$emenda['percent_execucao']}%");
            $alerta->setDescricao("A emenda '{$emenda['nome']}' está com execução abaixo de 30% e faltam {$emenda['dias_restantes']} dias para o prazo final.");
            $alerta->setProjetoId($emenda['id']);
            $alerta->setUnidadeId($emenda['unidade_id']);
            $alerta->setPrioridade(AlertaEntity::PRIORIDADE_CRITICA);
            $alerta->setAcaoRequerida('Acelerar execução ou comunicar impossibilidade ao parlamentar');
            $alerta->setDestinatarioId($emenda['responsavel_id']);
            
            if ($this->alertaRepo->criarSeNaoExistir($alerta)) {
                $count++;
            }
            
            // Também alerta o prefeito
            $prefeito = $this->buscarPrefeito();
            if ($prefeito) {
                $alerta2 = clone $alerta;
                $alerta2->setDestinatarioId($prefeito);
                if ($this->alertaRepo->criarSeNaoExistir($alerta2)) {
                    $count++;
                }
            }
        }
        
        return $count;
    }
    
    /**
     * Verifica etapas com prazo próximo (< 7 dias)
     */
    private function verificarPrazoEtapaProximo(): int
    {
        $tenantProjectsAlias = $this->tenantAndCondition('dotp_projects', 'p');

        $sql = "SELECT 
                    et.id,
                    et.nome,
                    et.data_prevista_fim,
                    et.projeto_id,
                    p.project_name as projeto_nome,
                    p.project_company as unidade_id,
                    p.project_coordenador_id as responsavel_id,
                    DATEDIFF(et.data_prevista_fim, CURDATE()) as dias_restantes
                FROM dotp_etapas et
                JOIN dotp_projects p ON p.project_id = et.projeto_id
                WHERE et.estado NOT IN ('Concluida', 'Concluida_Com_Atraso')
                AND et.data_prevista_fim IS NOT NULL
                AND DATEDIFF(et.data_prevista_fim, CURDATE()) BETWEEN 0 AND 7
                {$tenantProjectsAlias}";
        
        $etapas = $this->db->fetchAll($sql);
        $count = 0;
        
        foreach ($etapas as $etapa) {
            $alerta = new AlertaEntity();
            $alerta->setTipo(AlertaEntity::TIPO_PRAZO_ETAPA_PROXIMO);
            $alerta->setTitulo("Etapa '{$etapa['nome']}' vence em {$etapa['dias_restantes']} dias");
            $alerta->setDescricao("A etapa do projeto '{$etapa['projeto_nome']}' está próxima do prazo final.");
            $alerta->setProjetoId($etapa['projeto_id']);
            $alerta->setEtapaId($etapa['id']);
            $alerta->setUnidadeId($etapa['unidade_id']);
            $alerta->setPrioridade($etapa['dias_restantes'] <= 3 ? AlertaEntity::PRIORIDADE_ALTA : AlertaEntity::PRIORIDADE_MEDIA);
            $alerta->setAcaoRequerida('Concluir etapa ou solicitar prorrogação');
            $alerta->setDestinatarioId($etapa['responsavel_id']);
            
            if ($this->alertaRepo->criarSeNaoExistir($alerta)) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Verifica etapas atrasadas
     */
    private function verificarEtapasAtrasadas(): int
    {
        $tenantProjectsAlias = $this->tenantAndCondition('dotp_projects', 'p');

        $sql = "SELECT 
                    et.id,
                    et.nome,
                    et.data_prevista_fim,
                    et.projeto_id,
                    p.project_name as projeto_nome,
                    p.project_company as unidade_id,
                    p.project_coordenador_id as responsavel_id,
                    DATEDIFF(CURDATE(), et.data_prevista_fim) as dias_atraso
                FROM dotp_etapas et
                JOIN dotp_projects p ON p.project_id = et.projeto_id
                WHERE et.estado = 'Atrasada'
                AND DATEDIFF(CURDATE(), et.data_prevista_fim) > 0
                {$tenantProjectsAlias}";
        
        $etapas = $this->db->fetchAll($sql);
        $count = 0;
        
        foreach ($etapas as $etapa) {
            $alerta = new AlertaEntity();
            $alerta->setTipo(AlertaEntity::TIPO_ETAPA_ATRASADA);
            $alerta->setTitulo("Etapa '{$etapa['nome']}' atrasada há {$etapa['dias_atraso']} dias");
            $alerta->setDescricao("A etapa do projeto '{$etapa['projeto_nome']}' está atrasada desde {$etapa['data_prevista_fim']}.");
            $alerta->setProjetoId($etapa['projeto_id']);
            $alerta->setEtapaId($etapa['id']);
            $alerta->setUnidadeId($etapa['unidade_id']);
            $alerta->setPrioridade($etapa['dias_atraso'] > 15 ? AlertaEntity::PRIORIDADE_CRITICA : AlertaEntity::PRIORIDADE_ALTA);
            $alerta->setAcaoRequerida('Concluir etapa e registrar justificativa do atraso');
            $alerta->setDestinatarioId($etapa['responsavel_id']);
            
            if ($this->alertaRepo->criarSeNaoExistir($alerta)) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Busca o ID do secretário responsável pela unidade
     */
    private function buscarSecretario(int $unidadeId): ?int
    {
        $nivelColumn = $this->getUnidadeNivelColumn();
        $tenantUnidades = $this->tenantAndCondition('dotp_unidades_organizacionais');
        $tenantUnidadesAlias = $this->tenantAndCondition('dotp_unidades_organizacionais', 'u');
        // Sobe na hierarquia até encontrar a secretaria
        $sql = "WITH RECURSIVE hierarquia AS (
                    SELECT unidade_id, unidade_pai_id, {$nivelColumn} as nivel
                    FROM dotp_unidades_organizacionais
                    WHERE unidade_id = ?{$tenantUnidades}
                    UNION ALL
                    SELECT u.unidade_id, u.unidade_pai_id, u.{$nivelColumn} as nivel
                    FROM dotp_unidades_organizacionais u
                    JOIN hierarquia h ON h.unidade_pai_id = u.unidade_id
                    WHERE 1=1{$tenantUnidadesAlias}
                )
                SELECT unidade_id FROM hierarquia WHERE nivel = 2 LIMIT 1";
        
        $secretariaId = $this->db->fetchColumn($sql, [$unidadeId]);
        
        if (!$secretariaId) {
            return null;
        }
        
        // Busca o responsável (secretário)
        $sql = "SELECT unidade_responsavel_id FROM dotp_unidades_organizacionais WHERE unidade_id = ?{$tenantUnidades}";
        return $this->db->fetchColumn($sql, [$secretariaId]) ?: null;
    }
    
    /**
     * Busca o ID do prefeito (nível 1)
     */
    private function buscarPrefeito(): ?int
    {
        $nivelColumn = $this->getUnidadeNivelColumn();
        $statusFilter = $this->getUnidadeStatusFilter();
        $tenantUnidades = $this->tenantAndCondition('dotp_unidades_organizacionais');
        $sql = "SELECT unidade_responsavel_id 
                FROM dotp_unidades_organizacionais 
                WHERE {$nivelColumn} = 1 AND {$statusFilter}{$tenantUnidades}
                LIMIT 1";
        
        return $this->db->fetchColumn($sql) ?: null;
    }

    private function getUnidadeNivelColumn(): string
    {
        if ($this->unidadeNivelColumn !== null) {
            return $this->unidadeNivelColumn;
        }

        $exists = $this->db->fetchValue(
            "SELECT COUNT(*) FROM information_schema.columns 
             WHERE table_schema = DATABASE() 
               AND table_name = 'dotp_unidades_organizacionais' 
               AND column_name = 'unidade_nivel_id'"
        );

        $this->unidadeNivelColumn = ((int) $exists > 0) ? 'unidade_nivel_id' : 'unidade_nivel';
        return $this->unidadeNivelColumn;
    }

    private function getUnidadeStatusFilter(): string
    {
        if ($this->unidadeStatusColumn === null) {
            $hasStatus = $this->db->fetchValue(
                "SELECT COUNT(*) FROM information_schema.columns 
                 WHERE table_schema = DATABASE() 
                   AND table_name = 'dotp_unidades_organizacionais' 
                   AND column_name = 'unidade_status'"
            );
            if ((int) $hasStatus > 0) {
                $this->unidadeStatusColumn = 'unidade_status';
            } else {
                $hasAtiva = $this->db->fetchValue(
                    "SELECT COUNT(*) FROM information_schema.columns 
                     WHERE table_schema = DATABASE() 
                       AND table_name = 'dotp_unidades_organizacionais' 
                       AND column_name = 'unidade_ativa'"
                );
                $this->unidadeStatusColumn = ((int) $hasAtiva > 0) ? 'unidade_ativa' : '';
            }
        }

        if ($this->unidadeStatusColumn === 'unidade_status') {
            return "unidade_status = 'ativo'";
        }

        if ($this->unidadeStatusColumn === 'unidade_ativa') {
            return "unidade_ativa = 1";
        }

        return '1=1';
    }

    private function tenantAndCondition(string $table, ?string $alias = null): string
    {
        $tenantId = $this->getTenantId();
        if ($tenantId === null || !$this->tableHasTenantColumn($table)) {
            return '';
        }

        $column = $alias !== null && $alias !== ''
            ? $alias . '.tenant_id'
            : 'tenant_id';

        return " AND {$column} = {$tenantId}";
    }

    private function tableHasTenantColumn(string $table): bool
    {
        $table = trim($table, '`');
        if (array_key_exists($table, $this->tableHasTenantColumn)) {
            return $this->tableHasTenantColumn[$table];
        }

        try {
            $exists = (int) ($this->db->fetchValue(
                "SELECT COUNT(*)
                 FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = ?
                   AND column_name = 'tenant_id'",
                [$table]
            ) ?? 0);
            $this->tableHasTenantColumn[$table] = $exists > 0;
        } catch (\Throwable) {
            $this->tableHasTenantColumn[$table] = false;
        }

        return $this->tableHasTenantColumn[$table];
    }

    private function getTenantId(): ?int
    {
        if (!TenantContext::isEnabled()) {
            return null;
        }

        $tenantId = TenantContext::getTenantId();
        if ($tenantId === null || $tenantId <= 0) {
            return null;
        }

        return $tenantId;
    }
}
