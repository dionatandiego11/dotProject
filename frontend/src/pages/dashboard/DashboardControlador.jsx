/**
 * Dashboard do Controlador - Visão de Fiscalização
 * 
 * Mostra alertas de conformidade e panorama por secretaria.
 */

import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { getDashboardControlador } from '../../services/api'
import {
    StatCard,
    ProgressBar,
    AlertaCard,
    SectionHeader,
    DataTable
} from '../../components/dashboard/DashboardComponents'

function DashboardControlador() {
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
            const response = await getDashboardControlador()
            setData(response.data || response)
        } catch (err) {
            setError(err.message)
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

    const usuario = data?.usuario || { nome: 'Auditor' }
    const indicadores = data?.indicadores || { alertas_criticos: 3, alertas_atencao: 5, projetos_auditados: 45, conformidade: 87 }
    const alertas = data?.alertas || []
    const secretarias = data?.secretarias || []
    const relatorios = data?.relatorios || []

    const colunasPanorama = [
        { titulo: 'Secretaria', campo: 'nome' },
        { titulo: 'Projetos', campo: 'projetos' },
        {
            titulo: 'Alertas', campo: 'alertas', render: (row) => (
                <span style={{
                    color: row.alertas > 3 ? '#ef4444' : row.alertas > 0 ? '#eab308' : '#22c55e',
                    fontWeight: 600
                }}>
                    {row.alertas > 0 ? `${row.alertas} 🔴` : '0 🟢'}
                </span>
            )
        },
        { titulo: 'Execução', campo: 'execucao', render: (row) => `${row.execucao}%` },
        {
            titulo: 'Transparência', campo: 'transparencia', render: (row) => (
                <span style={{
                    color: row.transparencia >= 90 ? '#22c55e' : row.transparencia >= 80 ? '#eab308' : '#ef4444',
                    fontWeight: 600
                }}>
                    {row.transparencia}%
                </span>
            )
        }
    ]

    const alertasMock = [
        { id: 1, titulo: '3 projetos sem prestação de contas há +90 dias', prioridade: 'critica', data_criacao: new Date().toISOString() },
        { id: 2, titulo: '2 convênios com execução acima do cronograma físico', prioridade: 'critica', data_criacao: new Date().toISOString() },
        { id: 3, titulo: '5 processos de licitação sem publicação no portal', prioridade: 'alta', data_criacao: new Date().toISOString() },
        { id: 4, titulo: '8 projetos com diferença entre empenho e execução >20%', prioridade: 'media', data_criacao: new Date().toISOString() },
    ]

    const secretariasMock = [
        { nome: 'Obras', projetos: 32, alertas: 5, execucao: 68, transparencia: 95 },
        { nome: 'Saúde', projetos: 28, alertas: 2, execucao: 72, transparencia: 88 },
        { nome: 'Educação', projetos: 18, alertas: 1, execucao: 81, transparencia: 92 },
        { nome: 'Assistência Social', projetos: 12, alertas: 0, execucao: 75, transparencia: 98 },
    ]

    const relatoriosMock = [
        { titulo: 'Relatório de Execução Orçamentária (Consolidado)', icone: '📄' },
        { titulo: 'Relatório de Convênios (Prestação de Contas)', icone: '📄' },
        { titulo: 'Relatório de Emendas Parlamentares', icone: '📄' },
        { titulo: 'Relatório de Transparência Passiva', icone: '📄' },
        { titulo: 'Relatório de Atrasos e suas Causas', icone: '📄' },
    ]

    return (
        <div style={{ padding: 24, maxWidth: 1400, margin: '0 auto' }}>
            {/* Header */}
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24 }}>
                <div>
                    <h1 style={{ margin: 0, fontSize: 28, color: '#1f2937' }}>
                        🔍 Controladoria Interna
                    </h1>
                    <p style={{ margin: '8px 0 0', color: '#6b7280' }}>
                        Auditor: {usuario.nome} | Acesso: Todas Secretarias (Leitura)
                    </p>
                </div>
                <div style={{ display: 'flex', gap: 12 }}>
                    <button onClick={() => navigate('/dashboard')} style={{ backgroundColor: '#f3f4f6', border: 'none', padding: '10px 16px', borderRadius: 8, cursor: 'pointer' }}>
                        ← Voltar
                    </button>
                    <button onClick={loadData} style={{ backgroundColor: '#3b82f6', color: 'white', border: 'none', padding: '10px 16px', borderRadius: 8, cursor: 'pointer' }}>
                        🔄 Atualizar
                    </button>
                </div>
            </div>

            {/* Indicadores */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 16, marginBottom: 24 }}>
                <StatCard titulo="Alertas Críticos" valor={indicadores.alertas_criticos} icone="🔴" cor="red" />
                <StatCard titulo="Alertas Atenção" valor={indicadores.alertas_atencao} icone="🟡" cor="yellow" />
                <StatCard titulo="Projetos Auditados" valor={indicadores.projetos_auditados} icone="📋" cor="blue" />
                <StatCard titulo="Conformidade Geral" valor={`${indicadores.conformidade}%`} icone="✅" cor="green" />
            </div>

            {/* Grid principal */}
            <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: 24, marginBottom: 24 }}>
                {/* Alertas de Conformidade */}
                <div style={{ backgroundColor: 'white', borderRadius: 12, padding: 24, boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }}>
                    <SectionHeader titulo="Alertas de Conformidade" icone="🚨" />
                    <div style={{ maxHeight: 350, overflowY: 'auto' }}>
                        {(alertas.length > 0 ? alertas : alertasMock).map((alerta) => (
                            <AlertaCard key={alerta.id} alerta={alerta} />
                        ))}
                    </div>
                </div>

                {/* Relatórios */}
                <div style={{ backgroundColor: 'white', borderRadius: 12, padding: 24, boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }}>
                    <SectionHeader titulo="Relatórios Disponíveis" icone="📈" />
                    <div>
                        {(relatorios.length > 0 ? relatorios : relatoriosMock).map((rel, idx) => (
                            <div key={idx} style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 12,
                                padding: 12,
                                borderBottom: '1px solid #e5e7eb',
                                cursor: 'pointer'
                            }}
                                onMouseEnter={(e) => e.currentTarget.style.backgroundColor = '#f9fafb'}
                                onMouseLeave={(e) => e.currentTarget.style.backgroundColor = 'transparent'}
                            >
                                <span>{rel.icone}</span>
                                <span style={{ fontSize: 14, color: '#374151' }}>{rel.titulo}</span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            {/* Panorama por Secretaria */}
            <div style={{ backgroundColor: 'white', borderRadius: 12, padding: 24, boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }}>
                <SectionHeader titulo="Panorama Geral por Secretaria" icone="📊" />
                <DataTable
                    colunas={colunasPanorama}
                    dados={secretarias.length > 0 ? secretarias : secretariasMock}
                    emptyMessage="Nenhuma secretaria cadastrada"
                />
            </div>
        </div>
    )
}

export default DashboardControlador
