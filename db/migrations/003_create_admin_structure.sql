-- ===========================================
-- MIGRATION: Estrutura Administrativa - Fase 0
-- Área Administrativa da Estrutura Organizacional
-- ===========================================

-- ===========================================
-- TABELA: Níveis Hierárquicos Configuráveis
-- ===========================================
CREATE TABLE IF NOT EXISTS dotp_niveis_hierarquicos (
    nivel_id INT AUTO_INCREMENT PRIMARY KEY,
    nivel_ordem INT NOT NULL UNIQUE COMMENT '1, 2, 3... define a hierarquia',
    nivel_nome VARCHAR(100) NOT NULL COMMENT 'Ex: Secretaria, Coordenação',
    nivel_titulo_responsavel VARCHAR(100) COMMENT 'Ex: Secretário, Coordenador',
    nivel_descricao TEXT,
    nivel_cor VARCHAR(7) DEFAULT '#007bff' COMMENT 'Cor no organograma',
    nivel_ativo BOOLEAN DEFAULT TRUE,
    nivel_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    nivel_updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ordem (nivel_ordem),
    INDEX idx_ativo (nivel_ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir níveis padrão
INSERT INTO dotp_niveis_hierarquicos (nivel_id, nivel_ordem, nivel_nome, nivel_titulo_responsavel, nivel_cor, nivel_descricao) VALUES
(1, 1, 'Prefeitura', 'Prefeito', '#1e40af', 'Chefe do Executivo Municipal'),
(2, 2, 'Secretaria', 'Secretário', '#3b82f6', 'Secretarias Municipais'),
(3, 3, 'Coordenação', 'Coordenador', '#60a5fa', 'Coordenações e Superintendências'),
(4, 4, 'Departamento', 'Diretor', '#93c5fd', 'Departamentos e Divisões'),
(5, 5, 'Equipe Técnica', 'Técnico', '#dbeafe', 'Equipe operacional')
ON DUPLICATE KEY UPDATE 
    nivel_nome = VALUES(nivel_nome),
    nivel_titulo_responsavel = VALUES(nivel_titulo_responsavel);

-- ===========================================
-- TABELA: Permissões por Nível (Template)
-- ===========================================
CREATE TABLE IF NOT EXISTS dotp_permissoes_nivel (
    permissao_id INT AUTO_INCREMENT PRIMARY KEY,
    permissao_nivel_id INT NOT NULL,
    permissao_recurso VARCHAR(100) NOT NULL COMMENT 'projeto, programa, tarefa, relatorio',
    permissao_acao VARCHAR(50) NOT NULL COMMENT 'visualizar, criar, editar, excluir, aprovar',
    permissao_escopo VARCHAR(50) NOT NULL COMMENT 'todos, unidade, subordinados, proprio',
    permissao_ativo BOOLEAN DEFAULT TRUE,
    permissao_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (permissao_nivel_id) REFERENCES dotp_niveis_hierarquicos(nivel_id) ON DELETE CASCADE,
    UNIQUE KEY uk_nivel_recurso_acao (permissao_nivel_id, permissao_recurso, permissao_acao),
    INDEX idx_recurso (permissao_recurso)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir permissões padrão por nível
-- Nível 1: Prefeito (tudo)
INSERT INTO dotp_permissoes_nivel (permissao_nivel_id, permissao_recurso, permissao_acao, permissao_escopo) VALUES
(1, 'ppa', 'visualizar', 'todos'),
(1, 'ppa', 'criar', 'todos'),
(1, 'ppa', 'editar', 'todos'),
(1, 'programa', 'visualizar', 'todos'),
(1, 'programa', 'aprovar', 'todos'),
(1, 'projeto', 'visualizar', 'todos'),
(1, 'projeto', 'aprovar', 'todos'),
(1, 'relatorio', 'visualizar', 'todos'),
(1, 'alerta', 'visualizar', 'todos'),
(1, 'admin', 'acessar', 'todos')
ON DUPLICATE KEY UPDATE permissao_escopo = VALUES(permissao_escopo);

-- Nível 2: Secretário (sua secretaria + subordinados)
INSERT INTO dotp_permissoes_nivel (permissao_nivel_id, permissao_recurso, permissao_acao, permissao_escopo) VALUES
(2, 'programa', 'visualizar', 'subordinados'),
(2, 'programa', 'criar', 'subordinados'),
(2, 'programa', 'editar', 'subordinados'),
(2, 'programa', 'aprovar', 'subordinados'),
(2, 'projeto', 'visualizar', 'subordinados'),
(2, 'projeto', 'criar', 'subordinados'),
(2, 'projeto', 'editar', 'subordinados'),
(2, 'projeto', 'aprovar', 'subordinados'),
(2, 'tarefa', 'visualizar', 'subordinados'),
(2, 'tarefa', 'atribuir', 'subordinados'),
(2, 'relatorio', 'visualizar', 'subordinados'),
(2, 'dashboard', 'acessar', 'secretario')
ON DUPLICATE KEY UPDATE permissao_escopo = VALUES(permissao_escopo);

-- Nível 3: Coordenador (sua coordenação)
INSERT INTO dotp_permissoes_nivel (permissao_nivel_id, permissao_recurso, permissao_acao, permissao_escopo) VALUES
(3, 'programa', 'visualizar', 'unidade'),
(3, 'projeto', 'visualizar', 'proprio'),
(3, 'projeto', 'criar', 'unidade'),
(3, 'projeto', 'editar', 'proprio'),
(3, 'etapa', 'visualizar', 'proprio'),
(3, 'etapa', 'editar', 'proprio'),
(3, 'tarefa', 'visualizar', 'equipe'),
(3, 'tarefa', 'criar', 'equipe'),
(3, 'tarefa', 'editar', 'equipe'),
(3, 'tarefa', 'atribuir', 'equipe'),
(3, 'dashboard', 'acessar', 'coordenador')
ON DUPLICATE KEY UPDATE permissao_escopo = VALUES(permissao_escopo);

-- Nível 4: Departamento/Diretor (sua área)
INSERT INTO dotp_permissoes_nivel (permissao_nivel_id, permissao_recurso, permissao_acao, permissao_escopo) VALUES
(4, 'projeto', 'visualizar', 'unidade'),
(4, 'etapa', 'visualizar', 'unidade'),
(4, 'tarefa', 'visualizar', 'equipe'),
(4, 'tarefa', 'criar', 'equipe'),
(4, 'tarefa', 'editar', 'equipe'),
(4, 'dashboard', 'acessar', 'tecnico')
ON DUPLICATE KEY UPDATE permissao_escopo = VALUES(permissao_escopo);

-- Nível 5: Equipe Técnica (suas tarefas)
INSERT INTO dotp_permissoes_nivel (permissao_nivel_id, permissao_recurso, permissao_acao, permissao_escopo) VALUES
(5, 'projeto', 'visualizar', 'proprio'),
(5, 'etapa', 'visualizar', 'proprio'),
(5, 'tarefa', 'visualizar', 'proprio'),
(5, 'tarefa', 'editar', 'proprio'),
(5, 'dashboard', 'acessar', 'tecnico')
ON DUPLICATE KEY UPDATE permissao_escopo = VALUES(permissao_escopo);

-- ===========================================
-- TABELA: Histórico de Movimentações
-- ===========================================
CREATE TABLE IF NOT EXISTS dotp_historico_movimentacoes (
    historico_id INT AUTO_INCREMENT PRIMARY KEY,
    historico_user_id INT NOT NULL,
    historico_unidade_origem_id INT,
    historico_unidade_destino_id INT NOT NULL,
    historico_cargo_anterior VARCHAR(100),
    historico_cargo_novo VARCHAR(100),
    historico_tipo_movimentacao ENUM('promocao', 'remocao', 'exoneracao', 'reintegracao', 'transferencia') NOT NULL,
    historico_data_movimentacao DATE NOT NULL,
    historico_observacao TEXT,
    historico_responsavel_id INT COMMENT 'Quem fez a movimentação',
    historico_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (historico_user_id) REFERENCES dotp_users(user_id),
    FOREIGN KEY (historico_unidade_origem_id) REFERENCES dotp_unidades_organizacionais(unidade_id) ON DELETE SET NULL,
    FOREIGN KEY (historico_unidade_destino_id) REFERENCES dotp_unidades_organizacionais(unidade_id),
    FOREIGN KEY (historico_responsavel_id) REFERENCES dotp_users(user_id),
    INDEX idx_user (historico_user_id),
    INDEX idx_data (historico_data_movimentacao),
    INDEX idx_tipo (historico_tipo_movimentacao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================
-- VIEW: Organograma Completo
-- ===========================================
CREATE OR REPLACE VIEW view_organograma AS
SELECT 
    u.unidade_id,
    u.unidade_nome,
    u.unidade_sigla,
    u.unidade_pai_id,
    u.unidade_nivel,
    u.unidade_ativa,
    u.unidade_responsavel_id,
    n.nivel_nome,
    n.nivel_titulo_responsavel,
    n.nivel_cor,
    pai.unidade_nome as unidade_pai_nome,
    pai.unidade_sigla as unidade_pai_sigla,
    COUNT(DISTINCT f.unidade_id) as total_filhas,
    COUNT(DISTINCT v.vinculo_id) as total_vinculos,
    COUNT(DISTINCT CASE WHEN v.vinculo_ativo = 1 THEN v.vinculo_id END) as vinculos_ativos,
    usr.user_first_name as responsavel_nome,
    usr.user_last_name as responsavel_sobrenome,
    usr.user_email as responsavel_email
FROM dotp_unidades_organizacionais u
LEFT JOIN dotp_niveis_hierarquicos n ON n.nivel_id = u.unidade_nivel
LEFT JOIN dotp_unidades_organizacionais pai ON pai.unidade_id = u.unidade_pai_id
LEFT JOIN dotp_unidades_organizacionais f ON f.unidade_pai_id = u.unidade_id
LEFT JOIN dotp_usuario_unidades v ON v.vinculo_unidade_id = u.unidade_id
LEFT JOIN dotp_users usr ON usr.user_id = u.unidade_responsavel_id
GROUP BY u.unidade_id;

-- ===========================================
-- VIEW: Usuários com Hierarquia Completa
-- ===========================================
CREATE OR REPLACE VIEW view_usuarios_hierarquia AS
SELECT 
    u.user_id,
    u.user_first_name,
    u.user_last_name,
    u.user_email,
    v.vinculo_id,
    v.vinculo_role,
    v.vinculo_ativo,
    v.vinculo_data_inicio,
    v.vinculo_data_fim,
    un.unidade_id,
    un.unidade_nome,
    un.unidade_sigla,
    un.unidade_nivel,
    n.nivel_nome as nivel_tipo,
    un_pai.unidade_id as secretaria_id,
    un_pai.unidade_nome as secretaria_nome,
    CASE 
        WHEN un.unidade_pai_id IS NULL THEN un.unidade_id
        WHEN un_pai.unidade_pai_id IS NULL THEN un_pai.unidade_id
        ELSE un_pai.unidade_pai_id
    END as prefeitura_id
FROM dotp_users u
LEFT JOIN dotp_usuario_unidades v ON v.vinculo_user_id = u.user_id
LEFT JOIN dotp_unidades_organizacionais un ON un.unidade_id = v.vinculo_unidade_id
LEFT JOIN dotp_niveis_hierarquicos n ON n.nivel_id = un.unidade_nivel
LEFT JOIN dotp_unidades_organizacionais un_pai ON un_pai.unidade_id = un.unidade_pai_id;

-- ===========================================
-- VIEW: Matriz de Permissões Consolidada
-- ===========================================
CREATE OR REPLACE VIEW view_permissoes_matriz AS
SELECT 
    n.nivel_id,
    n.nivel_nome,
    n.nivel_titulo_responsavel,
    pn.permissao_recurso,
    pn.permissao_acao,
    pn.permissao_escopo,
    CASE pn.permissao_escopo
        WHEN 'todos' THEN '✅ Todos'
        WHEN 'subordinados' THEN '🔶 Subordinados'
        WHEN 'unidade' THEN '🏢 Unidade'
        WHEN 'proprio' THEN '👤 Próprio'
        WHEN 'equipe' THEN '👥 Equipe'
        ELSE pn.permissao_escopo
    END as escopo_label
FROM dotp_niveis_hierarquicos n
LEFT JOIN dotp_permissoes_nivel pn ON pn.permissao_nivel_id = n.nivel_id AND pn.permissao_ativo = TRUE
WHERE n.nivel_ativo = TRUE
ORDER BY n.nivel_ordem, pn.permissao_recurso, pn.permissao_acao;

-- ===========================================
-- VIEW: Dashboard Administrativo
-- ===========================================
CREATE OR REPLACE VIEW view_dashboard_admin AS
SELECT 
    (SELECT COUNT(*) FROM dotp_niveis_hierarquicos WHERE nivel_ativo = TRUE) as total_niveis,
    (SELECT COUNT(*) FROM dotp_unidades_organizacionais WHERE unidade_ativa = TRUE) as total_unidades,
    (SELECT COUNT(*) FROM dotp_unidades_organizacionais WHERE unidade_ativa = TRUE AND unidade_pai_id IS NULL) as unidades_raiz,
    (SELECT COUNT(*) FROM dotp_usuario_unidades WHERE vinculo_ativo = TRUE) as total_vinculos,
    (SELECT COUNT(DISTINCT vinculo_user_id) FROM dotp_usuario_unidades WHERE vinculo_ativo = TRUE) as usuarios_vinculados,
    (SELECT COUNT(*) FROM dotp_users) as total_usuarios,
    (SELECT COUNT(*) FROM dotp_users u WHERE NOT EXISTS (
        SELECT 1 FROM dotp_usuario_unidades v WHERE v.vinculo_user_id = u.user_id AND v.vinculo_ativo = TRUE
    )) as usuarios_sem_vinculo,
    (SELECT COUNT(*) FROM dotp_historico_movimentacoes WHERE historico_created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as movimentacoes_30d;

-- Registrar migration
INSERT INTO migrations (migration, batch) 
VALUES ('003_create_admin_structure.sql', 2)
ON DUPLICATE KEY UPDATE executed_at = executed_at;
