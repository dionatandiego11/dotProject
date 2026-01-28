-- =====================================================
-- Notifications Schema
-- Tabelas para o sistema de notificações
-- =====================================================

-- Tabela de Notificações
CREATE TABLE IF NOT EXISTS dotp_notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    notification_user_id INT NOT NULL,
    notification_type VARCHAR(50) NOT NULL COMMENT 'task_assigned, task_completed, etc',
    notification_title VARCHAR(255) NOT NULL,
    notification_message TEXT NOT NULL,
    notification_entity_type VARCHAR(50) NULL COMMENT 'project, task, etc',
    notification_entity_id INT NULL,
    notification_data JSON NULL COMMENT 'Dados adicionais em JSON',
    notification_is_read TINYINT DEFAULT 0 COMMENT '0=não lida, 1=lida',
    notification_read_at TIMESTAMP NULL,
    notification_channel VARCHAR(20) DEFAULT 'in_app' COMMENT 'in_app, email, push',
    notification_is_sent TINYINT DEFAULT 0 COMMENT '0=pendente, 1=enviado',
    notification_sent_at TIMESTAMP NULL,
    notification_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_notification_user (notification_user_id),
    INDEX idx_notification_type (notification_type),
    INDEX idx_notification_read (notification_is_read),
    INDEX idx_notification_created (notification_created_at),
    INDEX idx_notification_entity (notification_entity_type, notification_entity_id),
    INDEX idx_notification_channel (notification_channel, notification_is_sent),
    
    FOREIGN KEY (notification_user_id) REFERENCES dotp_users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Preferências de Notificação por Usuário
CREATE TABLE IF NOT EXISTS dotp_notification_preferences (
    preference_id INT AUTO_INCREMENT PRIMARY KEY,
    preference_user_id INT NOT NULL,
    preference_type VARCHAR(50) NOT NULL COMMENT 'Tipo de notificação',
    preference_in_app TINYINT DEFAULT 1 COMMENT 'Receber no app',
    preference_email TINYINT DEFAULT 1 COMMENT 'Receber por email',
    preference_push TINYINT DEFAULT 0 COMMENT 'Receber push notification',
    preference_updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_user_type (preference_user_id, preference_type),
    FOREIGN KEY (preference_user_id) REFERENCES dotp_users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- View para notificações não lidas com contagem
CREATE OR REPLACE VIEW view_notification_summary AS
SELECT 
    notification_user_id,
    COUNT(*) as total_notifications,
    SUM(CASE WHEN notification_is_read = 0 THEN 1 ELSE 0 END) as unread_count,
    MAX(notification_created_at) as last_notification_at
FROM dotp_notifications
GROUP BY notification_user_id;

-- View para notificações recentes com detalhes
CREATE OR REPLACE VIEW view_notifications_recent AS
SELECT 
    n.*,
    u.user_username,
    u.user_contact,
    CASE 
        WHEN n.notification_created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 'just_now'
        WHEN n.notification_created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 'today'
        WHEN n.notification_created_at > DATE_SUB(NOW(), INTERVAL 48 HOUR) THEN 'yesterday'
        ELSE 'older'
    END as time_category
FROM dotp_notifications n
JOIN dotp_users u ON u.user_id = n.notification_user_id
ORDER BY n.notification_created_at DESC;

-- Inserir preferências padrão para usuários existentes
INSERT INTO dotp_notification_preferences 
    (preference_user_id, preference_type, preference_in_app, preference_email, preference_push)
SELECT 
    u.user_id,
    'task_assigned',
    1, 1, 0
FROM dotp_users u
WHERE u.user_type >= 0
ON DUPLICATE KEY UPDATE preference_id = preference_id;

INSERT INTO dotp_notification_preferences 
    (preference_user_id, preference_type, preference_in_app, preference_email, preference_push)
SELECT 
    u.user_id,
    'task_completed',
    1, 1, 0
FROM dotp_users u
WHERE u.user_type >= 0
ON DUPLICATE KEY UPDATE preference_id = preference_id;

INSERT INTO dotp_notification_preferences 
    (preference_user_id, preference_type, preference_in_app, preference_email, preference_push)
SELECT 
    u.user_id,
    'task_overdue',
    1, 1, 1
FROM dotp_users u
WHERE u.user_type >= 0
ON DUPLICATE KEY UPDATE preference_id = preference_id;

INSERT INTO dotp_notification_preferences 
    (preference_user_id, preference_type, preference_in_app, preference_email, preference_push)
SELECT 
    u.user_id,
    'deadline_approaching',
    1, 1, 1
FROM dotp_users u
WHERE u.user_type >= 0
ON DUPLICATE KEY UPDATE preference_id = preference_id;
