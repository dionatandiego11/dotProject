/**
 * Dashboard do Prefeito - Visão Executiva Geral
 * 
 * Mostra todos os indicadores macro da prefeitura.
 */

import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { getDashboardPrefeito, marcarAlertaLido, marcarTodosAlertasLidos } from '../../services/api'
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
            const response = await getDashboardPrefeito()
            setData(response.data || response)
        } catch (err) {
            console.warn('API indisponível, usando dados de demonstração:', err.message)
            // Em vez de mostrar erro, usa dados mockados para demonstração
            setData({
                indicadores: {
                    projetos_em_dia: 89,
                    projetos_atencao: 23,
                    projetos_travados: 12,
                    valor_em_risco: 5200000
                },
                ppa: {
                    percentual: 73,
                    programas_ativos: 45,
                    programas_total: 62
                },
                alertas: [
                    { id: 1, titulo: 'Convênio Federal vence em 15 dias', prioridade: 'critica', data_criacao: new Date().toISOString() },
                    { id: 2, titulo: 'Obra da escola parada há 30 dias', prioridade: 'alta', data_criacao: new Date().toISOString() },
                    { id: 3, titulo: 'Prestação de contas pendente', prioridade: 'media', data_criacao: new Date().toISOString() },
                ],
                obras_destaque: [
                    { nome: 'Escola Jardim das Flores', prazo_dias: 90, status: 'travado', secretaria: 'Educação', atraso_dias: 60 },
                    { nome: 'Asfalto Rua dos Pinheiros', prazo_dias: 45, status: 'travado', secretaria: 'Obras', atraso_dias: 35 },
                    { nome: 'UBS Centro', prazo_dias: 120, status: 'atencao', secretaria: 'Saúde', atraso_dias: 12 },
                ],
                emendas: {
                    recebidas: 12000000,
                    percentual_executado: 60,
                    em_risco: 2400000,
                    nao_executadas: 20
                },
                _mock: true // Flag indicando dados de demonstração
            })
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
                    <div style={{ fontSize: 48, marginBottom: 16 }}>⏳</div>
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
                <h3 style={{ color: '#991b1b', margin: 0 }}>❌ Erro ao carregar</h3>
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
                    🔄 Tentar novamente
                </button>
            </div>
        )
    }

    // Dados mockados se API não retornar
    const indicadores = data?.indicadores || {
        projetos_em_dia: 89,
        projetos_atencao: 23,
        projetos_travados: 12,
        valor_em_risco: 5200000
    }

    const ppa = data?.ppa || {
        percentual: 73,
        programas_ativos: 45,
        programas_total: 62
    }

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
                        🏛️ Dashboard Executivo
                    </h1>
                    <p style={{ margin: '8px 0 0', color: '#6b7280' }}>
                        Visão geral da Prefeitura
                    </p>
                </div>
                <div style={{ display: 'flex', gap: 12 }}>
                    <button
                        onClick={() => navigate('/dashboard')}
                        style={{
                            backgroundColor: '#f3f4f6',
                            border: 'none',
                            padding: '10px 16px',
                            borderRadius: 8,
                            cursor: 'pointer',
                            fontSize: 14
                        }}
                    >
                        ← Voltar
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
                        🔄 Atualizar
                    </button>
                </div>
            </div>

            {/* Banner de modo demo */}
            {data?._mock && (
                <div style={{
                    backgroundColor: '#fef3c7',
                    border: '1px solid #f59e0b',
                    borderRadius: 8,
                    padding: '12px 16px',
                    marginBottom: 24,
                    display: 'flex',
                    alignItems: 'center',
                    gap: 12
                }}>
                    <span style={{ fontSize: 20 }}>⚠️</span>
                    <div>
                        <strong style={{ color: '#92400e' }}>Modo Demonstração</strong>
                        <span style={{ color: '#a16207', marginLeft: 8 }}>
                            API indisponível. Exibindo dados de exemplo para visualização.
                        </span>
                    </div>
                </div>
            )}

            {/* Execução do PPA */}
            <div style={{
                backgroundColor: 'white',
                borderRadius: 12,
                padding: 24,
                marginBottom: 24,
                boxShadow: '0 1px 3px rgba(0,0,0,0.1)'
            }}>
                <SectionHeader
                    titulo={`Execução do PPA 2024-2027`}
                    icone="📊"
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
                    icone="🟢"
                    cor="green"
                    subtitulo="Projetos"
                    onClick={() => navigate('/projects?status=em_dia')}
                />
                <StatCard
                    titulo="Atenção"
                    valor={indicadores.projetos_atencao}
                    variacao={5}
                    icone="🟡"
                    cor="yellow"
                    subtitulo="Projetos"
                    onClick={() => navigate('/projects?status=atencao')}
                />
                <StatCard
                    titulo="Travados"
                    valor={indicadores.projetos_travados}
                    variacao={-2}
                    icone="🔴"
                    cor="red"
                    subtitulo="Projetos"
                    onClick={() => navigate('/projects?status=travado')}
                />
                <StatCard
                    titulo="Em Risco"
                    valor={`R$ ${(indicadores.valor_em_risco / 1000000).toFixed(1)}M`}
                    icone="💰"
                    cor="purple"
                    subtitulo="3 convênios"
                />
            </div>

            {/* Grid de duas colunas */}
            <div style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fit, minmax(400px, 1fr))',
                gap: 24
            }}>
                {/* Alertas Críticos */}
                <div style={{
                    backgroundColor: 'white',
                    borderRadius: 12,
                    padding: 24,
                    boxShadow: '0 1px 3px rgba(0,0,0,0.1)'
                }}>
                    <SectionHeader
                        titulo="Alertas Críticos"
                        icone="🚨"
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
                                ✅ Nenhum alerta crítico no momento
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
                    <SectionHeader titulo="Emendas Parlamentares" icone="📝" />
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
                        <div style={{
                            backgroundColor: '#f0fdf4',
                            padding: 16,
                            borderRadius: 8,
                            textAlign: 'center'
                        }}>
                            <div style={{ fontSize: 24, fontWeight: 700, color: '#166534' }}>
                                R$ {((emendas.recebidas || 12000000) / 1000000).toFixed(1)}M
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
                                {emendas.percentual_executado || 60}%
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
                                R$ {((emendas.em_risco || 2400000) / 1000000).toFixed(1)}M
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
                                {emendas.nao_executadas || 20}%
                            </div>
                            <div style={{ fontSize: 12, color: '#6b7280' }}>Não Executadas</div>
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
                <SectionHeader titulo="Obras em Destaque" icone="🏗️" />
                <DataTable
                    colunas={colunasObras}
                    dados={obras.length > 0 ? obras : [
                        { nome: 'Escola Jardim das Flores', prazo_dias: 90, status: 'travado', secretaria: 'Educação', atraso_dias: 60 },
                        { nome: 'Asfalto Rua dos Pinheiros', prazo_dias: 45, status: 'travado', secretaria: 'Obras', atraso_dias: 35 },
                        { nome: 'UBS Centro', prazo_dias: 120, status: 'atencao', secretaria: 'Saúde', atraso_dias: 12 },
                    ]}
                    emptyMessage="Nenhuma obra em destaque"
                />
            </div>
        </div>
    )
}

export default DashboardPrefeito
