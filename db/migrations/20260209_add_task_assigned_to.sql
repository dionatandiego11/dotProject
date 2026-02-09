-- Migration: add and normalize tasks.task_assigned_to
-- Date: 2026-02-09
-- Goal: align legacy tasks schema with modern services/repositories that use assignee semantics.

-- 1) Add missing column used by modern layers.
SET @task_assigned_column_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_tasks'
      AND column_name = 'task_assigned_to'
);
SET @sql := IF(
    @task_assigned_column_exists = 0,
    'ALTER TABLE dotp_tasks ADD COLUMN task_assigned_to INT(11) NULL DEFAULT NULL AFTER task_owner',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Normalize legacy sentinel values.
UPDATE dotp_tasks
SET task_assigned_to = NULL
WHERE task_assigned_to = 0;

-- 3) Backfill from owner when assignee is missing.
UPDATE dotp_tasks
SET task_assigned_to = task_owner
WHERE task_assigned_to IS NULL
  AND task_owner IS NOT NULL
  AND task_owner <> 0;

-- 4) Remove invalid assignee references before enforcing FK.
UPDATE dotp_tasks t
LEFT JOIN dotp_users u ON u.user_id = t.task_assigned_to
SET t.task_assigned_to = NULL
WHERE t.task_assigned_to IS NOT NULL
  AND u.user_id IS NULL;

-- 5) Keep nullable to support unassigned tasks.
ALTER TABLE dotp_tasks
    MODIFY COLUMN task_assigned_to INT(11) NULL DEFAULT NULL;

-- 6) Add index for assignment queries.
SET @idx_task_assigned_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_tasks'
      AND index_name = 'idx_task_assigned_to'
);
SET @sql := IF(
    @idx_task_assigned_exists = 0,
    'ALTER TABLE dotp_tasks ADD INDEX idx_task_assigned_to (task_assigned_to)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 7) Add FK only once.
SET @fk_tasks_assigned_exists := (
    SELECT COUNT(*)
    FROM information_schema.referential_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'dotp_tasks'
      AND constraint_name = 'fk_tasks_assigned_to'
);
SET @sql := IF(
    @fk_tasks_assigned_exists = 0,
    'ALTER TABLE dotp_tasks ADD CONSTRAINT fk_tasks_assigned_to FOREIGN KEY (task_assigned_to) REFERENCES dotp_users(user_id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
