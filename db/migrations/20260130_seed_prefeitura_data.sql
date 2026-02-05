-- Seed Data: Dados de demonstração para testes do sistema de prefeituras
-- Data: 2026-01-30
-- Autor: Kimi

-- Primeiro vamos descobrir os IDs das unidades inseridas
SET @unidade_mob = (SELECT unidade_id FROM dotp_unidades_organizacionais WHERE unidade_sigla = 'SEMOB');
SET @unidade_edu = (SELECT unidade_id FROM dotp_unidades_organizacionais WHERE unidade_sigla = 'SEMED');
SET @unidade_sau = (SELECT unidade_id FROM dotp_unidades_organizacionais WHERE unidade_sigla = 'SEMSA');
SET @unidade_amb = (SELECT unidade_id FROM dotp_unidades_organizacionais WHERE unidade_sigla = 'SEMA');

-- ===========================================
-- INSERIR PROGRAMAS
-- ===========================================
INSERT INTO `dotp_programas` (`codigo`, `nome`, `objetivo_estrategico`, `descricao`, `unidade_id`, `estado`, `percent_execucao`, `valor_orcamentario`, `data_inicio`, `data_fim`, `eixo_ppa`) VALUES
('MOB-2024', 'Programa de Mobilidade Urbana', 'Melhorar a mobilidade urbana da cidade', 'Programa focado em infraestrutura viária, transporte público e acessibilidade', @unidade_mob, 'Execucao', 45, 15000000.00, '2024-01-01', '2027-12-31', 'Infraestrutura e Mobilidade'),
('EDU-2024', 'Educação de Qualidade', 'Universalizar o acesso à educação de qualidade', 'Programa de construção e reforma de escolas, capacitação de professores', @unidade_edu, 'Execucao', 60, 25000000.00, '2024-01-01', '2027-12-31', 'Educação'),
('SAU-2024', 'Saúde para Todos', 'Ampliar o acesso à saúde pública', 'Construção de UBSs, aquisição de equipamentos médicos', @unidade_sau, 'Execucao', 30, 18000000.00, '2024-01-01', '2027-12-31', 'Saúde'),
('AMB-2024', 'Meio Ambiente Sustentável', 'Preservar o meio ambiente urbano', 'Programa de arborização, coleta seletiva e preservação de áreas verdes', @unidade_amb, 'Planejamento', 0, 8000000.00, '2024-06-01', '2027-12-31', 'Meio Ambiente');

-- ===========================================
-- INSERIR PROJETOS
-- ===========================================
-- Pegar os IDs dos programas
SET @prog_mob = LAST_INSERT_ID();
SET @prog_edu = @prog_mob + 1;
SET @prog_sau = @prog_mob + 2;
SET @prog_amb = @prog_mob + 3;

-- Projetos do programa MOB-2024 (Mobilidade)
INSERT INTO `dotp_projetos_prefeitura` (`programa_id`, `nome`, `descricao`, `tipo`, `unidade_id`, `coordenador_id`, `estado`, `percent_execucao`, `valor_previsto`, `data_prevista_inicio`, `data_prevista_fim`, `etapa_atual`, `situacao_orcamentaria`) VALUES
(@prog_mob, 'Pavimentação Avenida Brasil', 'Asfaltamento completo da Avenida Brasil com drenagem e iluminação', 'Obra', @unidade_mob, 1, 'Em_Andamento', 65, 3500000.00, '2024-03-01', '2025-06-30', 3, 'empenhado'),
(@prog_mob, 'Terminal de Ônibus Centro', 'Construção do novo terminal de ônibus do centro', 'Obra', @unidade_mob, 1, 'Atrasado', 30, 4200000.00, '2024-01-15', '2025-03-30', 2, 'empenhado'),
(@prog_mob, 'Ciclovia Leste-Oeste', 'Implantação de ciclovia ligando os bairros leste e oeste', 'Obra', @unidade_mob, 1, 'Nao_Iniciado', 0, 1800000.00, '2025-01-01', '2025-12-31', 1, 'nao_empenhado'),
(@prog_mob, 'Convênio Mobilidade Sustentável', 'Projeto de mobilidade sustentável em parceria com governo federal', 'Convenio', @unidade_mob, 1, 'Em_Andamento', 45, 2800000.00, '2024-06-01', '2026-05-30', 2, 'empenhado');

-- Projetos do programa EDU-2024 (Educação)
INSERT INTO `dotp_projetos_prefeitura` (`programa_id`, `nome`, `descricao`, `tipo`, `unidade_id`, `coordenador_id`, `estado`, `percent_execucao`, `valor_previsto`, `data_prevista_inicio`, `data_prevista_fim`, `etapa_atual`, `situacao_orcamentaria`) VALUES
(@prog_edu, 'Escola Municipal Profª Maria Silva', 'Construção de nova escola municipal no bairro Jardim das Flores', 'Obra', @unidade_edu, 1, 'Em_Andamento', 80, 5500000.00, '2024-02-01', '2025-08-30', 4, 'empenhado'),
(@prog_edu, 'Reforma Escola Central', 'Reforma completa da escola central do município', 'Obra', @unidade_edu, 1, 'Concluido', 100, 2200000.00, '2024-01-01', '2024-12-20', 5, 'pago'),
(@prog_edu, 'Emenda Parlamentar - Laboratórios', 'Aquisição de equipamentos para laboratórios de ciências', 'Emenda', @unidade_edu, 1, 'Em_Andamento', 40, 850000.00, '2024-08-01', '2025-02-28', 2, 'empenhado');

-- Projetos do programa SAU-2024 (Saúde)
INSERT INTO `dotp_projetos_prefeitura` (`programa_id`, `nome`, `descricao`, `tipo`, `unidade_id`, `coordenador_id`, `estado`, `percent_execucao`, `valor_previsto`, `data_prevista_inicio`, `data_prevista_fim`, `etapa_atual`, `situacao_orcamentaria`) VALUES
(@prog_sau, 'UBS Bairro Novo', 'Construção da Unidade Básica de Saúde do Bairro Novo', 'Obra', @unidade_sau, 1, 'Em_Andamento', 25, 3200000.00, '2024-06-01', '2025-12-31', 1, 'empenhado'),
(@prog_sau, 'Aquisição Ambulâncias', 'Aquisição de 5 novas ambulâncias para o SAMU', 'Compra', @unidade_sau, 1, 'Em_Andamento', 60, 1500000.00, '2024-09-01', '2025-03-31', 2, 'empenhado'),
(@prog_sau, 'Convênio Saúde da Família', 'Ampliação do programa saúde da família', 'Convenio', @unidade_sau, 1, 'Nao_Iniciado', 0, 2100000.00, '2025-02-01', '2026-01-31', 1, 'nao_empenhado');

-- Projoto em estado crítico (para testar alertas)
INSERT INTO `dotp_projetos_prefeitura` (`programa_id`, `nome`, `descricao`, `tipo`, `unidade_id`, `coordenador_id`, `estado`, `percent_execucao`, `valor_previsto`, `data_prevista_inicio`, `data_prevista_fim`, `etapa_atual`, `situacao_orcamentaria`, `justificativa_atraso`) VALUES
(@prog_mob, 'Ponte Rio Verde', 'Construção da ponte sobre o Rio Verde', 'Obra', @unidade_mob, 1, 'Atrasado', 15, 4800000.00, '2024-01-01', '2025-01-15', 1, 'empenhado', 'Atraso devido às chuvas fortes do primeiro semestre e dificuldades na liberação de licenças ambientais');

-- ===========================================
-- INSERIR ETAPAS
-- ===========================================
-- Pegar os IDs dos projetos inseridos
SET @proj_av_brasil = LAST_INSERT_ID() - 10;
SET @proj_terminal = @proj_av_brasil + 1;
SET @proj_ciclovia = @proj_av_brasil + 2;
SET @proj_conv_mob = @proj_av_brasil + 3;
SET @proj_escola = @proj_av_brasil + 4;
SET @proj_reforma = @proj_av_brasil + 5;
SET @proj_emenda = @proj_av_brasil + 6;
SET @proj_ubs = @proj_av_brasil + 7;
SET @proj_ambulancia = @proj_av_brasil + 8;
SET @proj_conv_sau = @proj_av_brasil + 9;
SET @proj_ponte = @proj_av_brasil + 10;

-- Etapas do projeto Pavimentação Avenida Brasil
INSERT INTO `dotp_etapas` (`projeto_id`, `numero`, `nome`, `descricao`, `estado`, `percent_conclusao`, `data_prevista_inicio`, `data_prevista_fim`) VALUES
(@proj_av_brasil, 1, 'Licenciamento e Aprovações', 'Obtenção de licenças e aprovações necessárias', 'Concluida', 100, '2024-03-01', '2024-04-30'),
(@proj_av_brasil, 2, 'Terraplanagem', 'Preparação do terreno', 'Concluida', 100, '2024-05-01', '2024-07-31'),
(@proj_av_brasil, 3, 'Drenagem e Saneamento', 'Execução da rede de drenagem', 'Em_Andamento', 60, '2024-08-01', '2025-01-31'),
(@proj_av_brasil, 4, 'Pavimentação', 'Asfaltamento da via', 'Nao_Iniciada', 0, '2025-02-01', '2025-05-31'),
(@proj_av_brasil, 5, 'Sinalização e Iluminação', 'Instalação de semáforos, placas e postes', 'Nao_Iniciada', 0, '2025-06-01', '2025-06-30');

-- Etapas do projeto Terminal de Ônibus
INSERT INTO `dotp_etapas` (`projeto_id`, `numero`, `nome`, `descricao`, `estado`, `percent_conclusao`, `data_prevista_inicio`, `data_prevista_fim`) VALUES
(@proj_terminal, 1, 'Demolição e Limpeza', 'Remoção das estruturas existentes', 'Concluida', 100, '2024-01-15', '2024-03-15'),
(@proj_terminal, 2, 'Fundações', 'Execução das fundações do terminal', 'Atrasada', 40, '2024-03-16', '2024-08-31'),
(@proj_terminal, 3, 'Estrutura', 'Construção da estrutura principal', 'Nao_Iniciada', 0, '2024-09-01', '2025-01-31'),
(@proj_terminal, 4, 'Acabamentos', 'Acabamentos e instalações', 'Nao_Iniciada', 0, '2025-02-01', '2025-03-30');

-- Etapas do projeto Escola Municipal
INSERT INTO `dotp_etapas` (`projeto_id`, `numero`, `nome`, `descricao`, `estado`, `percent_conclusao`, `data_prevista_inicio`, `data_prevista_fim`) VALUES
(@proj_escola, 1, 'Fundações', 'Execução das fundações', 'Concluida', 100, '2024-02-01', '2024-05-30'),
(@proj_escola, 2, 'Alvenaria', 'Construção das paredes', 'Concluida', 100, '2024-06-01', '2024-10-31'),
(@proj_escola, 3, 'Cobertura', 'Telhado e impermeabilização', 'Concluida', 100, '2024-11-01', '2025-01-31'),
(@proj_escola, 4, 'Instalações Elétricas e Hidráulicas', 'Instalações internas', 'Em_Andamento', 70, '2025-02-01', '2025-06-30'),
(@proj_escola, 5, 'Acabamentos e Mobiliário', 'Pintura, pisos e mobiliário escolar', 'Nao_Iniciada', 0, '2025-07-01', '2025-08-30');

-- Etapas do projeto UBS Bairro Novo
INSERT INTO `dotp_etapas` (`projeto_id`, `numero`, `nome`, `descricao`, `estado`, `percent_conclusao`, `data_prevista_inicio`, `data_prevista_fim`) VALUES
(@proj_ubs, 1, 'Licenciamento Ambiental', 'Obtenção de licenças', 'Em_Andamento', 80, '2024-06-01', '2024-09-30'),
(@proj_ubs, 2, 'Terraplanagem', 'Preparação do terreno', 'Nao_Iniciada', 0, '2024-10-01', '2024-12-31'),
(@proj_ubs, 3, 'Construção', 'Edificação da UBS', 'Nao_Iniciada', 0, '2025-01-01', '2025-10-31'),
(@proj_ubs, 4, 'Equipamentos', 'Aquisição e instalação de equipamentos', 'Nao_Iniciada', 0, '2025-11-01', '2025-12-31');

-- Etapas do projeto Ponte Rio Verde - Atrasado
INSERT INTO `dotp_etapas` (`projeto_id`, `numero`, `nome`, `descricao`, `estado`, `percent_conclusao`, `data_prevista_inicio`, `data_prevista_fim`) VALUES
(@proj_ponte, 1, 'Estudos e Projetos', 'Projetos estruturais e geotécnicos', 'Concluida', 100, '2024-01-01', '2024-04-30'),
(@proj_ponte, 2, 'Fundações das Pontes', 'Estacas e blocos de fundação', 'Atrasada', 30, '2024-05-01', '2024-11-30'),
(@proj_ponte, 3, 'Estrutura da Ponte', 'Vigas e tabuleiro', 'Nao_Iniciada', 0, '2024-12-01', '2025-03-31'),
(@proj_ponte, 4, 'Acessos e Acabamentos', 'Vias de acesso e proteções', 'Nao_Iniciada', 0, '2025-04-01', '2025-06-30');

-- ===========================================
-- INSERIR ALERTAS
-- ===========================================
INSERT INTO `dotp_alertas` (`destinatario_id`, `tipo`, `titulo`, `mensagem`, `projeto_id`, `programa_id`, `prioridade`, `lido`) VALUES
(1, 'Prazo', 'Projeto atrasado: Terminal de Ônibus Centro', 'O projeto Terminal de Ônibus Centro está atrasado em relação ao cronograma. Etapa atual: Fundações (40% concluída).', @proj_terminal, @prog_mob, 'Alta', 0),
(1, 'Prazo', 'Projeto crítico: Ponte Rio Verde', 'O projeto Ponte Rio Verde está com sérios atrasos. Justificativa: Atraso devido às chuvas fortes do primeiro semestre.', @proj_ponte, @prog_mob, 'Critica', 0),
(1, 'Orcamento', 'Alerta de Orçamento - Programa Mobilidade', 'O Programa de Mobilidade Urbana já consumiu 75% do orçamento previsto para o ano.', NULL, @prog_mob, 'Media', 0),
(1, 'Prazo', 'Convênio próximo ao vencimento', 'O Convênio Mobilidade Sustentável vence em 60 dias. Verificar necessidade de prorrogação.', @proj_conv_mob, @prog_mob, 'Alta', 0);
