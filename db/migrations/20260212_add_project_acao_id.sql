-- Migration: adiciona vinculo de acao em dotp_projects.
-- Data: 2026-02-12
-- Objetivo: habilitar relacionamento Projeto -> Acao (PPA) via project_acao_id.

-- 1) Garante coluna project_acao_id.
SET @projects_exists := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projects'
      AND table_type = 'BASE TABLE'
);
SET @project_acao_col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projects'
      AND column_name = 'project_acao_id'
);
SET @sql := IF(
    @projects_exists = 1 AND @project_acao_col_exists = 0,
    'ALTER TABLE dotp_projects ADD COLUMN project_acao_id INT(11) UNSIGNED NULL DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) Garante indice para filtros e joins.
SET @project_acao_col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projects'
      AND column_name = 'project_acao_id'
);
SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projects'
      AND index_name = 'idx_project_acao_id'
);
SET @sql := IF(
    @projects_exists = 1 AND @project_acao_col_exists = 1 AND @idx_exists = 0,
    'ALTER TABLE dotp_projects ADD INDEX idx_project_acao_id (project_acao_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) Cria FK para dotp_acoes(id) somente quando tabela/coluna existir e tipos forem compativeis.
SET @acoes_exists := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_acoes'
      AND table_type = 'BASE TABLE'
);
SET @acao_id_col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_acoes'
      AND column_name = 'id'
);
SET @project_acao_type := (
    SELECT COALESCE(MAX(column_type), '')
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projects'
      AND column_name = 'project_acao_id'
);
SET @acao_id_type := (
    SELECT COALESCE(MAX(column_type), '')
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_acoes'
      AND column_name = 'id'
);
SET @sql := IF(
    @projects_exists = 1
    AND @project_acao_col_exists = 1
    AND @acoes_exists = 1
    AND @acao_id_col_exists = 1
    AND @project_acao_type <> ''
    AND @acao_id_type <> ''
    AND @project_acao_type <> @acao_id_type,
    CONCAT('ALTER TABLE dotp_projects MODIFY COLUMN project_acao_id ', @acao_id_type, ' NULL DEFAULT NULL'),
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    @projects_exists = 1 AND @project_acao_col_exists = 1 AND @acoes_exists = 1 AND @acao_id_col_exists = 1,
    'UPDATE dotp_projects p LEFT JOIN dotp_acoes a ON a.id = p.project_acao_id SET p.project_acao_id = NULL WHERE p.project_acao_id IS NOT NULL AND a.id IS NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.key_column_usage
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projects'
      AND column_name = 'project_acao_id'
      AND referenced_table_name = 'dotp_acoes'
      AND referenced_column_name = 'id'
);
SET @sql := IF(
    @projects_exists = 1
    AND @project_acao_col_exists = 1
    AND @acoes_exists = 1
    AND @acao_id_col_exists = 1
    AND @fk_exists = 0,
    'ALTER TABLE dotp_projects ADD CONSTRAINT fk_projects_acao FOREIGN KEY (project_acao_id) REFERENCES dotp_acoes(id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
