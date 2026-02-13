/**
 * Dashboard do Técnico - Visão de Tarefas
 * 
 * Interface minimalista focada nas tarefas do dia.
 */

import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { getDashboardTecnico } from '../../services/api'
import { adaptTecnicoDashboard } from './adapters'
import { StatCard, SectionHeader, StatusBadge } from '../../components/dashboard/DashboardComponents'

function DashboardTecnico() {
    const navigate = useNavigate()
    const [loading, setLoading] = useState(true)
    const [error, setError] = useState(null)
    const [data, setData] = useState(null)

    useEffect(() => {
        loadData()
    }, [])

    const loadData = async () => {
        try {
            setLoading(true)
            setError(null)
            const response = await getDashboardTecnico()
            const payload = response?.data || response
            setData(adaptTecnicoDashboard(payload))
        } catch (err) {
            setError(err.message || 'Erro ao carregar dashboard')
        } finally {
            setLoading(false)
        }
    }

    if (loading) {
        return (
            <div style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '60vh' }}>
                <div style={{ textAlign: 'center' }}>
                    <div style={{ fontSize: 48, marginBottom: 16 }}>⏳</div>
                    <div style={{ color: '#6b7280' }}>Carregando...</div>
                </div>
            </div>
        )
    }

    if (error) {
        return (
            <div style={{ padding: 24, backgroundColor: '#fef2f2', borderRadius: 12, margin: 24 }}>
                <h3 style={{ color: '#991b1b', margin: 0 }}>❌ Erro</h3>
                <p style={{ color: '#b91c1c' }}>{error}</p>
                <button onClick={loadData} style={{ backgroundColor: '#3b82f6', color: 'white', border: 'none', padding: '10px 20px', borderRadius: 6, cursor: 'pointer' }}>
                    🔄 Tentar
                </button>
            </div>
        )
    }

    const usuario = data?.usuario || { nome: 'Tecnico', coordenacao: 'Nao informada', supervisor: 'Nao informado' }
    const indicadores = data?.indicadores || { a_fazer: 0, em_andamento: 0, em_revisao: 0, concluidas: 0 }
    const tarefas = data?.tarefas || []
    const projetos = data?.projetos || []
    const producao = data?.producao || { semana: 0, mes: 0, media_dia: 0, taxa_prazo: 0 }

    const prioridadeCores = {
        urgente: { bg: '#fef2f2', border: '#ef4444', texto: '🔴 Urgente - Vence hoje' },
        importante: { bg: '#fff7ed', border: '#f97316', texto: '🟡 Importante' },
        normal: { bg: '#f9fafb', border: '#d1d5db', texto: '⬜ Normal' },
    }

    return (
        <div style={{ padding: 24, maxWidth: 900, margin: '0 auto' }}>
            {/* Header */}
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24 }}>
                <div>
                    <h1 style={{ margin: 0, fontSize: 28, color: '#1f2937' }}>
                        👷 {usuario.nome}
                    </h1>
                    <p style={{ margin: '8px 0 0', color: '#6b7280' }}>
                        Coordenação: {usuario.coordenacao} | Supervisor: {usuario.supervisor}
                    </p>
                </div>
                <button onClick={loadData} style={{ backgroundColor: '#3b82f6', color: 'white', border: 'none', padding: '10px 16px', borderRadius: 8, cursor: 'pointer' }}>
                    🔄
                </button>
            </div>

            {/* Indicadores */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 12, marginBottom: 24 }}>
                <StatCard titulo="A fazer" valor={indicadores.a_fazer} icone="📋" cor="blue" />
                <StatCard titulo="Em andamento" valor={indicadores.em_andamento} icone="🔄" cor="yellow" />
                <StatCard titulo="Em revisão" valor={indicadores.em_revisao} icone="👁️" cor="purple" />
                <StatCard titulo="Concluídas" valor={indicadores.concluidas} icone="✅" cor="green" />
            </div>

            {/* Tarefas do Dia */}
            <div style={{ backgroundColor: 'white', borderRadius: 12, padding: 24, marginBottom: 24, boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }}>
                <SectionHeader titulo="Prioridades de Hoje" icone="⭐" />
                <div>
                    {tarefas.map((tarefa) => {
                        const prio = prioridadeCores[tarefa.prioridade] || prioridadeCores.normal
                        return (
                            <div key={tarefa.id} style={{
                                backgroundColor: prio.bg,
                                border: `1px solid ${prio.border}`,
                                borderRadius: 8,
                                padding: 16,
                                marginBottom: 12
                            }}>
                                <div style={{ fontSize: 12, color: '#6b7280', marginBottom: 4 }}>
                                    {prio.texto}
                                </div>
                                <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                                    <input type="checkbox" style={{ width: 20, height: 20 }} />
                                    <div style={{ flex: 1 }}>
                                        <div style={{ fontWeight: 600, color: '#374151' }}>{tarefa.titulo}</div>
                                        <div style={{ fontSize: 12, color: '#9ca3af' }}>
                                            Projeto: {tarefa.projeto}
                                        </div>
                                    </div>
                                    <button style={{
                                        backgroundColor: '#3b82f6',
                                        color: 'white',
                                        border: 'none',
                                        padding: '8px 16px',
                                        borderRadius: 6,
                                        fontSize: 12,
                                        cursor: 'pointer'
                                    }}>
                                        ✓ Concluir
                                    </button>
                                </div>
                            </div>
                        )
                    })}
                </div>
            </div>

            {/* Grid */}
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 24 }}>
                {/* Produção */}
                <div style={{ backgroundColor: 'white', borderRadius: 12, padding: 24, boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }}>
                    <SectionHeader titulo="Minha Produção" icone="📊" />
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
                        <div style={{ backgroundColor: '#f0fdf4', padding: 16, borderRadius: 8, textAlign: 'center' }}>
                            <div style={{ fontSize: 28, fontWeight: 700, color: '#166534' }}>{producao.semana}</div>
                            <div style={{ fontSize: 12, color: '#6b7280' }}>Esta semana</div>
                        </div>
                        <div style={{ backgroundColor: '#eff6ff', padding: 16, borderRadius: 8, textAlign: 'center' }}>
                            <div style={{ fontSize: 28, fontWeight: 700, color: '#1e40af' }}>{producao.mes}</div>
                            <div style={{ fontSize: 12, color: '#6b7280' }}>Este mês</div>
                        </div>
                        <div style={{ backgroundColor: '#f9fafb', padding: 16, borderRadius: 8, textAlign: 'center' }}>
                            <div style={{ fontSize: 28, fontWeight: 700, color: '#374151' }}>{producao.media_dia}</div>
                            <div style={{ fontSize: 12, color: '#6b7280' }}>Média/dia</div>
                        </div>
                        <div style={{ backgroundColor: '#f0fdf4', padding: 16, borderRadius: 8, textAlign: 'center' }}>
                            <div style={{ fontSize: 28, fontWeight: 700, color: '#166534' }}>{producao.taxa_prazo}%</div>
                            <div style={{ fontSize: 12, color: '#6b7280' }}>No prazo</div>
                        </div>
                    </div>
                </div>

                {/* Projetos */}
                <div style={{ backgroundColor: 'white', borderRadius: 12, padding: 24, boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }}>
                    <SectionHeader titulo="Meus Projetos" icone="📁" />
                    {projetos.map((proj, idx) => (
                        <div key={idx} style={{
                            display: 'flex',
                            justifyContent: 'space-between',
                            alignItems: 'center',
                            padding: 12,
                            borderBottom: idx < projetos.length - 1 ? '1px solid #e5e7eb' : 'none'
                        }}>
                            <div>
                                <div style={{ fontWeight: 500, color: '#374151', fontSize: 14 }}>{proj.nome}</div>
                                <div style={{ fontSize: 12, color: '#9ca3af' }}>{proj.tarefas_ativas} tarefas</div>
                            </div>
                            <StatusBadge status={proj.status} />
                        </div>
                    ))}
                </div>
            </div>
        </div>
    )
}

export default DashboardTecnico



