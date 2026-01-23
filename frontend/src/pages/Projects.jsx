import { useState, useEffect } from 'react'
import { getProjects } from '../services/api'

function Projects() {
    const [projects, setProjects] = useState([])
    const [meta, setMeta] = useState({})
    const [loading, setLoading] = useState(true)
    const [error, setError] = useState(null)
    const [search, setSearch] = useState('')

    useEffect(() => {
        loadProjects()
    }, [])

    async function loadProjects(params = {}) {
        try {
            setLoading(true)
            const data = await getProjects(params)
            setProjects(data.data || [])
            setMeta(data.meta || {})
        } catch (err) {
            setError(err.message)
        } finally {
            setLoading(false)
        }
    }

    function handleSearch(e) {
        e.preventDefault()
        loadProjects({ search })
    }

    function getStatusLabel(status) {
        const labels = {
            0: 'Não definido',
            1: 'Proposto',
            2: 'Em planejamento',
            3: 'Em progresso',
            4: 'Em espera',
            5: 'Completo',
            6: 'Arquivado'
        }
        return labels[status] || 'Desconhecido'
    }

    function getStatusBadge(status) {
        const badges = {
            0: 'info',
            1: 'info',
            2: 'warning',
            3: 'success',
            4: 'warning',
            5: 'success',
            6: 'info'
        }
        return badges[status] || 'info'
    }

    return (
        <>
            <div className="topbar">
                <h1 className="page-title">Projetos</h1>
                <div style={{ display: 'flex', gap: 'var(--spacing-3)' }}>
                    <form onSubmit={handleSearch} style={{ display: 'flex', gap: 'var(--spacing-2)' }}>
                        <input
                            type="text"
                            placeholder="Buscar projetos..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            style={{
                                padding: 'var(--spacing-2) var(--spacing-3)',
                                border: '1px solid var(--color-gray-300)',
                                borderRadius: 'var(--radius-md)',
                                fontSize: '0.875rem'
                            }}
                        />
                        <button type="submit" className="btn btn-secondary">Buscar</button>
                    </form>
                    <button className="btn btn-primary">+ Novo Projeto</button>
                </div>
            </div>

            <div className="page-content">
                {error && (
                    <div className="card" style={{ marginBottom: 'var(--spacing-4)' }}>
                        <div className="card-body">
                            <p style={{ color: 'var(--color-danger-500)' }}>Erro: {error}</p>
                        </div>
                    </div>
                )}

                <div className="card">
                    <div className="card-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                        <h2 className="card-title">Lista de Projetos</h2>
                        <span style={{ fontSize: '0.875rem', color: 'var(--color-gray-500)' }}>
                            {meta.total || 0} projetos
                        </span>
                    </div>
                    <div className="card-body" style={{ padding: 0 }}>
                        {loading ? (
                            <div style={{ padding: 'var(--spacing-6)', textAlign: 'center' }}>
                                Carregando...
                            </div>
                        ) : projects.length === 0 ? (
                            <div style={{ padding: 'var(--spacing-6)', textAlign: 'center', color: 'var(--color-gray-500)' }}>
                                Nenhum projeto encontrado
                            </div>
                        ) : (
                            <table className="table">
                                <thead>
                                    <tr>
                                        <th>Projeto</th>
                                        <th>Empresa</th>
                                        <th>Status</th>
                                        <th>Progresso</th>
                                        <th>Prazo</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {projects.map(project => (
                                        <tr key={project.id}>
                                            <td>
                                                <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--spacing-3)' }}>
                                                    <div
                                                        style={{
                                                            width: 8,
                                                            height: 8,
                                                            borderRadius: '50%',
                                                            background: project.color || 'var(--color-primary-500)'
                                                        }}
                                                    />
                                                    <div>
                                                        <strong>{project.name}</strong>
                                                        {project.short_name && (
                                                            <div style={{ fontSize: '0.75rem', color: 'var(--color-gray-500)' }}>
                                                                {project.short_name}
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                            </td>
                                            <td>{project.company?.name || '-'}</td>
                                            <td>
                                                <span className={`badge badge-${getStatusBadge(project.status)}`}>
                                                    {getStatusLabel(project.status)}
                                                </span>
                                            </td>
                                            <td>
                                                <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--spacing-2)' }}>
                                                    <div className="progress" style={{ width: 80 }}>
                                                        <div
                                                            className="progress-bar"
                                                            style={{ width: `${project.percent_complete}%` }}
                                                        />
                                                    </div>
                                                    <span style={{ fontSize: '0.75rem' }}>{project.percent_complete}%</span>
                                                </div>
                                            </td>
                                            <td>
                                                {project.end_date ? (
                                                    <span>{new Date(project.end_date).toLocaleDateString('pt-BR')}</span>
                                                ) : (
                                                    <span style={{ color: 'var(--color-gray-400)' }}>-</span>
                                                )}
                                            </td>
                                            <td>
                                                <button className="btn btn-secondary" style={{ padding: 'var(--spacing-1) var(--spacing-2)' }}>
                                                    Ver
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                </div>

                {/* Pagination */}
                {meta.total_pages > 1 && (
                    <div style={{
                        display: 'flex',
                        justifyContent: 'center',
                        gap: 'var(--spacing-2)',
                        marginTop: 'var(--spacing-6)'
                    }}>
                        {Array.from({ length: meta.total_pages }, (_, i) => i + 1).map(page => (
                            <button
                                key={page}
                                className={`btn ${page === meta.page ? 'btn-primary' : 'btn-secondary'}`}
                                onClick={() => loadProjects({ page })}
                                style={{ minWidth: 40 }}
                            >
                                {page}
                            </button>
                        ))}
                    </div>
                )}
            </div>
        </>
    )
}

export default Projects
