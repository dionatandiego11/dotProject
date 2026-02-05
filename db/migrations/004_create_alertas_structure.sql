-- ===========================================
-- MIGRATION: Sistema de Alertas
-- ===========================================

CREATE TABLE IF NOT EXISTS dotp_alertas (
    alerta_id INT AUTO_INCREMENT PRIMARY KEY,
    alerta_tipo VARCHAR(50) NOT NULL,
    alerta_titulo VARCHAR(255) NOT NULL,
    alerta_descricao TEXT,
    alerta_projeto_id INT NULL,
    alerta_etapa_id INT NULL,
    alerta_programa_id INT NULL,
    alerta_destinatario_id INT NOT NULL,
    alerta_unidade_id INT NULL,
    alerta_prioridade ENUM('baixa', 'media', 'alta', 'critica') DEFAULT 'media',
    alerta_lido BOOLEAN DEFAULT FALSE,
    alerta_data_leitura TIMESTAMP NULL,
    alerta_acao_requerida VARCHAR(255),
    alerta_link_acao VARCHAR(500),
    alerta_data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_destinatario (alerta_destinatario_id),
    INDEX idx_destinatario_lido (alerta_destinatario_id, alerta_lido),
    INDEX idx_tipo (alerta_tipo),
    INDEX idx_prioridade (alerta_prioridade),
    INDEX idx_projeto (alerta_projeto_id),
    INDEX idx_data_criacao (alerta_data_criacao),
    INDEX idx_nao_lidos (alerta_destinatario_id, alerta_prioridade, alerta_lido)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registrar migration
INSERT INTO migrations (migration, batch) 
VALUES ('004_create_alertas_structure.sql', 3)
ON DUPLICATE KEY UPDATE executed_at = executed_at;
