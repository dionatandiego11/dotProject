<?php
require_once __DIR__ . '/../base.php';
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../classes/csscolor.class.php';
require_once __DIR__ . '/../includes/main_functions.php';
require_once __DIR__ . '/../includes/db_adodb.php';
require_once __DIR__ . '/../includes/db_connect.php';

use DotProject\Core\Database;

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    echo "ERROR [$errno]: $errstr in $errfile:$errline\n";
    return true;
});

function columnExists(Database $db, string $table, string $column): bool
{
    $sql = "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?";
    return ((int) ($db->fetchValue($sql, [$table, $column]) ?? 0)) > 0;
}

function findUnitId(Database $db, string $table, string $statusColumn, int|string $statusValue, string $name, ?int $parentId): ?int
{
    $whereParent = $parentId === null ? "unidade_pai_id IS NULL" : "unidade_pai_id = ?";
    $params = $parentId === null ? [$name, $statusValue] : [$name, $parentId, $statusValue];
    $sql = "SELECT unidade_id FROM {$table} WHERE unidade_nome = ? AND {$whereParent} AND {$statusColumn} = ? LIMIT 1";
    $row = $db->fetchOne($sql, $params);
    return $row ? (int) $row['unidade_id'] : null;
}

function insertUnit(Database $db, string $nivelColumn, string $statusColumn, int|string $statusValue, array $data): int
{
    $payload = [
        'unidade_pai_id' => $data['unidade_pai_id'],
        'unidade_nome' => $data['unidade_nome'],
        $nivelColumn => $data[$nivelColumn],
        'unidade_sigla' => $data['unidade_sigla'] ?? null,
        'unidade_descricao' => $data['unidade_descricao'] ?? null,
        'unidade_endereco' => $data['unidade_endereco'] ?? null,
        'unidade_email' => $data['unidade_email'] ?? null,
        'unidade_telefone' => $data['unidade_telefone'] ?? null,
        'unidade_responsavel_id' => $data['unidade_responsavel_id'] ?? null,
        $statusColumn => $statusValue,
        'unidade_pode_criar_projetos' => 1,
        'unidade_pode_criar_programas' => 0,
    ];

    $id = $db->insert('unidades_organizacionais', $payload);
    if (!$id) {
        $error = $db->getError();
        throw new RuntimeException('Falha ao inserir unidade: ' . ($error ?: 'erro desconhecido'));
    }
    return (int) $id;
}

function upsertUnit(Database $db, string $table, string $nivelColumn, string $statusColumn, int|string $statusValue, string $name, ?int $parentId, int $level): int
{
    $existing = findUnitId($db, $table, $statusColumn, $statusValue, $name, $parentId);
    if ($existing !== null) {
        return $existing;
    }

    return insertUnit($db, $nivelColumn, $statusColumn, $statusValue, [
        'unidade_nome' => $name,
        'unidade_pai_id' => $parentId,
        $nivelColumn => $level,
    ]);
}

function insertTree(Database $db, string $table, string $nivelColumn, string $statusColumn, int|string $statusValue, array $nodes, ?int $parentId, int $level): void
{
    foreach ($nodes as $node) {
        $name = $node['name'] ?? '';
        if ($name === '') {
            continue;
        }
        $currentLevel = min(5, $level);
        $id = upsertUnit($db, $table, $nivelColumn, $statusColumn, $statusValue, $name, $parentId, $currentLevel);
        if (!empty($node['children']) && is_array($node['children'])) {
            insertTree($db, $table, $nivelColumn, $statusColumn, $statusValue, $node['children'], $id, $currentLevel + 1);
        }
    }
}

try {
    $db = Database::getInstance();
    $table = $db->table('unidades_organizacionais');

    $nivelColumn = columnExists($db, $table, 'unidade_nivel_id') ? 'unidade_nivel_id' : 'unidade_nivel';
    $statusColumn = columnExists($db, $table, 'unidade_ativa') ? 'unidade_ativa' : 'unidade_status';
    $statusValue = $statusColumn === 'unidade_ativa' ? 1 : 'ativo';

    $rootName = 'Prefeitura Municipal';
    $rootId = upsertUnit($db, $table, $nivelColumn, $statusColumn, $statusValue, $rootName, null, 1);

    $tree = [
        [
            'name' => 'Secretaria de Administracao',
            'children' => [
                ['name' => 'Gabinete e Correios'],
                ['name' => 'Gestao de Projetos e Inovacoes Tecnologicas'],
                ['name' => 'SESMT'],
                ['name' => 'Cemiterio'],
                ['name' => 'Convenios e Apoio as Parcerias'],
                ['name' => 'Protocolo / Recepcao'],
                [
                    'name' => 'Recursos Humanos',
                    'children' => [
                        ['name' => 'Diretoria e Coordenacao'],
                        ['name' => 'Medicina do Trabalho'],
                    ],
                ],
                ['name' => 'Tecnologia da Informacao (T.I)'],
                [
                    'name' => 'Central de Compras, Licitacoes e Contratos',
                    'children' => [
                        ['name' => 'Compras'],
                        ['name' => 'Licitacao'],
                        ['name' => 'Contratos'],
                    ],
                ],
                ['name' => 'Patrimonio'],
                [
                    'name' => 'Almoxarifado',
                    'children' => [
                        ['name' => 'Arquivo'],
                    ],
                ],
                ['name' => 'Transportes'],
                ['name' => 'Manutencao Predial'],
                ['name' => 'Estagios'],
                ['name' => 'Rondas'],
            ],
        ],
        [
            'name' => 'Secretaria de Agricultura, Pecuaria e Abastecimento',
            'children' => [
                ['name' => 'Banco de Alimentos'],
                ['name' => 'Emater'],
                ['name' => 'Incra'],
                ['name' => 'Siat'],
                ['name' => 'Patrulha Mecanizada'],
                ['name' => 'IMA'],
                ['name' => 'Veterinaria'],
                [
                    'name' => 'Galpao do Aranha',
                    'children' => [
                        ['name' => 'Coordenacao'],
                        ['name' => 'Patrulha'],
                        ['name' => 'IMA (Galpao)'],
                        ['name' => 'Siat/Incra'],
                    ],
                ],
            ],
        ],
        [
            'name' => 'Controladoria',
            'children' => [
                ['name' => 'Controle Interno'],
                ['name' => 'Ouvidoria'],
                ['name' => 'Auditoria Interna'],
                ['name' => 'Transparencia e Integridade'],
                ['name' => 'Processos'],
            ],
        ],
        [
            'name' => 'Secretaria de Desenvolvimento Economico',
            'children' => [
                ['name' => 'Codege'],
                ['name' => 'Gestao de Projeto'],
                ['name' => 'Nucleo Juridico'],
                ['name' => 'Assessoria Politica'],
                [
                    'name' => 'Escritorio de Projetos',
                    'children' => [
                        ['name' => 'Turismo'],
                        ['name' => 'Terceiro Setor'],
                        ['name' => 'Integracao com o Sistema S'],
                        ['name' => 'Agricultura'],
                    ],
                ],
                [
                    'name' => 'Nucleo de Capacitacao e Formacoes',
                    'children' => [
                        ['name' => 'Educacao Qualificada Tecnica e Superior'],
                        ['name' => 'Diretoria de Cursos Profissionalizantes'],
                    ],
                ],
                [
                    'name' => 'Nucleo de Atracao de Empresas e Investimentos',
                    'children' => [
                        ['name' => 'Captacao de Empresas'],
                        ['name' => 'Analise de Concessoes e Incentivos Fiscais e Economicos'],
                        ['name' => 'Governanca Interna'],
                    ],
                ],
                [
                    'name' => 'Nucleo de Desenvolvimento Economico',
                    'children' => [
                        ['name' => 'Plano de Diversificacao Economica'],
                        ['name' => 'Economia Circular e Sustentavel'],
                        ['name' => 'Programas de Fomento e Programas de Credito'],
                        ['name' => 'Estatisticas e Empregabilidade'],
                    ],
                ],
            ],
        ],
        [
            'name' => 'Secretaria de Desenvolvimento Social',
            'children' => [
                ['name' => 'Administrativo'],
                ['name' => 'Apoio Juridico SMDS'],
                ['name' => 'Habitacao e Interesse Social'],
                ['name' => 'Diretoria Orcamentaria, Financeira e Fundos Especiais'],
                ['name' => 'Diretoria de Gestao do Trabalho e Educacao Permanente'],
                ['name' => 'Diretoria de Cadastro Unico'],
                ['name' => 'Diretoria de Normas e Regulamentacoes'],
                [
                    'name' => 'Protecao Social Basica',
                    'children' => [
                        ['name' => 'CRAS Interior (Aranha)'],
                        ['name' => 'CRAS Centro'],
                        ['name' => 'CRAS Casa Branca'],
                        ['name' => 'CRAS COHAB'],
                        ['name' => 'Programa Crianca Feliz'],
                    ],
                ],
                [
                    'name' => 'Protecao Social Especial',
                    'children' => [
                        ['name' => 'CREAS'],
                        ['name' => 'Casa da Crianca'],
                        ['name' => 'Acolhimento Institucional Municipal'],
                    ],
                ],
                ['name' => 'SINE'],
                ['name' => 'Casa dos Conselhos (Secretaria Executiva)'],
            ],
        ],
        [
            'name' => 'Secretaria de Educacao',
            'children' => [
                ['name' => 'Legislacao e Inspecao Escolar'],
                ['name' => 'Recursos Humanos'],
                [
                    'name' => 'Diretoria Administrativa e Financeira',
                    'children' => [
                        ['name' => 'Compras'],
                        ['name' => 'Alimentacao e Nutricao'],
                        ['name' => 'Almoxarifado e Deposito'],
                        ['name' => 'Manutencao e Suprimentos'],
                    ],
                ],
                [
                    'name' => 'Coordenacao Educacional / Projetos Educacionais',
                    'children' => [
                        ['name' => 'Educacao Infantil'],
                        ['name' => 'Ensino Fundamental I'],
                        ['name' => 'Ensino Fundamental II'],
                        ['name' => 'Educacao de Jovens e Adultos'],
                        ['name' => 'Educacao Especial e Inclusao'],
                    ],
                ],
                [
                    'name' => 'Apoio Administrativo',
                    'children' => [
                        ['name' => 'T.I.'],
                        ['name' => 'Transporte Escolar'],
                    ],
                ],
                ['name' => 'Unidades de Ensino Municipais'],
            ],
        ],
        [
            'name' => 'Secretaria de Esportes',
            'children' => [
                [
                    'name' => 'Departamento Administrativo',
                    'children' => [
                        ['name' => 'Supervisao de RH e Controle de Documentacao'],
                    ],
                ],
                [
                    'name' => 'Licitacao / Contratos',
                    'children' => [
                        ['name' => 'Licitacao'],
                        ['name' => 'Contrato'],
                    ],
                ],
                [
                    'name' => 'Evento / Alvara',
                    'children' => [
                        ['name' => 'Evento'],
                        ['name' => 'Alvara'],
                    ],
                ],
                ['name' => 'Transportes'],
                ['name' => 'Esportes Radicais'],
                ['name' => 'Academia'],
            ],
        ],
        [
            'name' => 'Secretaria de Fazenda',
            'children' => [
                ['name' => 'Departamento Administrativo'],
                [
                    'name' => 'Arrecadacao Fazendaria',
                    'children' => [
                        ['name' => 'Previsao e Recolhimento Impostos'],
                        ['name' => 'Controle da Divida Ativa'],
                        ['name' => 'Fiscalizacao'],
                    ],
                ],
                [
                    'name' => 'Orcamento',
                    'children' => [
                        ['name' => 'Elaboracao e Acompanhamento PPA, LDO, LOA'],
                        ['name' => 'Analise e Abertura das Suplementacoes'],
                        ['name' => 'Servico de Classificacao Contabil'],
                    ],
                ],
                [
                    'name' => 'Contabilidade',
                    'children' => [
                        ['name' => 'Execucao Orcamentaria'],
                        ['name' => 'Analise e Prestacao de Contas'],
                    ],
                ],
                [
                    'name' => 'Tesouraria',
                    'children' => [
                        ['name' => 'Execucao Financeira'],
                        ['name' => 'Analise e Controle Financeiro'],
                        ['name' => 'Fluxo de Caixa'],
                    ],
                ],
            ],
        ],
        [
            'name' => 'Secretaria de Seguranca Publica',
            'children' => [
                ['name' => 'Gabinete'],
                ['name' => 'Defesa Civil'],
                [
                    'name' => 'STRANSB (Transito)',
                    'children' => [
                        ['name' => 'Fiscalizacao da CAF, Contratos e Campo'],
                        ['name' => 'Fiscalizacao Taxi'],
                        ['name' => 'Analise Tecnica da Eng. de Transito'],
                        ['name' => 'Apoio em Intervencoes Viarias e Eventos'],
                        ['name' => 'Manutencao Viaria'],
                        ['name' => 'Des. de Minuta e Decreto'],
                        ['name' => 'Educacao no Transito'],
                        ['name' => 'Setransb Itinerante'],
                        ['name' => 'Estudo de Trafico'],
                    ],
                ],
                ['name' => 'Olho Vivo'],
                ['name' => 'Guarda Municipal'],
            ],
        ],
        [
            'name' => 'Secretaria de Meio Ambiente',
            'children' => [
                [
                    'name' => 'Departamento Administrativo',
                    'children' => [
                        ['name' => 'Financeiro'],
                        ['name' => 'Suprimentos'],
                        ['name' => 'RH/Patrimonio'],
                    ],
                ],
                [
                    'name' => 'Recepcao',
                    'children' => [
                        ['name' => 'Atendimento'],
                        ['name' => 'Protocolos (Geral/Tecnico)'],
                    ],
                ],
                [
                    'name' => 'Educacao Ambiental',
                    'children' => [
                        ['name' => 'Projetos/Palestras'],
                        ['name' => 'Coleta Seletiva (Sede e Distrito)'],
                    ],
                ],
                [
                    'name' => 'Licenciamento Ambiental',
                    'children' => [
                        ['name' => 'Licenc. Empreendimentos e Atividades'],
                        ['name' => 'Regulamentacao Ambiental'],
                        ['name' => 'DAIA (Plantio, Corte, Poda, Movimentacao Terra)'],
                    ],
                ],
                [
                    'name' => 'Fiscalizacao',
                    'children' => [
                        ['name' => 'Ativo'],
                        ['name' => 'Passivo'],
                    ],
                ],
                [
                    'name' => 'Centro de Bem Estar Animal (Canil)',
                    'children' => [
                        ['name' => 'Resgate de Animais'],
                        ['name' => 'Recebimento de Denuncias'],
                        ['name' => 'Castracao'],
                        ['name' => 'Castracao Movel'],
                        ['name' => 'Controle Zoonotico'],
                        ['name' => 'Eutanasia'],
                        ['name' => 'Proc. Cirurgicos'],
                        ['name' => 'Tratamento de Animais Internos'],
                    ],
                ],
                [
                    'name' => 'Supressao e Logistica',
                    'children' => [
                        ['name' => 'Transportes'],
                        ['name' => 'Ferramentaria (Gestao da Arborizacao e Jardinagem)'],
                    ],
                ],
                [
                    'name' => 'Posturas',
                    'children' => [
                        ['name' => 'Alvara'],
                    ],
                ],
            ],
        ],
        [
            'name' => 'Secretaria de Obras e Servicos Publicos',
            'children' => [
                ['name' => 'Secretaria'],
                ['name' => 'RH/Seguranca do Trabalho'],
                [
                    'name' => 'Coordenador de Engenharia',
                    'children' => [
                        ['name' => 'Engenheiros'],
                        ['name' => 'Arquitetos'],
                        ['name' => 'Tecnicos'],
                        ['name' => 'Administrativo'],
                    ],
                ],
                [
                    'name' => 'Encarregado Operacional',
                    'children' => [
                        ['name' => 'Servicos e Obras Gerais'],
                        ['name' => 'Fiscalizacao'],
                        ['name' => 'Orcamentos'],
                        ['name' => 'Topografia'],
                    ],
                ],
                [
                    'name' => 'Supervisao de Operacoes',
                    'children' => [
                        [
                            'name' => 'Coord. Oficina',
                            'children' => [
                                ['name' => 'Administrativo'],
                                ['name' => 'Operacional'],
                            ],
                        ],
                        [
                            'name' => 'Operacao Hidrossanitaria',
                            'children' => [
                                ['name' => 'Administrativo'],
                                ['name' => 'Operacional'],
                                [
                                    'name' => 'Coord. Agua e Energia',
                                    'children' => [
                                        ['name' => 'Apoio Tecnico'],
                                        ['name' => 'Coord. Op. Agua'],
                                        ['name' => 'Coord. Eletrica'],
                                    ],
                                ],
                            ],
                        ],
                        ['name' => 'Coord. Transportes'],
                        ['name' => 'Coord. Obras em Estradas e Vias Rurais'],
                        ['name' => 'Coord. Obras em Estradas e Vias Sede'],
                        ['name' => 'Limpeza e Conservacao de Vias Publicas'],
                        [
                            'name' => 'Limpeza Urbana',
                            'children' => [
                                ['name' => 'Administrativo'],
                            ],
                        ],
                        [
                            'name' => 'Coord. Almoxarifado',
                            'children' => [
                                ['name' => 'Operacional'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        [
            'name' => 'Secretaria de Turismo e Cultura',
            'children' => [
                ['name' => 'Departamento Administrativo'],
                [
                    'name' => 'Patrimonio',
                    'children' => [
                        ['name' => 'Sup. Casa Cultura'],
                        ['name' => 'Sup. Bens Tombados'],
                        ['name' => 'Sup. Patrimonio'],
                        ['name' => 'Aprovacao de Projetos'],
                    ],
                ],
                [
                    'name' => 'Cultura',
                    'children' => [
                        ['name' => 'Sup. Centro Cultural'],
                        ['name' => 'Sup. Biblioteca'],
                        ['name' => 'Realizacao de Eventos Culturais'],
                    ],
                ],
                [
                    'name' => 'Turismo',
                    'children' => [
                        ['name' => 'Sup. Pontos Turisticos'],
                        ['name' => 'Realizacao de Eventos Turisticos'],
                        ['name' => 'C.A.T (Centro de Atendimento ao Turista)'],
                    ],
                ],
                ['name' => 'Igualdade Racial'],
            ],
        ],
        [
            'name' => 'Secretaria de Planejamento',
            'children' => [
                ['name' => 'Departamento Administrativo'],
                [
                    'name' => 'Departamento Juridico',
                    'children' => [
                        ['name' => 'Juridico Geral'],
                        ['name' => 'Regularizacao Fundiaria'],
                    ],
                ],
                [
                    'name' => 'Projetos',
                    'children' => [
                        ['name' => 'Alvara de Construcao'],
                        ['name' => 'Aprovacao de Projetos'],
                        ['name' => 'Habite-se'],
                        ['name' => 'Alvara de Demolicao'],
                        ['name' => 'Certidao de Demolicao'],
                        ['name' => 'Declaracoes Diversas'],
                    ],
                ],
                [
                    'name' => 'Fiscalizacao',
                    'children' => [
                        [
                            'name' => 'Obras',
                            'children' => [
                                ['name' => 'Fiscal. Obras Particulares'],
                                ['name' => 'Parcelamento do Solo'],
                                ['name' => 'Invasao de Area Publicas'],
                            ],
                        ],
                        [
                            'name' => 'Posturas',
                            'children' => [
                                ['name' => 'Fiscalizacao'],
                                ['name' => 'Preventivo/Ostensivo'],
                                ['name' => 'Lotes e Logradouros Publicos'],
                                ['name' => 'Organizacao do Espaco Urbano Publico'],
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'Fundiario',
                    'children' => [
                        ['name' => 'Desmembramento/Remembramento'],
                        ['name' => 'Certidao de Numero'],
                        ['name' => 'Loteamento'],
                        ['name' => 'Informacao Basica'],
                        ['name' => 'Viabilidade'],
                        ['name' => 'Atendimento Diversos'],
                    ],
                ],
            ],
        ],
    ];

    insertTree($db, $table, $nivelColumn, $statusColumn, $statusValue, $tree, $rootId, 2);

    echo "Organograma importado com sucesso.\n";
} catch (Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
