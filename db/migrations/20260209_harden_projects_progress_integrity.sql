-- Migration: harden projects status/percent integrity and project analytics indexes
-- Date: 2026-02-09
-- Goal: normalize legacy inconsistencies in project progress and add indexes aligned with project/dashboard queries.

-- 1) Normalize null/invalid legacy values while preserving semantic soft-delete status (-1).
UPDATE dotp_projects
SET project_percent_complete = 0
WHERE project_percent_complete IS NULL;

UPDATE dotp_projects
SET project_percent_complete = 0
WHERE project_percent_complete < 0;

UPDATE dotp_projects
SET project_percent_complete = 100
WHERE project_percent_complete > 100;

UPDATE dotp_projects
SET project_status = 0
WHERE project_status IS NULL;

UPDATE dotp_projects
SET project_status = -1
WHERE project_status < -1;

UPDATE dotp_projects
SET project_status = 7
WHERE project_status > 7;

-- 2) Canonical sync for primary workflow states.
UPDATE dotp_projects
SET project_percent_complete = 100
WHERE project_status = 5
  AND COALESCE(project_percent_complete, 0) < 100;

UPDATE dotp_projects
SET project_percent_complete = 0
WHERE project_status = 0
  AND COALESCE(project_percent_complete, 0) <> 0;

-- 3) Add composite indexes for project listing and dashboard filters.
SET @idx_project_status_percent_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projects'
      AND index_name = 'idx_project_status_percent'
);
SET @sql := IF(
    @idx_project_status_percent_exists = 0,
    'ALTER TABLE dotp_projects ADD INDEX idx_project_status_percent (project_status, project_percent_complete)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_project_company_status_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projects'
      AND index_name = 'idx_project_company_status'
);
SET @sql := IF(
    @idx_project_company_status_exists = 0,
    'ALTER TABLE dotp_projects ADD INDEX idx_project_company_status (project_company, project_status)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_project_owner_status_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projects'
      AND index_name = 'idx_project_owner_status'
);
SET @sql := IF(
    @idx_project_owner_status_exists = 0,
    'ALTER TABLE dotp_projects ADD INDEX idx_project_owner_status (project_owner, project_status)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
