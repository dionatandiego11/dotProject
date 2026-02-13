/**
 * Dashboard do SecretÃ¡rio - VisÃ£o da Secretaria
 * 
 * Mostra indicadores da secretaria do usuÃ¡rio.
 */

import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import {
    getDashboardSecretario,
    getDashboardAlertas,
    marcarAlertaLido
} from '../../services/api'
import { adaptSecretarioDashboard } from './adapters'
import {
    StatCard,
    AlertaCard,
    SectionHeader,
    StatusBadge,
    DataTable
} from '../../components/dashboard/DashboardComponents'

function DashboardSecretario() {
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
                getDashboardSecretario(),
                getDashboardAlertas(),
            ])
            const dashboardPayload = dashboardResponse?.data || dashboardResponse
            const alertasPayload = alertasResponse?.data || alertasResponse
            setData(adaptSecretarioDashboard(dashboardPayload, alertasPayload))
        } catch (err) {
            setError(err.message || 'Erro ao carregar dashboard')
        } finally {
            setLoading(false)
        }
    }

    const handleMarcarAlertaLido = async (alertaId) => {
        try {
            await marcarAlertaLido(alertaId)
            loadData()
        } catch (err) {
            console.error('Erro ao marcar alerta:', err)
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
            <div style={{ padding: 24, backgroundColor: '#fef2f2', borderRadius: 12, margin: 24 }}>
                <h3 style={{ color: '#991b1b', margin: 0 }}>âŒ Erro ao carregar</h3>
                <p style={{ color: '#b91c1c' }}>{error}</p>
                <button onClick={loadData} style={{ backgroundColor: '#3b82f6', color: 'white', border: 'none', padding: '10px 20px', borderRadius: 6, cursor: 'pointer' }}>
                    ðŸ”„ Tentar novamente
                </button>
            </div>
        )
    }

    const secretaria = data?.secretaria || { nome: 'Secretaria', projetos: 0, equipe: 0 }
    const indicadores = data?.indicadores || { em_dia: 0, atencao: 0, travados: 0, concluidos: 0 }
    const alertas = data?.alertas || []
    const coordenadores = data?.coordenadores || []
    const programas = data?.programas || []

    const colunasCoordenadores = [
        { titulo: 'Coordenador', campo: 'nome' },
        { titulo: 'Projetos', campo: 'total_projetos' },
        { titulo: 'Em dia', campo: 'em_dia' },
        { titulo: 'Atrasados', campo: 'atrasados' },
        {
            titulo: '% ExecuÃ§Ã£o', campo: 'percentual', render: (row) => (
                <span style={{
                    color: row.percentual >= 70 ? '#22c55e' : row.percentual >= 50 ? '#eab308' : '#ef4444',
                    fontWeight: 600
                }}>
                    {row.percentual}%
                </span>
            )
        }
    ]

    return (
        <div style={{ padding: 24, maxWidth: 1400, margin: '0 auto' }}>
            {/* Header */}
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24 }}>
                <div>
                    <h1 style={{ margin: 0, fontSize: 28, color: '#1f2937' }}>
                        ðŸ›ï¸ {secretaria.nome}
                    </h1>
                    <p style={{ margin: '8px 0 0', color: '#6b7280' }}>
                        Projetos: {secretaria.projetos} | Equipe: {secretaria.equipe}
                    </p>
                </div>
                <div style={{ display: 'flex', gap: 12 }}>
                    <button onClick={() => navigate('/')} style={{ backgroundColor: '#f3f4f6', border: 'none', padding: '10px 16px', borderRadius: 8, cursor: 'pointer', fontSize: 14 }}>
                        â† Voltar
                    </button>
                    <button onClick={loadData} style={{ backgroundColor: '#3b82f6', color: 'white', border: 'none', padding: '10px 16px', borderRadius: 8, cursor: 'pointer', fontSize: 14 }}>
                        ðŸ”„ Atualizar
                    </button>
                </div>
            </div>

            {/* Indicadores */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: 16, marginBottom: 24 }}>
                <StatCard titulo="Em Dia" valor={indicadores.em_dia} icone="ðŸŸ¢" cor="green" subtitulo="Projetos" />
                <StatCard titulo="AtenÃ§Ã£o" valor={indicadores.atencao} icone="ðŸŸ¡" cor="yellow" subtitulo="Projetos" />
                <StatCard titulo="Travados" valor={indicadores.travados} icone="ðŸ”´" cor="red" subtitulo="Projetos" />
                <StatCard titulo="ConcluÃ­dos" valor={indicadores.concluidos} icone="âœ…" cor="gray" subtitulo="Em 2024" />
            </div>

            {/* SaÃºde Resumo */}
            {data?.saude_resumo && (
                <div style={{
                    backgroundColor: 'white',
                    borderRadius: 12,
                    padding: 24,
                    marginBottom: 24,
                    boxShadow: '0 1px 3px rgba(0,0,0,0.1)'
                }}>
                    <SectionHeader titulo="SaÃºde da Secretaria" icone="ðŸ’Š" />
                    <div style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))',
                        gap: 16
                    }}>
                        {Object.entries(data.saude_resumo).map(([entidade, dist]) => {
                            const total = (dist.em_dia || 0) + (dist.atencao || 0) + (dist.critico || 0) + (dist.impedido || 0)
                            const label = entidade.charAt(0).toUpperCase() + entidade.slice(1)
                            return (
                                <div key={entidade} style={{
                                    border: '1px solid #e5e7eb',
                                    borderRadius: 8,
                                    padding: 16
                                }}>
                                    <div style={{ fontWeight: 600, marginBottom: 8, fontSize: 14 }}>
                                        {label} ({total})
                                    </div>
                                    <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginBottom: 8 }}>
                                        {[
                                            { key: 'em_dia', label: 'Em dia', color: '#16a34a', bg: '#f0fdf4' },
                                            { key: 'atencao', label: 'AtenÃ§Ã£o', color: '#d97706', bg: '#fffbeb' },
                                            { key: 'critico', label: 'CrÃ­tico', color: '#dc2626', bg: '#fef2f2' },
                                            { key: 'impedido', label: 'Impedido', color: '#6b7280', bg: '#f3f4f6' },
                                        ].map(s => (
                                            <span key={s.key} style={{
                                                padding: '3px 8px',
                                                borderRadius: 10,
                                                fontSize: 11,
                                                fontWeight: 600,
                                                color: s.color,
                                                background: s.bg,
                                            }}>
                                                {s.label}: {dist[s.key] || 0}
                                            </span>
                                        ))}
                                    </div>
                                    {total > 0 && (
                                        <div style={{
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

            {/* Grid */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(400px, 1fr))', gap: 24 }}>
                {/* Alertas */}
                <div style={{ backgroundColor: 'white', borderRadius: 12, padding: 24, boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }}>
                    <SectionHeader titulo="Alertas da Secretaria" icone="ðŸš¨" />
                    <div style={{ maxHeight: 350, overflowY: 'auto' }}>
                        {alertas.length > 0 ? (
                            alertas.slice(0, 5).map((alerta) => (
                                <AlertaCard key={alerta.id} alerta={alerta} onMarcarLido={handleMarcarAlertaLido} />
                            ))
                        ) : (
                            <div style={{ textAlign: 'center', padding: 40, color: '#9ca3af' }}>
                                âœ… Nenhum alerta no momento
                            </div>
                        )}
                    </div>
                </div>

                {/* Programas */}
                <div style={{ backgroundColor: 'white', borderRadius: 12, padding: 24, boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }}>
                    <SectionHeader titulo="Programas sob Responsabilidade" icone="ðŸ“‹" />
                    <div>
                        {programas.map((prog, idx) => (
                            <div key={idx} style={{
                                padding: 12,
                                borderBottom: '1px solid #e5e7eb',
                                display: 'flex',
                                justifyContent: 'space-between',
                                alignItems: 'center'
                            }}>
                                <div>
                                    <div style={{ fontWeight: 600, color: '#374151' }}>{prog.nome}</div>
                                    <div style={{ fontSize: 12, color: '#9ca3af' }}>
                                        {prog.projetos} projetos | {prog.travados} travados
                                    </div>
                                </div>
                                <StatusBadge status={prog.status} />
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            {/* Desempenho por Coordenador */}
            <div style={{ backgroundColor: 'white', borderRadius: 12, padding: 24, marginTop: 24, boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }}>
                <SectionHeader titulo="Desempenho por Coordenador" icone="ðŸ‘¥" />
                <DataTable
                    colunas={colunasCoordenadores}
                    dados={coordenadores}
                    emptyMessage="Nenhum coordenador cadastrado"
                />
            </div>
        </div>
    )
}

export default DashboardSecretario



