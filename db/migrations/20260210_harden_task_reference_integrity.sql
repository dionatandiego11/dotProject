-- Migration: harden task reference integrity in legacy auxiliary tables
-- Date: 2026-02-10
-- Goal: remove orphan rows and enforce FK constraints to dotp_tasks with cascade delete.

-- 1) Ensure InnoDB engine for FK support.
SET @tbl_exists := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_task_log'
);
SET @sql := IF(@tbl_exists = 1, 'ALTER TABLE dotp_task_log ENGINE=InnoDB', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @tbl_exists := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_task_contacts'
);
SET @sql := IF(@tbl_exists = 1, 'ALTER TABLE dotp_task_contacts ENGINE=InnoDB', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @tbl_exists := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_task_departments'
);
SET @sql := IF(@tbl_exists = 1, 'ALTER TABLE dotp_task_departments ENGINE=InnoDB', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @tbl_exists := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_user_tasks'
);
SET @sql := IF(@tbl_exists = 1, 'ALTER TABLE dotp_user_tasks ENGINE=InnoDB', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @tbl_exists := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_task_dependencies'
);
SET @sql := IF(@tbl_exists = 1, 'ALTER TABLE dotp_task_dependencies ENGINE=InnoDB', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Remove orphan references before adding FKs.
DELETE tl
FROM dotp_task_log tl
LEFT JOIN dotp_tasks t ON t.task_id = tl.task_log_task
WHERE t.task_id IS NULL;

DELETE tc
FROM dotp_task_contacts tc
LEFT JOIN dotp_tasks t ON t.task_id = tc.task_id
WHERE t.task_id IS NULL;

DELETE td
FROM dotp_task_departments td
LEFT JOIN dotp_tasks t ON t.task_id = td.task_id
WHERE t.task_id IS NULL;

DELETE ut
FROM dotp_user_tasks ut
LEFT JOIN dotp_tasks t ON t.task_id = ut.task_id
WHERE t.task_id IS NULL;

DELETE tdep
FROM dotp_task_dependencies tdep
LEFT JOIN dotp_tasks t1 ON t1.task_id = tdep.dependencies_task_id
LEFT JOIN dotp_tasks t2 ON t2.task_id = tdep.dependencies_req_task_id
WHERE t1.task_id IS NULL
   OR t2.task_id IS NULL;

-- 3) Ensure indexes exist for FK columns.
SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_task_log'
      AND index_name = 'idx_task_log_task_fk'
);
SET @sql := IF(@idx_exists = 0, 'ALTER TABLE dotp_task_log ADD INDEX idx_task_log_task_fk (task_log_task)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_task_contacts'
      AND index_name = 'idx_task_contacts_task_fk'
);
SET @sql := IF(@idx_exists = 0, 'ALTER TABLE dotp_task_contacts ADD INDEX idx_task_contacts_task_fk (task_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_task_departments'
      AND index_name = 'idx_task_departments_task_fk'
);
SET @sql := IF(@idx_exists = 0, 'ALTER TABLE dotp_task_departments ADD INDEX idx_task_departments_task_fk (task_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_user_tasks'
      AND index_name = 'idx_user_tasks_task_fk'
);
SET @sql := IF(@idx_exists = 0, 'ALTER TABLE dotp_user_tasks ADD INDEX idx_user_tasks_task_fk (task_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_task_dependencies'
      AND index_name = 'idx_task_dependencies_task_fk'
);
SET @sql := IF(@idx_exists = 0, 'ALTER TABLE dotp_task_dependencies ADD INDEX idx_task_dependencies_task_fk (dependencies_task_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_task_dependencies'
      AND index_name = 'idx_task_dependencies_req_task_fk'
);
SET @sql := IF(@idx_exists = 0, 'ALTER TABLE dotp_task_dependencies ADD INDEX idx_task_dependencies_req_task_fk (dependencies_req_task_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4) Add FK constraints when absent.
SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.key_column_usage
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_task_log'
      AND column_name = 'task_log_task'
      AND referenced_table_name = 'dotp_tasks'
      AND referenced_column_name = 'task_id'
);
SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE dotp_task_log ADD CONSTRAINT fk_task_log_task FOREIGN KEY (task_log_task) REFERENCES dotp_tasks(task_id) ON DELETE CASCADE ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.key_column_usage
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_task_contacts'
      AND column_name = 'task_id'
      AND referenced_table_name = 'dotp_tasks'
      AND referenced_column_name = 'task_id'
);
SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE dotp_task_contacts ADD CONSTRAINT fk_task_contacts_task FOREIGN KEY (task_id) REFERENCES dotp_tasks(task_id) ON DELETE CASCADE ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.key_column_usage
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_task_departments'
      AND column_name = 'task_id'
      AND referenced_table_name = 'dotp_tasks'
      AND referenced_column_name = 'task_id'
);
SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE dotp_task_departments ADD CONSTRAINT fk_task_departments_task FOREIGN KEY (task_id) REFERENCES dotp_tasks(task_id) ON DELETE CASCADE ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.key_column_usage
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_user_tasks'
      AND column_name = 'task_id'
      AND referenced_table_name = 'dotp_tasks'
      AND referenced_column_name = 'task_id'
);
SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE dotp_user_tasks ADD CONSTRAINT fk_user_tasks_task FOREIGN KEY (task_id) REFERENCES dotp_tasks(task_id) ON DELETE CASCADE ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.key_column_usage
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_task_dependencies'
      AND column_name = 'dependencies_task_id'
      AND referenced_table_name = 'dotp_tasks'
      AND referenced_column_name = 'task_id'
);
SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE dotp_task_dependencies ADD CONSTRAINT fk_task_dependencies_task FOREIGN KEY (dependencies_task_id) REFERENCES dotp_tasks(task_id) ON DELETE CASCADE ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.key_column_usage
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_task_dependencies'
      AND column_name = 'dependencies_req_task_id'
      AND referenced_table_name = 'dotp_tasks'
      AND referenced_column_name = 'task_id'
);
SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE dotp_task_dependencies ADD CONSTRAINT fk_task_dependencies_req_task FOREIGN KEY (dependencies_req_task_id) REFERENCES dotp_tasks(task_id) ON DELETE CASCADE ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
