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
use DotProject\Entity\AlertaEntity;
use DotProject\Repository\AlertaRepository;
use DotProject\Repository\UsuarioUnidadeRepository;
use DotProject\Service\PermissionService;

class VerificadorAlertasJob
{
    private Database $db;
    private AlertaRepository $alertaRepo;
    private UsuarioUnidadeRepository $vinculoRepo;
    
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
        $sql = "SELECT 
                    p.id,
                    p.nome,
                    p.data_prevista_fim,
                    p.unidade_id,
                    u.unidade_responsavel_id as responsavel_id,
                    DATEDIFF(p.data_prevista_fim, CURDATE()) as dias_restantes
                FROM projetos p
                JOIN dotp_unidades_organizacionais u ON u.unidade_id = p.unidade_id
                WHERE p.tipo = 'Convenio'
                AND p.estado NOT IN ('Concluido', 'Cancelado')
                AND p.data_prevista_fim IS NOT NULL
                AND DATEDIFF(p.data_prevista_fim, CURDATE()) BETWEEN 0 AND 60";
        
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
        $sql = "SELECT 
                    p.id,
                    p.nome,
                    p.unidade_id,
                    p.coordenador_id as responsavel_id,
                    DATEDIFF(CURDATE(), p.updated_at) as dias_sem_atualizacao
                FROM projetos p
                WHERE p.tipo = 'Obra'
                AND p.estado = 'Execucao'
                AND DATEDIFF(CURDATE(), p.updated_at) > 15";
        
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
        $sql = "SELECT 
                    p.id,
                    p.nome,
                    p.unidade_id,
                    p.coordenador_id as responsavel_id,
                    DATEDIFF(CURDATE(), p.updated_at) as dias_parado
                FROM projetos p
                WHERE p.estado NOT IN ('Concluido', 'Cancelado')
                AND DATEDIFF(CURDATE(), p.updated_at) > 30";
        
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
        // Esta verificação depende da integração com o sistema contábil
        // Simulação baseada na data de atualização
        $sql = "SELECT 
                    p.id,
                    p.nome,
                    p.unidade_id,
                    p.situacao_orcamentaria,
                    p.coordenador_id as responsavel_id
                FROM projetos p
                WHERE p.situacao_orcamentaria = 'empenhado'
                AND DATEDIFF(CURDATE(), p.updated_at) > 90";
        
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
        $sql = "SELECT 
                    p.id,
                    p.nome,
                    p.percent_execucao,
                    p.unidade_id,
                    p.coordenador_id as responsavel_id,
                    p.parlamentar,
                    DATEDIFF(p.data_prevista_fim, CURDATE()) as dias_restantes
                FROM projetos p
                WHERE p.tipo = 'Emenda'
                AND p.estado NOT IN ('Concluido', 'Cancelado')
                AND p.percent_execucao < 30
                AND DATEDIFF(p.data_prevista_fim, CURDATE()) BETWEEN 0 AND 120";
        
        $emendas = $this->db->fetchAll($sql);
        $count = 0;
        
        foreach ($emendas as $emenda) {
            $alerta = new AlertaEntity();
            $alerta->setTipo(AlertaEntity::TIPO_EMENDA_SEM_EXECUCAO);
            $alerta->setTitulo("Emenda de {$emenda['parlamentar']} - Execução em {$emenda['percent_execucao']}%");
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
        $sql = "SELECT 
                    et.id,
                    et.nome,
                    et.data_prevista_fim,
                    et.projeto_id,
                    p.nome as projeto_nome,
                    p.unidade_id,
                    p.coordenador_id as responsavel_id,
                    DATEDIFF(et.data_prevista_fim, CURDATE()) as dias_restantes
                FROM etapas et
                JOIN projetos p ON p.id = et.projeto_id
                WHERE et.estado NOT IN ('Concluida', 'Concluida_Com_Atraso')
                AND et.data_prevista_fim IS NOT NULL
                AND DATEDIFF(et.data_prevista_fim, CURDATE()) BETWEEN 0 AND 7";
        
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
        $sql = "SELECT 
                    et.id,
                    et.nome,
                    et.data_prevista_fim,
                    et.projeto_id,
                    p.nome as projeto_nome,
                    p.unidade_id,
                    p.coordenador_id as responsavel_id,
                    DATEDIFF(CURDATE(), et.data_prevista_fim) as dias_atraso
                FROM etapas et
                JOIN projetos p ON p.id = et.projeto_id
                WHERE et.estado = 'Atrasada'
                AND DATEDIFF(CURDATE(), et.data_prevista_fim) > 0";
        
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
        // Sobe na hierarquia até encontrar a secretaria
        $sql = "WITH RECURSIVE hierarquia AS (
                    SELECT unidade_id, unidade_pai_id, unidade_nivel
                    FROM dotp_unidades_organizacionais
                    WHERE unidade_id = ?
                    UNION ALL
                    SELECT u.unidade_id, u.unidade_pai_id, u.unidade_nivel
                    FROM dotp_unidades_organizacionais u
                    JOIN hierarquia h ON h.unidade_pai_id = u.unidade_id
                )
                SELECT unidade_id FROM hierarquia WHERE unidade_nivel = 2 LIMIT 1";
        
        $secretariaId = $this->db->fetchColumn($sql, [$unidadeId]);
        
        if (!$secretariaId) {
            return null;
        }
        
        // Busca o responsável (secretário)
        $sql = "SELECT unidade_responsavel_id FROM dotp_unidades_organizacionais WHERE unidade_id = ?";
        return $this->db->fetchColumn($sql, [$secretariaId]) ?: null;
    }
    
    /**
     * Busca o ID do prefeito (nível 1)
     */
    private function buscarPrefeito(): ?int
    {
        $sql = "SELECT unidade_responsavel_id 
                FROM dotp_unidades_organizacionais 
                WHERE unidade_nivel = 1 AND unidade_ativa = 1 
                LIMIT 1";
        
        return $this->db->fetchColumn($sql) ?: null;
    }
}
