-- ============================================================================
-- Migration 1B: Redesign PPA — Constraints + FKs + Backfill
-- Data: 2026-02-13
-- Pré-requisito: 20260213_ppa_redesign_additive.sql executada com sucesso.
-- ============================================================================

-- ============================================================================
-- 1) FK dotp_metas.acao_id → dotp_acoes.id
-- ============================================================================
SET @acoes_exists := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_acoes'
      AND table_type = 'BASE TABLE'
);
SET @metas_exists := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_metas'
      AND table_type = 'BASE TABLE'
);
SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.referential_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'dotp_metas'
      AND constraint_name = 'fk_metas_acao'
);
SET @sql := IF(
    @metas_exists = 1 AND @acoes_exists = 1 AND @fk_exists = 0,
    'ALTER TABLE dotp_metas ADD CONSTRAINT fk_metas_acao FOREIGN KEY (acao_id) REFERENCES dotp_acoes(id) ON DELETE CASCADE ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================================
-- 2) FK dotp_metas.tenant_id → dotp_tenants.tenant_id
-- ============================================================================
SET @tenants_exists := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_tenants'
      AND table_type = 'BASE TABLE'
);
SET @tenant_col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_metas'
      AND column_name = 'tenant_id'
);
SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.referential_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'dotp_metas'
      AND constraint_name = 'fk_metas_tenant'
);
SET @sql := IF(
    @metas_exists = 1 AND @tenants_exists = 1 AND @tenant_col_exists = 1 AND @fk_exists = 0,
    'ALTER TABLE dotp_metas ADD CONSTRAINT fk_metas_tenant FOREIGN KEY (tenant_id) REFERENCES dotp_tenants(tenant_id) ON DELETE RESTRICT ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================================
-- 3) Backfill status_fluxo a partir de project_status existente
--    Mapeia: 0→Planejamento, 1→Execucao, 2→Concluido, 3→Cancelado, 5→Suspenso
-- ============================================================================
SET @fluxo_col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projects'
      AND column_name = 'status_fluxo'
);
SET @status_col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projects'
      AND column_name = 'project_status'
);
SET @sql := IF(
    @fluxo_col_exists = 1 AND @status_col_exists = 1,
    'UPDATE dotp_projects SET status_fluxo = CASE
        WHEN project_status = 0 THEN ''Planejamento''
        WHEN project_status = 1 THEN ''Planejamento''
        WHEN project_status = 2 THEN ''Licitacao''
        WHEN project_status = 3 THEN ''Execucao''
        WHEN project_status = 4 THEN ''Suspenso''
        WHEN project_status = 5 THEN ''Concluido''
        WHEN project_status = 6 THEN ''Cancelado''
        ELSE ''Planejamento''
    END
    WHERE status_fluxo = ''Planejamento'' OR status_fluxo IS NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================================
-- 4) Backfill status_saude = 'em_dia' para registros existentes (já feito via DEFAULT,
--    mas garante registros NULL)
-- ============================================================================
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projects'
      AND column_name = 'status_saude'
);
SET @sql := IF(
    @col_exists = 1,
    'UPDATE dotp_projects SET status_saude = ''em_dia'' WHERE status_saude IS NULL OR status_saude = ''''',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Programas
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_programas'
      AND column_name = 'status_saude'
);
SET @sql := IF(
    @col_exists = 1,
    'UPDATE dotp_programas SET status_saude = ''em_dia'' WHERE status_saude IS NULL OR status_saude = ''''',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Acoes
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_acoes'
      AND column_name = 'status_saude'
);
SET @sql := IF(
    @col_exists = 1,
    'UPDATE dotp_acoes SET status_saude = ''em_dia'' WHERE status_saude IS NULL OR status_saude = ''''',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================================
-- 5) Backfill peso = 1.00 para etapas e tarefas existentes
-- ============================================================================
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_etapas'
      AND column_name = 'peso'
);
SET @sql := IF(
    @col_exists = 1,
    'UPDATE dotp_etapas SET peso = 1.00 WHERE peso IS NULL OR peso <= 0',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_tasks'
      AND column_name = 'peso'
);
SET @sql := IF(
    @col_exists = 1,
    'UPDATE dotp_tasks SET peso = 1.00 WHERE peso IS NULL OR peso <= 0',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================================
-- Fim da migration de constraints
-- ============================================================================
SELECT 'Migration 1B (constraints + backfill) concluída com sucesso.' AS resultado;
