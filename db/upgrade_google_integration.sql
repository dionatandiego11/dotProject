-- Google Integration Tables for dotProject
-- Migration: Add tables for Google OAuth and file syncing

-- Table: integrations
-- Stores OAuth tokens for external integrations (Google, Microsoft, etc.)
CREATE TABLE IF NOT EXISTS `integrations` (
    `integration_id` INT(11) AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT(11) NOT NULL,
    `provider` ENUM('google', 'microsoft') NOT NULL,
    `access_token` TEXT,
    `refresh_token` TEXT,
    `expires_at` DATETIME,
    `scope` VARCHAR(500),
    `enabled` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY `unique_user_provider` (`user_id`, `provider`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_provider` (`provider`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: google_calendar_events
-- Maps dotProject tasks to Google Calendar events
CREATE TABLE IF NOT EXISTS `google_calendar_events` (
    `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT(11) NOT NULL,
    `task_id` INT(11) NOT NULL,
    `event_id` VARCHAR(255) NOT NULL,
    `calendar_id` VARCHAR(255) DEFAULT 'primary',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY `unique_user_task` (`user_id`, `task_id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_task_id` (`task_id`),
    KEY `idx_event_id` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: google_drive_files
-- Links Google Drive files to projects/tasks
CREATE TABLE IF NOT EXISTS `google_drive_files` (
    `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT(11) NOT NULL,
    `project_id` INT(11),
    `task_id` INT(11),
    `drive_file_id` VARCHAR(255) NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `mime_type` VARCHAR(100),
    `web_link` VARCHAR(500),
    `icon_link` VARCHAR(255),
    `file_size` BIGINT DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    KEY `idx_user_id` (`user_id`),
    KEY `idx_project_id` (`project_id`),
    KEY `idx_task_id` (`task_id`),
    KEY `idx_drive_file_id` (`drive_file_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add Google configuration to config table (optional)
-- Insert these manually in your config.php:
-- $dPconfig['google_client_id'] = 'YOUR_CLIENT_ID';
-- $dPconfig['google_client_secret'] = 'YOUR_CLIENT_SECRET';
-- $dPconfig['google_redirect_uri'] = 'http://yoursite/api.php/v1/integrations/google/callback';
