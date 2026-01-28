import { useState, useEffect } from 'react'
import { getProjects, createProject } from '../services/api'
import Modal from '../components/ui/Modal'
import Button from '../components/ui/Button'
import Input from '../components/ui/Input'

function Projects() {
    const [projects, setProjects] = useState([])
    const [meta, setMeta] = useState({})
    const [loading, setLoading] = useState(true)
    const [error, setError] = useState(null)
    const [search, setSearch] = useState('')
    
    // Modal state
    const [isModalOpen, setIsModalOpen] = useState(false)
    const [newProject, setNewProject] = useState({
        name: '',
        short_name: '',
        description: '',
        start_date: '',
        end_date: ''
    })
    const [creating, setCreating] = useState(false)

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

    async function handleCreateProject(e) {
        e.preventDefault()
        if (!newProject.name.trim()) return
        
        try {
            setCreating(true)
            await createProject({
                name: newProject.name,
                short_name: newProject.short_name || null,
                description: newProject.description || null,
                start_date: newProject.start_date || null,
                end_date: newProject.end_date || null
            })
            setIsModalOpen(false)
            setNewProject({ name: '', short_name: '', description: '', start_date: '', end_date: '' })
            loadProjects()
        } catch (err) {
            alert('Erro ao criar projeto: ' + err.message)
        } finally {
            setCreating(false)
        }
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
                    <button className="btn btn-primary" onClick={() => setIsModalOpen(true)}>+ Novo Projeto</button>
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

            {/* Create Project Modal */}
            <Modal
                isOpen={isModalOpen}
                onClose={() => setIsModalOpen(false)}
                title="Novo Projeto"
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setIsModalOpen(false)}>
                            Cancelar
                        </Button>
                        <Button 
                            variant="primary" 
                            onClick={handleCreateProject}
                            disabled={creating || !newProject.name.trim()}
                        >
                            {creating ? 'Criando...' : 'Criar Projeto'}
                        </Button>
                    </>
                }
            >
                <form onSubmit={handleCreateProject}>
                    <div style={{ marginBottom: 'var(--spacing-4)' }}>
                        <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                            Nome do Projeto *
                        </label>
                        <Input
                            value={newProject.name}
                            onChange={(e) => setNewProject({ ...newProject, name: e.target.value })}
                            placeholder="Digite o nome do projeto"
                            required
                        />
                    </div>
                    
                    <div style={{ marginBottom: 'var(--spacing-4)' }}>
                        <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                            Nome Curto
                        </label>
                        <Input
                            value={newProject.short_name}
                            onChange={(e) => setNewProject({ ...newProject, short_name: e.target.value })}
                            placeholder="Ex: PROJ-2024"
                        />
                    </div>
                    
                    <div style={{ marginBottom: 'var(--spacing-4)' }}>
                        <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                            Descrição
                        </label>
                        <textarea
                            value={newProject.description}
                            onChange={(e) => setNewProject({ ...newProject, description: e.target.value })}
                            placeholder="Descrição do projeto"
                            rows={3}
                            style={{
                                width: '100%',
                                padding: 'var(--spacing-2) var(--spacing-3)',
                                border: '1px solid var(--color-gray-300)',
                                borderRadius: 'var(--radius-md)',
                                fontSize: '0.875rem',
                                fontFamily: 'inherit'
                            }}
                        />
                    </div>
                    
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 'var(--spacing-4)' }}>
                        <div>
                            <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                                Data de Início
                            </label>
                            <Input
                                type="date"
                                value={newProject.start_date}
                                onChange={(e) => setNewProject({ ...newProject, start_date: e.target.value })}
                            />
                        </div>
                        
                        <div>
                            <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                                Data de Término
                            </label>
                            <Input
                                type="date"
                                value={newProject.end_date}
                                onChange={(e) => setNewProject({ ...newProject, end_date: e.target.value })}
                            />
                        </div>
                    </div>
                </form>
            </Modal>
        </>
    )
}

export default Projects
