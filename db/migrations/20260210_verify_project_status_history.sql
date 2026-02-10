-- Verification for 20260210_create_project_status_history.sql
-- Expected result:
-- - has_project_status_history_table = 1
-- - has_history_source_column = 1
-- - has_idx_psh_project_created = 1
-- - has_idx_psh_changed_by_created = 1
-- - has_idx_psh_project_transition = 1
-- - has_fk_psh_project = 1
-- - has_fk_psh_changed_by_user = 1

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_project_status_history_table
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_project_status_history';

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_history_source_column
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_project_status_history'
  AND column_name = 'history_source';

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_idx_psh_project_created
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_project_status_history'
  AND index_name = 'idx_psh_project_created';

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_idx_psh_changed_by_created
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_project_status_history'
  AND index_name = 'idx_psh_changed_by_created';

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_idx_psh_project_transition
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_project_status_history'
  AND index_name = 'idx_psh_project_transition';

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_fk_psh_project
FROM information_schema.referential_constraints
WHERE constraint_schema = DATABASE()
  AND table_name = 'dotp_project_status_history'
  AND constraint_name = 'fk_psh_project';

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_fk_psh_changed_by_user
FROM information_schema.referential_constraints
WHERE constraint_schema = DATABASE()
  AND table_name = 'dotp_project_status_history'
  AND constraint_name = 'fk_psh_changed_by_user';
