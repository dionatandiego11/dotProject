-- Migration: add tenant_id to secondary/junction tables not covered by initial tenant foundation.
-- Date: 2026-02-12
-- Depends on: 20260211_add_tenant_foundation.sql (dotp_tenants must exist first).
-- Strategy: shared schema + tenant_id, same pattern as foundation migration.
--
-- Tables covered:
--   contacts, companies, kanban_boards, kanban_columns,
--   task_dependencies, user_tasks, task_log, user_access_log,
--   project_status_history

-- Helper: reusable block per table
-- 1) ADD COLUMN if missing
-- 2) BACKFILL existing rows
-- 3) MODIFY NOT NULL DEFAULT 1
-- 4) INDEX
-- 5) FK

-- ═══════════════════════════════════════════
-- 1) dotp_contacts
-- ═══════════════════════════════════════════
SET @tbl := 'dotp_contacts';
SET @tbl_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = @tbl);
SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = @tbl AND column_name = 'tenant_id');
SET @sql := IF(@tbl_exists = 1 AND @col_exists = 0, CONCAT('ALTER TABLE ', @tbl, ' ADD COLUMN tenant_id INT(11) NULL DEFAULT NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@tbl_exists = 1, CONCAT('UPDATE ', @tbl, ' SET tenant_id = 1 WHERE tenant_id IS NULL OR tenant_id = 0'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = @tbl AND column_name = 'tenant_id');
SET @sql := IF(@tbl_exists = 1 AND @col_exists = 1, CONCAT('ALTER TABLE ', @tbl, ' MODIFY COLUMN tenant_id INT(11) NOT NULL DEFAULT 1'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @idx_exists := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = @tbl AND index_name = 'idx_contacts_tenant_id');
SET @sql := IF(@tbl_exists = 1 AND @idx_exists = 0, CONCAT('ALTER TABLE ', @tbl, ' ADD INDEX idx_contacts_tenant_id (tenant_id)'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @fk_exists := (SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE() AND table_name = @tbl AND constraint_name = 'fk_contacts_tenant');
SET @sql := IF(@tbl_exists = 1 AND @fk_exists = 0, CONCAT('ALTER TABLE ', @tbl, ' ADD CONSTRAINT fk_contacts_tenant FOREIGN KEY (tenant_id) REFERENCES dotp_tenants(tenant_id) ON DELETE RESTRICT ON UPDATE CASCADE'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ═══════════════════════════════════════════
-- 2) dotp_companies
-- ═══════════════════════════════════════════
SET @tbl := 'dotp_companies';
SET @tbl_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = @tbl);
SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = @tbl AND column_name = 'tenant_id');
SET @sql := IF(@tbl_exists = 1 AND @col_exists = 0, CONCAT('ALTER TABLE ', @tbl, ' ADD COLUMN tenant_id INT(11) NULL DEFAULT NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@tbl_exists = 1, CONCAT('UPDATE ', @tbl, ' SET tenant_id = 1 WHERE tenant_id IS NULL OR tenant_id = 0'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = @tbl AND column_name = 'tenant_id');
SET @sql := IF(@tbl_exists = 1 AND @col_exists = 1, CONCAT('ALTER TABLE ', @tbl, ' MODIFY COLUMN tenant_id INT(11) NOT NULL DEFAULT 1'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @idx_exists := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = @tbl AND index_name = 'idx_companies_tenant_id');
SET @sql := IF(@tbl_exists = 1 AND @idx_exists = 0, CONCAT('ALTER TABLE ', @tbl, ' ADD INDEX idx_companies_tenant_id (tenant_id)'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @fk_exists := (SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE() AND table_name = @tbl AND constraint_name = 'fk_companies_tenant');
SET @sql := IF(@tbl_exists = 1 AND @fk_exists = 0, CONCAT('ALTER TABLE ', @tbl, ' ADD CONSTRAINT fk_companies_tenant FOREIGN KEY (tenant_id) REFERENCES dotp_tenants(tenant_id) ON DELETE RESTRICT ON UPDATE CASCADE'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ═══════════════════════════════════════════
-- 3) dotp_kanban_boards
-- ═══════════════════════════════════════════
SET @tbl := 'dotp_kanban_boards';
SET @tbl_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = @tbl);
SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = @tbl AND column_name = 'tenant_id');
SET @sql := IF(@tbl_exists = 1 AND @col_exists = 0, CONCAT('ALTER TABLE ', @tbl, ' ADD COLUMN tenant_id INT(11) NULL DEFAULT NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@tbl_exists = 1, CONCAT('UPDATE ', @tbl, ' SET tenant_id = 1 WHERE tenant_id IS NULL OR tenant_id = 0'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = @tbl AND column_name = 'tenant_id');
SET @sql := IF(@tbl_exists = 1 AND @col_exists = 1, CONCAT('ALTER TABLE ', @tbl, ' MODIFY COLUMN tenant_id INT(11) NOT NULL DEFAULT 1'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @idx_exists := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = @tbl AND index_name = 'idx_kanban_boards_tenant_id');
SET @sql := IF(@tbl_exists = 1 AND @idx_exists = 0, CONCAT('ALTER TABLE ', @tbl, ' ADD INDEX idx_kanban_boards_tenant_id (tenant_id)'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @fk_exists := (SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE() AND table_name = @tbl AND constraint_name = 'fk_kanban_boards_tenant');
SET @sql := IF(@tbl_exists = 1 AND @fk_exists = 0, CONCAT('ALTER TABLE ', @tbl, ' ADD CONSTRAINT fk_kanban_boards_tenant FOREIGN KEY (tenant_id) REFERENCES dotp_tenants(tenant_id) ON DELETE RESTRICT ON UPDATE CASCADE'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ═══════════════════════════════════════════
-- 4) dotp_kanban_columns
-- ═══════════════════════════════════════════
SET @tbl := 'dotp_kanban_columns';
SET @tbl_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = @tbl);
SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = @tbl AND column_name = 'tenant_id');
SET @sql := IF(@tbl_exists = 1 AND @col_exists = 0, CONCAT('ALTER TABLE ', @tbl, ' ADD COLUMN tenant_id INT(11) NULL DEFAULT NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@tbl_exists = 1, CONCAT('UPDATE ', @tbl, ' SET tenant_id = 1 WHERE tenant_id IS NULL OR tenant_id = 0'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = @tbl AND column_name = 'tenant_id');
SET @sql := IF(@tbl_exists = 1 AND @col_exists = 1, CONCAT('ALTER TABLE ', @tbl, ' MODIFY COLUMN tenant_id INT(11) NOT NULL DEFAULT 1'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @idx_exists := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = @tbl AND index_name = 'idx_kanban_columns_tenant_id');
SET @sql := IF(@tbl_exists = 1 AND @idx_exists = 0, CONCAT('ALTER TABLE ', @tbl, ' ADD INDEX idx_kanban_columns_tenant_id (tenant_id)'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @fk_exists := (SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE() AND table_name = @tbl AND constraint_name = 'fk_kanban_columns_tenant');
SET @sql := IF(@tbl_exists = 1 AND @fk_exists = 0, CONCAT('ALTER TABLE ', @tbl, ' ADD CONSTRAINT fk_kanban_columns_tenant FOREIGN KEY (tenant_id) REFERENCES dotp_tenants(tenant_id) ON DELETE RESTRICT ON UPDATE CASCADE'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ═══════════════════════════════════════════
-- 5) dotp_task_dependencies
-- ═══════════════════════════════════════════
SET @tbl := 'dotp_task_dependencies';
SET @tbl_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = @tbl);
SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = @tbl AND column_name = 'tenant_id');
SET @sql := IF(@tbl_exists = 1 AND @col_exists = 0, CONCAT('ALTER TABLE ', @tbl, ' ADD COLUMN tenant_id INT(11) NULL DEFAULT NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@tbl_exists = 1, CONCAT('UPDATE ', @tbl, ' SET tenant_id = 1 WHERE tenant_id IS NULL OR tenant_id = 0'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = @tbl AND column_name = 'tenant_id');
SET @sql := IF(@tbl_exists = 1 AND @col_exists = 1, CONCAT('ALTER TABLE ', @tbl, ' MODIFY COLUMN tenant_id INT(11) NOT NULL DEFAULT 1'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @idx_exists := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = @tbl AND index_name = 'idx_task_deps_tenant_id');
SET @sql := IF(@tbl_exists = 1 AND @idx_exists = 0, CONCAT('ALTER TABLE ', @tbl, ' ADD INDEX idx_task_deps_tenant_id (tenant_id)'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @fk_exists := (SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE() AND table_name = @tbl AND constraint_name = 'fk_task_deps_tenant');
SET @sql := IF(@tbl_exists = 1 AND @fk_exists = 0, CONCAT('ALTER TABLE ', @tbl, ' ADD CONSTRAINT fk_task_deps_tenant FOREIGN KEY (tenant_id) REFERENCES dotp_tenants(tenant_id) ON DELETE RESTRICT ON UPDATE CASCADE'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ═══════════════════════════════════════════
-- 6) dotp_user_tasks
-- ═══════════════════════════════════════════
SET @tbl := 'dotp_user_tasks';
SET @tbl_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = @tbl);
SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = @tbl AND column_name = 'tenant_id');
SET @sql := IF(@tbl_exists = 1 AND @col_exists = 0, CONCAT('ALTER TABLE ', @tbl, ' ADD COLUMN tenant_id INT(11) NULL DEFAULT NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@tbl_exists = 1, CONCAT('UPDATE ', @tbl, ' SET tenant_id = 1 WHERE tenant_id IS NULL OR tenant_id = 0'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = @tbl AND column_name = 'tenant_id');
SET @sql := IF(@tbl_exists = 1 AND @col_exists = 1, CONCAT('ALTER TABLE ', @tbl, ' MODIFY COLUMN tenant_id INT(11) NOT NULL DEFAULT 1'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @idx_exists := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = @tbl AND index_name = 'idx_user_tasks_tenant_id');
SET @sql := IF(@tbl_exists = 1 AND @idx_exists = 0, CONCAT('ALTER TABLE ', @tbl, ' ADD INDEX idx_user_tasks_tenant_id (tenant_id)'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @fk_exists := (SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE() AND table_name = @tbl AND constraint_name = 'fk_user_tasks_tenant');
SET @sql := IF(@tbl_exists = 1 AND @fk_exists = 0, CONCAT('ALTER TABLE ', @tbl, ' ADD CONSTRAINT fk_user_tasks_tenant FOREIGN KEY (tenant_id) REFERENCES dotp_tenants(tenant_id) ON DELETE RESTRICT ON UPDATE CASCADE'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ═══════════════════════════════════════════
-- 7) dotp_task_log
-- ═══════════════════════════════════════════
SET @tbl := 'dotp_task_log';
SET @tbl_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = @tbl);
SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = @tbl AND column_name = 'tenant_id');
SET @sql := IF(@tbl_exists = 1 AND @col_exists = 0, CONCAT('ALTER TABLE ', @tbl, ' ADD COLUMN tenant_id INT(11) NULL DEFAULT NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@tbl_exists = 1, CONCAT('UPDATE ', @tbl, ' SET tenant_id = 1 WHERE tenant_id IS NULL OR tenant_id = 0'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = @tbl AND column_name = 'tenant_id');
SET @sql := IF(@tbl_exists = 1 AND @col_exists = 1, CONCAT('ALTER TABLE ', @tbl, ' MODIFY COLUMN tenant_id INT(11) NOT NULL DEFAULT 1'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @idx_exists := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = @tbl AND index_name = 'idx_task_log_tenant_id');
SET @sql := IF(@tbl_exists = 1 AND @idx_exists = 0, CONCAT('ALTER TABLE ', @tbl, ' ADD INDEX idx_task_log_tenant_id (tenant_id)'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @fk_exists := (SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE() AND table_name = @tbl AND constraint_name = 'fk_task_log_tenant');
SET @sql := IF(@tbl_exists = 1 AND @fk_exists = 0, CONCAT('ALTER TABLE ', @tbl, ' ADD CONSTRAINT fk_task_log_tenant FOREIGN KEY (tenant_id) REFERENCES dotp_tenants(tenant_id) ON DELETE RESTRICT ON UPDATE CASCADE'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ═══════════════════════════════════════════
-- 8) dotp_user_access_log
-- ═══════════════════════════════════════════
SET @tbl := 'dotp_user_access_log';
SET @tbl_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = @tbl);
SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = @tbl AND column_name = 'tenant_id');
SET @sql := IF(@tbl_exists = 1 AND @col_exists = 0, CONCAT('ALTER TABLE ', @tbl, ' ADD COLUMN tenant_id INT(11) NULL DEFAULT NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@tbl_exists = 1, CONCAT('UPDATE ', @tbl, ' SET tenant_id = 1 WHERE tenant_id IS NULL OR tenant_id = 0'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = @tbl AND column_name = 'tenant_id');
SET @sql := IF(@tbl_exists = 1 AND @col_exists = 1, CONCAT('ALTER TABLE ', @tbl, ' MODIFY COLUMN tenant_id INT(11) NOT NULL DEFAULT 1'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @idx_exists := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = @tbl AND index_name = 'idx_user_access_log_tenant_id');
SET @sql := IF(@tbl_exists = 1 AND @idx_exists = 0, CONCAT('ALTER TABLE ', @tbl, ' ADD INDEX idx_user_access_log_tenant_id (tenant_id)'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @fk_exists := (SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE() AND table_name = @tbl AND constraint_name = 'fk_user_access_log_tenant');
SET @sql := IF(@tbl_exists = 1 AND @fk_exists = 0, CONCAT('ALTER TABLE ', @tbl, ' ADD CONSTRAINT fk_user_access_log_tenant FOREIGN KEY (tenant_id) REFERENCES dotp_tenants(tenant_id) ON DELETE RESTRICT ON UPDATE CASCADE'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ═══════════════════════════════════════════
-- 9) dotp_project_status_history
-- ═══════════════════════════════════════════
SET @tbl := 'dotp_project_status_history';
SET @tbl_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = @tbl);
SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = @tbl AND column_name = 'tenant_id');
SET @sql := IF(@tbl_exists = 1 AND @col_exists = 0, CONCAT('ALTER TABLE ', @tbl, ' ADD COLUMN tenant_id INT(11) NULL DEFAULT NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@tbl_exists = 1, CONCAT('UPDATE ', @tbl, ' SET tenant_id = 1 WHERE tenant_id IS NULL OR tenant_id = 0'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = @tbl AND column_name = 'tenant_id');
SET @sql := IF(@tbl_exists = 1 AND @col_exists = 1, CONCAT('ALTER TABLE ', @tbl, ' MODIFY COLUMN tenant_id INT(11) NOT NULL DEFAULT 1'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @idx_exists := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = @tbl AND index_name = 'idx_proj_status_hist_tenant_id');
SET @sql := IF(@tbl_exists = 1 AND @idx_exists = 0, CONCAT('ALTER TABLE ', @tbl, ' ADD INDEX idx_proj_status_hist_tenant_id (tenant_id)'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @fk_exists := (SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE() AND table_name = @tbl AND constraint_name = 'fk_proj_status_hist_tenant');
SET @sql := IF(@tbl_exists = 1 AND @fk_exists = 0, CONCAT('ALTER TABLE ', @tbl, ' ADD CONSTRAINT fk_proj_status_hist_tenant FOREIGN KEY (tenant_id) REFERENCES dotp_tenants(tenant_id) ON DELETE RESTRICT ON UPDATE CASCADE'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
