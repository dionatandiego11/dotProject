-- ============================================================================
-- Migration 1A: Redesign PPA — Schema Aditivo (sem quebrar nada)
-- Data: 2026-02-13
-- Objetivo: adicionar colunas e tabelas novas de forma segura e idempotente.
-- ============================================================================

-- ============================================================================
-- 1) CREATE TABLE dotp_metas (Metas/Indicadores para prestação de contas TCE)
-- ============================================================================
SET @metas_exists := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_metas'
      AND table_type = 'BASE TABLE'
);
SET @sql := IF(
    @metas_exists = 0,
    'CREATE TABLE dotp_metas (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        acao_id         INT NOT NULL,
        descricao       VARCHAR(300) NOT NULL,
        unidade_medida  VARCHAR(50)  NOT NULL DEFAULT ''unidades'',
        valor_previsto  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        valor_realizado DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        ano_referencia  SMALLINT      NOT NULL,
        observacao      TEXT          NULL,
        tenant_id       INT(11)       NULL DEFAULT NULL,
        created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_metas_acao_ano (acao_id, ano_referencia),
        INDEX idx_metas_tenant (tenant_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================================
-- 2) ADD status_saude VARCHAR(20) em dotp_programas
-- ============================================================================
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_programas'
      AND column_name = 'status_saude'
);
SET @tbl_exists := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_programas'
      AND table_type = 'BASE TABLE'
);
SET @sql := IF(
    @tbl_exists = 1 AND @col_exists = 0,
    'ALTER TABLE dotp_programas ADD COLUMN status_saude VARCHAR(20) NOT NULL DEFAULT ''em_dia''',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================================
-- 3) ADD status_saude VARCHAR(20) em dotp_acoes
-- ============================================================================
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_acoes'
      AND column_name = 'status_saude'
);
SET @tbl_exists := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_acoes'
      AND table_type = 'BASE TABLE'
);
SET @sql := IF(
    @tbl_exists = 1 AND @col_exists = 0,
    'ALTER TABLE dotp_acoes ADD COLUMN status_saude VARCHAR(20) NOT NULL DEFAULT ''em_dia''',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================================
-- 4) ADD status_saude VARCHAR(20) em dotp_projects
-- ============================================================================
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projects'
      AND column_name = 'status_saude'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE dotp_projects ADD COLUMN status_saude VARCHAR(20) NOT NULL DEFAULT ''em_dia''',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================================
-- 5) ADD peso DECIMAL(7,2) em dotp_etapas
-- ============================================================================
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_etapas'
      AND column_name = 'peso'
);
SET @tbl_exists := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_etapas'
      AND table_type = 'BASE TABLE'
);
SET @sql := IF(
    @tbl_exists = 1 AND @col_exists = 0,
    'ALTER TABLE dotp_etapas ADD COLUMN peso DECIMAL(7,2) NOT NULL DEFAULT 1.00',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================================
-- 6) ADD peso DECIMAL(7,2) em dotp_tasks
-- ============================================================================
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_tasks'
      AND column_name = 'peso'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE dotp_tasks ADD COLUMN peso DECIMAL(7,2) NOT NULL DEFAULT 1.00',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================================
-- 7) ADD tipo VARCHAR(30) em dotp_acoes
-- ============================================================================
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_acoes'
      AND column_name = 'tipo'
);
SET @sql := IF(
    @tbl_exists = 1 AND @col_exists = 0,
    'ALTER TABLE dotp_acoes ADD COLUMN tipo VARCHAR(30) NOT NULL DEFAULT ''Projeto''',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================================
-- 8) ADD valor_orcamentario DECIMAL(15,2) em dotp_acoes (se não existir)
-- ============================================================================
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_acoes'
      AND column_name = 'valor_orcamentario'
);
SET @sql := IF(
    @tbl_exists = 1 AND @col_exists = 0,
    'ALTER TABLE dotp_acoes ADD COLUMN valor_orcamentario DECIMAL(15,2) NULL DEFAULT 0.00',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================================
-- 9) ADD valor_executado DECIMAL(15,2) em dotp_acoes
-- ============================================================================
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_acoes'
      AND column_name = 'valor_executado'
);
SET @sql := IF(
    @tbl_exists = 1 AND @col_exists = 0,
    'ALTER TABLE dotp_acoes ADD COLUMN valor_executado DECIMAL(15,2) NOT NULL DEFAULT 0.00',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================================
-- 10) ADD valor_executado DECIMAL(15,2) em dotp_projects
-- ============================================================================
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projects'
      AND column_name = 'valor_executado'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE dotp_projects ADD COLUMN valor_executado DECIMAL(15,2) NOT NULL DEFAULT 0.00',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================================
-- 11) ADD status_fluxo VARCHAR(30) em dotp_projects
-- ============================================================================
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_projects'
      AND column_name = 'status_fluxo'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE dotp_projects ADD COLUMN status_fluxo VARCHAR(30) NOT NULL DEFAULT ''Planejamento''',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================================
-- 12) ADD data_inicio_prevista / data_fim_prevista / data_inicio_real / data_fim_real em dotp_acoes
-- ============================================================================
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_acoes'
      AND column_name = 'data_inicio_prevista'
);
SET @sql := IF(
    @tbl_exists = 1 AND @col_exists = 0,
    'ALTER TABLE dotp_acoes ADD COLUMN data_inicio_prevista DATE NULL, ADD COLUMN data_fim_prevista DATE NULL, ADD COLUMN data_inicio_real DATE NULL, ADD COLUMN data_fim_real DATE NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================================
-- Fim da migration aditiva
-- ============================================================================
SELECT 'Migration 1A (aditiva) concluída com sucesso.' AS resultado;
