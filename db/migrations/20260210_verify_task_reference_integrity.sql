-- Verification for 20260210_harden_task_reference_integrity.sql
-- Expected result:
-- - orphan_task_log_count = 0
-- - orphan_task_contacts_count = 0
-- - orphan_task_departments_count = 0
-- - orphan_user_tasks_count = 0
-- - orphan_task_dependencies_count = 0
-- - has_fk_task_log_task = 1
-- - has_fk_task_contacts_task = 1
-- - has_fk_task_departments_task = 1
-- - has_fk_user_tasks_task = 1
-- - has_fk_task_dependencies_task = 1
-- - has_fk_task_dependencies_req_task = 1

SELECT
    COUNT(*) AS orphan_task_log_count
FROM dotp_task_log tl
LEFT JOIN dotp_tasks t ON t.task_id = tl.task_log_task
WHERE t.task_id IS NULL;

SELECT
    COUNT(*) AS orphan_task_contacts_count
FROM dotp_task_contacts tc
LEFT JOIN dotp_tasks t ON t.task_id = tc.task_id
WHERE t.task_id IS NULL;

SELECT
    COUNT(*) AS orphan_task_departments_count
FROM dotp_task_departments td
LEFT JOIN dotp_tasks t ON t.task_id = td.task_id
WHERE t.task_id IS NULL;

SELECT
    COUNT(*) AS orphan_user_tasks_count
FROM dotp_user_tasks ut
LEFT JOIN dotp_tasks t ON t.task_id = ut.task_id
WHERE t.task_id IS NULL;

SELECT
    COUNT(*) AS orphan_task_dependencies_count
FROM dotp_task_dependencies d
LEFT JOIN dotp_tasks t1 ON t1.task_id = d.dependencies_task_id
LEFT JOIN dotp_tasks t2 ON t2.task_id = d.dependencies_req_task_id
WHERE t1.task_id IS NULL
   OR t2.task_id IS NULL;

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_fk_task_log_task
FROM information_schema.key_column_usage
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_task_log'
  AND column_name = 'task_log_task'
  AND referenced_table_name = 'dotp_tasks'
  AND referenced_column_name = 'task_id';

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_fk_task_contacts_task
FROM information_schema.key_column_usage
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_task_contacts'
  AND column_name = 'task_id'
  AND referenced_table_name = 'dotp_tasks'
  AND referenced_column_name = 'task_id';

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_fk_task_departments_task
FROM information_schema.key_column_usage
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_task_departments'
  AND column_name = 'task_id'
  AND referenced_table_name = 'dotp_tasks'
  AND referenced_column_name = 'task_id';

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_fk_user_tasks_task
FROM information_schema.key_column_usage
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_user_tasks'
  AND column_name = 'task_id'
  AND referenced_table_name = 'dotp_tasks'
  AND referenced_column_name = 'task_id';

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_fk_task_dependencies_task
FROM information_schema.key_column_usage
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_task_dependencies'
  AND column_name = 'dependencies_task_id'
  AND referenced_table_name = 'dotp_tasks'
  AND referenced_column_name = 'task_id';

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_fk_task_dependencies_req_task
FROM information_schema.key_column_usage
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_task_dependencies'
  AND column_name = 'dependencies_req_task_id'
  AND referenced_table_name = 'dotp_tasks'
  AND referenced_column_name = 'task_id';
