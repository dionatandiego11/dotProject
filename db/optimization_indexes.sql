-- ===========================================
-- Otimização de Performance - Índices
-- ===========================================
-- Execute este arquivo para adicionar índices de performance
-- 
-- Comando: mysql -u root -p dotproject < db/optimization_indexes.sql
-- ===========================================

-- ===========================================
-- TABELA: dotp_projects
-- ===========================================

-- Índice para listagem de projetos ativos por usuário
ALTER TABLE dotp_projects 
ADD INDEX idx_projects_status_owner (project_status, project_owner);

-- Índice para busca por datas
ALTER TABLE dotp_projects 
ADD INDEX idx_projects_dates (project_start_date, project_end_date);

-- Índice para ordenação por nome
ALTER TABLE dotp_projects 
ADD INDEX idx_projects_name (project_name);

-- Full-text search para busca por nome/descrição
ALTER TABLE dotp_projects 
ADD FULLTEXT INDEX ft_projects_search (project_name, project_description);

-- ===========================================
-- TABELA: dotp_tasks
-- ===========================================

-- Índice composto para queries mais comuns
ALTER TABLE dotp_tasks 
ADD INDEX idx_tasks_project_status (task_project, task_status);

-- Índice para busca por responsável
ALTER TABLE dotp_tasks 
ADD INDEX idx_tasks_assignee (task_assigned_to, task_status);

-- Índice para tasks atrasadas
ALTER TABLE dotp_tasks 
ADD INDEX idx_tasks_overdue (task_end_date, task_status);

-- Índice para progresso
ALTER TABLE dotp_tasks 
ADD INDEX idx_tasks_progress (task_percent_complete);

-- Índice para ordenação por prioridade
ALTER TABLE dotp_tasks 
ADD INDEX idx_tasks_priority (task_priority, task_start_date);

-- ===========================================
-- TABELA: dotp_users
-- ===========================================

-- Índice único para busca rápida por username
ALTER TABLE dotp_users 
ADD UNIQUE INDEX idx_users_username (user_username);

-- Índice para busca por email
ALTER TABLE dotp_users 
ADD INDEX idx_users_email (user_email);

-- Índice para busca por empresa
ALTER TABLE dotp_users 
ADD INDEX idx_users_company (user_company);

-- ===========================================
-- TABELA: dotp_contacts
-- ===========================================

-- Índice para busca por nome
ALTER TABLE dotp_contacts 
ADD INDEX idx_contacts_name (contact_first_name, contact_last_name);

-- Índice para busca por empresa
ALTER TABLE dotp_contacts 
ADD INDEX idx_contacts_company (contact_company);

-- ===========================================
-- TABELA: dotp_companies
-- ===========================================

-- Índice para busca por nome
ALTER TABLE dotp_companies 
ADD INDEX idx_companies_name (company_name);

-- Full-text search
ALTER TABLE dotp_companies 
ADD FULLTEXT INDEX ft_companies_search (company_name, company_description);

-- ===========================================
-- TABELA: dotp_user_tasks
-- ===========================================

-- Índice para relacionamento usuário-tarefa
ALTER TABLE dotp_user_tasks 
ADD INDEX idx_user_tasks (user_id, task_id);

-- ===========================================
-- TABELA: dotp_files
-- ===========================================

-- Índice para busca por projeto
ALTER TABLE dotp_files 
ADD INDEX idx_files_project (file_project);

-- Índice para busca por tarefa
ALTER TABLE dotp_files 
ADD INDEX idx_files_task (file_task);

-- ===========================================
-- TABELA: dotp_forum_messages
-- ===========================================

-- Índice para busca por tópico
ALTER TABLE dotp_forum_messages 
ADD INDEX idx_forum_topic (message_forum, message_parent);

-- Índice para ordenação por data
ALTER TABLE dotp_forum_messages 
ADD INDEX idx_forum_date (message_date);

-- ===========================================
-- ANÁLISE E OTIMIZAÇÃO
-- ===========================================

-- Analisar tabelas para otimizar estatísticas
ANALYZE TABLE dotp_projects;
ANALYZE TABLE dotp_tasks;
ANALYZE TABLE dotp_users;
ANALYZE TABLE dotp_contacts;
ANALYZE TABLE dotp_companies;
ANALYZE TABLE dotp_files;

-- ===========================================
-- VERIFICAÇÃO
-- ===========================================

-- Listar todos os índices criados
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    COLUMN_NAME,
    CARDINALITY
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE()
AND INDEX_NAME != 'PRIMARY'
ORDER BY TABLE_NAME, INDEX_NAME;
