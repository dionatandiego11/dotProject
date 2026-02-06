import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { getProjects, createProject, updateProject, deleteProject, getUnidades } from '../services/api'
import Modal from '../components/ui/Modal'
import Button from '../components/ui/Button'
import Input from '../components/ui/Input'
import { useToast } from '../contexts/ToastContext'
import { useValidation } from '../hooks/useValidation'

function Projects() {
    const toast = useToast()
    const validation = useValidation()
    const navigate = useNavigate()
    const [projects, setProjects] = useState([])
    const [meta, setMeta] = useState({})
    const [loading, setLoading] = useState(true)
    const [error, setError] = useState(null)
    const [search, setSearch] = useState('')
    const [unidades, setUnidades] = useState([])
    const [unidadesLoading, setUnidadesLoading] = useState(false)

    // Modal state
    const [isModalOpen, setIsModalOpen] = useState(false)
    const [isEditModalOpen, setIsEditModalOpen] = useState(false)
    const [editingProject, setEditingProject] = useState(null)
    const [newProject, setNewProject] = useState({
        name: '',
        short_name: '',
        description: '',
        start_date: '',
        end_date: '',
        company_id: '',
        status: '1'
    })
    const [creating, setCreating] = useState(false)

    function normalizeDateValue(value) {
        if (!value) return ''
        if (typeof value === 'string') {
            return value.substring(0, 10)
        }
        const date = new Date(value)
        if (Number.isNaN(date.getTime())) return ''
        return date.toISOString().substring(0, 10)
    }

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

    async function loadUnidades() {
        try {
            setUnidadesLoading(true)
            const data = await getUnidades()
            setUnidades(data.data || [])
        } catch (err) {
            console.error('Erro ao carregar unidades:', err)
        } finally {
            setUnidadesLoading(false)
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

    function getProjectUnitName(project) {
        return project?.unidade?.nome || project?.company?.name || '-'
    }

    function getProjectUnitId(project) {
        const value = project?.unidade_id
            ?? project?.unidade?.id
            ?? project?.company_id
            ?? project?.company?.id
            ?? null
        return value !== null && value !== undefined ? String(value) : ''
    }

    async function handleCreateProject(e) {
        e.preventDefault()
        validation.clearErrors()

        // Validações
        const isValid = validation.validateFields({
            name: () => validation.validateRequired(newProject.name, 'Nome do projeto'),
            short_name: () => validation.validateMaxLength(newProject.short_name, 10, 'Nome curto'),
            description: () => validation.validateMaxLength(newProject.description, 1000, 'Descrição'),
            dates: () => validation.validateDateRange(newProject.start_date, newProject.end_date),
            company_id: () => validation.validateRequired(newProject.company_id, 'Unidade responsavel')
        })

        if (!isValid) return

        try {
            setCreating(true)
            const shortName = newProject.short_name.trim()
            const unidadeId = newProject.company_id ? parseInt(newProject.company_id, 10) : null
            await createProject({
                name: newProject.name,
                short_name: shortName,
                description: newProject.description || null,
                start_date: newProject.start_date || null,
                end_date: newProject.end_date || null,
                unidade_id: unidadeId,
                company_id: unidadeId,
                status: newProject.status ? parseInt(newProject.status, 10) : 0
            })
            toast.success('Projeto criado com sucesso!')
            setIsModalOpen(false)
            setNewProject({ name: '', short_name: '', description: '', start_date: '', end_date: '', company_id: '', status: '1' })
            validation.clearErrors()
            loadProjects()
        } catch (err) {
            toast.error('Erro ao criar projeto: ' + err.message)
        } finally {
            setCreating(false)
        }
    }

    async function handleUpdateProject(e) {
        e.preventDefault()
        if (!editingProject) return
        validation.clearErrors()

        const isValid = validation.validateFields({
            name: () => validation.validateRequired(newProject.name, 'Nome do projeto'),
            short_name: () => validation.validateMaxLength(newProject.short_name, 10, 'Nome curto'),
            description: () => validation.validateMaxLength(newProject.description, 1000, 'Descrição'),
            dates: () => validation.validateDateRange(newProject.start_date, newProject.end_date),
            company_id: () => validation.validateRequired(newProject.company_id, 'Unidade responsavel')
        })

        if (!isValid) return

        try {
            setCreating(true)
            const shortName = newProject.short_name.trim()
            const unidadeId = newProject.company_id ? parseInt(newProject.company_id, 10) : null
            await updateProject(editingProject.id, {
                name: newProject.name,
                short_name: shortName,
                description: newProject.description || null,
                start_date: newProject.start_date || null,
                end_date: newProject.end_date || null,
                unidade_id: unidadeId,
                company_id: unidadeId,
                status: newProject.status ? parseInt(newProject.status, 10) : 0
            })
            toast.success('Projeto atualizado com sucesso!')
            setIsEditModalOpen(false)
            setEditingProject(null)
            setNewProject({ name: '', short_name: '', description: '', start_date: '', end_date: '', company_id: '', status: '1' })
            validation.clearErrors()
            loadProjects()
        } catch (err) {
            toast.error('Erro ao atualizar projeto: ' + err.message)
        } finally {
            setCreating(false)
        }
    }

    async function handleDeleteProject(project) {
        if (!project) return
        if (!confirm(`Deseja apagar o projeto "${project.name}"?`)) return
        try {
            await deleteProject(project.id)
            toast.success('Projeto apagado com sucesso!')
            loadProjects()
        } catch (err) {
            toast.error('Erro ao apagar projeto: ' + err.message)
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
                    <button className="btn btn-primary" onClick={async () => { await loadUnidades(); setIsModalOpen(true) }}>+ Novo Projeto</button>
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
                                        <th>Unidade</th>
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
                                            <td>{getProjectUnitName(project)}</td>
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
                                                <button
                                                    className="btn btn-secondary"
                                                    style={{ padding: 'var(--spacing-1) var(--spacing-2)' }}
                                                    onClick={() => navigate(`/projects/${project.id}`)}
                                                >
                                                    Ver
                                                </button>
                                                <button
                                                    className="btn btn-secondary"
                                                    style={{ padding: 'var(--spacing-1) var(--spacing-2)', marginLeft: 'var(--spacing-2)' }}
                                                    onClick={async () => {
                                                        await loadUnidades()
                                                        setEditingProject(project)
                                                        setNewProject({
                                                            name: project.name || '',
                                                            short_name: project.short_name || '',
                                                            description: project.description || '',
                                                            start_date: normalizeDateValue(project.start_date),
                                                            end_date: normalizeDateValue(project.end_date),
                                                            company_id: getProjectUnitId(project),
                                                            status: project.status != null ? String(project.status) : '1'
                                                        })
                                                        validation.clearErrors()
                                                        setIsEditModalOpen(true)
                                                    }}
                                                >
                                                    Editar
                                                </button>
                                                <button
                                                    className="btn btn-secondary"
                                                    style={{ padding: 'var(--spacing-1) var(--spacing-2)', marginLeft: 'var(--spacing-2)', color: 'var(--color-danger-600)' }}
                                                    onClick={() => handleDeleteProject(project)}
                                                >
                                                    Apagar
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
                            disabled={creating || !newProject.name.trim() || !newProject.company_id}
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
                            onChange={(e) => {
                                setNewProject({ ...newProject, name: e.target.value })
                                validation.clearFieldError('name')
                            }}
                            placeholder="Digite o nome do projeto"
                            style={validation.errors.name ? { borderColor: 'var(--color-danger-500)' } : {}}
                        />
                        {validation.errors.name && (
                            <span style={{ color: 'var(--color-danger-500)', fontSize: '0.75rem', marginTop: 'var(--spacing-1)', display: 'block' }}>
                                {validation.errors.name}
                            </span>
                        )}
                    </div>

                    <div style={{ marginBottom: 'var(--spacing-4)' }}>
                        <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                            Nome Curto
                            {newProject.short_name && (
                                <span style={{
                                    fontSize: '0.75rem',
                                    color: newProject.short_name.length > 8 ? 'var(--color-warning-500)' : 'var(--color-gray-400)',
                                    marginLeft: 'var(--spacing-2)',
                                    fontWeight: 'normal'
                                }}>
                                    ({newProject.short_name.length}/10)
                                </span>
                            )}
                        </label>
                        <Input
                            value={newProject.short_name}
                            onChange={(e) => {
                                setNewProject({ ...newProject, short_name: e.target.value })
                                validation.clearFieldError('short_name')
                            }}
                            placeholder="Ex: PROJ-2024"
                            style={validation.errors.short_name ? { borderColor: 'var(--color-danger-500)' } : {}}
                        />
                        {validation.errors.short_name && (
                            <span style={{ color: 'var(--color-danger-500)', fontSize: '0.75rem', marginTop: 'var(--spacing-1)', display: 'block' }}>
                                {validation.errors.short_name}
                            </span>
                        )}
                    </div>

                    <div style={{ marginBottom: 'var(--spacing-4)' }}>
                        <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                            Descrição
                            {newProject.description && (
                                <span style={{
                                    fontSize: '0.75rem',
                                    color: newProject.description.length > 900 ? 'var(--color-warning-500)' : 'var(--color-gray-400)',
                                    marginLeft: 'var(--spacing-2)',
                                    fontWeight: 'normal'
                                }}>
                                    ({newProject.description.length}/1000)
                                </span>
                            )}
                        </label>
                        <textarea
                            value={newProject.description}
                            onChange={(e) => {
                                setNewProject({ ...newProject, description: e.target.value })
                                validation.clearFieldError('description')
                            }}
                            placeholder="Descrição do projeto"
                            rows={3}
                            style={{
                                width: '100%',
                                padding: 'var(--spacing-2) var(--spacing-3)',
                                border: validation.errors.description ? '1px solid var(--color-danger-500)' : '1px solid var(--color-gray-300)',
                                borderRadius: 'var(--radius-md)',
                                fontSize: '0.875rem',
                                fontFamily: 'inherit'
                            }}
                        />
                        {validation.errors.description && (
                            <span style={{ color: 'var(--color-danger-500)', fontSize: '0.75rem', marginTop: 'var(--spacing-1)', display: 'block' }}>
                                {validation.errors.description}
                            </span>
                        )}
                    </div>

                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 'var(--spacing-4)' }}>
                        <div>
                        <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                            Data de Início
                        </label>
                        <Input
                            type="date"
                            value={newProject.start_date}
                            onChange={(e) => {
                                setNewProject({ ...newProject, start_date: e.target.value })
                                validation.clearFieldError('dates')
                            }}
                            style={{ width: '100%' }}
                        />
                    </div>

                    <div>
                        <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                            Data de Término
                        </label>
                        <Input
                            type="date"
                            value={newProject.end_date}
                            onChange={(e) => {
                                setNewProject({ ...newProject, end_date: e.target.value })
                                validation.clearFieldError('dates')
                            }}
                            min={newProject.start_date || undefined}
                            style={{
                                width: '100%',
                                ...(validation.errors.dates ? { borderColor: 'var(--color-danger-500)' } : {})
                            }}
                        />
                    </div>
                </div>
                    {validation.errors.dates && (
                        <span style={{ color: 'var(--color-danger-500)', fontSize: '0.75rem', marginTop: 'var(--spacing-2)', display: 'block' }}>
                            {validation.errors.dates}
                        </span>
                    )}

                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 'var(--spacing-4)', marginTop: 'var(--spacing-4)' }}>
                        <div>
                            <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                                Unidade Responsável {unidades.length > 0 ? '*' : ''}
                            </label>
                            <select
                                value={newProject.company_id}
                                onChange={(e) => {
                                    setNewProject({ ...newProject, company_id: e.target.value })
                                    validation.clearFieldError('company_id')
                                }}
                                disabled={unidadesLoading}
                                style={{
                                    width: '100%',
                                    padding: 'var(--spacing-2) var(--spacing-3)',
                                    border: validation.errors.company_id ? '1px solid var(--color-danger-500)' : '1px solid var(--color-gray-300)',
                                    borderRadius: 'var(--radius-md)',
                                    fontSize: '0.875rem',
                                    background: 'white'
                                }}
                            >
                                <option value="">
                                    {unidadesLoading ? 'Carregando unidades...' : 'Selecione uma unidade'}
                                </option>
                                {unidades.map((unidade) => (
                                    <option key={unidade.id} value={unidade.id}>
                                        {unidade.nome}
                                        {unidade.nivel_label ? ` (${unidade.nivel_label})` : ''}
                                    </option>
                                ))}
                            </select>
                            {validation.errors.company_id && (
                                <span style={{ color: 'var(--color-danger-500)', fontSize: '0.75rem', marginTop: 'var(--spacing-1)', display: 'block' }}>
                                    {validation.errors.company_id}
                                </span>
                            )}
                        </div>

                        <div>
                            <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                                Status Inicial
                            </label>
                            <select
                                value={newProject.status}
                                onChange={(e) => setNewProject({ ...newProject, status: e.target.value })}
                                style={{
                                    width: '100%',
                                    padding: 'var(--spacing-2) var(--spacing-3)',
                                    border: '1px solid var(--color-gray-300)',
                                    borderRadius: 'var(--radius-md)',
                                    fontSize: '0.875rem',
                                    background: 'white'
                                }}
                            >
                                <option value="0">Não definido</option>
                                <option value="1">Proposto</option>
                                <option value="2">Em planejamento</option>
                                <option value="3">Em progresso</option>
                                <option value="4">Em espera</option>
                                <option value="5">Completo</option>
                                <option value="6">Arquivado</option>
                            </select>
                        </div>
                    </div>
                </form>
            </Modal>

            {/* Edit Project Modal */}
            <Modal
                isOpen={isEditModalOpen}
                onClose={() => {
                    setIsEditModalOpen(false)
                    setEditingProject(null)
                }}
                title="Editar Projeto"
                footer={
                    <>
                        <Button variant="secondary" onClick={() => {
                            setIsEditModalOpen(false)
                            setEditingProject(null)
                        }}>
                            Cancelar
                        </Button>
                        <Button
                            variant="primary"
                            onClick={handleUpdateProject}
                            disabled={creating || !newProject.name.trim() || !newProject.company_id}
                        >
                            {creating ? 'Salvando...' : 'Salvar Alterações'}
                        </Button>
                    </>
                }
            >
                <form onSubmit={handleUpdateProject}>
                    <div style={{ marginBottom: 'var(--spacing-4)' }}>
                        <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                            Nome do Projeto *
                        </label>
                        <Input
                            value={newProject.name}
                            onChange={(e) => {
                                setNewProject({ ...newProject, name: e.target.value })
                                validation.clearFieldError('name')
                            }}
                            placeholder="Digite o nome do projeto"
                            style={validation.errors.name ? { borderColor: 'var(--color-danger-500)' } : {}}
                        />
                        {validation.errors.name && (
                            <span style={{ color: 'var(--color-danger-500)', fontSize: '0.75rem', marginTop: 'var(--spacing-1)', display: 'block' }}>
                                {validation.errors.name}
                            </span>
                        )}
                    </div>

                    <div style={{ marginBottom: 'var(--spacing-4)' }}>
                        <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                            Nome Curto
                            {newProject.short_name && (
                                <span style={{
                                    fontSize: '0.75rem',
                                    color: newProject.short_name.length > 8 ? 'var(--color-warning-500)' : 'var(--color-gray-400)',
                                    marginLeft: 'var(--spacing-2)',
                                    fontWeight: 'normal'
                                }}>
                                    ({newProject.short_name.length}/10)
                                </span>
                            )}
                        </label>
                        <Input
                            value={newProject.short_name}
                            onChange={(e) => {
                                setNewProject({ ...newProject, short_name: e.target.value })
                                validation.clearFieldError('short_name')
                            }}
                            placeholder="Ex: PROJ-2024"
                            style={validation.errors.short_name ? { borderColor: 'var(--color-danger-500)' } : {}}
                        />
                        {validation.errors.short_name && (
                            <span style={{ color: 'var(--color-danger-500)', fontSize: '0.75rem', marginTop: 'var(--spacing-1)', display: 'block' }}>
                                {validation.errors.short_name}
                            </span>
                        )}
                    </div>

                    <div style={{ marginBottom: 'var(--spacing-4)' }}>
                        <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                            Descrição
                            {newProject.description && (
                                <span style={{
                                    fontSize: '0.75rem',
                                    color: newProject.description.length > 900 ? 'var(--color-warning-500)' : 'var(--color-gray-400)',
                                    marginLeft: 'var(--spacing-2)',
                                    fontWeight: 'normal'
                                }}>
                                    ({newProject.description.length}/1000)
                                </span>
                            )}
                        </label>
                        <textarea
                            value={newProject.description}
                            onChange={(e) => {
                                setNewProject({ ...newProject, description: e.target.value })
                                validation.clearFieldError('description')
                            }}
                            placeholder="Descrição do projeto"
                            rows={3}
                            style={{
                                width: '100%',
                                padding: 'var(--spacing-2) var(--spacing-3)',
                                border: validation.errors.description ? '1px solid var(--color-danger-500)' : '1px solid var(--color-gray-300)',
                                borderRadius: 'var(--radius-md)',
                                fontSize: '0.875rem',
                                fontFamily: 'inherit'
                            }}
                        />
                        {validation.errors.description && (
                            <span style={{ color: 'var(--color-danger-500)', fontSize: '0.75rem', marginTop: 'var(--spacing-1)', display: 'block' }}>
                                {validation.errors.description}
                            </span>
                        )}
                    </div>

                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 'var(--spacing-4)' }}>
                        <div>
                            <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                                Data de Início
                            </label>
                            <Input
                                type="date"
                                value={newProject.start_date}
                                onChange={(e) => {
                                    setNewProject({ ...newProject, start_date: e.target.value })
                                    validation.clearFieldError('dates')
                                }}
                                style={{ width: '100%' }}
                            />
                        </div>

                        <div>
                            <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                                Data de Término
                            </label>
                            <Input
                                type="date"
                                value={newProject.end_date}
                                onChange={(e) => {
                                    setNewProject({ ...newProject, end_date: e.target.value })
                                    validation.clearFieldError('dates')
                                }}
                                min={newProject.start_date || undefined}
                                style={{
                                    width: '100%',
                                    ...(validation.errors.dates ? { borderColor: 'var(--color-danger-500)' } : {})
                                }}
                            />
                        </div>
                    </div>
                    {validation.errors.dates && (
                        <span style={{ color: 'var(--color-danger-500)', fontSize: '0.75rem', marginTop: 'var(--spacing-2)', display: 'block' }}>
                            {validation.errors.dates}
                        </span>
                    )}

                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 'var(--spacing-4)', marginTop: 'var(--spacing-4)' }}>
                        <div>
                            <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                                Unidade Responsável {unidades.length > 0 ? '*' : ''}
                            </label>
                            <select
                                value={newProject.company_id}
                                onChange={(e) => {
                                    setNewProject({ ...newProject, company_id: e.target.value })
                                    validation.clearFieldError('company_id')
                                }}
                                disabled={unidadesLoading}
                                style={{
                                    width: '100%',
                                    padding: 'var(--spacing-2) var(--spacing-3)',
                                    border: validation.errors.company_id ? '1px solid var(--color-danger-500)' : '1px solid var(--color-gray-300)',
                                    borderRadius: 'var(--radius-md)',
                                    fontSize: '0.875rem',
                                    background: 'white'
                                }}
                            >
                                <option value="">
                                    {unidadesLoading ? 'Carregando unidades...' : 'Selecione uma unidade'}
                                </option>
                                {unidades.map((unidade) => (
                                    <option key={unidade.id} value={unidade.id}>
                                        {unidade.nome}
                                        {unidade.nivel_label ? ` (${unidade.nivel_label})` : ''}
                                    </option>
                                ))}
                            </select>
                            {validation.errors.company_id && (
                                <span style={{ color: 'var(--color-danger-500)', fontSize: '0.75rem', marginTop: 'var(--spacing-1)', display: 'block' }}>
                                    {validation.errors.company_id}
                                </span>
                            )}
                        </div>

                        <div>
                            <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                                Status Inicial
                            </label>
                            <select
                                value={newProject.status}
                                onChange={(e) => setNewProject({ ...newProject, status: e.target.value })}
                                style={{
                                    width: '100%',
                                    padding: 'var(--spacing-2) var(--spacing-3)',
                                    border: '1px solid var(--color-gray-300)',
                                    borderRadius: 'var(--radius-md)',
                                    fontSize: '0.875rem',
                                    background: 'white'
                                }}
                            >
                                <option value="0">Não definido</option>
                                <option value="1">Proposto</option>
                                <option value="2">Em planejamento</option>
                                <option value="3">Em progresso</option>
                                <option value="4">Em espera</option>
                                <option value="5">Completo</option>
                                <option value="6">Arquivado</option>
                            </select>
                        </div>
                    </div>
                </form>
            </Modal>
        </>
    )
}

export default Projects


