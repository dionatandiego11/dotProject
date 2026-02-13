-- Verification for 20260213_add_project_acoes_bridge.sql
-- Expected:
-- - has_projeto_acoes_table = 1
-- - has_column_* = 1
-- - has_index_* = 1
-- - has_fk_project/has_fk_acao = 1
-- - backfill_missing_count = 0
-- - orphan_link_*_count = 0
-- - duplicate_pair_count = 0

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_projeto_acoes_table
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_projeto_acoes';

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_column_project_id
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_projeto_acoes'
  AND column_name = 'project_id';

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_column_acao_id
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_projeto_acoes'
  AND column_name = 'acao_id';

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_column_principal
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_projeto_acoes'
  AND column_name = 'principal';

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_column_peso_contribuicao
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_projeto_acoes'
  AND column_name = 'peso_contribuicao';

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_index_acao_id
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_projeto_acoes'
  AND index_name = 'idx_projeto_acoes_acao_id';

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_index_principal
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_projeto_acoes'
  AND index_name = 'idx_projeto_acoes_principal';

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_fk_project
FROM information_schema.referential_constraints
WHERE constraint_schema = DATABASE()
  AND table_name = 'dotp_projeto_acoes'
  AND constraint_name = 'fk_projeto_acoes_project';

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_fk_acao
FROM information_schema.referential_constraints
WHERE constraint_schema = DATABASE()
  AND table_name = 'dotp_projeto_acoes'
  AND constraint_name = 'fk_projeto_acoes_acao';

SELECT
    COUNT(*) AS duplicate_pair_count
FROM (
    SELECT project_id, acao_id, COUNT(*) AS total
    FROM dotp_projeto_acoes
    GROUP BY project_id, acao_id
    HAVING COUNT(*) > 1
) d;

SELECT
    COUNT(*) AS orphan_link_project_count
FROM dotp_projeto_acoes pa
LEFT JOIN dotp_projects p ON p.project_id = pa.project_id
WHERE p.project_id IS NULL;

SELECT
    COUNT(*) AS orphan_link_acao_count
FROM dotp_projeto_acoes pa
LEFT JOIN dotp_acoes a ON a.id = pa.acao_id
WHERE a.id IS NULL;

SELECT
    COUNT(*) AS backfill_missing_count
FROM dotp_projects p
LEFT JOIN dotp_projeto_acoes pa
       ON pa.project_id = p.project_id
      AND pa.acao_id = p.project_acao_id
WHERE p.project_acao_id IS NOT NULL
  AND p.project_acao_id > 0
  AND pa.project_id IS NULL;

SELECT
    COUNT(*) AS principal_missing_count
FROM dotp_projects p
LEFT JOIN dotp_projeto_acoes pa
       ON pa.project_id = p.project_id
      AND pa.acao_id = p.project_acao_id
      AND pa.principal = 1
WHERE p.project_acao_id IS NOT NULL
  AND p.project_acao_id > 0
  AND pa.project_id IS NULL;
