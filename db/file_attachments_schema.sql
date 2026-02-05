-- =====================================================
-- File Attachments Schema
-- Tabela para anexar arquivos em tarefas
-- =====================================================

CREATE TABLE IF NOT EXISTS dotp_task_files (
    file_id INT AUTO_INCREMENT PRIMARY KEY,
    file_task_id INT NOT NULL COMMENT 'Tarefa associada',
    file_name VARCHAR(255) NOT NULL COMMENT 'Nome original do arquivo',
    file_path VARCHAR(500) NOT NULL COMMENT 'Caminho de armazenamento',
    file_size INT NOT NULL COMMENT 'Tamanho em bytes',
    file_mime_type VARCHAR(100) NOT NULL COMMENT 'Tipo MIME',
    file_uploaded_by INT COMMENT 'Usuário que fez o upload',
    file_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_file_task (file_task_id),
    INDEX idx_file_uploaded_by (file_uploaded_by),
    
    FOREIGN KEY (file_task_id) REFERENCES dotp_tasks(task_id) ON DELETE CASCADE,
    FOREIGN KEY (file_uploaded_by) REFERENCES dotp_users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- View para listar arquivos com informações do usuário
CREATE OR REPLACE VIEW view_task_files AS
SELECT 
    f.*,
    u.user_username as uploaded_by_name,
    CONCAT(ROUND(f.file_size / 1024, 2), ' KB') as file_size_formatted
FROM dotp_task_files f
LEFT JOIN dotp_users u ON u.user_id = f.file_uploaded_by;
