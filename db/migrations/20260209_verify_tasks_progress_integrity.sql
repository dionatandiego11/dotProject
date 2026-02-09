-- Verification for 20260209_harden_tasks_progress_integrity.sql
-- Expected result:
-- - task_percent_null_count = 0
-- - task_percent_below_zero_count = 0
-- - task_percent_above_hundred_count = 0
-- - task_status_null_count = 0
-- - task_status_below_minus_one_count = 0
-- - task_status_above_seven_count = 0
-- - done_status_not_hundred_count = 0
-- - backlog_status_not_zero_count = 0
-- - hundred_percent_with_low_status_count = 0
-- - zero_percent_with_active_status_count = 0
-- - has_idx_task_status_percent_end = 1
-- - has_idx_task_project_status_percent = 1
-- - has_idx_task_owner_status_percent = 1

SELECT
    COUNT(*) AS task_percent_null_count
FROM dotp_tasks
WHERE task_percent_complete IS NULL;

SELECT
    COUNT(*) AS task_percent_below_zero_count
FROM dotp_tasks
WHERE task_percent_complete < 0;

SELECT
    COUNT(*) AS task_percent_above_hundred_count
FROM dotp_tasks
WHERE task_percent_complete > 100;

SELECT
    COUNT(*) AS task_status_null_count
FROM dotp_tasks
WHERE task_status IS NULL;

SELECT
    COUNT(*) AS task_status_below_minus_one_count
FROM dotp_tasks
WHERE task_status < -1;

SELECT
    COUNT(*) AS task_status_above_seven_count
FROM dotp_tasks
WHERE task_status > 7;

SELECT
    COUNT(*) AS done_status_not_hundred_count
FROM dotp_tasks
WHERE task_status = 3
  AND COALESCE(task_percent_complete, 0) < 100;

SELECT
    COUNT(*) AS backlog_status_not_zero_count
FROM dotp_tasks
WHERE task_status = 0
  AND COALESCE(task_percent_complete, 0) <> 0;

SELECT
    COUNT(*) AS hundred_percent_with_low_status_count
FROM dotp_tasks
WHERE COALESCE(task_percent_complete, 0) = 100
  AND task_status IN (0, 1, 2);

SELECT
    COUNT(*) AS zero_percent_with_active_status_count
FROM dotp_tasks
WHERE COALESCE(task_percent_complete, 0) = 0
  AND task_status IN (1, 2);

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_idx_task_status_percent_end
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_tasks'
  AND index_name = 'idx_task_status_percent_end';

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_idx_task_project_status_percent
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_tasks'
  AND index_name = 'idx_task_project_status_percent';

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_idx_task_owner_status_percent
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_tasks'
  AND index_name = 'idx_task_owner_status_percent';
