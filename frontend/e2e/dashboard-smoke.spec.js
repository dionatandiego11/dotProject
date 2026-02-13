import { expect, test } from '@playwright/test'

const ACCESS_TOKEN = 'smoke-e2e-token'
const APP_URL = process.env.E2E_BASE_URL || 'http://127.0.0.1:5173'

const profileFixtures = {
    prefeito: {
        user: {
            id: 101,
            username: 'prefeito.smoke',
            first_name: 'Prefeito',
            last_name: 'Smoke',
            profile: 'prefeito',
            role: 'prefeito',
            user_type: 2,
        },
        dashboard: {
            data: {
                perfil: 'prefeito',
                source: 'modern_tables',
                ppa_execucao: {
                    total_programas: 4,
                    concluidos: 1,
                    criticos: 1,
                    atencao: 1,
                    percentual_medio: 62.5,
                },
                projetos_status: [
                    { estado: 'Em_Andamento', total: 3 },
                    { estado: 'Atrasado', total: 1 },
                    { estado: 'Concluido', total: 1 },
                ],
                por_secretaria: [
                    {
                        unidade_id: 10,
                        unidade_nome: 'Secretaria de Obras',
                        unidade_sigla: 'OBR',
                        total_projetos: 3,
                        atrasados: 1,
                        concluidos: 1,
                        percentual_execucao: 58.2,
                    },
                ],
                obras_atrasadas: [
                    {
                        id: 9001,
                        nome: 'Obra Smoke Prefeito',
                        estado: 'Atrasado',
                        secretaria: 'Secretaria de Obras',
                        data_prevista_fim: '2030-01-15',
                        dias_atraso: 3,
                    },
                ],
                convenios_vencer: [],
                emendas: {
                    total: 2,
                    executadas: 1,
                    em_risco: 1,
                },
                timeline_30dias: [],
                orcamento: {
                    previsto: 1000000,
                    empenhado: 650000,
                    pago: 420000,
                    percentual_executado: 42,
                },
            },
        },
        dashboardAlertas: {
            data: [
                {
                    id: 5001,
                    titulo: 'Alerta Smoke Prefeito',
                    mensagem: 'Acompanhar projeto com atraso',
                    prioridade: 'alta',
                    created_at: '2030-01-01 09:00:00',
                    lido: 0,
                },
            ],
            estatisticas: {
                total: 1,
                nao_lidos: 1,
            },
        },
        expected: [
            'Dashboard Executivo',
            'Obra Smoke Prefeito',
        ],
    },
    secretario: {
        user: {
            id: 202,
            username: 'secretario.smoke',
            first_name: 'Secretario',
            last_name: 'Smoke',
            profile: 'secretario',
            role: 'secretario',
            user_type: 2,
        },
        dashboard: {
            data: {
                perfil: 'secretario',
                source: 'modern_tables',
                unidade_id: 20,
                unidade_nome: 'Secretaria de Educação',
                programas: [
                    {
                        id: 1010,
                        nome: 'Programa Smoke Educação',
                        estado: 'Execucao',
                        percent_execucao: 66,
                        total_projetos: 2,
                    },
                ],
                projetos_resumo: [
                    { estado: 'Em_Andamento', total: 1 },
                    { estado: 'Atrasado', total: 1 },
                ],
                projetos_atencao: [
                    {
                        id: 9100,
                        nome: 'Projeto Smoke Secretario',
                        estado: 'Atrasado',
                        percent_execucao: 45,
                        programa_nome: 'Programa Smoke Educação',
                    },
                ],
                coordenadores: [
                    {
                        nome: 'Coord A',
                        total_projetos: 2,
                        em_dia: 1,
                        atrasados: 1,
                        percentual: 60,
                    },
                ],
                alertas: {
                    total: 2,
                    nao_lidos: 1,
                },
            },
        },
        dashboardAlertas: {
            data: [
                {
                    id: 6001,
                    titulo: 'Alerta Smoke Secretario',
                    prioridade: 'media',
                    created_at: '2030-01-01 10:00:00',
                    lido: 0,
                },
            ],
            estatisticas: {
                total: 1,
                nao_lidos: 1,
            },
        },
        expected: [
            'Secretaria de Educação',
            'Programa Smoke Educação',
        ],
    },
    coordenador: {
        user: {
            id: 303,
            username: 'coordenador.smoke',
            first_name: 'Coordenador',
            last_name: 'Smoke',
            profile: 'coordenador',
            role: 'coordenador',
            user_type: 2,
        },
        dashboard: {
            data: {
                perfil: 'coordenador',
                source: 'modern_tables',
                resumo: {
                    total_projetos: 3,
                    concluidos: 1,
                    atrasados: 1,
                    em_andamento: 2,
                    percentual_medio: 61,
                },
                projetos: [
                    {
                        id: 9200,
                        nome: 'Projeto Smoke Coordenador',
                        estado: 'Atrasado',
                        percent_execucao: 40,
                        programa_nome: 'Programa Obras',
                    },
                ],
                proximas_etapas: [
                    {
                        projeto: 'Projeto Smoke Coordenador',
                        etapa: 'Medição',
                        prazo: '2030-01-12',
                        status: 'atencao',
                    },
                ],
                equipe: [
                    {
                        user_id: 304,
                        nome: 'Tecnico Smoke',
                        tarefas_ativas: 2,
                        concluidas: 4,
                    },
                ],
            },
        },
        expected: [
            'Coordenador',
            'Projeto Smoke Coordenador',
        ],
    },
    tecnico: {
        user: {
            id: 404,
            username: 'tecnico.smoke',
            first_name: 'Tecnico',
            last_name: 'Smoke',
            profile: 'tecnico',
            role: 'tecnico',
            user_type: 2,
        },
        dashboard: {
            data: {
                perfil: 'tecnico',
                source: 'base_tables',
                resumo: {
                    total: 5,
                    concluidas: 2,
                    pendentes: 3,
                    atrasadas: 1,
                },
                tarefas_prioritarias: [
                    {
                        id: 9300,
                        nome: 'Tarefa Smoke Técnico',
                        projeto: 'Projeto Técnico',
                        urgencia: 'urgente',
                    },
                ],
                concluidas_semana: [],
                projetos: [
                    {
                        id: 9301,
                        nome: 'Projeto Técnico',
                        percent_execucao: 55,
                        minhas_tarefas: 3,
                    },
                ],
                produtividade_30d: [
                    {
                        data: '2030-01-01',
                        tarefas_concluidas: 2,
                    },
                ],
            },
        },
        expected: [
            'Tecnico Smoke',
            'Tarefa Smoke Técnico',
        ],
    },
    controlador: {
        user: {
            id: 505,
            username: 'controlador.smoke',
            first_name: 'Controlador',
            last_name: 'Smoke',
            profile: 'controlador',
            role: 'controlador',
            user_type: 2,
        },
        dashboard: {
            data: {
                perfil: 'controlador',
                source: 'modern_tables',
                alertas_conformidade: {
                    sem_prestacao_contas: 2,
                    execucao_acima_cronograma: 1,
                    diferenca_empenho_execucao: 1,
                },
                panorama_secretarias: [
                    {
                        unidade_id: 77,
                        unidade_nome: 'Secretaria Smoke Controle',
                        total_projetos: 5,
                        alertas: 2,
                        execucao_media: 49,
                    },
                ],
                irregularidades: [
                    {
                        id: 9400,
                        nome: 'Projeto Irregular Smoke',
                        irregularidade: 'Execução acima do cronograma',
                    },
                ],
                convenios_prestacao_pendente: [],
            },
        },
        expected: [
            'Controladoria Interna',
            'Secretaria Smoke Controle',
        ],
    },
}

async function mockCommonApi(page, fixture, profile) {
    await page.addInitScript((token) => {
        window.localStorage.setItem('dp_token', token)
    }, ACCESS_TOKEN)

    await page.route('**/api/v1/auth/me', async (route) => {
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({ data: fixture.user }),
        })
    })

    await page.route('**/api/v1/dashboard/status', async (route) => {
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                status: 'ok',
                perfil: profile,
                modern_tables: true,
                timestamp: '2030-01-01 09:00:00',
            }),
        })
    })

    await page.route('**/api/v1/admin/onboarding/readiness', async (route) => {
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                data: {
                    progress: { completed: 4, total: 4 },
                    next_steps: [],
                },
            }),
        })
    })
}

function dashboardEndpointForProfile(profile) {
    return `/api/v1/dashboard/${profile}`
}

async function openBrowserContext(playwright, testInfo) {
    try {
        const browser = await playwright.chromium.launch({ headless: true })
        const context = await browser.newContext()
        const page = await context.newPage()
        return { browser, context, page }
    } catch (error) {
        const message = String(error?.message || error || '')
        const missingRuntimePattern = /error while loading shared libraries|libnspr4\.so|libnss3\.so|libatk-1\.0\.so/i
        if (missingRuntimePattern.test(message)) {
            testInfo.annotations.push({
                type: 'runtime',
                description: 'Chromium runtime dependencies missing in WSL',
            })
            test.skip(true, 'Chromium dependencies missing in WSL (ex: libnspr4).')
            return null
        }

        throw error
    }
}

test.describe('Dashboard smoke by profile', () => {
    for (const [profile, fixture] of Object.entries(profileFixtures)) {
        test(`renders ${profile} dashboard without integration errors`, async ({ playwright }, testInfo) => {
            const session = await openBrowserContext(playwright, testInfo)
            if (!session) {
                return
            }

            const { browser, page } = session
            try {
                await mockCommonApi(page, fixture, profile)

                await page.route(`**${dashboardEndpointForProfile(profile)}`, async (route) => {
                    await route.fulfill({
                        status: 200,
                        contentType: 'application/json',
                        body: JSON.stringify(fixture.dashboard),
                    })
                })

                const alertPayload = fixture.dashboardAlertas || {
                    data: [],
                    estatisticas: { total: 0, nao_lidos: 0 },
                }
                await page.route('**/api/v1/dashboard/alertas', async (route) => {
                    await route.fulfill({
                        status: 200,
                        contentType: 'application/json',
                        body: JSON.stringify(alertPayload),
                    })
                })
                await page.route('**/api/v1/dashboard/alertas/*/lido', async (route) => {
                    await route.fulfill({
                        status: 200,
                        contentType: 'application/json',
                        body: JSON.stringify({ message: 'ok' }),
                    })
                })
                await page.route('**/api/v1/dashboard/alertas/lidos', async (route) => {
                    await route.fulfill({
                        status: 200,
                        contentType: 'application/json',
                        body: JSON.stringify({ message: 'ok' }),
                    })
                })

                await page.goto(`${APP_URL}/`)

                for (const text of fixture.expected) {
                    await expect(page.getByText(text, { exact: false })).toBeVisible()
                }

                await expect(page.getByText(/erro ao carregar|request failed|network request failed/i)).toHaveCount(0)
            } finally {
                await browser.close()
            }
        })
    }
})
