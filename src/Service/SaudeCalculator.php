<?php
/**
 * Calculador de Status de Saúde
 *
 * Calcula o status_saude automaticamente para Projetos, Ações e Programas
 * baseado nos limiares definidos na Fase 0 — Contrato de Regras.
 *
 * Regra (avaliada em ordem de prioridade):
 *   1. impedido  — existem tarefas/etapas bloqueadas ou impedidas
 *   2. critico   — data_fim_prevista < HOJE E percent < 100
 *   3. atencao   — data_fim_prevista < HOJE + 15 dias E percent < 80
 *   4. em_dia    — nenhuma das acima
 *
 * Propagação ascendente:
 *   - Se QUALQUER filho é 'critico' → pai é 'critico'
 *   - Se QUALQUER filho é 'atencao' (e nenhum critico) → pai é 'atencao'
 *   - Se QUALQUER filho é 'impedido' → pai é 'impedido'
 *   - Senão → pai é 'em_dia'
 *
 * @package DotProject\Service
 */

declare(strict_types=1);

namespace DotProject\Service;

use DotProject\Core\Database;
use DotProject\Core\TenantContext;

class SaudeCalculator
{
    /** Ordem de gravidade: impedido > critico > atencao > em_dia */
    private const GRAVIDADE = [
        'impedido' => 4,
        'critico' => 3,
        'atencao' => 2,
        'em_dia' => 1,
    ];

    /** Valores válidos para status_saude */
    public const STATUS_VALIDOS = ['em_dia', 'atencao', 'critico', 'impedido'];

    private Database $db;
    private TenantContext $tenant;

    public function __construct(Database $db, TenantContext $tenant)
    {
        $this->db = $db;
        $this->tenant = $tenant;
    }

    // ========================================================================
    // Projeto
    // ========================================================================

    /**
     * Calcula status_saude de um projeto baseado em suas etapas e tarefas.
     *
     * @param array $projeto Array com campos: project_id, project_end_date, project_percent_complete
     * @return string em_dia|atencao|critico|impedido
     */
    public function calcularSaudeProjeto(array $projeto): string
    {
        $projetoId = (int) ($projeto['project_id'] ?? 0);

        // 1. Verificar se há tarefas/etapas bloqueadas
        $bloqueados = $this->contarBloqueados($projetoId);
        if ($bloqueados > 0) {
            return 'impedido';
        }

        // 2. Verificar prazo do projeto
        $dataFim = $projeto['project_end_date'] ?? null;
        $percent = (float) ($projeto['project_percent_complete'] ?? 0);

        return $this->avaliarPrazo($dataFim, $percent);
    }

    /**
     * Calcula e persiste status_saude para um projeto específico.
     */
    public function atualizarSaudeProjeto(int $projetoId): string
    {
        $projeto = $this->db->fetchOne(
            'SELECT project_id, project_end_date, project_percent_complete FROM dotp_projects WHERE project_id = :id',
            ['id' => $projetoId]
        );

        if (!$projeto) {
            return 'em_dia';
        }

        $status = $this->calcularSaudeProjeto($projeto);

        $this->db->execute(
            'UPDATE dotp_projects SET status_saude = :status WHERE project_id = :id',
            ['status' => $status, 'id' => $projetoId]
        );

        return $status;
    }

    // ========================================================================
    // Ação
    // ========================================================================

    /**
     * Calcula status_saude de uma ação baseado na propagação ascendente dos projetos.
     */
    public function calcularSaudeAcao(int $acaoId): string
    {
        // Auto-avaliação: prazo da ação
        $acao = $this->db->fetchOne(
            'SELECT id, data_fim_prevista, percent_execucao FROM dotp_acoes WHERE id = :id',
            ['id' => $acaoId]
        );

        $statusProprio = 'em_dia';
        if ($acao) {
            $statusProprio = $this->avaliarPrazo(
                $acao['data_fim_prevista'] ?? null,
                (float) ($acao['percent_execucao'] ?? 0)
            );
        }

        // Propagação: pior status entre os projetos vinculados
        $sql = "
            SELECT p.status_saude
            FROM dotp_projeto_acoes pa
            JOIN dotp_projects p ON p.project_id = pa.project_id
            WHERE pa.acao_id = :acao_id
        ";

        $projetos = $this->db->fetchAll($sql, ['acao_id' => $acaoId]);
        $piorFilho = $this->piorStatus(array_column($projetos, 'status_saude'));

        // Resultado: o pior entre o status próprio e o pior filho
        $status = $this->piorEntreDois($statusProprio, $piorFilho);

        // Persistir
        $this->db->execute(
            'UPDATE dotp_acoes SET status_saude = :status WHERE id = :id',
            ['status' => $status, 'id' => $acaoId]
        );

        return $status;
    }

    // ========================================================================
    // Programa
    // ========================================================================

    /**
     * Calcula status_saude de um programa baseado na propagação das ações.
     */
    public function calcularSaudePrograma(int $programaId): string
    {
        $tenantCond = $this->tenantCondition('a');

        $sql = "
            SELECT a.status_saude
            FROM dotp_acoes a
            WHERE a.programa_id = :programa_id
              {$tenantCond}
        ";

        $acoes = $this->db->fetchAll($sql, ['programa_id' => $programaId]);
        $status = $this->piorStatus(array_column($acoes, 'status_saude'));

        // Persistir
        $this->db->execute(
            'UPDATE dotp_programas SET status_saude = :status WHERE id = :id',
            ['status' => $status, 'id' => $programaId]
        );

        return $status;
    }

    // ========================================================================
    // Batch: Recomputar saúde global
    // ========================================================================

    /**
     * Recomputa status_saude de toda a hierarquia (bottom-up).
     * Retorna estatísticas.
     */
    public function recomputarSaudeGlobal(): array
    {
        $stats = [
            'projetos' => 0,
            'acoes' => 0,
            'programas' => 0,
            'erros' => [],
        ];

        // 1) Projetos (base)
        $projetos = $this->db->fetchAll(
            'SELECT project_id, project_end_date, project_percent_complete FROM dotp_projects'
        );
        foreach ($projetos as $proj) {
            try {
                $status = $this->calcularSaudeProjeto($proj);
                $this->db->execute(
                    'UPDATE dotp_projects SET status_saude = :status WHERE project_id = :id',
                    ['status' => $status, 'id' => $proj['project_id']]
                );
                $stats['projetos']++;
            } catch (\Throwable $e) {
                $stats['erros'][] = "Projeto {$proj['project_id']}: {$e->getMessage()}";
            }
        }

        // 2) Ações (propaga de projetos)
        $acoes = $this->db->fetchAll('SELECT id FROM dotp_acoes');
        foreach ($acoes as $acao) {
            try {
                $this->calcularSaudeAcao((int) $acao['id']);
                $stats['acoes']++;
            } catch (\Throwable $e) {
                $stats['erros'][] = "Ação {$acao['id']}: {$e->getMessage()}";
            }
        }

        // 3) Programas (propaga de ações)
        $programas = $this->db->fetchAll('SELECT id FROM dotp_programas');
        foreach ($programas as $prog) {
            try {
                $this->calcularSaudePrograma((int) $prog['id']);
                $stats['programas']++;
            } catch (\Throwable $e) {
                $stats['erros'][] = "Programa {$prog['id']}: {$e->getMessage()}";
            }
        }

        return $stats;
    }

    // ========================================================================
    // Helpers
    // ========================================================================

    /**
     * Avalia prazo e retorna status baseado nos limiares.
     *
     * @param string|null $dataFimStr Data fim prevista (Y-m-d ou datetime)
     * @param float $percent Percentual de conclusão
     */
    private function avaliarPrazo(?string $dataFimStr, float $percent): string
    {
        if ($dataFimStr === null || $dataFimStr === '') {
            return 'em_dia';
        }

        try {
            $dataFim = new \DateTime($dataFimStr);
        } catch (\Throwable $e) {
            return 'em_dia';
        }

        $hoje = new \DateTime('today');

        // Já concluído → sempre em dia
        if ($percent >= 100) {
            return 'em_dia';
        }

        // Vencido → crítico
        if ($dataFim < $hoje) {
            return 'critico';
        }

        // Próximo do prazo (15 dias) e pouco progresso (<80%) → atenção
        $limite = (clone $hoje)->modify('+15 days');
        if ($dataFim <= $limite && $percent < 80) {
            return 'atencao';
        }

        return 'em_dia';
    }

    /**
     * Conta tarefas/etapas bloqueadas de um projeto.
     */
    private function contarBloqueados(int $projetoId): int
    {
        // Tarefas bloqueadas
        $tarefas = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM dotp_tasks WHERE task_project = :pid AND (task_status = 'Bloqueada' OR task_status = 'Impedida')",
            ['pid' => $projetoId]
        );

        // Etapas críticas/bloqueadas
        $etapas = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM dotp_etapas WHERE projeto_id = :pid AND estado IN ('Bloqueada', 'Impedida', 'Critica')",
            ['pid' => $projetoId]
        );

        return ((int) ($tarefas['total'] ?? 0)) + ((int) ($etapas['total'] ?? 0));
    }

    /**
     * Retorna o pior status de uma lista de strings.
     */
    private function piorStatus(array $statusList): string
    {
        if (empty($statusList)) {
            return 'em_dia';
        }

        $pior = 'em_dia';
        $piorGravidade = 1;

        foreach ($statusList as $s) {
            $s = is_string($s) ? $s : 'em_dia';
            $gravidade = self::GRAVIDADE[$s] ?? 1;
            if ($gravidade > $piorGravidade) {
                $pior = $s;
                $piorGravidade = $gravidade;
            }
        }

        return $pior;
    }

    /**
     * Retorna o pior entre dois status.
     */
    private function piorEntreDois(string $a, string $b): string
    {
        $ga = self::GRAVIDADE[$a] ?? 1;
        $gb = self::GRAVIDADE[$b] ?? 1;
        return $ga >= $gb ? $a : $b;
    }

    /**
     * Gera condição AND para filtro de tenant.
     */
    private function tenantCondition(string $alias): string
    {
        $tenantId = $this->tenant->getTenantId();
        if ($tenantId === null) {
            return '';
        }

        return " AND {$alias}.tenant_id = {$tenantId}";
    }
}
