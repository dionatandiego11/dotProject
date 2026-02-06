-- Migration: Unifica PPA com dotp_projects (projetos canônicos)
-- Data: 2026-02-06
-- Objetivo: mover dados de dotp_projetos_prefeitura para dotp_projects

-- ===========================================
-- 1) Estender dotp_projects com colunas PPA
-- ===========================================
ALTER TABLE dotp_projects
    ADD COLUMN IF NOT EXISTS project_programa_id INT NULL AFTER project_company,
    ADD COLUMN IF NOT EXISTS project_tipo VARCHAR(30) NULL AFTER project_programa_id,
    ADD COLUMN IF NOT EXISTS project_estado VARCHAR(30) NULL AFTER project_tipo,
    ADD COLUMN IF NOT EXISTS project_etapa_atual TINYINT DEFAULT 1 AFTER project_estado,
    ADD COLUMN IF NOT EXISTS project_percent_execucao DECIMAL(5,2) DEFAULT 0.00 AFTER project_etapa_atual,
    ADD COLUMN IF NOT EXISTS project_situacao_orcamentaria VARCHAR(20) NULL AFTER project_percent_execucao,
    ADD COLUMN IF NOT EXISTS project_data_real_inicio DATE NULL AFTER project_situacao_orcamentaria,
    ADD COLUMN IF NOT EXISTS project_data_real_fim DATE NULL AFTER project_data_real_inicio,
    ADD COLUMN IF NOT EXISTS project_coordenador_id INT NULL AFTER project_data_real_fim,
    ADD COLUMN IF NOT EXISTS project_fonte_recurso VARCHAR(100) NULL AFTER project_coordenador_id,
    ADD COLUMN IF NOT EXISTS project_numero_contrato VARCHAR(50) NULL AFTER project_fonte_recurso,
    ADD COLUMN IF NOT EXISTS project_numero_convenio VARCHAR(50) NULL AFTER project_numero_contrato,
    ADD COLUMN IF NOT EXISTS project_justificativa_atraso TEXT NULL AFTER project_numero_convenio,
    ADD COLUMN IF NOT EXISTS project_impedimento_descricao TEXT NULL AFTER project_justificativa_atraso,
    ADD COLUMN IF NOT EXISTS project_created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER project_impedimento_descricao,
    ADD COLUMN IF NOT EXISTS project_updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER project_created_at,
    ADD COLUMN IF NOT EXISTS project_prefeitura_id INT NULL AFTER project_updated_at;

CREATE INDEX IF NOT EXISTS idx_project_programa_id ON dotp_projects (project_programa_id);
CREATE INDEX IF NOT EXISTS idx_project_estado ON dotp_projects (project_estado);
CREATE INDEX IF NOT EXISTS idx_project_tipo ON dotp_projects (project_tipo);
CREATE INDEX IF NOT EXISTS idx_project_coordenador ON dotp_projects (project_coordenador_id);
CREATE INDEX IF NOT EXISTS idx_project_etapa_atual ON dotp_projects (project_etapa_atual);

-- ===========================================
-- 2) Garantir coluna project_id em dotp_projetos_prefeitura
-- ===========================================
ALTER TABLE dotp_projetos_prefeitura
    ADD COLUMN IF NOT EXISTS project_id INT NULL AFTER programa_id;

-- Ajustes em dotp_etapas para campos usados nos dashboards
ALTER TABLE dotp_etapas
    ADD COLUMN IF NOT EXISTS dias_atraso INT DEFAULT 0 AFTER responsavel_id,
    ADD COLUMN IF NOT EXISTS justificativa_atraso TEXT NULL AFTER dias_atraso,
    ADD COLUMN IF NOT EXISTS evidencia_url VARCHAR(500) NULL AFTER justificativa_atraso;

-- ===========================================
-- 3) Migração de dados (dotp_projetos_prefeitura -> dotp_projects)
-- ===========================================

-- a) Vincula projetos já existentes (quando project_id estiver preenchido)
UPDATE dotp_projects dp
JOIN dotp_projetos_prefeitura p ON p.project_id = dp.project_id
SET dp.project_prefeitura_id = p.id
WHERE dp.project_prefeitura_id IS NULL;

-- b) Insere projetos faltantes em dotp_projects
INSERT INTO dotp_projects (
    project_prefeitura_id,
    project_company,
    project_name,
    project_description,
    project_start_date,
    project_end_date,
    project_percent_complete,
    project_target_budget,
    project_actual_budget,
    project_owner,
    project_creator,
    project_programa_id,
    project_tipo,
    project_estado,
    project_etapa_atual,
    project_percent_execucao,
    project_situacao_orcamentaria,
    project_data_real_inicio,
    project_data_real_fim,
    project_coordenador_id,
    project_fonte_recurso,
    project_numero_contrato,
    project_numero_convenio,
    project_justificativa_atraso,
    project_impedimento_descricao,
    project_created_at,
    project_updated_at
)
SELECT
    p.id,
    p.unidade_id,
    p.nome,
    p.descricao,
    p.data_prevista_inicio,
    p.data_prevista_fim,
    p.percent_execucao,
    p.valor_previsto,
    p.valor_executado,
    COALESCE(p.coordenador_id, 0),
    COALESCE(p.coordenador_id, 0),
    p.programa_id,
    p.tipo,
    p.estado,
    p.etapa_atual,
    p.percent_execucao,
    p.situacao_orcamentaria,
    p.data_real_inicio,
    p.data_real_fim,
    p.coordenador_id,
    NULL,
    p.numero_contrato,
    p.numero_convenio,
    p.justificativa_atraso,
    NULL,
    p.created_at,
    p.updated_at
FROM dotp_projetos_prefeitura p
LEFT JOIN dotp_projects dp ON dp.project_prefeitura_id = p.id
WHERE dp.project_id IS NULL;

-- c) Atualiza colunas PPA para projetos já existentes
UPDATE dotp_projects dp
JOIN dotp_projetos_prefeitura p ON dp.project_prefeitura_id = p.id
SET
    dp.project_programa_id = p.programa_id,
    dp.project_tipo = p.tipo,
    dp.project_estado = p.estado,
    dp.project_etapa_atual = p.etapa_atual,
    dp.project_percent_execucao = p.percent_execucao,
    dp.project_situacao_orcamentaria = p.situacao_orcamentaria,
    dp.project_data_real_inicio = p.data_real_inicio,
    dp.project_data_real_fim = p.data_real_fim,
    dp.project_coordenador_id = p.coordenador_id,
    dp.project_numero_contrato = p.numero_contrato,
    dp.project_numero_convenio = p.numero_convenio,
    dp.project_justificativa_atraso = p.justificativa_atraso,
    dp.project_target_budget = COALESCE(dp.project_target_budget, p.valor_previsto),
    dp.project_actual_budget = COALESCE(dp.project_actual_budget, p.valor_executado),
    dp.project_percent_complete = COALESCE(dp.project_percent_complete, p.percent_execucao),
    dp.project_company = COALESCE(dp.project_company, p.unidade_id),
    dp.project_name = COALESCE(dp.project_name, p.nome),
    dp.project_description = COALESCE(dp.project_description, p.descricao),
    dp.project_start_date = COALESCE(dp.project_start_date, p.data_prevista_inicio),
    dp.project_end_date = COALESCE(dp.project_end_date, p.data_prevista_fim),
    dp.project_created_at = COALESCE(dp.project_created_at, p.created_at),
    dp.project_updated_at = COALESCE(dp.project_updated_at, p.updated_at);

-- d) Atualiza project_id na tabela antiga
UPDATE dotp_projetos_prefeitura p
JOIN dotp_projects dp ON dp.project_prefeitura_id = p.id
SET p.project_id = dp.project_id
WHERE p.project_id IS NULL;

-- ===========================================
-- 4) Remover FKs antigas (etapas e alertas)
-- ===========================================

-- Drop FK dotp_etapas -> dotp_projetos_prefeitura (se existir)
SET @fk_etapas := (
    SELECT CONSTRAINT_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'dotp_etapas'
      AND COLUMN_NAME = 'projeto_id'
      AND REFERENCED_TABLE_NAME = 'dotp_projetos_prefeitura'
    LIMIT 1
);
SET @sql := IF(@fk_etapas IS NULL, 'SELECT 1', CONCAT('ALTER TABLE dotp_etapas DROP FOREIGN KEY ', @fk_etapas));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Drop FK dotp_alertas -> dotp_projetos_prefeitura (se existir)
SET @fk_alertas := (
    SELECT CONSTRAINT_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'dotp_alertas'
      AND COLUMN_NAME = 'projeto_id'
      AND REFERENCED_TABLE_NAME = 'dotp_projetos_prefeitura'
    LIMIT 1
);
SET @sql := IF(@fk_alertas IS NULL, 'SELECT 1', CONCAT('ALTER TABLE dotp_alertas DROP FOREIGN KEY ', @fk_alertas));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ===========================================
-- 5) Atualizar referências de etapas e alertas
-- ===========================================

-- Remove etapas que já apontam para project_id canônico e podem conflitar
DELETE e FROM dotp_etapas e
LEFT JOIN dotp_projetos_prefeitura p ON p.id = e.projeto_id
WHERE p.id IS NULL
  AND e.projeto_id IN (SELECT project_id FROM dotp_projetos_prefeitura WHERE project_id IS NOT NULL);

-- Etapas: aponta para project_id canônico
UPDATE dotp_etapas e
JOIN dotp_projetos_prefeitura p ON p.id = e.projeto_id
SET e.projeto_id = p.project_id
WHERE p.project_id IS NOT NULL;

-- Alertas: aponta para project_id canônico
UPDATE dotp_alertas a
JOIN dotp_projetos_prefeitura p ON p.id = a.projeto_id
SET a.projeto_id = p.project_id
WHERE a.projeto_id IS NOT NULL AND p.project_id IS NOT NULL;

-- ===========================================
-- 6) Ajustar tipos das colunas para FK
-- ===========================================

ALTER TABLE dotp_etapas
    MODIFY COLUMN projeto_id INT(11) NOT NULL;

ALTER TABLE dotp_alertas
    MODIFY COLUMN projeto_id INT(11) NULL;

-- ===========================================
-- 7) Recriar FKs para dotp_projects (etapas e alertas)
-- ===========================================

-- Add FK dotp_etapas -> dotp_projects (se ainda não existir)
SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'dotp_etapas'
      AND CONSTRAINT_NAME = 'fk_etapas_project'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE dotp_etapas ADD CONSTRAINT fk_etapas_project FOREIGN KEY (projeto_id) REFERENCES dotp_projects(project_id) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add FK dotp_alertas -> dotp_projects (se ainda não existir)
SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'dotp_alertas'
      AND CONSTRAINT_NAME = 'fk_alertas_project'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE dotp_alertas ADD CONSTRAINT fk_alertas_project FOREIGN KEY (projeto_id) REFERENCES dotp_projects(project_id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ===========================================
-- 8) Atualizar views de prefeitura (se existirem)
-- ===========================================
CREATE OR REPLACE VIEW view_programas_resumo AS
SELECT 
    p.*,
    u.unidade_nome,
    u.unidade_sigla,
    rp.user_username as responsavel_politico_nome,
    rt.user_username as responsavel_tecnico_nome,
    COUNT(DISTINCT pr.project_id) as total_projetos,
    COUNT(DISTINCT CASE WHEN pr.project_estado = 'Concluido' THEN pr.project_id END) as projetos_concluidos,
    COUNT(DISTINCT CASE WHEN pr.project_estado = 'Atrasado' THEN pr.project_id END) as projetos_atrasados,
    AVG(pr.project_percent_execucao) as media_execucao
FROM dotp_programas p
LEFT JOIN dotp_unidades_organizacionais u ON u.unidade_id = p.unidade_id
LEFT JOIN dotp_users rp ON rp.user_id = p.responsavel_politico_id
LEFT JOIN dotp_users rt ON rt.user_id = p.responsavel_tecnico_id
LEFT JOIN dotp_projects pr ON pr.project_programa_id = p.id
GROUP BY p.id;

CREATE OR REPLACE VIEW view_projetos_completo AS
SELECT 
    pr.project_id as id,
    pr.project_programa_id as programa_id,
    pr.project_name as nome,
    pr.project_description as descricao,
    pr.project_tipo as tipo,
    pr.project_estado as estado,
    pr.project_etapa_atual as etapa_atual,
    pr.project_percent_execucao as percent_execucao,
    pr.project_target_budget as valor_previsto,
    pr.project_actual_budget as valor_executado,
    pr.project_company as unidade_id,
    pr.project_coordenador_id as coordenador_id,
    pr.project_start_date as data_prevista_inicio,
    pr.project_end_date as data_prevista_fim,
    pr.project_data_real_inicio as data_real_inicio,
    pr.project_data_real_fim as data_real_fim,
    pr.project_situacao_orcamentaria as situacao_orcamentaria,
    pr.project_numero_contrato as numero_contrato,
    pr.project_numero_convenio as numero_convenio,
    pr.project_justificativa_atraso as justificativa_atraso,
    pr.project_created_at as created_at,
    pr.project_updated_at as updated_at,
    prog.codigo as programa_codigo,
    prog.nome as programa_nome,
    u.unidade_nome,
    u.unidade_sigla,
    c.user_username as coordenador_nome,
    COUNT(DISTINCT e.id) as total_etapas,
    COUNT(DISTINCT CASE WHEN e.estado = 'Concluida' THEN e.id END) as etapas_concluidas
FROM dotp_projects pr
LEFT JOIN dotp_programas prog ON prog.id = pr.project_programa_id
LEFT JOIN dotp_unidades_organizacionais u ON u.unidade_id = pr.project_company
LEFT JOIN dotp_users c ON c.user_id = pr.project_coordenador_id
LEFT JOIN dotp_etapas e ON e.projeto_id = pr.project_id
GROUP BY pr.project_id;

-- ===========================================
-- 9) Compatibilidade: view dotp_projetos_prefeitura
-- ===========================================
DROP VIEW IF EXISTS dotp_projetos_prefeitura;
DROP TABLE IF EXISTS dotp_projetos_prefeitura;
CREATE OR REPLACE VIEW dotp_projetos_prefeitura AS
SELECT
    pr.project_id as id,
    pr.project_programa_id as programa_id,
    pr.project_name as nome,
    pr.project_description as descricao,
    pr.project_tipo as tipo,
    pr.project_company as unidade_id,
    pr.project_coordenador_id as coordenador_id,
    pr.project_target_budget as valor_previsto,
    pr.project_actual_budget as valor_executado,
    pr.project_start_date as data_prevista_inicio,
    pr.project_end_date as data_prevista_fim,
    pr.project_data_real_inicio,
    pr.project_data_real_fim,
    pr.project_estado as estado,
    pr.project_percent_execucao as percent_execucao,
    pr.project_etapa_atual as etapa_atual,
    pr.project_situacao_orcamentaria as situacao_orcamentaria,
    pr.project_justificativa_atraso as justificativa_atraso,
    pr.project_numero_contrato as numero_contrato,
    pr.project_numero_convenio as numero_convenio,
    pr.project_created_at as created_at,
    pr.project_updated_at as updated_at
FROM dotp_projects pr
WHERE pr.project_programa_id IS NOT NULL OR pr.project_prefeitura_id IS NOT NULL;

-- Registrar migration
CREATE TABLE IF NOT EXISTS migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(255) NOT NULL,
    batch INT NOT NULL,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO migrations (migration, batch) 
VALUES ('20260206_unify_projects.sql', 1)
ON DUPLICATE KEY UPDATE executed_at = executed_at;
