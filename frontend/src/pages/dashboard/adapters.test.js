import { describe, expect, it } from 'vitest'
import {
    adaptPrefeitoDashboard,
    adaptSecretarioDashboard,
    adaptCoordenadorDashboard,
    adaptTecnicoDashboard,
    adaptControladorDashboard,
} from './adapters'

describe('dashboard adapters', () => {
    it('adapta payload do prefeito com alertas e resumo de status', () => {
        const payload = {
            perfil: 'prefeito',
            ppa_execucao: { total_programas: 10, concluidos: 3, percentual_medio: 62.5 },
            projetos_status: [
                { estado: 'Concluido', total: 2 },
                { estado: 'Atrasado', total: 1 },
                { estado: 'Atencao', total: 4 },
            ],
            orcamento: { previsto: 1000, pago: 400, percentual_executado: 40 },
            obras_atrasadas: [{ id: 9, nome: 'UBS', estado: 'Atrasado', secretaria: 'Saude', dias_atraso: 7 }],
        }

        const adapted = adaptPrefeitoDashboard(payload, {
            data: [{ id: 1, titulo: 'Alerta critico', prioridade: 'critica' }],
        })

        expect(adapted.perfil).toBe('prefeito')
        expect(adapted.ppa.percentual).toBe(62.5)
        expect(adapted.indicadores.projetos_travados).toBe(1)
        expect(adapted.alertas).toHaveLength(1)
        expect(adapted.obras_destaque[0].status).toBe('travado')
    })

    it('adapta payload do secretario com programas e alertas', () => {
        const payload = {
            perfil: 'secretario',
            unidade_nome: 'Obras',
            projetos_resumo: [
                { estado: 'Concluido', total: 3 },
                { estado: 'Atrasado', total: 2 },
            ],
            programas: [{ nome: 'Pavimentacao', estado: 'Atencao', total_projetos: 5, percent_execucao: 40 }],
            coordenadores: [{ nome: 'Coordenador A' }],
            alertas: { total: 5, nao_lidos: 2, criticos: 1 },
        }

        const adapted = adaptSecretarioDashboard(payload, {
            data: [{ id: 11, titulo: 'Pendencia', prioridade: 'alta' }],
        })

        expect(adapted.secretaria.nome).toBe('Obras')
        expect(adapted.indicadores.travados).toBe(2)
        expect(adapted.programas[0].status).toBe('atencao')
        expect(adapted.alertas_estatisticas.total).toBe(5)
    })

    it('adapta payload do coordenador agrupando projetos por programa', () => {
        const payload = {
            perfil: 'coordenador',
            resumo: { total_projetos: 2, em_andamento: 2, atrasados: 1, concluidos: 0 },
            projetos: [
                { programa_nome: 'Programa A', estado: 'Atrasado', percent_execucao: 30 },
                { programa_nome: 'Programa A', estado: 'Em andamento', percent_execucao: 60 },
            ],
            equipe: [{ nome: 'Tecnico A', tarefas_ativas: 2, concluidas: 10 }],
        }

        const adapted = adaptCoordenadorDashboard(payload)

        expect(adapted.programas).toHaveLength(1)
        expect(adapted.programas[0].travados).toBe(1)
        expect(adapted.equipe[0].performance).toBe('Excelente')
    })

    it('adapta payload do tecnico com produtividade e prioridades', () => {
        const payload = {
            perfil: 'tecnico',
            resumo: { total: 10, pendentes: 4, atrasadas: 1, concluidas: 6 },
            tarefas_prioritarias: [
                { id: 1, nome: 'Tarefa 1', projeto: 'Projeto X', urgencia: 'atrasada', dias_restantes: -2 },
                { id: 2, nome: 'Tarefa 2', projeto: 'Projeto X', urgencia: 'urgente', dias_restantes: 0 },
            ],
            produtividade_30d: [
                { tarefas_concluidas: 3 },
                { tarefas_concluidas: 2 },
            ],
            projetos: [{ nome: 'Projeto X', minhas_tarefas: 2, percent_execucao: 90 }],
        }

        const adapted = adaptTecnicoDashboard(payload)

        expect(adapted.indicadores.concluidas).toBe(6)
        expect(adapted.tarefas[0].prioridade).toBe('urgente')
        expect(adapted.producao.mes).toBe(5)
        expect(adapted.projetos[0].status).toBe('em_dia')
    })

    it('adapta payload do controlador com indicadores consolidados', () => {
        const payload = {
            perfil: 'controlador',
            alertas_conformidade: {
                sem_prestacao_contas: 2,
                execucao_acima_cronograma: 1,
                diferenca_empenho_execucao: 3,
            },
            panorama_secretarias: [
                { unidade_nome: 'Obras', total_projetos: 10, alertas: 2, execucao_media: 60 },
            ],
            irregularidades: [{ id: 1, nome: 'Projeto Y', irregularidade: 'Sem prestacao' }],
        }

        const adapted = adaptControladorDashboard(payload)

        expect(adapted.indicadores.alertas_criticos).toBe(3)
        expect(adapted.indicadores.alertas_atencao).toBe(3)
        expect(adapted.secretarias).toHaveLength(1)
        expect(adapted.alertas).toHaveLength(1)
    })
})
