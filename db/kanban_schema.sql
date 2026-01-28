-- =====================================================
-- Kanban Board Schema
-- Tabelas para o sistema de quadros Kanban
-- =====================================================

-- Tabela de Boards
CREATE TABLE IF NOT EXISTS dotp_kanban_boards (
    board_id INT AUTO_INCREMENT PRIMARY KEY,
    board_name VARCHAR(255) NOT NULL,
    board_description TEXT,
    board_project INT NULL,  -- NULL = board global/independente
    board_company INT NOT NULL,
    board_created_by INT,
    board_status TINYINT DEFAULT 0 COMMENT '0=ativo, 1=arquivado',
    board_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    board_updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_board_project (board_project),
    INDEX idx_board_company (board_company),
    INDEX idx_board_status (board_status),
    INDEX idx_board_created_by (board_created_by),
    
    FOREIGN KEY (board_project) REFERENCES dotp_projects(project_id) ON DELETE CASCADE,
    FOREIGN KEY (board_company) REFERENCES dotp_companies(company_id) ON DELETE CASCADE,
    FOREIGN KEY (board_created_by) REFERENCES dotp_users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Colunas
CREATE TABLE IF NOT EXISTS dotp_kanban_columns (
    column_id INT AUTO_INCREMENT PRIMARY KEY,
    column_board_id INT NOT NULL,
    column_name VARCHAR(100) NOT NULL,
    column_color VARCHAR(7) NULL COMMENT 'Hex color (#RRGGBB)',
    column_order INT DEFAULT 0 COMMENT 'Ordem da coluna no board',
    column_wip_limit INT NULL COMMENT 'Limite de trabalho em progresso',
    column_status TINYINT DEFAULT 0 COMMENT '0=ativo, 1=arquivado',
    column_is_done TINYINT DEFAULT 0 COMMENT '1=coluna de concluídos',
    column_is_backlog TINYINT DEFAULT 0 COMMENT '1=coluna de backlog',
    
    INDEX idx_column_board (column_board_id),
    INDEX idx_column_order (column_order),
    INDEX idx_column_status (column_status),
    
    FOREIGN KEY (column_board_id) REFERENCES dotp_kanban_boards(board_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Tarefas no Kanban (posição das tarefas nas colunas)
CREATE TABLE IF NOT EXISTS dotp_kanban_tasks (
    kanban_task_id INT AUTO_INCREMENT PRIMARY KEY,
    kanban_task_column_id INT NOT NULL,
    kanban_task_task_id INT NOT NULL,
    kanban_task_order INT DEFAULT 0 COMMENT 'Ordem da tarefa na coluna',
    kanban_task_moved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    kanban_task_moved_by INT,
    
    INDEX idx_kt_column (kanban_task_column_id),
    INDEX idx_kt_task (kanban_task_task_id),
    INDEX idx_kt_order (kanban_task_order),
    INDEX idx_kt_moved_at (kanban_task_moved_at),
    
    UNIQUE KEY unique_task_column (kanban_task_column_id, kanban_task_task_id),
    
    FOREIGN KEY (kanban_task_column_id) REFERENCES dotp_kanban_columns(column_id) ON DELETE CASCADE,
    FOREIGN KEY (kanban_task_task_id) REFERENCES dotp_tasks(task_id) ON DELETE CASCADE,
    FOREIGN KEY (kanban_task_moved_by) REFERENCES dotp_users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Colunas padrão para novos boards
-- Estes são os valores padrão, a aplicação pode criar outras configurações

-- =====================================================
-- Colunas padrão sugeridas:
-- 1. Backlog (is_backlog=1)
-- 2. To Do
-- 3. In Progress (wip_limit recomendado)
-- 4. Review
-- 5. Done (is_done=1)
-- =====================================================

-- View para facilitar consultas de boards com estatísticas
CREATE OR REPLACE VIEW view_kanban_board_stats AS
SELECT 
    b.board_id,
    b.board_name,
    b.board_project,
    b.board_company,
    b.board_status,
    COUNT(DISTINCT c.column_id) as total_columns,
    COUNT(DISTINCT kt.kanban_task_id) as total_tasks,
    SUM(CASE WHEN col_done.column_is_done = 1 THEN 1 ELSE 0 END) as done_tasks
FROM dotp_kanban_boards b
LEFT JOIN dotp_kanban_columns c ON c.column_board_id = b.board_id AND c.column_status = 0
LEFT JOIN dotp_kanban_tasks kt ON kt.kanban_task_column_id = c.column_id
LEFT JOIN dotp_kanban_columns col_done ON col_done.column_id = kt.kanban_task_column_id AND col_done.column_is_done = 1
WHERE b.board_status = 0
GROUP BY b.board_id;

-- View para tarefas no kanban com informações completas
CREATE OR REPLACE VIEW view_kanban_task_details AS
SELECT 
    kt.kanban_task_id,
    kt.kanban_task_column_id,
    kt.kanban_task_task_id,
    kt.kanban_task_order,
    kt.kanban_task_moved_at,
    kt.kanban_task_moved_by,
    c.column_name,
    c.column_board_id,
    c.column_color,
    c.column_wip_limit,
    c.column_is_done,
    c.column_order as column_position,
    t.task_name,
    t.task_project,
    t.task_percent_complete,
    t.task_end_date,
    t.task_status,
    p.project_name,
    u.user_username as moved_by_name
FROM dotp_kanban_tasks kt
JOIN dotp_kanban_columns c ON c.column_id = kt.kanban_task_column_id
JOIN dotp_tasks t ON t.task_id = kt.kanban_task_task_id
LEFT JOIN dotp_projects p ON p.project_id = t.task_project
LEFT JOIN dotp_users u ON u.user_id = kt.kanban_task_moved_by;
