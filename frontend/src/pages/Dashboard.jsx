import { useState, useEffect } from 'react'
import { getDashboard, getProjectsHealth } from '../services/api'

function Dashboard() {
    const [dashboard, setDashboard] = useState(null)
    const [projectsHealth, setProjectsHealth] = useState(null)
    const [loading, setLoading] = useState(true)
    const [error, setError] = useState(null)

    useEffect(() => {
        loadDashboard()
    }, [])

    async function loadDashboard() {
        try {
            setLoading(true)
            const [dashData, healthData] = await Promise.all([
                getDashboard(),
                getProjectsHealth()
            ])
            setDashboard(dashData)
            setProjectsHealth(healthData)
        } catch (err) {
            setError(err.message)
        } finally {
            setLoading(false)
        }
    }

    if (loading) {
        return (
            <>
                <div className="topbar">
                    <h1 className="page-title">Dashboard</h1>
                </div>
                <div className="page-content">
                    <p>Carregando...</p>
                </div>
            </>
        )
    }

    if (error) {
        return (
            <>
                <div className="topbar">
                    <h1 className="page-title">Dashboard</h1>
                </div>
                <div className="page-content">
                    <div className="card">
                        <div className="card-body">
                            <p style={{ color: 'var(--color-danger-500)' }}>Erro: {error}</p>
                            <button className="btn btn-primary" onClick={loadDashboard}>
                                Tentar novamente
                            </button>
                        </div>
                    </div>
                </div>
            </>
        )
    }

    const { projects, tasks } = dashboard || {}

    return (
        <>
            <div className="topbar">
                <h1 className="page-title">Dashboard</h1>
                <div>
                    <button className="btn btn-secondary" onClick={loadDashboard}>
                        Atualizar
                    </button>
                </div>
            </div>

            <div className="page-content">
                {/* Stats */}
                <div className="stats-grid">
                    <div className="stat-card">
                        <div className="stat-label">Projetos Ativos</div>
                        <div className="stat-value">{projects?.active || 0}</div>
                    </div>

                    <div className="stat-card">
                        <div className="stat-label">Tarefas Total</div>
                        <div className="stat-value">{tasks?.total || 0}</div>
                    </div>

                    <div className="stat-card">
                        <div className="stat-label">Tarefas Concluídas</div>
                        <div className="stat-value">{tasks?.completed || 0}</div>
                        <div className="stat-change positive">
                            {tasks?.completion_rate?.toFixed(1) || 0}% taxa de conclusão
                        </div>
                    </div>

                    <div className="stat-card">
                        <div className="stat-label">Tarefas Atrasadas</div>
                        <div className="stat-value" style={{ color: 'var(--color-danger-500)' }}>
                            {tasks?.overdue || 0}
                        </div>
                    </div>
                </div>

                {/* Projects Health */}
                <div className="card">
                    <div className="card-header">
                        <h2 className="card-title">Saúde dos Projetos</h2>
                    </div>
                    <div className="card-body" style={{ padding: 0 }}>
                        <table className="table">
                            <thead>
                                <tr>
                                    <th>Projeto</th>
                                    <th>Progresso</th>
                                    <th>Tarefas</th>
                                    <th>Atrasadas</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {projectsHealth?.projects?.slice(0, 10).map(project => (
                                    <tr key={project.id}>
                                        <td>
                                            <strong>{project.name}</strong>
                                        </td>
                                        <td>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--spacing-2)' }}>
                                                <div className="progress" style={{ width: 100 }}>
                                                    <div
                                                        className="progress-bar"
                                                        style={{ width: `${project.progress}%` }}
                                                    />
                                                </div>
                                                <span>{project.progress}%</span>
                                            </div>
                                        </td>
                                        <td>{project.completed_tasks}/{project.total_tasks}</td>
                                        <td>
                                            {project.overdue_tasks > 0 ? (
                                                <span style={{ color: 'var(--color-danger-500)', fontWeight: 500 }}>
                                                    {project.overdue_tasks}
                                                </span>
                                            ) : (
                                                <span style={{ color: 'var(--color-gray-400)' }}>0</span>
                                            )}
                                        </td>
                                        <td>
                                            <span className={`badge badge-${project.health === 'healthy' ? 'success' :
                                                    project.health === 'at_risk' ? 'warning' : 'danger'
                                                }`}>
                                                {project.health === 'healthy' ? 'Saudável' :
                                                    project.health === 'at_risk' ? 'Em risco' : 'Crítico'}
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    )
}

export default Dashboard
