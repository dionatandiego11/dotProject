-- Migration: add users.user_status for soft delete compatibility
-- Date: 2026-02-09
-- Goal: ensure admin user deactivation does not require hard delete and FK cleanup.

-- 1) Add missing status column.
SET @user_status_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_users'
      AND column_name = 'user_status'
);
SET @sql := IF(
    @user_status_exists = 0,
    'ALTER TABLE dotp_users ADD COLUMN user_status TINYINT(1) NOT NULL DEFAULT 0 AFTER user_department',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Normalize null/legacy values to active(0)/inactive(1).
UPDATE dotp_users
SET user_status = 0
WHERE user_status IS NULL;

UPDATE dotp_users
SET user_status = 1
WHERE user_status NOT IN (0, 1);

-- 3) Add index for active-user filters.
SET @idx_user_status_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_users'
      AND index_name = 'idx_user_status'
);
SET @sql := IF(
    @idx_user_status_exists = 0,
    'ALTER TABLE dotp_users ADD INDEX idx_user_status (user_status)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
