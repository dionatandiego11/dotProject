-- Verification for 20260211_add_tenant_foundation.sql
-- Expected:
-- - tenants_table_exists = 1
-- - default_tenant_exists = 1
-- - each has_tenant_column_* = 1 when table exists
-- - each null_tenant_count_* = 0 when table exists

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS tenants_table_exists
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_tenants';

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS default_tenant_exists
FROM dotp_tenants
WHERE tenant_id = 1
  AND tenant_active = 1;

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_tenant_column_users
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_users'
  AND column_name = 'tenant_id';

SELECT
    COUNT(*) AS null_tenant_count_users
FROM dotp_users
WHERE tenant_id IS NULL OR tenant_id = 0;

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_tenant_column_projects
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_projects'
  AND column_name = 'tenant_id';

SELECT
    COUNT(*) AS null_tenant_count_projects
FROM dotp_projects
WHERE tenant_id IS NULL OR tenant_id = 0;

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_tenant_column_tasks
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_tasks'
  AND column_name = 'tenant_id';

SELECT
    COUNT(*) AS null_tenant_count_tasks
FROM dotp_tasks
WHERE tenant_id IS NULL OR tenant_id = 0;

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_tenant_column_unidades
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_unidades_organizacionais'
  AND column_name = 'tenant_id';

SELECT
    COUNT(*) AS null_tenant_count_unidades
FROM dotp_unidades_organizacionais
WHERE tenant_id IS NULL OR tenant_id = 0;

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_tenant_column_projetos_prefeitura
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_projetos_prefeitura'
  AND column_name = 'tenant_id';

SELECT
    COUNT(*) AS null_tenant_count_projetos_prefeitura
FROM dotp_projetos_prefeitura
WHERE tenant_id IS NULL OR tenant_id = 0;

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_tenant_column_programas
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_programas'
  AND column_name = 'tenant_id';

SELECT
    COUNT(*) AS null_tenant_count_programas
FROM dotp_programas
WHERE tenant_id IS NULL OR tenant_id = 0;
