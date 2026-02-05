import { useState, useEffect } from 'react'
import { getDashboardAnalytics, getProductivityTrend } from '../services/api'
import { useToast } from '../contexts/ToastContext'
import { StatCard, ProgressRing, DonutChart, BarChart } from '../components/charts/SimpleChart'

function DashboardGeral() {
    const toast = useToast()
    const [loading, setLoading] = useState(true)
    const [data, setData] = useState(null)
    const [productivity, setProductivity] = useState([])

    useEffect(() => {
        loadData()
    }, [])

    async function loadData() {
        try {
            setLoading(true)
            const [dashboardData, productivityData] = await Promise.all([
                getDashboardAnalytics(),
                getProductivityTrend(7)
            ])
            
            setData(dashboardData.data)
            setProductivity(productivityData.data?.data || [])
        } catch (err) {
            console.error('Failed to load dashboard:', err)
            toast.error('Erro ao carregar dashboard')
        } finally {
            setLoading(false)
        }
    }

    if (loading) {
        return (
            <div style={{ 
                display: 'flex', 
                justifyContent: 'center', 
                alignItems: 'center',
                height: '60vh'
            }}>
                <div style={{ textAlign: 'center' }}>
                    <div style={{ 
                        width: 40, 
                        height: 40, 
                        border: '3px solid var(--color-gray-200)',
                        borderTop: '3px solid var(--color-primary-500)',
                        borderRadius: '50%',
                        animation: 'spin 1s linear infinite',
                        margin: '0 auto var(--spacing-4)'
                    }} />
                    <p style={{ color: 'var(--color-gray-500)' }}>Carregando dashboard...</p>
                    <style>{`@keyframes spin { to { transform: rotate(360deg); } }`}</style>
                </div>
            </div>
        )
    }

    if (!data) {
        return (
            <div style={{ textAlign: 'center', padding: 'var(--spacing-8)' }}>
                <p style={{ color: 'var(--color-gray-500)' }}>Não foi possível carregar os dados</p>
                <button className="btn btn-primary" onClick={loadData} style={{ marginTop: 'var(--spacing-4)' }}>
                    Tentar novamente
                </button>
            </div>
        )
    }

    const { projects, tasks, deadlines, activity } = data

    return (
        <div style={{ padding: 'var(--spacing-4)' }}>
            {/* Header */}
            <div style={{ marginBottom: 'var(--spacing-6)' }}>
                <h1 style={{ fontSize: '1.75rem', fontWeight: 700, marginBottom: 'var(--spacing-2)' }}>
                    Dashboard
                </h1>
                <p style={{ color: 'var(--color-gray-500)' }}>
                    Visão geral dos seus projetos e tarefas
                </p>
            </div>

            {/* Stats Grid */}
            <div style={{ 
                display: 'grid', 
                gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
                gap: 'var(--spacing-4)',
                marginBottom: 'var(--spacing-6)'
            }}>
                <StatCard
                    title="Total de Projetos"
                    value={projects?.total || 0}
                    subtitle={`${projects?.by_status?.[0]?.value || 0} em progresso`}
                    icon="📁"
                />
                <StatCard
                    title="Tarefas"
                    value={tasks?.total || 0}
                    subtitle={`${tasks?.completed || 0} concluídas`}
                    icon="✓"
                />
                <StatCard
                    title="Tarefas Atrasadas"
                    value={tasks?.overdue || 0}
                    subtitle="Precisam de atenção"
                    icon="⚠️"
                />
                <StatCard
                    title="Prazos esta semana"
                    value={deadlines?.this_week || 0}
                    subtitle={`${deadlines?.next_week || 0} na próxima`}
                    icon="📅"
                />
            </div>

            {/* Charts Grid */}
            <div style={{ 
                display: 'grid', 
                gridTemplateColumns: 'repeat(auto-fit, minmax(350px, 1fr))',
                gap: 'var(--spacing-4)',
                marginBottom: 'var(--spacing-6)'
            }}>
                {/* Projetos por Status */}
                <div className="card">
                    <div className="card-header">
                        <h3 className="card-title">Projetos por Status</h3>
                    </div>
                    <div className="card-body" style={{ display: 'flex', alignItems: 'center', gap: 'var(--spacing-6)' }}>
                        <DonutChart 
                            data={projects?.by_status?.filter(s => s.value > 0) || []}
                            size={140}
                            strokeWidth={25}
                        />
                        <div style={{ flex: 1 }}>
                            {projects?.by_status?.filter(s => s.value > 0).map((status, index) => (
                                <div key={index} style={{ 
                                    display: 'flex', 
                                    justifyContent: 'space-between',
                                    alignItems: 'center',
                                    padding: 'var(--spacing-1) 0',
                                    borderBottom: index < projects.by_status.length - 1 ? '1px solid var(--color-gray-100)' : 'none'
                                }}>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--spacing-2)' }}>
                                        <span style={{ 
                                            width: 10, 
                                            height: 10, 
                                            borderRadius: '50%', 
                                            backgroundColor: status.color 
                                        }} />
                                        <span style={{ fontSize: '0.875rem' }}>{status.label}</span>
                                    </div>
                                    <span style={{ fontWeight: 600, fontSize: '0.875rem' }}>{status.value}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                {/* Tarefas por Prioridade */}
                <div className="card">
                    <div className="card-header">
                        <h3 className="card-title">Tarefas Pendentes por Prioridade</h3>
                    </div>
                    <div className="card-body" style={{ display: 'flex', alignItems: 'center', gap: 'var(--spacing-6)' }}>
                        <DonutChart 
                            data={tasks?.by_priority?.filter(p => p.value > 0) || []}
                            size={140}
                            strokeWidth={25}
                        />
                        <div style={{ flex: 1 }}>
                            {tasks?.by_priority?.filter(p => p.value > 0).map((priority, index) => (
                                <div key={index} style={{ 
                                    display: 'flex', 
                                    justifyContent: 'space-between',
                                    alignItems: 'center',
                                    padding: 'var(--spacing-1) 0',
                                    borderBottom: index < tasks.by_priority.length - 1 ? '1px solid var(--color-gray-100)' : 'none'
                                }}>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--spacing-2)' }}>
                                        <span style={{ 
                                            width: 10, 
                                            height: 10, 
                                            borderRadius: '50%', 
                                            backgroundColor: priority.color 
                                        }} />
                                        <span style={{ fontSize: '0.875rem' }}>{priority.label}</span>
                                    </div>
                                    <span style={{ fontWeight: 600, fontSize: '0.875rem' }}>{priority.value}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                {/* Progresso Médio */}
                <div className="card">
                    <div className="card-header">
                        <h3 className="card-title">Progresso Médio dos Projetos</h3>
                    </div>
                    <div className="card-body" style={{ 
                        display: 'flex', 
                        alignItems: 'center', 
                        justifyContent: 'center',
                        padding: 'var(--spacing-6)'
                    }}>
                        <div style={{ textAlign: 'center' }}>
                            <ProgressRing progress={projects?.avg_progress || 0} size={150} strokeWidth={12} />
                            <p style={{ marginTop: 'var(--spacing-3)', color: 'var(--color-gray-500)', fontSize: '0.875rem' }}>
                                Média geral
                            </p>
                        </div>
                    </div>
                </div>

                {/* Produtividade */}
                <div className="card">
                    <div className="card-header">
                        <h3 className="card-title">Produtividade (últimos 7 dias)</h3>
                    </div>
                    <div className="card-body">
                        {productivity.length > 0 ? (
                            <BarChart 
                                data={productivity}
                                height={150}
                                color="var(--color-success-500)"
                            />
                        ) : (
                            <div style={{ 
                                height: 150, 
                                display: 'flex', 
                                alignItems: 'center', 
                                justifyContent: 'center',
                                color: 'var(--color-gray-400)'
                            }}>
                                Sem dados de produtividade
                            </div>
                        )}
                        <p style={{ 
                            textAlign: 'center', 
                            marginTop: 'var(--spacing-3)', 
                            fontSize: '0.75rem',
                            color: 'var(--color-gray-400)'
                        }}>
                            Tarefas concluídas por dia
                        </p>
                    </div>
                </div>
            </div>

            {/* Atividade Recente & Prazos */}
            <div style={{ 
                display: 'grid', 
                gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))',
                gap: 'var(--spacing-4)'
            }}>
                {/* Atividade Recente */}
                <div className="card">
                    <div className="card-header">
                        <h3 className="card-title">Atividade Recente</h3>
                    </div>
                    <div className="card-body" style={{ padding: 0 }}>
                        {activity && activity.length > 0 ? (
                            <div>
                                {activity.map((item, index) => (
                                    <div key={index} style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 'var(--spacing-3)',
                                        padding: 'var(--spacing-3) var(--spacing-4)',
                                        borderBottom: index < activity.length - 1 ? '1px solid var(--color-gray-100)' : 'none'
                                    }}>
                                        <div style={{
                                            width: 32,
                                            height: 32,
                                            borderRadius: '50%',
                                            backgroundColor: 'var(--color-primary-100)',
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            fontSize: '0.875rem'
                                        }}>
                                            {item.icon}
                                        </div>
                                        <div style={{ flex: 1 }}>
                                            <p style={{ fontSize: '0.875rem', fontWeight: 500, marginBottom: 2 }}>
                                                {item.title}
                                            </p>
                                            {item.subtitle && (
                                                <p style={{ fontSize: '0.75rem', color: 'var(--color-gray-500)' }}>
                                                    {item.subtitle}
                                                </p>
                                            )}
                                        </div>
                                        <span style={{ fontSize: '0.75rem', color: 'var(--color-gray-400)' }}>
                                            {item.date ? new Date(item.date).toLocaleDateString('pt-BR') : ''}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div style={{ padding: 'var(--spacing-6)', textAlign: 'center', color: 'var(--color-gray-400)' }}>
                                Nenhuma atividade recente
                            </div>
                        )}
                    </div>
                </div>

                {/* Resumo de Tarefas */}
                <div className="card">
                    <div className="card-header">
                        <h3 className="card-title">Resumo de Tarefas</h3>
                    </div>
                    <div className="card-body">
                        <div style={{ display: 'grid', gap: 'var(--spacing-3)' }}>
                            <div style={{ 
                                display: 'flex', 
                                justifyContent: 'space-between', 
                                alignItems: 'center',
                                padding: 'var(--spacing-3)',
                                backgroundColor: 'var(--color-success-50)',
                                borderRadius: 'var(--radius-md)'
                            }}>
                                <span style={{ display: 'flex', alignItems: 'center', gap: 'var(--spacing-2)' }}>
                                    <span style={{ fontSize: '1.25rem' }}>✓</span>
                                    <span>Concluídas</span>
                                </span>
                                <span style={{ fontWeight: 700, color: 'var(--color-success-600)' }}>
                                    {tasks?.completed || 0}
                                </span>
                            </div>
                            
                            <div style={{ 
                                display: 'flex', 
                                justifyContent: 'space-between', 
                                alignItems: 'center',
                                padding: 'var(--spacing-3)',
                                backgroundColor: 'var(--color-primary-50)',
                                borderRadius: 'var(--radius-md)'
                            }}>
                                <span style={{ display: 'flex', alignItems: 'center', gap: 'var(--spacing-2)' }}>
                                    <span style={{ fontSize: '1.25rem' }}>▶</span>
                                    <span>Em Progresso</span>
                                </span>
                                <span style={{ fontWeight: 700, color: 'var(--color-primary-600)' }}>
                                    {tasks?.in_progress || 0}
                                </span>
                            </div>
                            
                            <div style={{ 
                                display: 'flex', 
                                justifyContent: 'space-between', 
                                alignItems: 'center',
                                padding: 'var(--spacing-3)',
                                backgroundColor: 'var(--color-gray-100)',
                                borderRadius: 'var(--radius-md)'
                            }}>
                                <span style={{ display: 'flex', alignItems: 'center', gap: 'var(--spacing-2)' }}>
                                    <span style={{ fontSize: '1.25rem' }}>○</span>
                                    <span>Não Iniciadas</span>
                                </span>
                                <span style={{ fontWeight: 700, color: 'var(--color-gray-600)' }}>
                                    {tasks?.not_started || 0}
                                </span>
                            </div>
                            
                            <div style={{ 
                                display: 'flex', 
                                justifyContent: 'space-between', 
                                alignItems: 'center',
                                padding: 'var(--spacing-3)',
                                backgroundColor: 'var(--color-danger-50)',
                                borderRadius: 'var(--radius-md)'
                            }}>
                                <span style={{ display: 'flex', alignItems: 'center', gap: 'var(--spacing-2)' }}>
                                    <span style={{ fontSize: '1.25rem' }}>⚠</span>
                                    <span>Atrasadas</span>
                                </span>
                                <span style={{ fontWeight: 700, color: 'var(--color-danger-600)' }}>
                                    {tasks?.overdue || 0}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    )
}

export default DashboardGeral
