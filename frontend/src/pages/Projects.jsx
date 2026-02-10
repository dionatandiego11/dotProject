import { useState, useEffect, useMemo } from 'react'
import { useNavigate } from 'react-router-dom'
import { getProjects, createProject, updateProject, updateProjectStatus, deleteProject, getUnidades } from '../services/api'
import Modal from '../components/ui/Modal'
import Button from '../components/ui/Button'
import Input from '../components/ui/Input'
import { useToast } from '../contexts/ToastContext'
import { useValidation } from '../hooks/useValidation'

const PROJECT_STATUS_LABELS = {
    0: 'Nao definido',
    1: 'Proposto',
    2: 'Em planejamento',
    3: 'Em progresso',
    4: 'Em espera',
    5: 'Completo',
    6: 'Arquivado'
}

const PROJECT_STATUS_BADGES = {
    0: 'info',
    1: 'info',
    2: 'warning',
    3: 'success',
    4: 'warning',
    5: 'success',
    6: 'info'
}

const CREATE_ALLOWED_PROJECT_STATUSES = [0, 1, 2, 3, 4]

const PROJECT_STATUS_TRANSITIONS = {
    0: [1, 2, 3, 4],
    1: [0, 2, 3, 4],
    2: [0, 1, 3, 4],
    3: [4, 5],
    4: [3, 5],
    5: [3, 6],
    6: []
}

function getStatusLabelById(status) {
    return PROJECT_STATUS_LABELS[Number(status)] || 'Desconhecido'
}

function getStatusBadgeById(status) {
    return PROJECT_STATUS_BADGES[Number(status)] || 'info'
}

function getAllowedProjectStatuses(currentStatus, options = {}) {
    const includeCurrent = options.includeCurrent !== false
    const forCreate = options.forCreate === true

    if (forCreate) {
        return [...CREATE_ALLOWED_PROJECT_STATUSES]
    }

    const numericCurrent = Number(currentStatus)
    if (Number.isNaN(numericCurrent)) {
        return [...CREATE_ALLOWED_PROJECT_STATUSES]
    }

    const transitions = PROJECT_STATUS_TRANSITIONS[numericCurrent] || []
    const statuses = includeCurrent ? [numericCurrent, ...transitions] : [...transitions]

    return Array.from(new Set(statuses)).sort((a, b) => a - b)
}

function canTransitionProjectStatus(currentStatus, targetStatus) {
    const numericCurrent = Number(currentStatus)
    const numericTarget = Number(targetStatus)

    if (Number.isNaN(numericCurrent) || Number.isNaN(numericTarget)) {
        return false
    }

    if (numericCurrent === numericTarget) {
        return true
    }

    const transitions = PROJECT_STATUS_TRANSITIONS[numericCurrent] || []
    return transitions.includes(numericTarget)
}

function buildUnidadeHierarchy(unidade, unidadeById) {
    const hierarchy = []
    const visited = new Set()
    let current = unidade

    while (current && !visited.has(current.id)) {
        hierarchy.unshift(current)
        visited.add(current.id)
        const parentId = current.pai_id
        current = parentId ? unidadeById.get(parentId) || null : null
    }

    return hierarchy
}

function buildGroupedUnidades(unidades) {
    const unidadeById = new Map()
    const collator = new Intl.Collator('pt-BR', { sensitivity: 'base', numeric: true })

    for (const rawUnidade of unidades) {
        if (!rawUnidade || rawUnidade.id === null || rawUnidade.id === undefined) continue
        const id = Number(rawUnidade.id)
        const paiId = rawUnidade.pai_id === null || rawUnidade.pai_id === undefined
            ? null
            : Number(rawUnidade.pai_id)

        unidadeById.set(id, {
            ...rawUnidade,
            id,
            pai_id: paiId
        })
    }

    const grouped = new Map()

    for (const unidade of unidadeById.values()) {
        const hierarchy = buildUnidadeHierarchy(unidade, unidadeById)
        const secretariaNode = hierarchy.find((node) => node.nivel === 2)
        const groupLabel = secretariaNode?.nome || 'Demais unidades'

        let relativeHierarchy = hierarchy
        if (secretariaNode) {
            const index = hierarchy.findIndex((node) => node.id === secretariaNode.id)
            relativeHierarchy = hierarchy.slice(index)
        }

        const path = hierarchy.map((node) => node.nome).join(' > ')
        const pathLabel = relativeHierarchy.map((node) => node.nome).join(' > ') || unidade.nome
        const levelLabel = unidade.nivel_label ? ` (${unidade.nivel_label})` : ''
        const option = {
            id: unidade.id,
            label: `${pathLabel}${levelLabel}`,
            path,
            nivel: unidade.nivel ?? Number.MAX_SAFE_INTEGER
        }

        if (!grouped.has(groupLabel)) {
            grouped.set(groupLabel, [])
        }

        grouped.get(groupLabel).push(option)
    }

    return Array.from(grouped.entries())
        .map(([label, options]) => ({
            label,
            options: options.sort((a, b) => {
                if (a.nivel !== b.nivel) {
                    return a.nivel - b.nivel
                }

                return collator.compare(a.path, b.path)
            })
        }))
        .sort((a, b) => collator.compare(a.label, b.label))
}

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
    const groupedUnidades = useMemo(() => buildGroupedUnidades(unidades), [unidades])

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
    const [quickStatusSelectionByProjectId, setQuickStatusSelectionByProjectId] = useState({})
    const [quickStatusLoadingByProjectId, setQuickStatusLoadingByProjectId] = useState({})
    const createStatusOptions = useMemo(
        () => getAllowedProjectStatuses(null, { forCreate: true }),
        []
    )
    const editCurrentStatus = useMemo(() => {
        if (!editingProject) return null
        const numeric = Number(editingProject.status)
        return Number.isNaN(numeric) ? 0 : numeric
    }, [editingProject])
    const editStatusOptions = useMemo(
        () => getAllowedProjectStatuses(editCurrentStatus),
        [editCurrentStatus]
    )
    const editStatusHint = useMemo(() => {
        if (editCurrentStatus === null) return ''
        const transitions = getAllowedProjectStatuses(editCurrentStatus, { includeCurrent: false })
        if (transitions.length === 0) {
            return 'Este status nao permite novas transicoes.'
        }

        return `Transicoes permitidas: ${transitions.map(getStatusLabelById).join(', ')}.`
    }, [editCurrentStatus])

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
            const data = await getUnidades({ escopo: 1 })
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

    function getNumericProjectStatus(project) {
        const numeric = Number(project?.status)
        return Number.isNaN(numeric) ? 0 : numeric
    }

    function isQuickStatusLoading(projectId) {
        return Boolean(quickStatusLoadingByProjectId[String(projectId)])
    }

    function getQuickStatusSelection(projectId) {
        return quickStatusSelectionByProjectId[String(projectId)] ?? ''
    }

    function confirmProjectStatusTransition(currentStatus, nextStatus) {
        if (currentStatus === nextStatus) {
            return true
        }

        if (nextStatus === 5) {
            return confirm('Marcar este projeto como Completo? O progresso sera ajustado para 100%.')
        }

        if (nextStatus === 6) {
            return confirm('Arquivar este projeto? Ele sera removido dos fluxos operacionais ativos.')
        }

        return true
    }

    async function handleApplyQuickStatus(project) {
        const projectId = Number(project?.id)
        if (!projectId) return

        const selection = getQuickStatusSelection(projectId)
        if (!selection) {
            toast.error('Selecione um status para aplicar.')
            return
        }

        const currentStatus = getNumericProjectStatus(project)
        const nextStatus = parseInt(selection, 10)

        if (Number.isNaN(nextStatus)) {
            toast.error('Status selecionado invalido.')
            return
        }

        if (!canTransitionProjectStatus(currentStatus, nextStatus)) {
            toast.error(
                `Transicao de status invalida: ${getStatusLabelById(currentStatus)} -> ${getStatusLabelById(nextStatus)}.`
            )
            return
        }

        if (!confirmProjectStatusTransition(currentStatus, nextStatus)) {
            return
        }

        const projectKey = String(projectId)
        setQuickStatusLoadingByProjectId((prev) => ({ ...prev, [projectKey]: true }))

        try {
            const payload = await updateProjectStatus(projectId, { status: nextStatus })
            const updatedStatus = Number(payload?.status ?? nextStatus)
            const updatedPercent = Number(
                payload?.percent_complete
                ?? (updatedStatus === 5 ? 100 : project?.percent_complete ?? 0)
            )

            setProjects((prevProjects) => prevProjects.map((item) => {
                if (Number(item.id) !== projectId) return item
                return {
                    ...item,
                    status: updatedStatus,
                    percent_complete: Number.isNaN(updatedPercent) ? item.percent_complete : updatedPercent
                }
            }))
            setQuickStatusSelectionByProjectId((prev) => ({ ...prev, [projectKey]: '' }))
            toast.success(`Status atualizado para "${getStatusLabelById(updatedStatus)}".`)
        } catch (err) {
            toast.error('Erro ao atualizar status: ' + err.message)
        } finally {
            setQuickStatusLoadingByProjectId((prev) => ({ ...prev, [projectKey]: false }))
        }
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
            const statusValue = newProject.status ? parseInt(newProject.status, 10) : 0

            if (!CREATE_ALLOWED_PROJECT_STATUSES.includes(statusValue)) {
                toast.error('Status inicial invalido para criacao de projeto.')
                return
            }

            await createProject({
                name: newProject.name,
                short_name: shortName,
                description: newProject.description || null,
                start_date: newProject.start_date || null,
                end_date: newProject.end_date || null,
                unidade_id: unidadeId,
                company_id: unidadeId,
                status: statusValue
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
            const currentStatus = getNumericProjectStatus(editingProject)
            const nextStatus = newProject.status ? parseInt(newProject.status, 10) : currentStatus

            if (!canTransitionProjectStatus(currentStatus, nextStatus)) {
                toast.error(
                    `Transicao de status invalida: ${getStatusLabelById(currentStatus)} -> ${getStatusLabelById(nextStatus)}.`
                )
                return
            }

            if (!confirmProjectStatusTransition(currentStatus, nextStatus)) {
                return
            }

            await updateProject(editingProject.id, {
                name: newProject.name,
                short_name: shortName,
                description: newProject.description || null,
                start_date: newProject.start_date || null,
                end_date: newProject.end_date || null,
                unidade_id: unidadeId,
                company_id: unidadeId
            })

            if (currentStatus !== nextStatus) {
                await updateProjectStatus(editingProject.id, {
                    status: nextStatus
                })
            }
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

    function handleEditStatusSelection(nextStatusValue) {
        const nextStatus = parseInt(nextStatusValue, 10)

        if (!editingProject) {
            setNewProject({ ...newProject, status: String(nextStatus) })
            return
        }

        const currentStatus = getNumericProjectStatus(editingProject)
        if (!canTransitionProjectStatus(currentStatus, nextStatus)) {
            toast.error(
                `Transicao nao permitida: ${getStatusLabelById(currentStatus)} -> ${getStatusLabelById(nextStatus)}.`
            )
            return
        }

        setNewProject({ ...newProject, status: String(nextStatus) })
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
                                    {projects.map((project) => {
                                        const currentStatus = getNumericProjectStatus(project)
                                        const quickStatusOptions = getAllowedProjectStatuses(currentStatus, { includeCurrent: false })
                                        const quickStatusValue = getQuickStatusSelection(project.id)
                                        const quickStatusLoading = isQuickStatusLoading(project.id)

                                        return (
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
                                                    <span className={`badge badge-${getStatusBadgeById(project.status)}`}>
                                                        {getStatusLabelById(project.status)}
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
                                                        type="button"
                                                        className="btn btn-secondary"
                                                        style={{ padding: 'var(--spacing-1) var(--spacing-2)' }}
                                                        onClick={() => navigate(`/projects/${project.id}`)}
                                                    >
                                                        Ver
                                                    </button>
                                                    <button
                                                        type="button"
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
                                                        type="button"
                                                        className="btn btn-secondary"
                                                        style={{ padding: 'var(--spacing-1) var(--spacing-2)', marginLeft: 'var(--spacing-2)', color: 'var(--color-danger-600)' }}
                                                        onClick={() => handleDeleteProject(project)}
                                                    >
                                                        Apagar
                                                    </button>

                                                    {quickStatusOptions.length > 0 && (
                                                        <div style={{ display: 'flex', gap: 'var(--spacing-2)', marginTop: 'var(--spacing-2)' }}>
                                                            <select
                                                                value={quickStatusValue}
                                                                onChange={(e) => {
                                                                    const projectKey = String(project.id)
                                                                    setQuickStatusSelectionByProjectId((prev) => ({
                                                                        ...prev,
                                                                        [projectKey]: e.target.value
                                                                    }))
                                                                }}
                                                                disabled={quickStatusLoading}
                                                                style={{
                                                                    minWidth: 160,
                                                                    padding: 'var(--spacing-1) var(--spacing-2)',
                                                                    border: '1px solid var(--color-gray-300)',
                                                                    borderRadius: 'var(--radius-md)',
                                                                    fontSize: '0.75rem',
                                                                    background: 'white'
                                                                }}
                                                            >
                                                                <option value="">Status rapido...</option>
                                                                {quickStatusOptions.map((status) => (
                                                                    <option key={status} value={String(status)}>
                                                                        {getStatusLabelById(status)}
                                                                    </option>
                                                                ))}
                                                            </select>
                                                            <button
                                                                type="button"
                                                                className="btn btn-secondary"
                                                                style={{ padding: 'var(--spacing-1) var(--spacing-2)' }}
                                                                disabled={!quickStatusValue || quickStatusLoading}
                                                                onClick={() => handleApplyQuickStatus(project)}
                                                            >
                                                                {quickStatusLoading ? 'Aplicando...' : 'Aplicar'}
                                                            </button>
                                                        </div>
                                                    )}
                                                </td>
                                            </tr>
                                        )
                                    })}
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
                                {groupedUnidades.map((grupo, index) => (
                                    <optgroup key={`${grupo.label}-${index}`} label={grupo.label}>
                                        {grupo.options.map((unidade) => (
                                            <option key={unidade.id} value={unidade.id}>
                                                {unidade.label}
                                            </option>
                                        ))}
                                    </optgroup>
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
                                {createStatusOptions.map((status) => (
                                    <option key={status} value={String(status)}>
                                        {getStatusLabelById(status)}
                                    </option>
                                ))}
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
                                {groupedUnidades.map((grupo, index) => (
                                    <optgroup key={`${grupo.label}-${index}`} label={grupo.label}>
                                        {grupo.options.map((unidade) => (
                                            <option key={unidade.id} value={unidade.id}>
                                                {unidade.label}
                                            </option>
                                        ))}
                                    </optgroup>
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
                                Status do Projeto
                            </label>
                            <select
                                value={newProject.status}
                                onChange={(e) => handleEditStatusSelection(e.target.value)}
                                style={{
                                    width: '100%',
                                    padding: 'var(--spacing-2) var(--spacing-3)',
                                    border: '1px solid var(--color-gray-300)',
                                    borderRadius: 'var(--radius-md)',
                                    fontSize: '0.875rem',
                                    background: 'white'
                                }}
                            >
                                {editStatusOptions.map((status) => (
                                    <option key={status} value={String(status)}>
                                        {getStatusLabelById(status)}
                                    </option>
                                ))}
                            </select>
                            {editStatusHint && (
                                <span style={{ color: 'var(--color-gray-500)', fontSize: '0.75rem', marginTop: 'var(--spacing-1)', display: 'block' }}>
                                    {editStatusHint}
                                </span>
                            )}
                        </div>
                    </div>
                </form>
            </Modal>
        </>
    )
}

export default Projects
