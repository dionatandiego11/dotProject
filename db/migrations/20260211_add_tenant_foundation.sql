-- Migration: create tenant foundation and add tenant_id to core shared tables.
-- Date: 2026-02-11
-- Strategy: shared schema + tenant_id (Sprint 6).

-- 1) Tenant registry table.
CREATE TABLE IF NOT EXISTS dotp_tenants (
    tenant_id INT(11) NOT NULL AUTO_INCREMENT,
    tenant_slug VARCHAR(100) NOT NULL,
    tenant_name VARCHAR(255) NOT NULL,
    tenant_domain VARCHAR(255) DEFAULT NULL,
    tenant_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (tenant_id),
    UNIQUE KEY uq_tenants_slug (tenant_slug),
    UNIQUE KEY uq_tenants_domain (tenant_domain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO dotp_tenants (tenant_id, tenant_slug, tenant_name, tenant_domain, tenant_active)
VALUES (1, 'default', 'Tenant Default', 'localhost', 1)
ON DUPLICATE KEY UPDATE
    tenant_slug = VALUES(tenant_slug),
    tenant_name = VALUES(tenant_name),
    tenant_domain = IFNULL(dotp_tenants.tenant_domain, VALUES(tenant_domain)),
    tenant_active = VALUES(tenant_active);

-- Helper pattern repeated per table:
-- - add column tenant_id if missing
-- - backfill existing rows
-- - enforce NOT NULL default 1
-- - create index
-- - create FK to dotp_tenants(tenant_id)

-- 2) dotp_users
SET @tbl_exists := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_users'
);
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_users'
      AND column_name = 'tenant_id'
);
SET @sql := IF(@tbl_exists = 1 AND @col_exists = 0, 'ALTER TABLE dotp_users ADD COLUMN tenant_id INT(11) NULL DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@tbl_exists = 1, 'UPDATE dotp_users SET tenant_id = 1 WHERE tenant_id IS NULL OR tenant_id = 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_users'
      AND column_name = 'tenant_id'
);
SET @sql := IF(@tbl_exists = 1 AND @col_exists = 1, 'ALTER TABLE dotp_users MODIFY COLUMN tenant_id INT(11) NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_users'
      AND index_name = 'idx_users_tenant_id'
);
SET @sql := IF(@tbl_exists = 1 AND @idx_exists = 0, 'ALTER TABLE dotp_users ADD INDEX idx_users_tenant_id (tenant_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.referential_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'dotp_users'
      AND constraint_name = 'fk_users_tenant'
);
SET @sql := IF(@tbl_exists = 1 AND @fk_exists = 0, 'ALTER TABLE dotp_users ADD CONSTRAINT fk_users_tenant FOREIGN KEY (tenant_id) REFERENCES dotp_tenants(tenant_id) ON DELETE RESTRICT ON UPDATE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) dotp_projects
SET @tbl_exists := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projects'
);
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projects'
      AND column_name = 'tenant_id'
);
SET @sql := IF(@tbl_exists = 1 AND @col_exists = 0, 'ALTER TABLE dotp_projects ADD COLUMN tenant_id INT(11) NULL DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@tbl_exists = 1, 'UPDATE dotp_projects SET tenant_id = 1 WHERE tenant_id IS NULL OR tenant_id = 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projects'
      AND column_name = 'tenant_id'
);
SET @sql := IF(@tbl_exists = 1 AND @col_exists = 1, 'ALTER TABLE dotp_projects MODIFY COLUMN tenant_id INT(11) NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projects'
      AND index_name = 'idx_projects_tenant_id'
);
SET @sql := IF(@tbl_exists = 1 AND @idx_exists = 0, 'ALTER TABLE dotp_projects ADD INDEX idx_projects_tenant_id (tenant_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.referential_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'dotp_projects'
      AND constraint_name = 'fk_projects_tenant'
);
SET @sql := IF(@tbl_exists = 1 AND @fk_exists = 0, 'ALTER TABLE dotp_projects ADD CONSTRAINT fk_projects_tenant FOREIGN KEY (tenant_id) REFERENCES dotp_tenants(tenant_id) ON DELETE RESTRICT ON UPDATE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4) dotp_tasks
SET @tbl_exists := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_tasks'
);
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_tasks'
      AND column_name = 'tenant_id'
);
SET @sql := IF(@tbl_exists = 1 AND @col_exists = 0, 'ALTER TABLE dotp_tasks ADD COLUMN tenant_id INT(11) NULL DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@tbl_exists = 1, 'UPDATE dotp_tasks t LEFT JOIN dotp_projects p ON p.project_id = t.task_project SET t.tenant_id = COALESCE(p.tenant_id, 1) WHERE t.tenant_id IS NULL OR t.tenant_id = 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_tasks'
      AND column_name = 'tenant_id'
);
SET @sql := IF(@tbl_exists = 1 AND @col_exists = 1, 'ALTER TABLE dotp_tasks MODIFY COLUMN tenant_id INT(11) NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_tasks'
      AND index_name = 'idx_tasks_tenant_id'
);
SET @sql := IF(@tbl_exists = 1 AND @idx_exists = 0, 'ALTER TABLE dotp_tasks ADD INDEX idx_tasks_tenant_id (tenant_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.referential_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'dotp_tasks'
      AND constraint_name = 'fk_tasks_tenant'
);
SET @sql := IF(@tbl_exists = 1 AND @fk_exists = 0, 'ALTER TABLE dotp_tasks ADD CONSTRAINT fk_tasks_tenant FOREIGN KEY (tenant_id) REFERENCES dotp_tenants(tenant_id) ON DELETE RESTRICT ON UPDATE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5) dotp_unidades_organizacionais
SET @tbl_exists := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_unidades_organizacionais'
);
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_unidades_organizacionais'
      AND column_name = 'tenant_id'
);
SET @sql := IF(@tbl_exists = 1 AND @col_exists = 0, 'ALTER TABLE dotp_unidades_organizacionais ADD COLUMN tenant_id INT(11) NULL DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@tbl_exists = 1, 'UPDATE dotp_unidades_organizacionais SET tenant_id = 1 WHERE tenant_id IS NULL OR tenant_id = 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_unidades_organizacionais'
      AND column_name = 'tenant_id'
);
SET @sql := IF(@tbl_exists = 1 AND @col_exists = 1, 'ALTER TABLE dotp_unidades_organizacionais MODIFY COLUMN tenant_id INT(11) NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_unidades_organizacionais'
      AND index_name = 'idx_unidades_tenant_id'
);
SET @sql := IF(@tbl_exists = 1 AND @idx_exists = 0, 'ALTER TABLE dotp_unidades_organizacionais ADD INDEX idx_unidades_tenant_id (tenant_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.referential_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'dotp_unidades_organizacionais'
      AND constraint_name = 'fk_unidades_tenant'
);
SET @sql := IF(@tbl_exists = 1 AND @fk_exists = 0, 'ALTER TABLE dotp_unidades_organizacionais ADD CONSTRAINT fk_unidades_tenant FOREIGN KEY (tenant_id) REFERENCES dotp_tenants(tenant_id) ON DELETE RESTRICT ON UPDATE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 6) dotp_projetos_prefeitura
SET @tbl_exists := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projetos_prefeitura'
);
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projetos_prefeitura'
      AND column_name = 'tenant_id'
);
SET @sql := IF(@tbl_exists = 1 AND @col_exists = 0, 'ALTER TABLE dotp_projetos_prefeitura ADD COLUMN tenant_id INT(11) NULL DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@tbl_exists = 1, 'UPDATE dotp_projetos_prefeitura SET tenant_id = 1 WHERE tenant_id IS NULL OR tenant_id = 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projetos_prefeitura'
      AND column_name = 'tenant_id'
);
SET @sql := IF(@tbl_exists = 1 AND @col_exists = 1, 'ALTER TABLE dotp_projetos_prefeitura MODIFY COLUMN tenant_id INT(11) NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projetos_prefeitura'
      AND index_name = 'idx_projetos_pref_tenant_id'
);
SET @sql := IF(@tbl_exists = 1 AND @idx_exists = 0, 'ALTER TABLE dotp_projetos_prefeitura ADD INDEX idx_projetos_pref_tenant_id (tenant_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.referential_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'dotp_projetos_prefeitura'
      AND constraint_name = 'fk_projetos_prefeitura_tenant'
);
SET @sql := IF(@tbl_exists = 1 AND @fk_exists = 0, 'ALTER TABLE dotp_projetos_prefeitura ADD CONSTRAINT fk_projetos_prefeitura_tenant FOREIGN KEY (tenant_id) REFERENCES dotp_tenants(tenant_id) ON DELETE RESTRICT ON UPDATE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 7) dotp_programas
SET @tbl_exists := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_programas'
);
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_programas'
      AND column_name = 'tenant_id'
);
SET @sql := IF(@tbl_exists = 1 AND @col_exists = 0, 'ALTER TABLE dotp_programas ADD COLUMN tenant_id INT(11) NULL DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@tbl_exists = 1, 'UPDATE dotp_programas SET tenant_id = 1 WHERE tenant_id IS NULL OR tenant_id = 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_programas'
      AND column_name = 'tenant_id'
);
SET @sql := IF(@tbl_exists = 1 AND @col_exists = 1, 'ALTER TABLE dotp_programas MODIFY COLUMN tenant_id INT(11) NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_programas'
      AND index_name = 'idx_programas_tenant_id'
);
SET @sql := IF(@tbl_exists = 1 AND @idx_exists = 0, 'ALTER TABLE dotp_programas ADD INDEX idx_programas_tenant_id (tenant_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.referential_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'dotp_programas'
      AND constraint_name = 'fk_programas_tenant'
);
SET @sql := IF(@tbl_exists = 1 AND @fk_exists = 0, 'ALTER TABLE dotp_programas ADD CONSTRAINT fk_programas_tenant FOREIGN KEY (tenant_id) REFERENCES dotp_tenants(tenant_id) ON DELETE RESTRICT ON UPDATE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
