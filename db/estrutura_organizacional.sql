-- ===========================================
-- Tabelas de Estrutura Organizacional
-- Módulo Administrativo para Prefeituras
-- ===========================================

-- Tabela de Níveis Hierárquicos (configurável)
CREATE TABLE IF NOT EXISTS dotp_niveis_hierarquicos (
    nivel_id INT AUTO_INCREMENT PRIMARY KEY,
    nivel_ordem INT NOT NULL UNIQUE,
    nivel_nome VARCHAR(100) NOT NULL,
    nivel_titulo_responsavel VARCHAR(100),
    nivel_descricao TEXT,
    nivel_cor VARCHAR(7) DEFAULT '#3b82f6',
    nivel_ativo BOOLEAN DEFAULT TRUE,
    nivel_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    nivel_updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de Unidades Organizacionais
CREATE TABLE IF NOT EXISTS dotp_unidades_organizacionais (
    unidade_id INT AUTO_INCREMENT PRIMARY KEY,
    unidade_nivel_id INT NOT NULL,
    unidade_pai_id INT NULL,
    unidade_nome VARCHAR(255) NOT NULL,
    unidade_sigla VARCHAR(50),
    unidade_descricao TEXT,
    unidade_endereco VARCHAR(255),
    unidade_email VARCHAR(100),
    unidade_telefone VARCHAR(20),
    unidade_responsavel_id INT NULL,
    unidade_pode_criar_projetos BOOLEAN DEFAULT TRUE,
    unidade_pode_criar_programas BOOLEAN DEFAULT FALSE,
    unidade_aprova_pagamentos BOOLEAN DEFAULT FALSE,
    unidade_aprova_contratacoes BOOLEAN DEFAULT FALSE,
    unidade_status ENUM('ativo', 'inativo', 'em_reorganizacao') DEFAULT 'ativo',
    unidade_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    unidade_updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (unidade_nivel_id) REFERENCES dotp_niveis_hierarquicos(nivel_id),
    FOREIGN KEY (unidade_pai_id) REFERENCES dotp_unidades_organizacionais(unidade_id),
    FOREIGN KEY (unidade_responsavel_id) REFERENCES dotp_users(user_id)
);

-- Tabela de Vínculo Usuário-Unidade
CREATE TABLE IF NOT EXISTS dotp_usuario_unidades (
    vinculo_id INT AUTO_INCREMENT PRIMARY KEY,
    vinculo_user_id INT NOT NULL,
    vinculo_unidade_id INT NOT NULL,
    vinculo_cargo VARCHAR(100),
    vinculo_nivel_acesso INT DEFAULT 3,
    vinculo_is_principal BOOLEAN DEFAULT FALSE,
    vinculo_data_inicio DATE,
    vinculo_data_fim DATE NULL,
    vinculo_status ENUM('ativo', 'afastado', 'substituto', 'inativo') DEFAULT 'ativo',
    vinculo_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    vinculo_updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vinculo_user_id) REFERENCES dotp_users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (vinculo_unidade_id) REFERENCES dotp_unidades_organizacionais(unidade_id) ON DELETE CASCADE,
    UNIQUE KEY unique_usuario_unidade (vinculo_user_id, vinculo_unidade_id)
);

-- Tabela de Permissões por Nível (template)
CREATE TABLE IF NOT EXISTS dotp_permissoes_nivel (
    permissao_id INT AUTO_INCREMENT PRIMARY KEY,
    permissao_nivel_id INT NOT NULL,
    permissao_recurso VARCHAR(100) NOT NULL,
    permissao_acao VARCHAR(50) NOT NULL,
    permissao_escopo VARCHAR(50) NOT NULL,
    permissao_ativo BOOLEAN DEFAULT TRUE,
    permissao_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (permissao_nivel_id) REFERENCES dotp_niveis_hierarquicos(nivel_id) ON DELETE CASCADE,
    UNIQUE KEY unique_permissao_nivel (permissao_nivel_id, permissao_recurso, permissao_acao)
);

-- Tabela de Histórico de Movimentações
CREATE TABLE IF NOT EXISTS dotp_historico_movimentacoes (
    historico_id INT AUTO_INCREMENT PRIMARY KEY,
    historico_user_id INT NOT NULL,
    historico_unidade_origem_id INT,
    historico_unidade_destino_id INT NOT NULL,
    historico_cargo_anterior VARCHAR(100),
    historico_cargo_novo VARCHAR(100),
    historico_tipo_movimentacao ENUM('promocao', 'remocao', 'exoneracao', 'reintegracao'),
    historico_data_movimentacao DATE,
    historico_observacao TEXT,
    historico_responsavel_id INT,
    historico_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (historico_user_id) REFERENCES dotp_users(user_id),
    FOREIGN KEY (historico_unidade_origem_id) REFERENCES dotp_unidades_organizacionais(unidade_id),
    FOREIGN KEY (historico_unidade_destino_id) REFERENCES dotp_unidades_organizacionais(unidade_id),
    FOREIGN KEY (historico_responsavel_id) REFERENCES dotp_users(user_id)
);

-- ===========================================
-- Dados Iniciais
-- ===========================================

-- Níveis hierárquicos padrão para prefeituras
INSERT INTO dotp_niveis_hierarquicos (nivel_ordem, nivel_nome, nivel_titulo_responsavel, nivel_descricao, nivel_cor) VALUES
(1, 'Prefeitura', 'Prefeito', 'Chefe do Executivo Municipal', '#dc2626'),
(2, 'Secretaria', 'Secretário', 'Secretarias Municipais', '#2563eb'),
(3, 'Coordenação', 'Coordenador', 'Coordenações e Gerências', '#0891b2'),
(4, 'Departamento', 'Diretor', 'Departamentos e Divisões', '#7c3aed'),
(5, 'Equipe Técnica', 'Técnico', 'Equipe Operacional', '#059669')
ON DUPLICATE KEY UPDATE 
    nivel_titulo_responsavel = VALUES(nivel_titulo_responsavel),
    nivel_descricao = VALUES(nivel_descricao);

-- Permissões padrão por nível
INSERT INTO dotp_permissoes_nivel (permissao_nivel_id, permissao_recurso, permissao_acao, permissao_escopo) VALUES
-- Prefeito (nível 1)
(1, 'projeto', 'visualizar', 'todos'),
(1, 'projeto', 'aprovar', 'todos'),
(1, 'programa', 'visualizar', 'todos'),
(1, 'relatorio', 'visualizar', 'todos'),
(1, 'alerta', 'visualizar', 'todos'),
(1, 'admin', 'acessar', 'todos'),
-- Secretário (nível 2)
(2, 'projeto', 'visualizar', 'unidade'),
(2, 'projeto', 'criar', 'unidade'),
(2, 'projeto', 'editar', 'unidade'),
(2, 'projeto', 'aprovar', 'unidade'),
(2, 'programa', 'visualizar', 'unidade'),
(2, 'programa', 'criar', 'unidade'),
(2, 'programa', 'editar', 'unidade'),
-- Coordenador (nível 3)
(3, 'projeto', 'visualizar', 'subordinados'),
(3, 'projeto', 'criar', 'unidade'),
(3, 'projeto', 'editar', 'proprio'),
(3, 'programa', 'visualizar', 'proprio'),
(3, 'tarefa', 'visualizar', 'equipe'),
(3, 'tarefa', 'criar', 'equipe'),
(3, 'tarefa', 'editar', 'equipe'),
-- Departamento/Diretor (nível 4)
(4, 'projeto', 'visualizar', 'subordinados'),
(4, 'projeto', 'editar', 'proprio'),
(4, 'tarefa', 'visualizar', 'equipe'),
(4, 'tarefa', 'editar', 'equipe'),
-- Técnico (nível 5)
(5, 'projeto', 'visualizar', 'atribuidos'),
(5, 'tarefa', 'visualizar', 'atribuidos'),
(5, 'tarefa', 'executar', 'proprio')
ON DUPLICATE KEY UPDATE permissao_escopo = VALUES(permissao_escopo);

-- Unidade raiz (Prefeitura)
INSERT INTO dotp_unidades_organizacionais (
    unidade_nivel_id, unidade_pai_id, unidade_nome, unidade_sigla, 
    unidade_descricao, unidade_pode_criar_projetos, unidade_pode_criar_programas
) VALUES (
    1, NULL, 'Prefeitura Municipal', 'PM', 
    'Administração Municipal - Executivo', TRUE, TRUE
) ON DUPLICATE KEY UPDATE unidade_nome = VALUES(unidade_nome);
