-- Seed de Unidades Organizacionais com Hierarquia
-- Estrutura: Prefeitura > Secretaria > Coordenação > Departamento > Equipe Técnica
--              + Controladoria, Procuradoria (filhas de Prefeitura)

-- Primeiro, garantir que os níveis existam
INSERT IGNORE INTO dotp_niveis_hierarquicos (nivel_id, nivel_ordem, nivel_nome, nivel_titulo_responsavel, nivel_descricao, nivel_cor, nivel_ativo) VALUES
(1, 1, 'Prefeitura', 'Prefeito', 'Chefe do Executivo Municipal', '#dc2626', 1),
(2, 2, 'Secretaria', 'Secretário', 'Secretarias Municipais', '#2563eb', 1),
(3, 3, 'Coordenação', 'Coordenador', 'Coordenações e Gerências', '#0891b2', 1),
(4, 4, 'Departamento', 'Diretor', 'Departamentos e Divisões', '#7c3aed', 1),
(5, 5, 'Equipe Técnica', 'Técnico', 'Equipe Operacional', '#059669', 1);

-- Limpar unidades existentes (opcional - cuidado em produção)
-- DELETE FROM dotp_unidades_organizacionais WHERE unidade_id > 0;

-- Nível 1: Prefeitura (Raiz) - nivel_id = 1
INSERT INTO dotp_unidades_organizacionais 
    (unidade_nome, unidade_sigla, unidade_descricao, unidade_nivel_id, unidade_pai_id, unidade_status, unidade_pode_criar_projetos, unidade_pode_criar_programas)
VALUES 
    ('Prefeitura Municipal', 'PM', 'Administração Central da Prefeitura', 1, NULL, 'ativo', 1, 1);

SET @prefeitura_id = LAST_INSERT_ID();

-- Nível 2: Secretarias (filhas de Prefeitura) - nivel_id = 2
INSERT INTO dotp_unidades_organizacionais 
    (unidade_nome, unidade_sigla, unidade_descricao, unidade_nivel_id, unidade_pai_id, unidade_status, unidade_pode_criar_projetos)
VALUES 
    ('Secretaria Municipal de Administração', 'SEAD', 'Gestão administrativa e recursos humanos', 2, @prefeitura_id, 'ativo', 1),
    ('Secretaria Municipal de Educação', 'SEMED', 'Gestão da educação municipal', 2, @prefeitura_id, 'ativo', 1),
    ('Secretaria Municipal de Saúde', 'SMS', 'Gestão da saúde municipal', 2, @prefeitura_id, 'ativo', 1),
    ('Secretaria Municipal de Obras', 'SEMOB', 'Gestão de obras e infraestrutura', 2, @prefeitura_id, 'ativo', 1),
    ('Secretaria Municipal de Finanças', 'SEFIN', 'Gestão financeira e orçamentária', 2, @prefeitura_id, 'ativo', 1);

SET @sead_id = LAST_INSERT_ID();
SET @semed_id = @sead_id + 1;
SET @sms_id = @sead_id + 2;
SET @semob_id = @sead_id + 3;
SET @sefin_id = @sead_id + 4;

-- Nível 2: Controladoria (filha de Prefeitura) - nivel_id = 2
INSERT INTO dotp_unidades_organizacionais 
    (unidade_nome, unidade_sigla, unidade_descricao, unidade_nivel_id, unidade_pai_id, unidade_status, unidade_pode_criar_projetos)
VALUES 
    ('Controladoria Geral do Município', 'CGM', 'Fiscalização e controle interno', 2, @prefeitura_id, 'ativo', 0);

SET @cgm_id = LAST_INSERT_ID();

-- Nível 2: Procuradoria (filha de Prefeitura) - nivel_id = 2
INSERT INTO dotp_unidades_organizacionais 
    (unidade_nome, unidade_sigla, unidade_descricao, unidade_nivel_id, unidade_pai_id, unidade_status, unidade_pode_criar_projetos)
VALUES 
    ('Procuradoria Geral do Município', 'PGM', 'Assessoria jurídica e consultoria', 2, @prefeitura_id, 'ativo', 0);

SET @pgm_id = LAST_INSERT_ID();

-- Nível 3: Coordenações (filhas de Secretarias) - nivel_id = 3
INSERT INTO dotp_unidades_organizacionais 
    (unidade_nome, unidade_sigla, unidade_descricao, unidade_nivel_id, unidade_pai_id, unidade_status, unidade_pode_criar_projetos)
VALUES 
    -- Coordenações de SEAD
    ('Coordenadoria de Recursos Humanos', 'CRH-SEAD', 'Gestão de pessoas', 3, @sead_id, 'ativo', 0),
    ('Coordenadoria de Tecnologia da Informação', 'CTI-SEAD', 'Gestão de TI', 3, @sead_id, 'ativo', 1),
    ('Coordenadoria de Licitações', 'CLIC-SEAD', 'Gestão de compras', 3, @sead_id, 'ativo', 0),
    
    -- Coordenações de SEMED
    ('Coordenadoria de Ensino Fundamental', 'CEF-SEMED', 'Gestão do ensino fundamental', 3, @semed_id, 'ativo', 0),
    ('Coordenadoria de Educação Infantil', 'CEI-SEMED', 'Gestão da educação infantil', 3, @semed_id, 'ativo', 0),
    
    -- Coordenações de SMS
    ('Coordenadoria de Atenção Básica', 'CAB-SMS', 'Saúde da família', 3, @sms_id, 'ativo', 0),
    ('Coordenadoria de Vigilância em Saúde', 'CVS-SMS', 'Vigilância epidemiológica', 3, @sms_id, 'ativo', 0);

-- Pegar IDs das coordenações
SET @crh_id = (SELECT unidade_id FROM dotp_unidades_organizacionais WHERE unidade_sigla = 'CRH-SEAD');
SET @cti_id = (SELECT unidade_id FROM dotp_unidades_organizacionais WHERE unidade_sigla = 'CTI-SEAD');
SET @cef_id = (SELECT unidade_id FROM dotp_unidades_organizacionais WHERE unidade_sigla = 'CEF-SEMED');
SET @cab_id = (SELECT unidade_id FROM dotp_unidades_organizacionais WHERE unidade_sigla = 'CAB-SMS');

-- Nível 4: Departamentos/Unidades Operacionais (filhas de Coordenações) - nivel_id = 4
INSERT INTO dotp_unidades_organizacionais 
    (unidade_nome, unidade_sigla, unidade_descricao, unidade_nivel_id, unidade_pai_id, unidade_status, unidade_pode_criar_projetos)
VALUES 
    -- Departamentos de CRH
    ('Departamento de Folha de Pagamento', 'DFP-CRH', 'Cálculo e pagamento', 4, @crh_id, 'ativo', 0),
    ('Departamento de Benefícios', 'DBEN-CRH', 'Gestão de benefícios', 4, @crh_id, 'ativo', 0),
    
    -- Departamentos de CTI
    ('Departamento de Suporte Técnico', 'DST-CTI', 'Help desk', 4, @cti_id, 'ativo', 0),
    ('Departamento de Desenvolvimento', 'DDEV-CTI', 'Software', 4, @cti_id, 'ativo', 0),
    
    -- Departamentos de CEF
    ('Escola Municipal João Silva', 'EMJS', 'Ensino fundamental - Centro', 4, @cef_id, 'ativo', 0),
    ('Escola Municipal Maria Santos', 'EMMS', 'Ensino fundamental - Norte', 4, @cef_id, 'ativo', 0),
    
    -- Departamentos de CAB
    ('UBS Centro', 'UBS-CENTRO', 'Unidade de saúde - Centro', 4, @cab_id, 'ativo', 0),
    ('UBS Bairro Norte', 'UBS-NORTE', 'Unidade de saúde - Norte', 4, @cab_id, 'ativo', 0);

-- Verificar estrutura criada
SELECT 
    u.unidade_id,
    CONCAT(REPEAT('  ', n.nivel_ordem - 1), u.unidade_nome) as hierarquia_visual,
    u.unidade_sigla,
    n.nivel_nome as nivel,
    p.unidade_nome as unidade_pai
FROM dotp_unidades_organizacionais u
JOIN dotp_niveis_hierarquicos n ON u.unidade_nivel_id = n.nivel_id
LEFT JOIN dotp_unidades_organizacionais p ON u.unidade_pai_id = p.unidade_id
ORDER BY n.nivel_ordem, u.unidade_id;
