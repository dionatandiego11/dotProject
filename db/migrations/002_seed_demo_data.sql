-- ===========================================
-- SEED: Dados de Demonstração
-- ===========================================

-- Verifica se já existe dados
SET @ existe_ppa = (SELECT COUNT(*) FROM ppa);

-- Só insere se não houver dados
INSERT IGNORE INTO dotp_unidades_organizacionais (unidade_pai_id, unidade_nome, unidade_nivel, unidade_sigla) VALUES
(1, 'Secretaria de Obras e Infraestrutura', 2, 'SOB'),
(1, 'Secretaria de Saúde', 2, 'SES'),
(1, 'Secretaria de Educação', 2, 'SED'),
(1, 'Secretaria de Finanças', 2, 'SEF');

-- Coordenações (nível 3)
INSERT IGNORE INTO dotp_unidades_organizacionais (unidade_pai_id, unidade_nome, unidade_nivel) VALUES
(2, 'Coordenação de Infraestrutura Urbana', 3),
(2, 'Coordenação de Manutenção Viária', 3),
(3, 'Coordenação de Hospitalares', 3),
(4, 'Coordenação de Escolas Municipais', 3);

-- Criar PPA de exemplo (se não existir)
INSERT INTO ppa (nome, periodo_inicio, periodo_fim, estado, objetivo_geral, prefeito_id)
SELECT 
    'PPA 2025-2028 - Municipal', 
    2025, 
    2028, 
    'Vigente',
    'Melhorar a infraestrutura urbana, saúde e educação do município',
    1
WHERE @existe_ppa = 0;

SET @ ppa_id = (SELECT id FROM ppa WHERE estado = 'Vigente' LIMIT 1);

-- Programas de exemplo
INSERT INTO programas (ppa_id, nome, objetivo, unidade_id, estado, prioridade)
SELECT 
    @ppa_id,
    'Requalificação Viária',
    'Asfaltar e recuperar vias urbanas prioritárias',
    2,
    'Ativo',
    'Alta'
WHERE @existe_ppa = 0;

INSERT INTO programas (ppa_id, nome, objetivo, unidade_id, estado, prioridade)
SELECT 
    @ppa_id,
    'Saúde para Todos',
    'Ampliar o atendimento na rede pública de saúde',
    3,
    'Ativo',
    'Alta'
WHERE @existe_ppa = 0;

-- Projetos de exemplo
SET @ programa_obras = (SELECT id FROM programas WHERE nome = 'Requalificação Viária' LIMIT 1);

INSERT INTO projetos (programa_id, nome, tipo, estado, etapa_atual, unidade_id, coordenador_id, valor_previsto, data_prevista_inicio, data_prevista_fim)
SELECT 
    @programa_obras,
    'Asfaltamento Rua das Flores',
    'Obra',
    'Execucao',
    3,
    5,
    1,
    450000.00,
    '2025-03-01',
    '2025-08-30'
WHERE @existe_ppa = 0 AND @programa_obras IS NOT NULL;

INSERT INTO projetos (programa_id, nome, tipo, estado, etapa_atual, unidade_id, coordenador_id, valor_previsto, data_prevista_inicio, data_prevista_fim)
SELECT 
    @programa_obras,
    'Reforma Praça Central',
    'Obra',
    'Licitacao',
    2,
    5,
    1,
    280000.00,
    '2025-06-01',
    '2025-12-15'
WHERE @existe_ppa = 0 AND @programa_obras IS NOT NULL;

-- Criar etapas para os projetos
SET @ projeto1 = (SELECT id FROM projetos WHERE nome = 'Asfaltamento Rua das Flores' LIMIT 1);
SET @ projeto2 = (SELECT id FROM projetos WHERE nome = 'Reforma Praça Central' LIMIT 1);

-- Etapas do projeto 1 (em execução, etapa 3)
INSERT INTO etapas (projeto_id, numero, nome, estado, data_prevista_inicio, data_prevista_fim, data_real_inicio, data_real_fim, percent_conclusao)
SELECT @projeto1, 1, 'Planejamento', 'Concluida', '2025-03-01', '2025-03-31', '2025-03-01', '2025-03-28', 100
WHERE @projeto1 IS NOT NULL AND @existe_ppa = 0;

INSERT INTO etapas (projeto_id, numero, nome, estado, data_prevista_inicio, data_prevista_fim, data_real_inicio, data_real_fim, percent_conclusao)
SELECT @projeto1, 2, 'Licitação', 'Concluida', '2025-04-01', '2025-05-15', '2025-04-01', '2025-05-10', 100
WHERE @projeto1 IS NOT NULL AND @existe_ppa = 0;

INSERT INTO etapas (projeto_id, numero, nome, estado, data_prevista_inicio, data_prevista_fim, data_real_inicio, percent_conclusao)
SELECT @projeto1, 3, 'Execução', 'Em_Andamento', '2025-05-16', '2025-08-30', '2025-05-20', 45
WHERE @projeto1 IS NOT NULL AND @existe_ppa = 0;

INSERT INTO etapas (projeto_id, numero, nome, estado, data_prevista_inicio, data_prevista_fim, percent_conclusao)
SELECT @projeto1, 4, 'Medição', 'Nao_Iniciada', '2025-09-01', '2025-09-15', 0
WHERE @projeto1 IS NOT NULL AND @existe_ppa = 0;

INSERT INTO etapas (projeto_id, numero, nome, estado, data_prevista_inicio, data_prevista_fim, percent_conclusao)
SELECT @projeto1, 5, 'Pagamento', 'Nao_Iniciada', '2025-09-16', '2025-09-30', 0
WHERE @projeto1 IS NOT NULL AND @existe_ppa = 0;

-- Etapas do projeto 2 (em licitação)
INSERT INTO etapas (projeto_id, numero, nome, estado, data_prevista_inicio, data_prevista_fim, data_real_inicio, data_real_fim, percent_conclusao)
SELECT @projeto2, 1, 'Planejamento', 'Concluida', '2025-06-01', '2025-06-30', '2025-06-01', '2025-06-25', 100
WHERE @projeto2 IS NOT NULL AND @existe_ppa = 0;

INSERT INTO etapas (projeto_id, numero, nome, estado, data_prevista_inicio, data_prevista_fim, data_real_inicio, percent_conclusao)
SELECT @projeto2, 2, 'Licitação', 'Em_Andamento', '2025-07-01', '2025-08-15', '2025-07-01', 30
WHERE @projeto2 IS NOT NULL AND @existe_ppa = 0;

INSERT INTO etapas (projeto_id, numero, nome, estado, data_prevista_inicio, data_prevista_fim, percent_conclusao)
SELECT @projeto2, 3, 'Execução', 'Nao_Iniciada', '2025-08-16', '2025-11-30', 0
WHERE @projeto2 IS NOT NULL AND @existe_ppa = 0;

INSERT INTO etapas (projeto_id, numero, nome, estado, data_prevista_inicio, data_prevista_fim, percent_conclusao)
SELECT @projeto2, 4, 'Medição', 'Nao_Iniciada', '2025-12-01', '2025-12-10', 0
WHERE @projeto2 IS NOT NULL AND @existe_ppa = 0;

INSERT INTO etapas (projeto_id, numero, nome, estado, data_prevista_inicio, data_prevista_fim, percent_conclusao)
SELECT @projeto2, 5, 'Pagamento', 'Nao_Iniciada', '2025-12-11', '2025-12-15', 0
WHERE @projeto2 IS NOT NULL AND @existe_ppa = 0;

-- Criar tarefas de exemplo (se existir tabela dotp_tasks)
INSERT INTO dotp_tasks (task_name, task_description, task_project, task_owner, task_assigned_to, task_start_date, task_end_date, task_priority, estado, etapa_id)
SELECT 
    'Preparar base da via',
    'Remover entulhos e nivelar terreno',
    @projeto1,
    1,
    1,
    '2025-05-20',
    '2025-06-10',
    2,
    'Concluida',
    3
WHERE @projeto1 IS NOT NULL AND @existe_ppa = 0;

INSERT INTO dotp_tasks (task_name, task_description, task_project, task_owner, task_assigned_to, task_start_date, task_end_date, task_priority, estado, etapa_id)
SELECT 
    'Aplicar camada de asfalto',
    'Aplicação de 5cm de asfalto CBUQ',
    @projeto1,
    1,
    1,
    '2025-06-11',
    '2025-07-15',
    2,
    'Em_Andamento',
    3
WHERE @projeto1 IS NOT NULL AND @existe_ppa = 0;

INSERT INTO dotp_tasks (task_name, task_description, task_project, task_owner, task_assigned_to, task_start_date, task_end_date, task_priority, estado, etapa_id)
SELECT 
    'Pintura de sinalização',
    'Faixas de pedestre e divisão de pista',
    @projeto1,
    1,
    1,
    '2025-07-16',
    '2025-07-25',
    1,
    'A_Fazer',
    3
WHERE @projeto1 IS NOT NULL AND @existe_ppa = 0;

-- Atualizar percentuais dos projetos
UPDATE projetos p
SET percent_execucao = (
    SELECT ((e.numero - 1) / 5 * 100) + (e.percent_conclusao / 5)
    FROM etapas e 
    WHERE e.projeto_id = p.id AND e.numero = p.etapa_atual
)
WHERE EXISTS (SELECT 1 FROM etapas e WHERE e.projeto_id = p.id);

-- Atualizar percentuais dos programas
UPDATE programas p
SET percent_execucao = COALESCE((
    SELECT AVG(percent_execucao) 
    FROM projetos 
    WHERE programa_id = p.id
), 0);
