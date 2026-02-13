<?php
/**
 * Serviço de Rollup Ponderado
 *
 * Calcula percentuais de execução de forma cascateada:
 *   Tarefa → Etapa → Projeto → Ação → Programa → PPA
 *
 * Fórmulas oficiais (Fase 0 — Contrato de Regras):
 *   % etapa    = Σ(tarefa.percent × tarefa.peso)     / Σ(tarefa.peso)
 *   % projeto  = Σ(etapa.percent  × etapa.peso)      / Σ(etapa.peso)
 *   % ação     = Σ(proj.percent   × ponte.peso)       / Σ(ponte.peso)
 *   % programa = Σ(ação.percent   × ação.valor_orçam) / Σ(ação.valor_orçam)
 *   % PPA      = Σ(prog.percent   × prog.valor_orçam) / Σ(prog.valor_orçam)
 *
 * Fallbacks:
 *   - Σ(peso) = 0  → retorna 0%
 *   - valor_orçam = 0  → fallback para média simples
 *
 * @package DotProject\Service
 */

declare(strict_types=1);

namespace DotProject\Service;

use DotProject\Core\Database;
use DotProject\Core\TenantContext;

class RollupService
{
    private Database $db;
    private TenantContext $tenant;
    private ?AuditService $auditService = null;

    public function __construct(Database $db, TenantContext $tenant)
    {
        $this->db = $db;
        $this->tenant = $tenant;
    }

    // ========================================================================
    // Cálculos individuais
    // ========================================================================

    /**
     * % etapa = Σ(tarefa.percent_complete × tarefa.peso) / Σ(tarefa.peso)
     */
    public function calcularPercentEtapa(int $etapaId): float
    {
        $tenantCond = $this->tenantCondition('t');

        $sql = "
            SELECT
                COALESCE(SUM(t.task_percent_complete * t.peso), 0) AS soma_ponderada,
                COALESCE(SUM(t.peso), 0) AS soma_pesos
            FROM dotp_tasks t
            WHERE t.task_etapa_id = :etapa_id
              {$tenantCond}
        ";

        $row = $this->db->fetchOne($sql, ['etapa_id' => $etapaId]);

        return $this->dividirSeguro(
            (float) ($row['soma_ponderada'] ?? 0),
            (float) ($row['soma_pesos'] ?? 0)
        );
    }

    /**
     * % projeto = Σ(etapa.percent_conclusao × etapa.peso) / Σ(etapa.peso)
     */
    public function calcularPercentProjeto(int $projetoId): float
    {
        $tenantCond = $this->tenantCondition('e');

        $sql = "
            SELECT
                COALESCE(SUM(e.percent_conclusao * e.peso), 0) AS soma_ponderada,
                COALESCE(SUM(e.peso), 0) AS soma_pesos
            FROM dotp_etapas e
            WHERE e.projeto_id = :projeto_id
              {$tenantCond}
        ";

        $row = $this->db->fetchOne($sql, ['projeto_id' => $projetoId]);

        return $this->dividirSeguro(
            (float) ($row['soma_ponderada'] ?? 0),
            (float) ($row['soma_pesos'] ?? 0)
        );
    }

    /**
     * % ação = Σ(projeto.percent × ponte.peso_contribuicao) / Σ(ponte.peso_contribuicao)
     * Usa tabela ponte dotp_projeto_acoes para N:N.
     */
    public function calcularPercentAcao(int $acaoId): float
    {
        $sql = "
            SELECT
                COALESCE(SUM(p.project_percent_complete * COALESCE(pa.peso_contribuicao, 1.00)), 0) AS soma_ponderada,
                COALESCE(SUM(COALESCE(pa.peso_contribuicao, 1.00)), 0) AS soma_pesos
            FROM dotp_projeto_acoes pa
            JOIN dotp_projects p ON p.project_id = pa.project_id
            WHERE pa.acao_id = :acao_id
        ";

        $row = $this->db->fetchOne($sql, ['acao_id' => $acaoId]);

        return $this->dividirSeguro(
            (float) ($row['soma_ponderada'] ?? 0),
            (float) ($row['soma_pesos'] ?? 0)
        );
    }

    /**
     * % programa = Σ(ação.percent × ação.valor_orcamentario) / Σ(ação.valor_orcamentario)
     * Fallback: se todos valor_orcamentario = 0 → média simples.
     */
    public function calcularPercentPrograma(int $programaId): float
    {
        $tenantCond = $this->tenantCondition('a');

        $sql = "
            SELECT
                COALESCE(SUM(a.percent_execucao * COALESCE(a.valor_orcamentario, 0)), 0) AS soma_ponderada_orcam,
                COALESCE(SUM(COALESCE(a.valor_orcamentario, 0)), 0) AS soma_orcam,
                COALESCE(SUM(a.percent_execucao), 0) AS soma_simples,
                COUNT(*) AS total
            FROM dotp_acoes a
            WHERE a.programa_id = :programa_id
              {$tenantCond}
        ";

        $row = $this->db->fetchOne($sql, ['programa_id' => $programaId]);

        $somaOrcam = (float) ($row['soma_orcam'] ?? 0);

        if ($somaOrcam > 0) {
            return $this->dividirSeguro(
                (float) ($row['soma_ponderada_orcam'] ?? 0),
                $somaOrcam
            );
        }

        // Fallback: média simples
        $total = (int) ($row['total'] ?? 0);
        if ($total === 0) {
            return 0.0;
        }

        return round((float) ($row['soma_simples'] ?? 0) / $total, 2);
    }

    /**
     * % PPA = Σ(programa.percent × programa.valor_orcamentario_total) / Σ(programa.valor_orcamentario_total)
     * programa.valor_orcamentario_total = Σ(ação.valor_orcamentario) do programa
     * Fallback: média simples.
     */
    public function calcularPercentPpa(int $ppaId): float
    {
        $tenantCond = $this->tenantCondition('prog');

        $sql = "
            SELECT
                prog.id AS programa_id,
                prog.percent_execucao,
                COALESCE(orcam.total_orcam, 0) AS valor_orcamentario_total
            FROM dotp_programas prog
            LEFT JOIN (
                SELECT programa_id, SUM(COALESCE(valor_orcamentario, 0)) AS total_orcam
                FROM dotp_acoes
                GROUP BY programa_id
            ) orcam ON orcam.programa_id = prog.id
            WHERE prog.ppa_id = :ppa_id
              {$tenantCond}
        ";

        $rows = $this->db->fetchAll($sql, ['ppa_id' => $ppaId]);

        if (empty($rows)) {
            return 0.0;
        }

        $somaPonderada = 0.0;
        $somaOrcam = 0.0;
        $somaSimples = 0.0;

        foreach ($rows as $row) {
            $percent = (float) ($row['percent_execucao'] ?? 0);
            $orcam = (float) ($row['valor_orcamentario_total'] ?? 0);

            $somaPonderada += $percent * $orcam;
            $somaOrcam += $orcam;
            $somaSimples += $percent;
        }

        if ($somaOrcam > 0) {
            return round($somaPonderada / $somaOrcam, 2);
        }

        // Fallback: média simples
        return round($somaSimples / count($rows), 2);
    }

    // ========================================================================
    // Agregação de valor_executado na Ação (campo AGREGADO)
    // ========================================================================

    /**
     * Recalcula valor_executado da ação a partir dos projetos vinculados.
     * valor_executado_acao = Σ(projeto.valor_executado × ponte.peso_contribuicao / 100)
     */
    public function recalcularValorExecutadoAcao(int $acaoId): float
    {
        $anteriorRow = $this->db->fetchOne(
            'SELECT valor_executado FROM dotp_acoes WHERE id = :id',
            ['id' => $acaoId]
        );
        $valorAnterior = $anteriorRow !== null && isset($anteriorRow['valor_executado'])
            ? (float) $anteriorRow['valor_executado']
            : null;

        $sql = "
            SELECT
                COALESCE(SUM(p.valor_executado * COALESCE(pa.peso_contribuicao, 100) / 100), 0) AS total
            FROM dotp_projeto_acoes pa
            JOIN dotp_projects p ON p.project_id = pa.project_id
            WHERE pa.acao_id = :acao_id
        ";

        $row = $this->db->fetchOne($sql, ['acao_id' => $acaoId]);
        $valor = round((float) ($row['total'] ?? 0), 2);

        // Atualiza no banco
        $this->db->execute(
            'UPDATE dotp_acoes SET valor_executado = :valor WHERE id = :id',
            ['valor' => $valor, 'id' => $acaoId]
        );

        $this->audit()->logValorExecutadoChange(
            'acao',
            $acaoId,
            $valorAnterior,
            $valor,
            null,
            'rollup_service',
            ['regra' => 'soma_ponderada_projetos']
        );

        return $valor;
    }

    // ========================================================================
    // Batch: Recomputar tudo
    // ========================================================================

    /**
     * Recomputa percentuais e valor_executado de todas as entidades.
     * Retorna array com estatísticas.
     */
    public function recomputarTudo(): array
    {
        $stats = [
            'etapas' => 0,
            'projetos' => 0,
            'acoes' => 0,
            'programas' => 0,
            'ppas' => 0,
            'erros' => [],
        ];

        // 1) Etapas
        $etapas = $this->db->fetchAll('SELECT id FROM dotp_etapas');
        foreach ($etapas as $etapa) {
            try {
                $percent = $this->calcularPercentEtapa((int) $etapa['id']);
                $this->db->execute(
                    'UPDATE dotp_etapas SET percent_conclusao = :p WHERE id = :id',
                    ['p' => $percent, 'id' => $etapa['id']]
                );
                $stats['etapas']++;
            } catch (\Throwable $e) {
                $stats['erros'][] = "Etapa {$etapa['id']}: {$e->getMessage()}";
            }
        }

        // 2) Projetos
        $projetos = $this->db->fetchAll('SELECT project_id FROM dotp_projects');
        foreach ($projetos as $proj) {
            try {
                $percent = $this->calcularPercentProjeto((int) $proj['project_id']);
                $this->db->execute(
                    'UPDATE dotp_projects SET project_percent_complete = :p WHERE project_id = :id',
                    ['p' => $percent, 'id' => $proj['project_id']]
                );
                $stats['projetos']++;
            } catch (\Throwable $e) {
                $stats['erros'][] = "Projeto {$proj['project_id']}: {$e->getMessage()}";
            }
        }

        // 3) Ações (percent + valor_executado)
        $acoes = $this->db->fetchAll('SELECT id FROM dotp_acoes');
        foreach ($acoes as $acao) {
            try {
                $acaoId = (int) $acao['id'];
                $percent = $this->calcularPercentAcao($acaoId);
                $this->db->execute(
                    'UPDATE dotp_acoes SET percent_execucao = :p WHERE id = :id',
                    ['p' => $percent, 'id' => $acaoId]
                );
                $this->recalcularValorExecutadoAcao($acaoId);
                $stats['acoes']++;
            } catch (\Throwable $e) {
                $stats['erros'][] = "Ação {$acao['id']}: {$e->getMessage()}";
            }
        }

        // 4) Programas
        $programas = $this->db->fetchAll('SELECT id FROM dotp_programas');
        foreach ($programas as $prog) {
            try {
                $percent = $this->calcularPercentPrograma((int) $prog['id']);
                $this->db->execute(
                    'UPDATE dotp_programas SET percent_execucao = :p WHERE id = :id',
                    ['p' => $percent, 'id' => $prog['id']]
                );
                $stats['programas']++;
            } catch (\Throwable $e) {
                $stats['erros'][] = "Programa {$prog['id']}: {$e->getMessage()}";
            }
        }

        // 5) PPAs
        $ppas = $this->db->fetchAll("SELECT id FROM dotp_ppa");
        foreach ($ppas as $ppa) {
            try {
                $percent = $this->calcularPercentPpa((int) $ppa['id']);
                $this->db->execute(
                    'UPDATE dotp_ppa SET percent_execucao = :p WHERE id = :id',
                    ['p' => $percent, 'id' => $ppa['id']]
                );
                $stats['ppas']++;
            } catch (\Throwable $e) {
                $stats['erros'][] = "PPA {$ppa['id']}: {$e->getMessage()}";
            }
        }

        return $stats;
    }

    // ========================================================================
    // Helpers
    // ========================================================================

    /**
     * Divisão segura — retorna 0 se divisor = 0.
     */
    private function dividirSeguro(float $numerador, float $denominador): float
    {
        if ($denominador <= 0) {
            return 0.0;
        }

        return round($numerador / $denominador, 2);
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

    private function audit(): AuditService
    {
        if ($this->auditService === null) {
            $this->auditService = new AuditService($this->db);
        }

        return $this->auditService;
    }
}
