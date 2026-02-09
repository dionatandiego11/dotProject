-- Verification for 20260209_harden_projects_progress_integrity.sql
-- Expected result:
-- - project_percent_null_count = 0
-- - project_percent_below_zero_count = 0
-- - project_percent_above_hundred_count = 0
-- - project_status_null_count = 0
-- - project_status_below_minus_one_count = 0
-- - project_status_above_seven_count = 0
-- - done_status_not_hundred_count = 0
-- - backlog_status_not_zero_count = 0
-- - has_idx_project_status_percent = 1
-- - has_idx_project_company_status = 1
-- - has_idx_project_owner_status = 1

SELECT
    COUNT(*) AS project_percent_null_count
FROM dotp_projects
WHERE project_percent_complete IS NULL;

SELECT
    COUNT(*) AS project_percent_below_zero_count
FROM dotp_projects
WHERE project_percent_complete < 0;

SELECT
    COUNT(*) AS project_percent_above_hundred_count
FROM dotp_projects
WHERE project_percent_complete > 100;

SELECT
    COUNT(*) AS project_status_null_count
FROM dotp_projects
WHERE project_status IS NULL;

SELECT
    COUNT(*) AS project_status_below_minus_one_count
FROM dotp_projects
WHERE project_status < -1;

SELECT
    COUNT(*) AS project_status_above_seven_count
FROM dotp_projects
WHERE project_status > 7;

SELECT
    COUNT(*) AS done_status_not_hundred_count
FROM dotp_projects
WHERE project_status = 5
  AND COALESCE(project_percent_complete, 0) < 100;

SELECT
    COUNT(*) AS backlog_status_not_zero_count
FROM dotp_projects
WHERE project_status = 0
  AND COALESCE(project_percent_complete, 0) <> 0;

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_idx_project_status_percent
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_projects'
  AND index_name = 'idx_project_status_percent';

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_idx_project_company_status
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_projects'
  AND index_name = 'idx_project_company_status';

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_idx_project_owner_status
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_projects'
  AND index_name = 'idx_project_owner_status';
