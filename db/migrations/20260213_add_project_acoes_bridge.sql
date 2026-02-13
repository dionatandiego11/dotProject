-- Migration: cria tabela ponte Projeto <-> Acao (N:N) e faz backfill do legado.
-- Data: 2026-02-13
-- Objetivo: permitir que um projeto tenha multiplas acoes.

-- 1) Criar tabela ponte com tipos alinhados ao schema atual.
SET @projects_exists := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projects'
      AND table_type = 'BASE TABLE'
);
SET @acoes_exists := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_acoes'
      AND table_type = 'BASE TABLE'
);
SET @link_exists := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projeto_acoes'
      AND table_type = 'BASE TABLE'
);
SET @project_id_type := (
    SELECT COALESCE(MAX(column_type), 'int(11)')
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projects'
      AND column_name = 'project_id'
);
SET @acao_id_type := (
    SELECT COALESCE(MAX(column_type), 'int(11)')
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_acoes'
      AND column_name = 'id'
);
SET @sql := IF(
    @link_exists = 0,
    CONCAT(
        'CREATE TABLE dotp_projeto_acoes (',
        'project_id ', @project_id_type, ' NOT NULL, ',
        'acao_id ', @acao_id_type, ' NOT NULL, ',
        'principal TINYINT(1) NOT NULL DEFAULT 0, ',
        'peso_contribuicao DECIMAL(7,2) NULL, ',
        'tenant_id INT(11) NULL DEFAULT NULL, ',
        'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ',
        'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, ',
        'PRIMARY KEY (project_id, acao_id), ',
        'KEY idx_projeto_acoes_acao_id (acao_id), ',
        'KEY idx_projeto_acoes_principal (project_id, principal), ',
        'KEY idx_projeto_acoes_tenant_id (tenant_id)',
        ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    ),
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) Garantir colunas auxiliares para ambientes que ja possuem a tabela.
SET @principal_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projeto_acoes'
      AND column_name = 'principal'
);
SET @sql := IF(
    @link_exists = 1 AND @principal_exists = 0,
    'ALTER TABLE dotp_projeto_acoes ADD COLUMN principal TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @peso_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projeto_acoes'
      AND column_name = 'peso_contribuicao'
);
SET @sql := IF(
    @link_exists = 1 AND @peso_exists = 0,
    'ALTER TABLE dotp_projeto_acoes ADD COLUMN peso_contribuicao DECIMAL(7,2) NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tenant_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projeto_acoes'
      AND column_name = 'tenant_id'
);
SET @sql := IF(
    @link_exists = 1 AND @tenant_exists = 0,
    'ALTER TABLE dotp_projeto_acoes ADD COLUMN tenant_id INT(11) NULL DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @created_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projeto_acoes'
      AND column_name = 'created_at'
);
SET @sql := IF(
    @link_exists = 1 AND @created_exists = 0,
    'ALTER TABLE dotp_projeto_acoes ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @updated_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projeto_acoes'
      AND column_name = 'updated_at'
);
SET @sql := IF(
    @link_exists = 1 AND @updated_exists = 0,
    'ALTER TABLE dotp_projeto_acoes ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_acao_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projeto_acoes'
      AND index_name = 'idx_projeto_acoes_acao_id'
);
SET @sql := IF(
    @idx_acao_exists = 0,
    'ALTER TABLE dotp_projeto_acoes ADD INDEX idx_projeto_acoes_acao_id (acao_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_principal_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projeto_acoes'
      AND index_name = 'idx_projeto_acoes_principal'
);
SET @sql := IF(
    @idx_principal_exists = 0,
    'ALTER TABLE dotp_projeto_acoes ADD INDEX idx_projeto_acoes_principal (project_id, principal)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_tenant_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projeto_acoes'
      AND index_name = 'idx_projeto_acoes_tenant_id'
);
SET @sql := IF(
    @idx_tenant_exists = 0,
    'ALTER TABLE dotp_projeto_acoes ADD INDEX idx_projeto_acoes_tenant_id (tenant_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) Alinhar tipos da tabela ponte para nao falhar FK.
SET @link_project_type := (
    SELECT COALESCE(MAX(column_type), '')
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projeto_acoes'
      AND column_name = 'project_id'
);
SET @sql := IF(
    @projects_exists = 1
    AND @link_project_type <> ''
    AND @project_id_type <> ''
    AND @link_project_type <> @project_id_type,
    CONCAT('ALTER TABLE dotp_projeto_acoes MODIFY COLUMN project_id ', @project_id_type, ' NOT NULL'),
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @link_acao_type := (
    SELECT COALESCE(MAX(column_type), '')
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projeto_acoes'
      AND column_name = 'acao_id'
);
SET @sql := IF(
    @acoes_exists = 1
    AND @link_acao_type <> ''
    AND @acao_id_type <> ''
    AND @link_acao_type <> @acao_id_type,
    CONCAT('ALTER TABLE dotp_projeto_acoes MODIFY COLUMN acao_id ', @acao_id_type, ' NOT NULL'),
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4) Backfill: copia legado dotp_projects.project_acao_id para a tabela ponte.
SET @legacy_col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projects'
      AND column_name = 'project_acao_id'
);
SET @projects_tenant_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projects'
      AND column_name = 'tenant_id'
);
SET @link_tenant_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projeto_acoes'
      AND column_name = 'tenant_id'
);
SET @insert_tenant_col := IF(@link_tenant_exists = 1, ', tenant_id', '');
SET @insert_tenant_value := IF(@link_tenant_exists = 1, IF(@projects_tenant_exists = 1, ', p.tenant_id', ', NULL'), '');
SET @sql := IF(
    @projects_exists = 1 AND @acoes_exists = 1 AND @legacy_col_exists = 1,
    CONCAT(
        'INSERT INTO dotp_projeto_acoes (project_id, acao_id, principal, peso_contribuicao, created_at, updated_at', @insert_tenant_col, ') ',
        'SELECT p.project_id, p.project_acao_id, 1, 100.00, NOW(), NOW()', @insert_tenant_value, ' ',
        'FROM dotp_projects p ',
        'JOIN dotp_acoes a ON a.id = p.project_acao_id ',
        'LEFT JOIN dotp_projeto_acoes pa ON pa.project_id = p.project_id AND pa.acao_id = p.project_acao_id ',
        'WHERE p.project_acao_id IS NOT NULL AND p.project_acao_id > 0 AND pa.project_id IS NULL'
    ),
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5) FKs idempotentes.
SET @fk_project_exists := (
    SELECT COUNT(*)
    FROM information_schema.referential_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'dotp_projeto_acoes'
      AND constraint_name = 'fk_projeto_acoes_project'
);
SET @sql := IF(
    @projects_exists = 1 AND @fk_project_exists = 0,
    'ALTER TABLE dotp_projeto_acoes ADD CONSTRAINT fk_projeto_acoes_project FOREIGN KEY (project_id) REFERENCES dotp_projects(project_id) ON DELETE CASCADE ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_acao_exists := (
    SELECT COUNT(*)
    FROM information_schema.referential_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'dotp_projeto_acoes'
      AND constraint_name = 'fk_projeto_acoes_acao'
);
SET @sql := IF(
    @acoes_exists = 1 AND @fk_acao_exists = 0,
    'ALTER TABLE dotp_projeto_acoes ADD CONSTRAINT fk_projeto_acoes_acao FOREIGN KEY (acao_id) REFERENCES dotp_acoes(id) ON DELETE CASCADE ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tenants_exists := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_tenants'
      AND table_type = 'BASE TABLE'
);
SET @fk_tenant_exists := (
    SELECT COUNT(*)
    FROM information_schema.referential_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'dotp_projeto_acoes'
      AND constraint_name = 'fk_projeto_acoes_tenant'
);
SET @sql := IF(
    @tenants_exists = 1 AND @link_tenant_exists = 1 AND @fk_tenant_exists = 0,
    'ALTER TABLE dotp_projeto_acoes ADD CONSTRAINT fk_projeto_acoes_tenant FOREIGN KEY (tenant_id) REFERENCES dotp_tenants(tenant_id) ON DELETE RESTRICT ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
