/**
 * Dashboard do Prefeito - VisÃ£o Executiva Geral
 * 
 * Mostra todos os indicadores macro da prefeitura.
 */

import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import {
    getDashboardPrefeito,
    getDashboardAlertas,
    marcarAlertaLido,
    marcarTodosAlertasLidos
} from '../../services/api'
import { adaptPrefeitoDashboard } from './adapters'
import {
    StatCard,
    ProgressBar,
    AlertaCard,
    SectionHeader,
    StatusBadge,
    DataTable
} from '../../components/dashboard/DashboardComponents'

function DashboardPrefeito() {
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
            const [dashboardResponse, alertasResponse] = await Promise.all([
                getDashboardPrefeito(),
                getDashboardAlertas(),
            ])
            const dashboardPayload = dashboardResponse?.data || dashboardResponse
            const alertasPayload = alertasResponse?.data || alertasResponse
            setData(adaptPrefeitoDashboard(dashboardPayload, alertasPayload))
        } catch (err) {
            setError(err.message || 'Erro ao carregar dashboard')
        } finally {
            setLoading(false)
        }
    }

    const handleMarcarAlertaLido = async (alertaId) => {
        try {
            await marcarAlertaLido(alertaId)
            // Recarrega os dados
            loadData()
        } catch (err) {
            console.error('Erro ao marcar alerta:', err)
        }
    }

    const handleMarcarTodosLidos = async () => {
        try {
            await marcarTodosAlertasLidos()
            loadData()
        } catch (err) {
            console.error('Erro ao marcar alertas:', err)
        }
    }

    if (loading) {
        return (
            <div style={{
                display: 'flex',
                justifyContent: 'center',
                alignItems: 'center',
                minHeight: '60vh'
            }}>
                <div style={{ textAlign: 'center' }}>
                    <div style={{ fontSize: 48, marginBottom: 16 }}>â³</div>
                    <div style={{ color: '#6b7280' }}>Carregando dashboard...</div>
                </div>
            </div>
        )
    }

    if (error) {
        return (
            <div style={{
                padding: 24,
                backgroundColor: '#fef2f2',
                borderRadius: 12,
                margin: 24
            }}>
                <h3 style={{ color: '#991b1b', margin: 0 }}>âŒ Erro ao carregar</h3>
                <p style={{ color: '#b91c1c' }}>{error}</p>
                <button
                    onClick={loadData}
                    style={{
                        backgroundColor: '#3b82f6',
                        color: 'white',
                        border: 'none',
                        padding: '10px 20px',
                        borderRadius: 6,
                        cursor: 'pointer'
                    }}
                >
                    ðŸ”„ Tentar novamente
                </button>
            </div>
        )
    }

    const indicadores = data?.indicadores || {}

    const ppa = data?.ppa || {}

    const alertas = data?.alertas || []
    const obras = data?.obras_destaque || []
    const emendas = data?.emendas || {}

    // Colunas da tabela de obras
    const colunasObras = [
        { titulo: 'Nome', campo: 'nome' },
        { titulo: 'Prazo', campo: 'prazo_dias', render: (row) => `${row.prazo_dias} dias` },
        { titulo: 'Status', campo: 'status', render: (row) => <StatusBadge status={row.status} /> },
        { titulo: 'Secretaria', campo: 'secretaria' },
        { titulo: 'Atraso', campo: 'atraso_dias', render: (row) => row.atraso_dias > 0 ? `${row.atraso_dias} dias` : '-' },
    ]

    return (
        <div style={{ padding: 24, maxWidth: 1400, margin: '0 auto' }}>
            {/* Header */}
            <div style={{
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'center',
                marginBottom: 24
            }}>
                <div>
                    <h1 style={{ margin: 0, fontSize: 28, color: '#1f2937' }}>
                        ðŸ›ï¸ Dashboard Executivo
                    </h1>
                    <p style={{ margin: '8px 0 0', color: '#6b7280' }}>
                        VisÃ£o geral da Prefeitura
                    </p>
                </div>
                <div style={{ display: 'flex', gap: 12 }}>
                    <button
                        onClick={() => navigate('/')}
                        style={{
                            backgroundColor: '#f3f4f6',
                            border: 'none',
                            padding: '10px 16px',
                            borderRadius: 8,
                            cursor: 'pointer',
                            fontSize: 14
                        }}
                    >
                        â† Voltar
                    </button>
                    <button
                        onClick={loadData}
                        style={{
                            backgroundColor: '#3b82f6',
                            color: 'white',
                            border: 'none',
                            padding: '10px 16px',
                            borderRadius: 8,
                            cursor: 'pointer',
                            fontSize: 14
                        }}
                    >
                        ðŸ”„ Atualizar
                    </button>
                </div>
            </div>

            {/* ExecuÃ§Ã£o do PPA */}
            <div style={{
                backgroundColor: 'white',
                borderRadius: 12,
                padding: 24,
                marginBottom: 24,
                boxShadow: '0 1px 3px rgba(0,0,0,0.1)'
            }}>
                <SectionHeader
                    titulo={`ExecuÃ§Ã£o do PPA 2024-2027`}
                    icone="ðŸ“Š"
                />
                <ProgressBar
                    valor={ppa.percentual}
                    total={100}
                    cor="#3b82f6"
                    subtitulo={`${ppa.programas_ativos} programas ativos de ${ppa.programas_total} previstos no plano`}
                />
            </div>

            {/* Cards de indicadores */}
            <div style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
                gap: 16,
                marginBottom: 24
            }}>
                <StatCard
                    titulo="Em Dia"
                    valor={indicadores.projetos_em_dia}
                    variacao={12}
                    icone="ðŸŸ¢"
                    cor="green"
                    subtitulo="Projetos"
                    onClick={() => navigate('/projects?status=3')}
                />
                <StatCard
                    titulo="AtenÃ§Ã£o"
                    valor={indicadores.projetos_atencao}
                    variacao={5}
                    icone="ðŸŸ¡"
                    cor="yellow"
                    subtitulo="Projetos"
                    onClick={() => navigate('/projects?status=2')}
                />
                <StatCard
                    titulo="Travados"
                    valor={indicadores.projetos_travados}
                    variacao={-2}
                    icone="ðŸ”´"
                    cor="red"
                    subtitulo="Projetos"
                    onClick={() => navigate('/projects?status=4')}
                />
                <StatCard
                    titulo="Em Risco"
                    valor={`R$ ${(indicadores.valor_em_risco / 1000000).toFixed(1)}M`}
                    icone="ðŸ’°"
                    cor="purple"
                    subtitulo="3 convÃªnios"
                />
            </div>

            {/* SaÃºde dos Projetos */}
            {data?.saude_resumo && (
                <div style={{
                    backgroundColor: 'white',
                    borderRadius: 12,
                    padding: 24,
                    marginBottom: 24,
                    boxShadow: '0 1px 3px rgba(0,0,0,0.1)'
                }}>
                    <SectionHeader titulo="SaÃºde dos Projetos" icone="ðŸ’Š" />
                    <div style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
                        gap: 16
                    }}>
                        {Object.entries(data.saude_resumo).map(([entidade, dist]) => {
                            const total = (dist.em_dia || 0) + (dist.atencao || 0) + (dist.critico || 0) + (dist.impedido || 0)
                            return (
                                <div key={entidade} style={{
                                    border: '1px solid #e5e7eb',
                                    borderRadius: 8,
                                    padding: 16
                                }}>
                                    <div style={{ fontWeight: 600, marginBottom: 12, fontSize: 14 }}>
                                        {entidade} ({total})
                                    </div>
                                    <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                        {[
                                            { key: 'em_dia', label: 'Em dia', color: '#16a34a', bg: '#f0fdf4' },
                                            { key: 'atencao', label: 'AtenÃ§Ã£o', color: '#d97706', bg: '#fffbeb' },
                                            { key: 'critico', label: 'CrÃ­tico', color: '#dc2626', bg: '#fef2f2' },
                                            { key: 'impedido', label: 'Impedido', color: '#6b7280', bg: '#f3f4f6' },
                                        ].map(s => (
                                            <span key={s.key} style={{
                                                display: 'inline-block',
                                                padding: '4px 10px',
                                                borderRadius: 12,
                                                fontSize: 12,
                                                fontWeight: 600,
                                                color: s.color,
                                                background: s.bg,
                                                border: `1px solid ${s.color}22`
                                            }}>
                                                {s.label}: {dist[s.key] || 0}
                                            </span>
                                        ))}
                                    </div>
                                    {total > 0 && (
                                        <div style={{
                                            marginTop: 8,
                                            height: 6,
                                            backgroundColor: '#f3f4f6',
                                            borderRadius: 3,
                                            display: 'flex',
                                            overflow: 'hidden'
                                        }}>
                                            <div style={{ width: `${((dist.em_dia || 0) / total) * 100}%`, backgroundColor: '#16a34a' }} />
                                            <div style={{ width: `${((dist.atencao || 0) / total) * 100}%`, backgroundColor: '#d97706' }} />
                                            <div style={{ width: `${((dist.critico || 0) / total) * 100}%`, backgroundColor: '#dc2626' }} />
                                            <div style={{ width: `${((dist.impedido || 0) / total) * 100}%`, backgroundColor: '#6b7280' }} />
                                        </div>
                                    )}
                                </div>
                            )
                        })}
                    </div>
                </div>
            )}

            {/* Grid de duas colunas */}
            <div style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fit, minmax(400px, 1fr))',
                gap: 24
            }}>
                {/* Alertas CrÃ­ticos */}
                <div style={{
                    backgroundColor: 'white',
                    borderRadius: 12,
                    padding: 24,
                    boxShadow: '0 1px 3px rgba(0,0,0,0.1)'
                }}>
                    <SectionHeader
                        titulo="Alertas CrÃ­ticos"
                        icone="ðŸš¨"
                        acao={
                            alertas.length > 0 && (
                                <button
                                    onClick={handleMarcarTodosLidos}
                                    style={{
                                        background: 'none',
                                        border: '1px solid #d1d5db',
                                        padding: '6px 12px',
                                        borderRadius: 6,
                                        fontSize: 12,
                                        color: '#6b7280',
                                        cursor: 'pointer'
                                    }}
                                >
                                    Marcar todos como lidos
                                </button>
                            )
                        }
                    />
                    <div style={{ maxHeight: 400, overflowY: 'auto' }}>
                        {alertas.length > 0 ? (
                            alertas.slice(0, 5).map((alerta) => (
                                <AlertaCard
                                    key={alerta.id}
                                    alerta={alerta}
                                    onMarcarLido={handleMarcarAlertaLido}
                                />
                            ))
                        ) : (
                            <div style={{
                                textAlign: 'center',
                                padding: 40,
                                color: '#9ca3af'
                            }}>
                                âœ… Nenhum alerta crÃ­tico no momento
                            </div>
                        )}
                    </div>
                </div>

                {/* Emendas Parlamentares */}
                <div style={{
                    backgroundColor: 'white',
                    borderRadius: 12,
                    padding: 24,
                    boxShadow: '0 1px 3px rgba(0,0,0,0.1)'
                }}>
                    <SectionHeader titulo="Emendas Parlamentares" icone="ðŸ“" />
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
                        <div style={{
                            backgroundColor: '#f0fdf4',
                            padding: 16,
                            borderRadius: 8,
                            textAlign: 'center'
                        }}>
                            <div style={{ fontSize: 24, fontWeight: 700, color: '#166534' }}>
                                R$ {((emendas.recebidas || 0) / 1000000).toFixed(1)}M
                            </div>
                            <div style={{ fontSize: 12, color: '#6b7280' }}>Recebidas</div>
                        </div>
                        <div style={{
                            backgroundColor: '#eff6ff',
                            padding: 16,
                            borderRadius: 8,
                            textAlign: 'center'
                        }}>
                            <div style={{ fontSize: 24, fontWeight: 700, color: '#1e40af' }}>
                                {emendas.percentual_executado || 0}%
                            </div>
                            <div style={{ fontSize: 12, color: '#6b7280' }}>Executadas</div>
                        </div>
                        <div style={{
                            backgroundColor: '#fef2f2',
                            padding: 16,
                            borderRadius: 8,
                            textAlign: 'center'
                        }}>
                            <div style={{ fontSize: 24, fontWeight: 700, color: '#991b1b' }}>
                                R$ {((emendas.em_risco || 0) / 1000000).toFixed(1)}M
                            </div>
                            <div style={{ fontSize: 12, color: '#6b7280' }}>Em Risco</div>
                        </div>
                        <div style={{
                            backgroundColor: '#f9fafb',
                            padding: 16,
                            borderRadius: 8,
                            textAlign: 'center'
                        }}>
                            <div style={{ fontSize: 24, fontWeight: 700, color: '#374151' }}>
                                {emendas.nao_executadas || 0}%
                            </div>
                            <div style={{ fontSize: 12, color: '#6b7280' }}>NÃ£o Executadas</div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Obras em Destaque */}
            <div style={{
                backgroundColor: 'white',
                borderRadius: 12,
                padding: 24,
                marginTop: 24,
                boxShadow: '0 1px 3px rgba(0,0,0,0.1)'
            }}>
                <SectionHeader titulo="Obras em Destaque" icone="ðŸ—ï¸" />
                <DataTable
                    colunas={colunasObras}
                    dados={obras}
                    emptyMessage="Nenhuma obra em destaque"
                />
            </div>
        </div>
    )
}

export default DashboardPrefeito



