-- Migration: create project status history audit table
-- Date: 2026-02-10
-- Goal: persist project status transitions with actor/source metadata for traceability.

CREATE TABLE IF NOT EXISTS `dotp_project_status_history` (
    `history_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `history_project_id` INT(11) NOT NULL,
    `history_from_status` TINYINT(4) NOT NULL,
    `history_to_status` TINYINT(4) NOT NULL,
    `history_changed_by_user_id` INT(11) DEFAULT NULL,
    `history_source` VARCHAR(64) NOT NULL DEFAULT 'system',
    `history_note` VARCHAR(255) DEFAULT NULL,
    `history_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`history_id`),
    KEY `idx_psh_project_created` (`history_project_id`, `history_created_at`),
    KEY `idx_psh_changed_by_created` (`history_changed_by_user_id`, `history_created_at`),
    KEY `idx_psh_project_transition` (`history_project_id`, `history_from_status`, `history_to_status`),
    CONSTRAINT `fk_psh_project`
        FOREIGN KEY (`history_project_id`) REFERENCES `dotp_projects` (`project_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_psh_changed_by_user`
        FOREIGN KEY (`history_changed_by_user_id`) REFERENCES `dotp_users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @idx_psh_project_created_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_project_status_history'
      AND index_name = 'idx_psh_project_created'
);
SET @sql := IF(
    @idx_psh_project_created_exists = 0,
    'ALTER TABLE dotp_project_status_history ADD INDEX idx_psh_project_created (history_project_id, history_created_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_psh_changed_by_created_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_project_status_history'
      AND index_name = 'idx_psh_changed_by_created'
);
SET @sql := IF(
    @idx_psh_changed_by_created_exists = 0,
    'ALTER TABLE dotp_project_status_history ADD INDEX idx_psh_changed_by_created (history_changed_by_user_id, history_created_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_psh_project_transition_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_project_status_history'
      AND index_name = 'idx_psh_project_transition'
);
SET @sql := IF(
    @idx_psh_project_transition_exists = 0,
    'ALTER TABLE dotp_project_status_history ADD INDEX idx_psh_project_transition (history_project_id, history_from_status, history_to_status)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
