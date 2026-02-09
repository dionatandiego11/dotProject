-- Verification for 20260209_add_task_assigned_to.sql
-- Expected result:
-- - has_task_assigned_to_column = 1
-- - task_assigned_to_zero_count = 0
-- - task_assigned_to_invalid_fk = 0
-- - tasks_with_owner_without_assignee = 0
-- - has_idx_task_assigned_to = 1
-- - has_fk_tasks_assigned_to = 1

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_task_assigned_to_column
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_tasks'
  AND column_name = 'task_assigned_to';

SELECT
    COUNT(*) AS task_assigned_to_zero_count
FROM dotp_tasks
WHERE task_assigned_to = 0;

SELECT
    COUNT(*) AS task_assigned_to_invalid_fk
FROM dotp_tasks t
LEFT JOIN dotp_users u ON u.user_id = t.task_assigned_to
WHERE t.task_assigned_to IS NOT NULL
  AND u.user_id IS NULL;

SELECT
    COUNT(*) AS tasks_with_owner_without_assignee
FROM dotp_tasks
WHERE task_owner IS NOT NULL
  AND task_owner <> 0
  AND task_assigned_to IS NULL;

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_idx_task_assigned_to
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_tasks'
  AND index_name = 'idx_task_assigned_to';

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_fk_tasks_assigned_to
FROM information_schema.referential_constraints
WHERE constraint_schema = DATABASE()
  AND table_name = 'dotp_tasks'
  AND constraint_name = 'fk_tasks_assigned_to';
