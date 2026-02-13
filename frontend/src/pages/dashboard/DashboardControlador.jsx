/**
 * Dashboard do Controlador - VisÃ£o de FiscalizaÃ§Ã£o
 * 
 * Mostra alertas de conformidade e panorama por secretaria.
 */

import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { getDashboardControlador } from '../../services/api'
import { adaptControladorDashboard } from './adapters'
import {
    StatCard,
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
            setError(null)
            const response = await getDashboardControlador()
            const payload = response?.data || response
            setData(adaptControladorDashboard(payload))
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
                    <div style={{ fontSize: 48, marginBottom: 16 }}>â³</div>
                    <div style={{ color: '#6b7280' }}>Carregando...</div>
                </div>
            </div>
        )
    }

    if (error) {
        return (
            <div style={{ padding: 24, backgroundColor: '#fef2f2', borderRadius: 12, margin: 24 }}>
                <h3 style={{ color: '#991b1b', margin: 0 }}>âŒ Erro</h3>
                <p style={{ color: '#b91c1c' }}>{error}</p>
                <button onClick={loadData} style={{ backgroundColor: '#3b82f6', color: 'white', border: 'none', padding: '10px 20px', borderRadius: 6, cursor: 'pointer' }}>
                    ðŸ”„ Tentar
                </button>
            </div>
        )
    }

    const usuario = data?.usuario || { nome: 'Controlador' }
    const indicadores = data?.indicadores || { alertas_criticos: 0, alertas_atencao: 0, projetos_auditados: 0, conformidade: 0 }
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
                    {row.alertas > 0 ? `${row.alertas} ðŸ”´` : '0 ðŸŸ¢'}
                </span>
            )
        },
        { titulo: 'ExecuÃ§Ã£o', campo: 'execucao', render: (row) => `${row.execucao}%` },
        {
            titulo: 'TransparÃªncia', campo: 'transparencia', render: (row) => (
                <span style={{
                    color: row.transparencia >= 90 ? '#22c55e' : row.transparencia >= 80 ? '#eab308' : '#ef4444',
                    fontWeight: 600
                }}>
                    {row.transparencia}%
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
                        ðŸ” Controladoria Interna
                    </h1>
                    <p style={{ margin: '8px 0 0', color: '#6b7280' }}>
                        Auditor: {usuario.nome} | Acesso: Todas Secretarias (Leitura)
                    </p>
                </div>
                <div style={{ display: 'flex', gap: 12 }}>
                    <button onClick={() => navigate('/')} style={{ backgroundColor: '#f3f4f6', border: 'none', padding: '10px 16px', borderRadius: 8, cursor: 'pointer' }}>
                        â† Voltar
                    </button>
                    <button onClick={loadData} style={{ backgroundColor: '#3b82f6', color: 'white', border: 'none', padding: '10px 16px', borderRadius: 8, cursor: 'pointer' }}>
                        ðŸ”„ Atualizar
                    </button>
                </div>
            </div>

            {/* Indicadores */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 16, marginBottom: 24 }}>
                <StatCard titulo="Alertas CrÃ­ticos" valor={indicadores.alertas_criticos} icone="ðŸ”´" cor="red" />
                <StatCard titulo="Alertas AtenÃ§Ã£o" valor={indicadores.alertas_atencao} icone="ðŸŸ¡" cor="yellow" />
                <StatCard titulo="Projetos Auditados" valor={indicadores.projetos_auditados} icone="ðŸ“‹" cor="blue" />
                <StatCard titulo="Conformidade Geral" valor={`${indicadores.conformidade}%`} icone="âœ…" cor="green" />
            </div>

            {/* Grid principal */}
            <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: 24, marginBottom: 24 }}>
                {/* Alertas de Conformidade */}
                <div style={{ backgroundColor: 'white', borderRadius: 12, padding: 24, boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }}>
                    <SectionHeader titulo="Alertas de Conformidade" icone="ðŸš¨" />
                    <div style={{ maxHeight: 350, overflowY: 'auto' }}>
                        {alertas.map((alerta) => (
                            <AlertaCard key={alerta.id} alerta={alerta} />
                        ))}
                    </div>
                </div>

                {/* RelatÃ³rios */}
                <div style={{ backgroundColor: 'white', borderRadius: 12, padding: 24, boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }}>
                    <SectionHeader titulo="RelatÃ³rios DisponÃ­veis" icone="ðŸ“ˆ" />
                    <div>
                        {relatorios.map((rel, idx) => (
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
                <SectionHeader titulo="Panorama Geral por Secretaria" icone="ðŸ“Š" />
                <DataTable
                    colunas={colunasPanorama}
                    dados={secretarias}
                    emptyMessage="Nenhuma secretaria cadastrada"
                />
            </div>
        </div>
    )
}

export default DashboardControlador



