-- Verification for 20260212_add_tenant_secondary_tables.sql
-- Expected: all has_tenant_column_* = 1, all null_tenant_count_* = 0

SELECT 'contacts' AS `table`,
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'dotp_contacts' AND column_name = 'tenant_id') AS has_tenant_column,
    (SELECT COUNT(*) FROM dotp_contacts WHERE tenant_id IS NULL OR tenant_id = 0) AS null_tenant_count
UNION ALL
SELECT 'companies',
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'dotp_companies' AND column_name = 'tenant_id'),
    (SELECT COUNT(*) FROM dotp_companies WHERE tenant_id IS NULL OR tenant_id = 0)
UNION ALL
SELECT 'kanban_boards',
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'dotp_kanban_boards' AND column_name = 'tenant_id'),
    IFNULL((SELECT COUNT(*) FROM dotp_kanban_boards WHERE tenant_id IS NULL OR tenant_id = 0), 0)
UNION ALL
SELECT 'kanban_columns',
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'dotp_kanban_columns' AND column_name = 'tenant_id'),
    IFNULL((SELECT COUNT(*) FROM dotp_kanban_columns WHERE tenant_id IS NULL OR tenant_id = 0), 0)
UNION ALL
SELECT 'task_dependencies',
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'dotp_task_dependencies' AND column_name = 'tenant_id'),
    IFNULL((SELECT COUNT(*) FROM dotp_task_dependencies WHERE tenant_id IS NULL OR tenant_id = 0), 0)
UNION ALL
SELECT 'user_tasks',
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'dotp_user_tasks' AND column_name = 'tenant_id'),
    IFNULL((SELECT COUNT(*) FROM dotp_user_tasks WHERE tenant_id IS NULL OR tenant_id = 0), 0)
UNION ALL
SELECT 'task_log',
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'dotp_task_log' AND column_name = 'tenant_id'),
    IFNULL((SELECT COUNT(*) FROM dotp_task_log WHERE tenant_id IS NULL OR tenant_id = 0), 0)
UNION ALL
SELECT 'user_access_log',
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'dotp_user_access_log' AND column_name = 'tenant_id'),
    IFNULL((SELECT COUNT(*) FROM dotp_user_access_log WHERE tenant_id IS NULL OR tenant_id = 0), 0)
UNION ALL
SELECT 'project_status_history',
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'dotp_project_status_history' AND column_name = 'tenant_id'),
    IFNULL((SELECT COUNT(*) FROM dotp_project_status_history WHERE tenant_id IS NULL OR tenant_id = 0), 0);
