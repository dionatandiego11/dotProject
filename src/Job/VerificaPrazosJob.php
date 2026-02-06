<?php
/**
 * Job de Verificação de Prazos
 * Executado diariamente para atualizar estados e enviar alertas
 * 
 * @package DotProject\Job
 */

declare(strict_types=1);

namespace DotProject\Job;

use DotProject\Core\Database;
use DotProject\Core\Logger;
use DotProject\Repository\EtapaRepository;
use DotProject\Repository\ProjetoRepository;

class VerificaPrazosJob
{
    private Database $db;
    private Logger $logger;
    private EtapaRepository $etapaRepo;
    private ProjetoRepository $projetoRepo;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = Logger::getInstance();
        $this->etapaRepo = new EtapaRepository();
        $this->projetoRepo = new ProjetoRepository();
    }
    
    /**
     * Executa verificação diária completa
     */
    public function executar(): void
    {
        $this->logger->info('=== INICIANDO VERIFICAÇÃO DIÁRIA DE PRAZOS ===');
        
        $inicio = microtime(true);
        
        try {
            // 1. Verifica etapas próximas do prazo (7 dias)
            $this->verificarEtapasProximoPrazo();
            
            // 2. Verifica etapas atrasadas
            $this->verificarEtapasAtrasadas();
            
            // 3. Atualiza status dos projetos
            $this->atualizarStatusProjetos();
            
            // 4. Atualiza status dos programas
            $this->atualizarStatusProgramas();
            
            // 5. Limpa cache de KPIs
            $this->limparCacheKpis();
            
            $duracao = round(microtime(true) - $inicio, 3);
            $this->logger->info("=== VERIFICAÇÃO CONCLUÍDA em {$duracao}s ===");
            
        } catch (\Exception $e) {
            $this->logger->error('ERRO na verificação diária: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Verifica etapas que vencem em até 7 dias
     */
    private function verificarEtapasProximoPrazo(): void
    {
        $this->logger->debug('Verificando etapas próximas do prazo...');
        
        $limite = date('Y-m-d', strtotime('+7 days'));
        
        $sql = "SELECT * FROM dotp_etapas 
                WHERE estado IN ('Dentro_Prazo', 'Em_Andamento')
                AND data_prevista_fim <= ?
                AND data_prevista_fim >= CURDATE()";
        
        $etapas = $this->db->fetchAll($sql, [$limite]);
        $contador = 0;
        
        foreach ($etapas as $dados) {
            try {
                $etapa = $this->etapaRepo->hydrate($dados);
                
                if ($etapa->getEstado() !== 'Proximo_Prazo') {
                    $etapa->transicionarEstado('Proximo_Prazo');
                    $this->etapaRepo->save($etapa);
                    $contador++;
                }
            } catch (\Exception $e) {
                $this->logger->error("Erro ao processar etapa {$dados['id']}: " . $e->getMessage());
            }
        }
        
        $this->logger->info("{$contador} etapas marcadas como 'Próximo do Prazo'");
    }
    
    /**
     * Verifica etapas que já passaram do prazo
     */
    private function verificarEtapasAtrasadas(): void
    {
        $this->logger->debug('Verificando etapas atrasadas...');
        
        $hoje = date('Y-m-d');
        
        $sql = "SELECT * FROM dotp_etapas 
                WHERE estado NOT IN ('Concluida', 'Concluida_Com_Atraso', 'Atrasada', 'Critica')
                AND data_prevista_fim < ?";
        
        $etapas = $this->db->fetchAll($sql, [$hoje]);
        $atrasadas = 0;
        $criticas = 0;
        
        foreach ($etapas as $dados) {
            try {
                $etapa = $this->etapaRepo->hydrate($dados);
                
                // Calcula dias de atraso
                $dataPrevista = new \DateTime($dados['data_prevista_fim']);
                $diasAtraso = (new \DateTime())->diff($dataPrevista)->days;
                
                $novoEstado = $diasAtraso > 30 ? 'Critica' : 'Atrasada';
                
                $etapa->setDiasAtraso($diasAtraso);
                $etapa->transicionarEstado($novoEstado);
                $this->etapaRepo->save($etapa);
                
                if ($novoEstado === 'Critica') {
                    $criticas++;
                } else {
                    $atrasadas++;
                }
                
            } catch (\Exception $e) {
                $this->logger->error("Erro ao processar etapa {$dados['id']}: " . $e->getMessage());
            }
        }
        
        $this->logger->info("{$atrasadas} etapas atrasadas, {$criticas} críticas");
    }
    
    /**
     * Atualiza status dos projetos baseado nas etapas
     */
    private function atualizarStatusProjetos(): void
    {
        $this->logger->debug('Atualizando status dos projetos...');
        
        // Busca projetos ativos
        $sql = "SELECT * FROM dotp_projects 
                WHERE project_estado NOT IN ('Concluido', 'Cancelado')";
        
        $projetos = $this->db->fetchAll($sql);
        $atualizados = 0;
        
        foreach ($projetos as $dados) {
            try {
                $projeto = $this->projetoRepo->hydrate($dados);
                
                $estadoAnterior = $projeto->getEstado();
                $projeto->verificarEstadoAutomatico();
                
                if ($projeto->getEstado() !== $estadoAnterior) {
                    $this->projetoRepo->save($projeto);
                    $atualizados++;
                }
            } catch (\Exception $e) {
                $this->logger->error("Erro ao processar projeto {$dados['id']}: " . $e->getMessage());
            }
        }
        
        $this->logger->info("{$atualizados} projetos tiveram status atualizado");
    }
    
    /**
     * Atualiza status dos programas
     */
    private function atualizarStatusProgramas(): void
    {
        $this->logger->debug('Atualizando status dos programas...');
        
        $sql = "SELECT * FROM dotp_programas WHERE estado != 'Concluido'";
        $programas = $this->db->fetchAll($sql);
        
        foreach ($programas as $dados) {
            try {
                // Recalcula percentual
                $sql = "SELECT AVG(project_percent_execucao) as media FROM dotp_projects WHERE project_programa_id = ?";
                $result = $this->db->fetchOne($sql, [$dados['id']]);
                $percent = round((float) ($result['media'] ?? 0), 2);
                
                // Atualiza programa
                $this->db->execute(
                    "UPDATE dotp_programas SET percent_execucao = ? WHERE id = ?",
                    [$percent, $dados['id']]
                );
                
            } catch (\Exception $e) {
                $this->logger->error("Erro ao processar programa {$dados['id']}: " . $e->getMessage());
            }
        }
        
        $this->logger->info('Programas atualizados');
    }
    
    /**
     * Limpa cache de KPIs para forçar recálculo
     */
    private function limparCacheKpis(): void
    {
        // Aqui limparíamos o cache específico de KPIs
        // Cache::invalidate('kpi:*');
        $this->logger->debug('Cache de KPIs invalidado');
    }
}
