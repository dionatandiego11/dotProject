-- ===========================================
-- MIGRATION: Estrutura PPA v1.3
-- Sistema de Gestão Pública baseado em Estados
-- ===========================================

-- ===========================================
-- SISTEMA DE PERMISSÕES (Role + Escopo)
-- ===========================================

-- Roles fixas do sistema
CREATE TABLE IF NOT EXISTS dotp_roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) UNIQUE NOT NULL,
    role_description VARCHAR(255),
    role_permissions JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir roles padrão
INSERT INTO dotp_roles (role_name, role_description, role_permissions) VALUES
('PREFEITO', 'Chefe do executivo municipal', '["*"]'),
('SECRETARIO', 'Gestor de secretaria', '["ppa.read", "programa.read", "programa.update", "projeto.read", "projeto.create", "dashboard.secretaria", "dashboard.executivo"]'),
('COORDENADOR', 'Gestor de projeto e coordenação', '["programa.read", "projeto.read", "projeto.update", "etapa.read", "etapa.update", "tarefa.read", "tarefa.create", "tarefa.update", "dashboard.coordenador"]'),
('TECNICO', 'Executor de tarefas', '["projeto.read", "tarefa.read", "tarefa.update", "dashboard.tecnico"]'),
('CONTROLADOR', 'Fiscal e auditor', '["*.read", "alerta.read", "dashboard.controlador"]')
ON DUPLICATE KEY UPDATE role_permissions = VALUES(role_permissions);

-- Árvore Organizacional Configurável
CREATE TABLE IF NOT EXISTS dotp_unidades_organizacionais (
    unidade_id INT AUTO_INCREMENT PRIMARY KEY,
    unidade_pai_id INT NULL,
    unidade_nome VARCHAR(255) NOT NULL,
    unidade_nivel INT NOT NULL COMMENT '1=Prefeitura, 2=Secretaria, 3=Coordenação',
    unidade_sigla VARCHAR(20) NULL,
    unidade_responsavel_id INT NULL COMMENT 'ID do usuário responsável (secretário/coordenador)',
    unidade_ativa TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (unidade_pai_id) REFERENCES dotp_unidades_organizacionais(unidade_id) ON DELETE SET NULL,
    INDEX idx_pai (unidade_pai_id),
    INDEX idx_nivel (unidade_nivel),
    INDEX idx_ativa (unidade_ativa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vínculo Usuário-Unidade (escopo - permite histórico)
CREATE TABLE IF NOT EXISTS dotp_usuario_unidades (
    vinculo_id INT AUTO_INCREMENT PRIMARY KEY,
    vinculo_user_id INT NOT NULL,
    vinculo_unidade_id INT NOT NULL,
    vinculo_role VARCHAR(50) NOT NULL,
    vinculo_data_inicio DATE NOT NULL,
    vinculo_data_fim DATE NULL COMMENT 'NULL = vínculo ativo',
    vinculo_ativo TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vinculo_user_id) REFERENCES dotp_users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (vinculo_unidade_id) REFERENCES dotp_unidades_organizacionais(unidade_id) ON DELETE CASCADE,
    FOREIGN KEY (vinculo_role) REFERENCES dotp_roles(role_name),
    INDEX idx_user_ativo (vinculo_user_id, vinculo_ativo),
    INDEX idx_unidade (vinculo_unidade_id),
    INDEX idx_periodo (vinculo_data_inicio, vinculo_data_fim)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================
-- NÍVEL 0: PPA
-- ===========================================
CREATE TABLE IF NOT EXISTS ppa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    periodo_inicio YEAR NOT NULL,
    periodo_fim YEAR NOT NULL,
    estado ENUM('Rascunho', 'Publicado', 'Vigente', 'Revisao_Anual', 'Encerrado', 'Arquivado') DEFAULT 'Rascunho',
    objetivo_geral TEXT,
    prefeito_id INT NOT NULL,
    data_publicacao DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_estado (estado),
    INDEX idx_periodo (periodo_inicio, periodo_fim)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================
-- NÍVEL 1: PROGRAMAS
-- ===========================================
CREATE TABLE IF NOT EXISTS programas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ppa_id INT NOT NULL,
    nome VARCHAR(255) NOT NULL,
    objetivo TEXT,
    unidade_id INT NOT NULL COMMENT 'Secretaria responsável',
    estado ENUM('Cadastrado', 'Ativo', 'Inativo', 'Em_Andamento', 'Em_Dia', 'Atencao', 'Critico', 'Parado', 'Concluido', 'Arquivado') DEFAULT 'Cadastrado',
    percent_execucao DECIMAL(5,2) DEFAULT 0.00,
    prioridade ENUM('Baixa', 'Media', 'Alta') DEFAULT 'Media',
    data_ultima_atualizacao TIMESTAMP NULL,
    observacao_estrategica TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ppa_id) REFERENCES ppa(id) ON DELETE CASCADE,
    FOREIGN KEY (unidade_id) REFERENCES dotp_unidades_organizacionais(unidade_id),
    INDEX idx_ppa_estado (ppa_id, estado),
    INDEX idx_unidade (unidade_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================
-- NÍVEL 2: PROJETOS
-- ===========================================
CREATE TABLE IF NOT EXISTS projetos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    programa_id INT NULL COMMENT 'NULL = projeto avulso',
    nome VARCHAR(255) NOT NULL,
    tipo ENUM('Obra', 'Politica_Publica', 'Convenio', 'Emenda', 'Outro') NOT NULL,
    estado ENUM('Cadastrado', 'Aguardando_Inicio', 'Planejamento', 'Licitacao', 'Execucao', 'Medicao', 'Pagamento', 'Concluido', 'Atrasado', 'Impedido', 'Recuperacao', 'Cancelado') DEFAULT 'Cadastrado',
    etapa_atual TINYINT DEFAULT 1 COMMENT '1=Planejamento, 2=Licitacao, 3=Execucao, 4=Medicao, 5=Pagamento',
    percent_execucao DECIMAL(5,2) DEFAULT 0.00,
    descricao TEXT,
    fonte_recurso VARCHAR(100),
    valor_previsto DECIMAL(15,2) NULL COMMENT 'Informativo - não é contabilidade',
    situacao_orcamentaria ENUM('nao_iniciado', 'em_execucao', 'empenhado', 'pago') NULL,
    unidade_id INT NOT NULL COMMENT 'Coordenação responsável',
    coordenador_id INT NOT NULL,
    data_prevista_inicio DATE,
    data_prevista_fim DATE,
    data_conclusao DATE NULL,
    justificativa_atraso TEXT,
    impedimento_descricao TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (programa_id) REFERENCES programas(id) ON DELETE SET NULL,
    FOREIGN KEY (unidade_id) REFERENCES dotp_unidades_organizacionais(unidade_id),
    INDEX idx_programa_estado (programa_id, estado),
    INDEX idx_coordenador (coordenador_id),
    INDEX idx_tipo (tipo),
    INDEX idx_unidade (unidade_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================
-- NÍVEL 3: ETAPAS
-- ===========================================
CREATE TABLE IF NOT EXISTS etapas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    projeto_id INT NOT NULL,
    numero TINYINT NOT NULL COMMENT '1-5 sequencial',
    nome VARCHAR(50) NOT NULL,
    estado ENUM('Nao_Iniciada', 'Em_Andamento', 'Dentro_Prazo', 'Proximo_Prazo', 'Atrasada', 'Critica', 'Impedida', 'Concluida', 'Concluida_Com_Atraso') DEFAULT 'Nao_Iniciada',
    data_prevista_inicio DATE,
    data_prevista_fim DATE NOT NULL,
    data_real_inicio DATE,
    data_real_fim DATE,
    responsavel_id INT,
    dias_atraso INT DEFAULT 0,
    justificativa_atraso TEXT,
    evidencia_url VARCHAR(500),
    percent_conclusao DECIMAL(5,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (projeto_id) REFERENCES projetos(id) ON DELETE CASCADE,
    UNIQUE KEY uk_projeto_numero (projeto_id, numero),
    INDEX idx_estado (estado),
    INDEX idx_data_prevista (data_prevista_fim)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================
-- NÍVEL 4: TAREFAS (extensão da tabela existente)
-- ===========================================
-- Adicionar colunas à tabela dotp_tasks existente
ALTER TABLE dotp_tasks 
ADD COLUMN IF NOT EXISTS estado ENUM('Backlog', 'A_Fazer', 'Em_Andamento', 'Pausada', 'Bloqueada', 'Em_Revisao', 'Concluida', 'Cancelada') DEFAULT 'A_Fazer' 
AFTER task_priority;

ALTER TABLE dotp_tasks 
ADD COLUMN IF NOT EXISTS etapa_id INT NULL 
AFTER task_project;

ALTER TABLE dotp_tasks 
ADD COLUMN IF NOT EXISTS evidencia_anexo VARCHAR(500) NULL 
AFTER task_notify;

ALTER TABLE dotp_tasks 
ADD INDEX IF NOT EXISTS idx_etapa (etapa_id);

ALTER TABLE dotp_tasks 
ADD INDEX IF NOT EXISTS idx_estado (estado);

-- ===========================================
-- HISTÓRICO DE STATUS (Audit Trail)
-- ===========================================
CREATE TABLE IF NOT EXISTS status_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entidade_tipo ENUM('PPA', 'Programa', 'Projeto', 'Etapa') NOT NULL,
    entidade_id INT NOT NULL,
    estado_anterior VARCHAR(50),
    estado_novo VARCHAR(50) NOT NULL,
    usuario_id INT,
    motivo TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entidade (entidade_tipo, entidade_id),
    INDEX idx_data (created_at),
    INDEX idx_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================
-- VIEWS PARA DASHBOARDS
-- ===========================================

CREATE OR REPLACE VIEW view_dashboard_executivo AS
SELECT 
    p.id as ppa_id,
    p.nome as ppa_nome,
    p.estado as ppa_estado,
    COUNT(DISTINCT prog.id) as total_programas,
    COUNT(DISTINCT CASE WHEN prog.estado = 'Concluido' THEN prog.id END) as programas_concluidos,
    COUNT(DISTINCT CASE WHEN prog.estado = 'Critico' THEN prog.id END) as programas_criticos,
    COUNT(DISTINCT CASE WHEN prog.estado = 'Parado' THEN prog.id END) as programas_parados,
    COUNT(DISTINCT CASE WHEN prog.estado = 'Atencao' THEN prog.id END) as programas_atencao,
    COUNT(DISTINCT pr.id) as total_projetos,
    COUNT(DISTINCT CASE WHEN pr.estado = 'Concluido' THEN pr.id END) as projetos_concluidos,
    COUNT(DISTINCT CASE WHEN pr.estado = 'Atrasado' THEN pr.id END) as projetos_atrasados,
    AVG(prog.percent_execucao) as percent_execucao_media,
    MAX(pr.updated_at) as ultima_atualizacao
FROM ppa p
LEFT JOIN programas prog ON prog.ppa_id = p.id
LEFT JOIN projetos pr ON pr.programa_id = prog.id
WHERE p.estado IN ('Vigente', 'Revisao_Anual')
GROUP BY p.id;

CREATE OR REPLACE VIEW view_projetos_status AS
SELECT 
    pr.id,
    pr.nome,
    pr.estado,
    pr.etapa_atual,
    pr.tipo,
    et.nome as etapa_nome,
    et.estado as etapa_estado,
    et.data_prevista_fim,
    et.data_real_fim,
    et.dias_atraso,
    prog.id as programa_id,
    prog.nome as programa_nome,
    u.unidade_nome as unidade_nome,
    u.unidade_id as unidade_id,
    pr.percent_execucao,
    pr.valor_previsto,
    CASE 
        WHEN pr.estado = 'Concluido' THEN '#22c55e'
        WHEN pr.estado = 'Atrasado' THEN '#ef4444'
        WHEN pr.estado = 'Impedido' THEN '#6b7280'
        WHEN et.estado IN ('Critica') THEN '#dc2626'
        WHEN et.estado IN ('Atrasada') THEN '#ef4444'
        WHEN et.estado IN ('Proximo_Prazo') THEN '#f59e0b'
        ELSE '#3b82f6'
    END as cor_status
FROM projetos pr
JOIN etapas et ON et.projeto_id = pr.id AND et.numero = pr.etapa_atual
LEFT JOIN programas prog ON prog.id = pr.programa_id
LEFT JOIN dotp_unidades_organizacionais u ON u.unidade_id = pr.unidade_id
WHERE pr.estado != 'Cancelado';

-- ===========================================
-- TRIGGER: Atualiza timestamp de programas
-- ===========================================
DELIMITER //

CREATE TRIGGER IF NOT EXISTS trg_projeto_after_update 
AFTER UPDATE ON projetos
FOR EACH ROW
BEGIN
    -- Atualiza timestamp do programa pai
    IF OLD.programa_id IS NOT NULL THEN
        UPDATE programas 
        SET data_ultima_atualizacao = NOW() 
        WHERE id = OLD.programa_id;
    END IF;
    
    -- Se mudou de estado, registra no histórico
    IF OLD.estado != NEW.estado THEN
        INSERT INTO status_historico 
        (entidade_tipo, entidade_id, estado_anterior, estado_novo, created_at)
        VALUES 
        ('Projeto', NEW.id, OLD.estado, NEW.estado, NOW());
    END IF;
END//

CREATE TRIGGER IF NOT EXISTS trg_programa_after_update
AFTER UPDATE ON programas
FOR EACH ROW
BEGIN
    IF OLD.estado != NEW.estado THEN
        INSERT INTO status_historico 
        (entidade_tipo, entidade_id, estado_anterior, estado_novo, created_at)
        VALUES 
        ('Programa', NEW.id, OLD.estado, NEW.estado, NOW());
    END IF;
END//

CREATE TRIGGER IF NOT EXISTS trg_etapa_after_update
AFTER UPDATE ON etapas
FOR EACH ROW
BEGIN
    IF OLD.estado != NEW.estado THEN
        INSERT INTO status_historico 
        (entidade_tipo, entidade_id, estado_anterior, estado_novo, created_at)
        VALUES 
        ('Etapa', NEW.id, OLD.estado, NEW.estado, NOW());
    END IF;
END//

DELIMITER ;

-- ===========================================
-- DADOS INICIAIS (Seed)
-- ===========================================

-- Criar estrutura organizacional básica
INSERT INTO dotp_unidades_organizacionais (unidade_id, unidade_pai_id, unidade_nome, unidade_nivel, unidade_sigla, unidade_ativa) VALUES
(1, NULL, 'Prefeitura Municipal', 1, 'PM', 1)
ON DUPLICATE KEY UPDATE unidade_nome = VALUES(unidade_nome);

-- Registrar migration
CREATE TABLE IF NOT EXISTS migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(255) NOT NULL,
    batch INT NOT NULL,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO migrations (migration, batch) 
VALUES ('001_create_ppa_structure.sql', 1)
ON DUPLICATE KEY UPDATE executed_at = executed_at;
