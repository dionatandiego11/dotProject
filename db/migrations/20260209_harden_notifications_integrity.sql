-- Migration: harden notifications integrity and query indexes
-- Date: 2026-02-09
-- Goal: remove nullable legacy flags and add composite indexes used by repository queries.

-- 1) Normalize legacy nulls before hardening columns.
UPDATE dotp_notifications
SET notification_is_read = 0
WHERE notification_is_read IS NULL;

UPDATE dotp_notifications
SET notification_is_sent = 0
WHERE notification_is_sent IS NULL;

UPDATE dotp_notifications
SET notification_channel = 'in_app'
WHERE notification_channel IS NULL OR TRIM(notification_channel) = '';

UPDATE dotp_notifications
SET notification_created_at = NOW()
WHERE notification_created_at IS NULL;

-- 2) Harden nullable legacy columns.
ALTER TABLE dotp_notifications
    MODIFY COLUMN notification_is_read TINYINT(4) NOT NULL DEFAULT 0 COMMENT '0=nao lida, 1=lida',
    MODIFY COLUMN notification_is_sent TINYINT(4) NOT NULL DEFAULT 0 COMMENT '0=pendente, 1=enviado',
    MODIFY COLUMN notification_channel VARCHAR(20) NOT NULL DEFAULT 'in_app' COMMENT 'in_app, email, push',
    MODIFY COLUMN notification_created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- 3) Add composite indexes aligned with repository query patterns.
SET @idx_notif_user_read_created_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_notifications'
      AND index_name = 'idx_notif_user_read_created'
);
SET @sql := IF(
    @idx_notif_user_read_created_exists = 0,
    'ALTER TABLE dotp_notifications ADD INDEX idx_notif_user_read_created (notification_user_id, notification_is_read, notification_created_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_notif_channel_sent_created_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_notifications'
      AND index_name = 'idx_notif_channel_sent_created'
);
SET @sql := IF(
    @idx_notif_channel_sent_created_exists = 0,
    'ALTER TABLE dotp_notifications ADD INDEX idx_notif_channel_sent_created (notification_channel, notification_is_sent, notification_created_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_notif_user_type_created_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_notifications'
      AND index_name = 'idx_notif_user_type_created'
);
SET @sql := IF(
    @idx_notif_user_type_created_exists = 0,
    'ALTER TABLE dotp_notifications ADD INDEX idx_notif_user_type_created (notification_user_id, notification_type, notification_created_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
