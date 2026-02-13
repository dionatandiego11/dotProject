/**
 * Dashboard do Coordenador - VisÃ£o de Projetos
 * 
 * Mostra projetos sob responsabilidade do coordenador.
 */

import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { getDashboardCoordenador } from '../../services/api'
import { adaptCoordenadorDashboard } from './adapters'
import {
    StatCard,
    ProgressBar,
    SectionHeader,
    StatusBadge,
    DataTable
} from '../../components/dashboard/DashboardComponents'

function DashboardCoordenador() {
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
            const response = await getDashboardCoordenador()
            const payload = response?.data || response
            setData(adaptCoordenadorDashboard(payload))
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
                    <div style={{ color: '#6b7280' }}>Carregando dashboard...</div>
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
                    ðŸ”„ Tentar novamente
                </button>
            </div>
        )
    }

    const usuario = data?.usuario || { nome: 'Coordenador', secretaria: 'Nao informada', programas: 0, projetos: 0, tecnicos: 0 }
    const indicadores = data?.indicadores || { em_dia: 0, atencao: 0, travados: 0, concluidos: 0 }
    const programas = data?.programas || []
    const proximas_etapas = data?.proximas_etapas || []
    const equipe = data?.equipe || []

    const colunasEtapas = [
        { titulo: 'Projeto', campo: 'projeto' },
        { titulo: 'Etapa', campo: 'etapa' },
        { titulo: 'Prazo', campo: 'prazo', render: (row) => new Date(row.prazo).toLocaleDateString('pt-BR') },
        { titulo: 'Status', campo: 'status', render: (row) => <StatusBadge status={row.status} /> }
    ]

    const colunasEquipe = [
        { titulo: 'TÃ©cnico', campo: 'nome' },
        { titulo: 'Tarefas Ativas', campo: 'tarefas_ativas' },
        { titulo: 'ConcluÃ­das', campo: 'concluidas' },
        {
            titulo: 'Performance', campo: 'performance', render: (row) => (
                <span style={{
                    padding: '4px 8px',
                    borderRadius: 12,
                    fontSize: 12,
                    backgroundColor: row.performance === 'Excelente' ? '#f0fdf4' : row.performance === 'Boa' ? '#eff6ff' : '#fefce8',
                    color: row.performance === 'Excelente' ? '#166534' : row.performance === 'Boa' ? '#1e40af' : '#854d0e'
                }}>
                    {row.performance}
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
                        ðŸ‘¤ {usuario.nome}
                    </h1>
                    <p style={{ margin: '8px 0 0', color: '#6b7280' }}>
                        {usuario.secretaria} | Programas: {usuario.programas} | Projetos: {usuario.projetos} | TÃ©cnicos: {usuario.tecnicos}
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
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(160px, 1fr))', gap: 16, marginBottom: 24 }}>
                <StatCard titulo="Em Dia" valor={indicadores.em_dia} icone="ðŸŸ¢" cor="green" />
                <StatCard titulo="AtenÃ§Ã£o" valor={indicadores.atencao} icone="ðŸŸ¡" cor="yellow" />
                <StatCard titulo="Travados" valor={indicadores.travados} icone="ðŸ”´" cor="red" />
                <StatCard titulo="ConcluÃ­dos" valor={indicadores.concluidos} icone="âœ…" cor="gray" subtitulo="2024" />
            </div>

            {/* Programas */}
            <div style={{ backgroundColor: 'white', borderRadius: 12, padding: 24, marginBottom: 24, boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }}>
                <SectionHeader titulo="Programas sob sua Responsabilidade" icone="ðŸŽ¯" />
                {programas.map((prog, idx) => (
                    <div key={idx} style={{ padding: 16, backgroundColor: '#f9fafb', borderRadius: 8, marginBottom: 12 }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                            <div>
                                <div style={{ fontWeight: 600, color: '#374151' }}>{prog.nome}</div>
                                <div style={{ fontSize: 12, color: '#9ca3af' }}>
                                    {prog.projetos} projetos | R$ {(prog.orcamento / 1000000).toFixed(0)}M orÃ§amento | {prog.travados} travados
                                </div>
                            </div>
                            <StatusBadge status={prog.travados > 0 ? 'atencao' : 'em_dia'} />
                        </div>
                        <ProgressBar valor={prog.executado} cor={prog.executado >= 70 ? '#22c55e' : '#eab308'} />
                    </div>
                ))}
            </div>

            {/* Grid */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(400px, 1fr))', gap: 24 }}>
                {/* PrÃ³ximas Etapas */}
                <div style={{ backgroundColor: 'white', borderRadius: 12, padding: 24, boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }}>
                    <SectionHeader titulo="PrÃ³ximas Etapas (7 dias)" icone="ðŸ“‹" />
                    <DataTable
                        colunas={colunasEtapas}
                        dados={proximas_etapas}
                        emptyMessage="Nenhuma etapa prÃ³xima"
                    />
                </div>

                {/* Equipe */}
                <div style={{ backgroundColor: 'white', borderRadius: 12, padding: 24, boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }}>
                    <SectionHeader titulo="Sua Equipe" icone="ðŸ‘¥" />
                    <DataTable
                        colunas={colunasEquipe}
                        dados={equipe}
                        emptyMessage="Nenhum tÃ©cnico na equipe"
                    />
                </div>
            </div>
        </div>
    )
}

export default DashboardCoordenador



