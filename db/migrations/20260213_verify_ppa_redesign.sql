-- ============================================================================
-- Verification: Redesign PPA — Confirma que migrations 1A e 1B foram aplicadas
-- Data: 2026-02-13
-- ============================================================================

SELECT '=== VERIFICAÇÃO dotp_metas ===' AS secao;
SELECT COUNT(*) AS metas_table_exists
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_metas';

SELECT '=== VERIFICAÇÃO colunas novas ===' AS secao;
SELECT table_name, column_name, column_type, column_default
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND (
    (table_name = 'dotp_programas' AND column_name IN ('status_saude'))
    OR (table_name = 'dotp_acoes' AND column_name IN ('status_saude', 'tipo', 'valor_orcamentario', 'valor_executado', 'data_inicio_prevista', 'data_fim_prevista'))
    OR (table_name = 'dotp_projects' AND column_name IN ('status_saude', 'valor_executado', 'status_fluxo'))
    OR (table_name = 'dotp_etapas' AND column_name IN ('peso'))
    OR (table_name = 'dotp_tasks' AND column_name IN ('peso'))
  )
ORDER BY table_name, column_name;

SELECT '=== VERIFICAÇÃO FKs ===' AS secao;
SELECT constraint_name, table_name, referenced_table_name
FROM information_schema.referential_constraints
WHERE constraint_schema = DATABASE()
  AND table_name = 'dotp_metas';

SELECT '=== CONTAGEM registros dotp_metas ===' AS secao;
SELECT COUNT(*) AS total_metas FROM dotp_metas;

SELECT 'Verificação concluída.' AS resultado;
