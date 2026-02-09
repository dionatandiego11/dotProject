-- Migration: harden tasks status/percent integrity and task analytics indexes
-- Date: 2026-02-09
-- Goal: normalize legacy inconsistencies for task progress and add indexes aligned with dashboard/task queries.

-- 1) Normalize null/invalid legacy values while preserving semantic soft-delete status (-1).
UPDATE dotp_tasks
SET task_percent_complete = 0
WHERE task_percent_complete IS NULL;

UPDATE dotp_tasks
SET task_percent_complete = 0
WHERE task_percent_complete < 0;

UPDATE dotp_tasks
SET task_percent_complete = 100
WHERE task_percent_complete > 100;

UPDATE dotp_tasks
SET task_status = 0
WHERE task_status IS NULL;

UPDATE dotp_tasks
SET task_status = -1
WHERE task_status < -1;

UPDATE dotp_tasks
SET task_status = 7
WHERE task_status > 7;

-- 2) Canonical sync for core workflow statuses (0=backlog, 1=todo, 2=in_progress, 3=done).
UPDATE dotp_tasks
SET task_percent_complete = 100
WHERE task_status = 3
  AND COALESCE(task_percent_complete, 0) < 100;

UPDATE dotp_tasks
SET task_percent_complete = 0
WHERE task_status = 0
  AND COALESCE(task_percent_complete, 0) <> 0;

UPDATE dotp_tasks
SET task_status = 3
WHERE COALESCE(task_percent_complete, 0) = 100
  AND task_status IN (0, 1, 2);

UPDATE dotp_tasks
SET task_status = 0
WHERE COALESCE(task_percent_complete, 0) = 0
  AND task_status IN (1, 2);

-- 3) Add composite indexes for frequent filters in analytics/task flows.
SET @idx_task_status_percent_end_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_tasks'
      AND index_name = 'idx_task_status_percent_end'
);
SET @sql := IF(
    @idx_task_status_percent_end_exists = 0,
    'ALTER TABLE dotp_tasks ADD INDEX idx_task_status_percent_end (task_status, task_percent_complete, task_end_date)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_task_project_status_percent_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_tasks'
      AND index_name = 'idx_task_project_status_percent'
);
SET @sql := IF(
    @idx_task_project_status_percent_exists = 0,
    'ALTER TABLE dotp_tasks ADD INDEX idx_task_project_status_percent (task_project, task_status, task_percent_complete)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_task_owner_status_percent_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_tasks'
      AND index_name = 'idx_task_owner_status_percent'
);
SET @sql := IF(
    @idx_task_owner_status_percent_exists = 0,
    'ALTER TABLE dotp_tasks ADD INDEX idx_task_owner_status_percent (task_owner, task_status, task_percent_complete)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
