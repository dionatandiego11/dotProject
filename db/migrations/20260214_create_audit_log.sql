-- Migration: cria trilha de auditoria para campos financeiros (TCE)
-- Data: 2026-02-14
-- Objetivo: registrar alteracoes de valor_executado por entidade com origem e usuario.

SET @audit_table_exists := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_audit_log'
      AND table_type = 'BASE TABLE'
);

SET @sql := IF(
    @audit_table_exists = 0,
    'CREATE TABLE dotp_audit_log (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        tenant_id INT(11) NULL DEFAULT NULL,
        entidade_tipo VARCHAR(40) NOT NULL,
        entidade_id INT(11) UNSIGNED NOT NULL,
        campo VARCHAR(80) NOT NULL,
        valor_anterior DECIMAL(15,2) NULL DEFAULT NULL,
        valor_novo DECIMAL(15,2) NULL DEFAULT NULL,
        usuario_id INT(11) NULL DEFAULT NULL,
        origem VARCHAR(64) NOT NULL DEFAULT ''system'',
        contexto_json LONGTEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_audit_entidade_data (entidade_tipo, entidade_id, created_at),
        INDEX idx_audit_campo_data (campo, created_at),
        INDEX idx_audit_usuario_data (usuario_id, created_at),
        INDEX idx_audit_tenant_data (tenant_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_audit_entidade_data_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_audit_log'
      AND index_name = 'idx_audit_entidade_data'
);
SET @sql := IF(
    @idx_audit_entidade_data_exists = 0,
    'ALTER TABLE dotp_audit_log ADD INDEX idx_audit_entidade_data (entidade_tipo, entidade_id, created_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_audit_campo_data_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_audit_log'
      AND index_name = 'idx_audit_campo_data'
);
SET @sql := IF(
    @idx_audit_campo_data_exists = 0,
    'ALTER TABLE dotp_audit_log ADD INDEX idx_audit_campo_data (campo, created_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_audit_usuario_data_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_audit_log'
      AND index_name = 'idx_audit_usuario_data'
);
SET @sql := IF(
    @idx_audit_usuario_data_exists = 0,
    'ALTER TABLE dotp_audit_log ADD INDEX idx_audit_usuario_data (usuario_id, created_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_audit_tenant_data_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_audit_log'
      AND index_name = 'idx_audit_tenant_data'
);
SET @sql := IF(
    @idx_audit_tenant_data_exists = 0,
    'ALTER TABLE dotp_audit_log ADD INDEX idx_audit_tenant_data (tenant_id, created_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
