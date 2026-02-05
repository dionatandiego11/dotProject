-- Migration: Criação das tabelas para o sistema de gestão pública (Prefeituras)
-- Data: 2026-01-30
-- Autor: Kimi

-- ===========================================
-- TABELA: programas
-- Programas de governo (PPA - Plano Plurianual)
-- ===========================================
CREATE TABLE IF NOT EXISTS `dotp_programas` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `codigo` VARCHAR(20) NOT NULL COMMENT 'Cigo do programa (ex: MOB-2024)',
    `nome` VARCHAR(255) NOT NULL COMMENT 'Nome do programa',
    `objetivo_estrategico` TEXT COMMENT 'Objetivo estratégico do programa',
    `descricao` TEXT COMMENT 'Descrição detalhada',
    `unidade_id` INT(11) NOT NULL COMMENT 'Secretaria líder (unidade organizacional)',
    `responsavel_politico_id` INT(11) COMMENT 'ID do secretário (usuário)',
    `responsavel_tecnico_id` INT(11) COMMENT 'ID do diretor técnico (usuário)',
    `valor_orcamentario` DECIMAL(15,2) DEFAULT 0.00,
    `data_inicio` DATE,
    `data_fim` DATE,
    `estado` ENUM('Planejamento', 'Execucao', 'Concluido', 'Suspenso', 'Arquivado', 'Critico', 'Atencao') DEFAULT 'Planejamento',
    `percent_execucao` TINYINT(4) DEFAULT 0,
    `eixo_ppa` VARCHAR(100) COMMENT 'Eixo do PPA',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_unidade` (`unidade_id`),
    KEY `idx_estado` (`estado`),
    KEY `idx_codigo` (`codigo`),
    FOREIGN KEY (`unidade_id`) REFERENCES `dotp_unidades_organizacionais`(`unidade_id`),
    FOREIGN KEY (`responsavel_politico_id`) REFERENCES `dotp_users`(`user_id`),
    FOREIGN KEY (`responsavel_tecnico_id`) REFERENCES `dotp_users`(`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Programas de Governo (PPA)';

-- ===========================================
-- TABELA: projetos (estendida para prefeituras)
-- Projetos específicos vinculados a programas
-- ===========================================
CREATE TABLE IF NOT EXISTS `dotp_projetos_prefeitura` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `programa_id` INT(11) UNSIGNED NOT NULL COMMENT 'Programa vinculado',
    `project_id` INT(11) COMMENT 'ID do projeto no dotProject original (se existir)',
    `nome` VARCHAR(255) NOT NULL,
    `descricao` TEXT,
    `tipo` ENUM('Obra', 'Convenio', 'Emenda', 'Compra', 'Servico', 'Evento', 'Outro') DEFAULT 'Obra',
    `unidade_id` INT(11) NOT NULL COMMENT 'Secretaria/Unidade responsável',
    `coordenador_id` INT(11) COMMENT 'Coordenador do projeto (usuário)',
    `valor_previsto` DECIMAL(15,2) DEFAULT 0.00,
    `valor_executado` DECIMAL(15,2) DEFAULT 0.00,
    `data_prevista_inicio` DATE,
    `data_prevista_fim` DATE,
    `data_real_inicio` DATE,
    `data_real_fim` DATE,
    `estado` ENUM('Nao_Iniciado', 'Em_Andamento', 'Atrasado', 'Concluido', 'Cancelado', 'Suspenso', 'Parado', 'Critico', 'Atencao') DEFAULT 'Nao_Iniciado',
    `percent_execucao` TINYINT(4) DEFAULT 0,
    `etapa_atual` INT(11) DEFAULT 1,
    `situacao_orcamentaria` ENUM('nao_empenhado', 'empenhado', 'pago', 'parcial') DEFAULT 'nao_empenhado',
    `justificativa_atraso` TEXT,
    `numero_contrato` VARCHAR(50),
    `numero_convenio` VARCHAR(50),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_programa` (`programa_id`),
    KEY `idx_unidade` (`unidade_id`),
    KEY `idx_estado` (`estado`),
    KEY `idx_tipo` (`tipo`),
    KEY `idx_coordenador` (`coordenador_id`),
    FOREIGN KEY (`programa_id`) REFERENCES `dotp_programas`(`id`),
    FOREIGN KEY (`unidade_id`) REFERENCES `dotp_unidades_organizacionais`(`unidade_id`),
    FOREIGN KEY (`coordenador_id`) REFERENCES `dotp_users`(`user_id`),
    FOREIGN KEY (`project_id`) REFERENCES `dotp_projects`(`project_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Projetos da Prefeitura';

-- ===========================================
-- TABELA: etapas
-- Etapas/marcos dos projetos
-- ===========================================
CREATE TABLE IF NOT EXISTS `dotp_etapas` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `projeto_id` INT(11) UNSIGNED NOT NULL,
    `numero` INT(11) NOT NULL DEFAULT 1 COMMENT 'Número sequencial da etapa',
    `nome` VARCHAR(255) NOT NULL COMMENT 'Nome da etapa',
    `descricao` TEXT,
    `estado` ENUM('Nao_Iniciada', 'Em_Andamento', 'Concluida', 'Concluida_Com_Atraso', 'Atrasada', 'Proximo_Prazo', 'Critica', 'Bloqueada') DEFAULT 'Nao_Iniciada',
    `percent_conclusao` TINYINT(4) DEFAULT 0,
    `data_prevista_inicio` DATE,
    `data_prevista_fim` DATE,
    `data_real_inicio` DATE,
    `data_real_fim` DATE,
    `responsavel_id` INT(11) COMMENT 'Responsável técnico pela etapa',
    `observacoes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_projeto_numero` (`projeto_id`, `numero`),
    KEY `idx_projeto` (`projeto_id`),
    KEY `idx_estado` (`estado`),
    KEY `idx_responsavel` (`responsavel_id`),
    FOREIGN KEY (`projeto_id`) REFERENCES `dotp_projetos_prefeitura`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`responsavel_id`) REFERENCES `dotp_users`(`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Etapas dos Projetos';

-- ===========================================
-- TABELA: alertas
-- Alertas do sistema para usuários
-- ===========================================
CREATE TABLE IF NOT EXISTS `dotp_alertas` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `destinatario_id` INT(11) NOT NULL COMMENT 'Usuário que recebe o alerta',
    `tipo` ENUM('Prazo', 'Orcamento', 'Conformidade', 'Sistema', 'Outro') DEFAULT 'Sistema',
    `titulo` VARCHAR(255) NOT NULL,
    `mensagem` TEXT,
    `projeto_id` INT(11) UNSIGNED,
    `programa_id` INT(11) UNSIGNED,
    `etapa_id` INT(11) UNSIGNED,
    `prioridade` ENUM('Baixa', 'Media', 'Alta', 'Critica') DEFAULT 'Media',
    `lido` TINYINT(1) DEFAULT 0,
    `data_leitura` DATETIME,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_destinatario` (`destinatario_id`),
    KEY `idx_lido` (`lido`),
    KEY `idx_tipo` (`tipo`),
    KEY `idx_projeto` (`projeto_id`),
    KEY `idx_programa` (`programa_id`),
    FOREIGN KEY (`destinatario_id`) REFERENCES `dotp_users`(`user_id`),
    FOREIGN KEY (`projeto_id`) REFERENCES `dotp_projetos_prefeitura`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`programa_id`) REFERENCES `dotp_programas`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Alertas do Sistema';

-- ===========================================
-- VIEWS ÚTEIS
-- ===========================================

-- View: Resumo de programas com estatísticas
CREATE OR REPLACE VIEW `view_programas_resumo` AS
SELECT 
    p.*,
    u.unidade_nome,
    u.unidade_sigla,
    rp.user_username as responsavel_politico_nome,
    rt.user_username as responsavel_tecnico_nome,
    COUNT(DISTINCT pr.id) as total_projetos,
    COUNT(DISTINCT CASE WHEN pr.estado = 'Concluido' THEN pr.id END) as projetos_concluidos,
    COUNT(DISTINCT CASE WHEN pr.estado = 'Atrasado' THEN pr.id END) as projetos_atrasados,
    AVG(pr.percent_execucao) as media_execucao
FROM dotp_programas p
LEFT JOIN dotp_unidades_organizacionais u ON u.unidade_id = p.unidade_id
LEFT JOIN dotp_users rp ON rp.user_id = p.responsavel_politico_id
LEFT JOIN dotp_users rt ON rt.user_id = p.responsavel_tecnico_id
LEFT JOIN dotp_projetos_prefeitura pr ON pr.programa_id = p.id
GROUP BY p.id;

-- View: Projetos com detalhes completos
-- View: Projetos com detalhes completos
CREATE OR REPLACE VIEW `view_projetos_completo` AS
SELECT 
    pr.*,
    prog.codigo as programa_codigo,
    prog.nome as programa_nome,
    u.unidade_nome,
    u.unidade_sigla,
    c.user_username as coordenador_nome,
    COUNT(DISTINCT e.id) as total_etapas,
    COUNT(DISTINCT CASE WHEN e.estado = 'Concluida' THEN e.id END) as etapas_concluidas
FROM dotp_projetos_prefeitura pr
LEFT JOIN dotp_programas prog ON prog.id = pr.programa_id
LEFT JOIN dotp_unidades_organizacionais u ON u.unidade_id = pr.unidade_id
LEFT JOIN dotp_users c ON c.user_id = pr.coordenador_id
LEFT JOIN dotp_etapas e ON e.projeto_id = pr.id
GROUP BY pr.id;
