import { useEffect, useMemo, useState } from 'react'
import { createTask, getProjects, getTasks, updateTask } from '../../services/api'
import Modal from '../../components/ui/Modal'
import Button from '../../components/ui/Button'
import Input from '../../components/ui/Input'
import TaskEditModal from '../../components/TaskEditModal'
import { useToast } from '../../contexts/ToastContext'
import { useValidation } from '../../hooks/useValidation'
import useCurrentUser from '../../hooks/useCurrentUser'

const EMPTY_TASK_FORM = {
    name: '',
    description: '',
    project_id: '',
    priority: '1',
    end_date: '',
}

const TASK_STATUS_OPTIONS = [
    { value: '', label: 'Todos os status' },
    { value: '0', label: 'Backlog' },
    { value: '1', label: 'A fazer' },
    { value: '2', label: 'Em andamento' },
    { value: '3', label: 'Concluído' },
    { value: '4', label: 'Em espera' },
    { value: '5', label: 'Cancelado' },
    { value: '6', label: 'Arquivado' },
    { value: '7', label: 'Revisão' },
]

const PRIORITY_FILTER_OPTIONS = [
    { value: '', label: 'Todas as prioridades' },
    { value: '0', label: 'Baixa' },
    { value: '1', label: 'Normal' },
    { value: '2', label: 'Alta' },
    { value: '3', label: 'Urgente' },
]

function getPriorityLabel(priority) {
    const labels = { 0: 'Baixa', 1: 'Normal', 2: 'Alta', 3: 'Urgente' }
    return labels[priority] || 'Normal'
}

function getPriorityColor(priority) {
    const colors = {
        0: 'var(--color-gray-400)',
        1: 'var(--color-primary-500)',
        2: 'var(--color-warning-500)',
        3: 'var(--color-danger-500)',
    }
    return colors[priority] || 'var(--color-gray-400)'
}

function getTaskStatusLabel(status) {
    const option = TASK_STATUS_OPTIONS.find((item) => Number(item.value) === Number(status))
    return option?.label || 'Indefinido'
}

function getTaskStatusStyles(status) {
    const map = {
        0: { color: '#6b7280', background: '#f3f4f6' },
        1: { color: '#475569', background: '#f1f5f9' },
        2: { color: '#1d4ed8', background: '#eff6ff' },
        3: { color: '#166534', background: '#f0fdf4' },
        4: { color: '#92400e', background: '#fffbeb' },
        5: { color: '#991b1b', background: '#fef2f2' },
        6: { color: '#374151', background: '#f3f4f6' },
        7: { color: '#6d28d9', background: '#f5f3ff' },
    }
    return map[Number(status)] || map[0]
}

function isOverdue(endDate, percentComplete) {
    if (!endDate || Number(percentComplete) >= 100) {
        return false
    }

    return new Date(endDate) < new Date()
}

export default function TarefasPage() {
    const { user } = useCurrentUser()
    const toast = useToast()
    const validation = useValidation()
    const [tasks, setTasks] = useState([])
    const [meta, setMeta] = useState({})
    const [projects, setProjects] = useState([])
    const [loading, setLoading] = useState(true)
    const [error, setError] = useState('')
    const [page, setPage] = useState(1)

    const [searchInput, setSearchInput] = useState('')
    const [search, setSearch] = useState('')
    const [projectIdFilter, setProjectIdFilter] = useState('')
    const [statusFilter, setStatusFilter] = useState('')
    const [priorityFilter, setPriorityFilter] = useState('')
    const [ownerIdFilter, setOwnerIdFilter] = useState('')
    const [onlyMine, setOnlyMine] = useState(false)
    const [overdueOnly, setOverdueOnly] = useState(false)

    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false)
    const [isEditModalOpen, setIsEditModalOpen] = useState(false)
    const [editingTask, setEditingTask] = useState(null)
    const [newTask, setNewTask] = useState(EMPTY_TASK_FORM)
    const [saving, setSaving] = useState(false)

    const totalTasks = useMemo(() => Number(meta.total || tasks.length || 0), [meta.total, tasks.length])
    const activeFilters = useMemo(() => {
        const labels = []

        if (search.trim()) {
            labels.push(`Busca: "${search.trim()}"`)
        }

        if (projectIdFilter) {
            const project = projects.find((item) => Number(item.id) === Number(projectIdFilter))
            labels.push(`Projeto: ${project?.name || `#${projectIdFilter}`}`)
        }

        if (statusFilter) {
            labels.push(`Status: ${getTaskStatusLabel(statusFilter)}`)
        }

        if (priorityFilter) {
            labels.push(`Prioridade: ${getPriorityLabel(priorityFilter)}`)
        }

        if (onlyMine && user?.id) {
            labels.push('Somente minhas tarefas')
        } else if (ownerIdFilter.trim()) {
            labels.push(`Responsável: #${ownerIdFilter.trim()}`)
        }

        if (overdueOnly) {
            labels.push('Somente atrasadas')
        }

        return labels
    }, [search, projectIdFilter, projects, statusFilter, priorityFilter, onlyMine, ownerIdFilter, overdueOnly, user?.id])

    useEffect(() => {
        loadProjects()
    }, [])

    useEffect(() => {
        loadTasks(page)
    }, [page, search, projectIdFilter, statusFilter, priorityFilter, ownerIdFilter, onlyMine, overdueOnly, user?.id])

    async function loadProjects() {
        try {
            const data = await getProjects({ per_page: 200 })
            setProjects(Array.isArray(data?.data) ? data.data : [])
        } catch {
            setProjects([])
        }
    }

    async function loadTasks(targetPage = 1) {
        try {
            setLoading(true)
            setError('')
            const params = {
                page: targetPage,
                per_page: 30,
            }

            if (search.trim()) {
                params.search = search.trim()
            }
            if (projectIdFilter) {
                params.project_id = projectIdFilter
            }
            if (statusFilter) {
                params.status = statusFilter
            }
            if (priorityFilter) {
                params.priority = priorityFilter
            }

            if (onlyMine && user?.id) {
                params.owner_id = String(user.id)
            } else if (ownerIdFilter.trim()) {
                params.owner_id = ownerIdFilter.trim()
            }
            if (overdueOnly) {
                params.overdue = 'true'
            }

            const data = await getTasks(params)
            setTasks(Array.isArray(data?.data) ? data.data : [])
            setMeta(data?.meta || {})
        } catch (err) {
            setError(err?.message || 'Falha ao carregar tarefas.')
            setTasks([])
            setMeta({})
        } finally {
            setLoading(false)
        }
    }

    function applyFilters(event) {
        event?.preventDefault()
        setPage(1)
        setSearch(searchInput)
    }

    function resetFilters() {
        setSearchInput('')
        setSearch('')
        setProjectIdFilter('')
        setStatusFilter('')
        setPriorityFilter('')
        setOwnerIdFilter('')
        setOnlyMine(false)
        setOverdueOnly(false)
        setPage(1)
    }

    async function handleProgressChange(taskId, percent) {
        try {
            await updateTask(taskId, { percent_complete: percent })
            setTasks((current) => current.map((task) => (
                Number(task.id) === Number(taskId)
                    ? { ...task, percent_complete: percent }
                    : task
            )))
            toast.success('Progresso atualizado.')
        } catch (err) {
            toast.error(err?.message || 'Erro ao atualizar progresso.')
        }
    }

    async function handleCreateTask(event) {
        event.preventDefault()
        validation.clearErrors()

        const isValid = validation.validateFields({
            name: () => validation.validateRequired(newTask.name, 'Nome da tarefa'),
            description: () => validation.validateMaxLength(newTask.description, 500, 'Descrição'),
            end_date: () => validation.validateFutureDate(newTask.end_date, 'Prazo'),
            project_id: () => validation.validateRequired(newTask.project_id, 'Projeto'),
        })

        if (!isValid) {
            return
        }

        try {
            setSaving(true)
            await createTask({
                name: newTask.name.trim(),
                description: newTask.description || null,
                project_id: newTask.project_id ? parseInt(newTask.project_id, 10) : null,
                priority: parseInt(newTask.priority, 10),
                end_date: newTask.end_date || null,
            })

            toast.success('Tarefa criada com sucesso.')
            setIsCreateModalOpen(false)
            setNewTask(EMPTY_TASK_FORM)
            validation.clearErrors()
            await loadTasks(page)
        } catch (err) {
            toast.error(err?.message || 'Falha ao criar tarefa.')
        } finally {
            setSaving(false)
        }
    }

    function openEditModal(task) {
        setEditingTask(task)
        setIsEditModalOpen(true)
    }

    async function handleUpdateTask(updatedData) {
        if (!editingTask?.id) {
            return
        }

        await updateTask(editingTask.id, updatedData)
        setIsEditModalOpen(false)
        setEditingTask(null)
        await loadTasks(page)
    }

    return (
        <>
            <div className="topbar">
                <h1 className="page-title">Tarefas</h1>
                <Button onClick={() => setIsCreateModalOpen(true)}>+ Nova tarefa</Button>
            </div>

            <div className="page-content">
                <form className="card" style={{ marginBottom: 'var(--spacing-4)' }} onSubmit={applyFilters}>
                    <div
                        className="card-body"
                        style={{
                            display: 'grid',
                            gap: 'var(--spacing-3)',
                            gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))',
                            alignItems: 'end',
                        }}
                    >
                        <Input
                            value={searchInput}
                            onChange={(event) => setSearchInput(event.target.value)}
                            placeholder="Buscar por nome ou descrição"
                        />
                        <select
                            value={projectIdFilter}
                            onChange={(event) => setProjectIdFilter(event.target.value)}
                            style={{
                                padding: 'var(--spacing-2) var(--spacing-3)',
                                border: '1px solid var(--color-gray-300)',
                                borderRadius: 'var(--radius-md)',
                                background: 'white',
                            }}
                        >
                            <option value="">Todos os projetos</option>
                            {projects.map((project) => (
                                <option key={project.id} value={String(project.id)}>
                                    {project.name}
                                </option>
                            ))}
                        </select>
                        <select
                            value={statusFilter}
                            onChange={(event) => setStatusFilter(event.target.value)}
                            style={{
                                padding: 'var(--spacing-2) var(--spacing-3)',
                                border: '1px solid var(--color-gray-300)',
                                borderRadius: 'var(--radius-md)',
                                background: 'white',
                            }}
                        >
                            {TASK_STATUS_OPTIONS.map((status) => (
                                <option key={status.value || 'all'} value={status.value}>
                                    {status.label}
                                </option>
                            ))}
                        </select>
                        <select
                            value={priorityFilter}
                            onChange={(event) => setPriorityFilter(event.target.value)}
                            style={{
                                padding: 'var(--spacing-2) var(--spacing-3)',
                                border: '1px solid var(--color-gray-300)',
                                borderRadius: 'var(--radius-md)',
                                background: 'white',
                            }}
                        >
                            {PRIORITY_FILTER_OPTIONS.map((priority) => (
                                <option key={priority.value || 'all'} value={priority.value}>
                                    {priority.label}
                                </option>
                            ))}
                        </select>
                        <Input
                            value={ownerIdFilter}
                            onChange={(event) => setOwnerIdFilter(event.target.value)}
                            placeholder="ID do responsável"
                            disabled={onlyMine}
                        />
                        <label style={{ display: 'flex', alignItems: 'center', gap: 'var(--spacing-2)', fontSize: '0.875rem' }}>
                            <input
                                type="checkbox"
                                checked={onlyMine}
                                onChange={(event) => setOnlyMine(event.target.checked)}
                                disabled={!user?.id}
                            />
                            Minhas tarefas
                        </label>
                        <label style={{ display: 'flex', alignItems: 'center', gap: 'var(--spacing-2)', fontSize: '0.875rem' }}>
                            <input
                                type="checkbox"
                                checked={overdueOnly}
                                onChange={(event) => setOverdueOnly(event.target.checked)}
                            />
                            Atrasadas
                        </label>
                        <div style={{ display: 'flex', gap: 'var(--spacing-2)', flexWrap: 'wrap' }}>
                            <Button type="submit" variant="secondary">Filtrar</Button>
                            <Button type="button" variant="ghost" onClick={resetFilters}>Limpar</Button>
                        </div>
                    </div>
                </form>

                {activeFilters.length > 0 && (
                    <div className="card" style={{ marginBottom: 'var(--spacing-4)' }}>
                        <div className="card-body" style={{ fontSize: '0.875rem', color: 'var(--color-gray-700)' }}>
                            <strong>Filtros ativos:</strong> {activeFilters.join(' | ')}
                        </div>
                    </div>
                )}

                {error && (
                    <div className="card" style={{ marginBottom: 'var(--spacing-4)' }}>
                        <div className="card-body" style={{ color: 'var(--color-danger-500)' }}>
                            {error}
                        </div>
                    </div>
                )}

                <div className="card">
                    <div className="card-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                        <h2 className="card-title">Lista de tarefas</h2>
                        <span style={{ fontSize: '0.875rem', color: 'var(--color-gray-500)' }}>
                            {totalTasks} tarefas
                        </span>
                    </div>
                    <div className="card-body" style={{ padding: 0 }}>
                        {loading ? (
                            <div style={{ padding: 'var(--spacing-6)', textAlign: 'center' }}>
                                Carregando...
                            </div>
                        ) : tasks.length === 0 ? (
                            <div style={{ padding: 'var(--spacing-6)', textAlign: 'center', color: 'var(--color-gray-500)' }}>
                                Nenhuma tarefa encontrada.
                            </div>
                        ) : (
                            <table className="table">
                                <thead>
                                    <tr>
                                        <th>Tarefa</th>
                                        <th>Projeto</th>
                                        <th>Status</th>
                                        <th>Prioridade</th>
                                        <th>Prazo</th>
                                        <th>Progresso</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {tasks.map((task) => {
                                        const overdue = isOverdue(task.end_date, task.percent_complete)

                                        return (
                                            <tr key={task.id} style={overdue ? { background: '#fef2f2' } : {}}>
                                                <td>
                                                    <strong>{task.name}</strong>
                                                    {task.description && (
                                                        <div style={{ fontSize: '0.8rem', color: 'var(--color-gray-600)' }}>
                                                            {task.description}
                                                        </div>
                                                    )}
                                                </td>
                                                <td>{task.project?.name || '-'}</td>
                                                <td>
                                                    <span
                                                        style={{
                                                            display: 'inline-block',
                                                            padding: '2px 8px',
                                                            borderRadius: 999,
                                                            fontSize: '0.75rem',
                                                            fontWeight: 600,
                                                            ...getTaskStatusStyles(task.status),
                                                        }}
                                                    >
                                                        {getTaskStatusLabel(task.status)}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span style={{ color: getPriorityColor(task.priority), fontWeight: 600 }}>
                                                        {getPriorityLabel(task.priority)}
                                                    </span>
                                                </td>
                                                <td>
                                                    {task.end_date
                                                        ? new Date(task.end_date).toLocaleDateString('pt-BR')
                                                        : '-'}
                                                    {overdue ? ' (atrasada)' : ''}
                                                </td>
                                                <td>
                                                    <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--spacing-2)' }}>
                                                        <input
                                                            type="range"
                                                            min="0"
                                                            max="100"
                                                            step="10"
                                                            value={task.percent_complete || 0}
                                                            onChange={(event) => handleProgressChange(task.id, parseInt(event.target.value, 10))}
                                                            style={{ width: 90 }}
                                                        />
                                                        <span style={{ minWidth: 38, fontSize: '0.75rem' }}>
                                                            {task.percent_complete || 0}%
                                                        </span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <button
                                                        className="btn btn-secondary"
                                                        style={{ padding: 'var(--spacing-1) var(--spacing-2)' }}
                                                        onClick={() => openEditModal(task)}
                                                    >
                                                        Editar
                                                    </button>
                                                </td>
                                            </tr>
                                        )
                                    })}
                                </tbody>
                            </table>
                        )}
                    </div>
                </div>

                {Number(meta?.total_pages || 0) > 1 && (
                    <div style={{ display: 'flex', justifyContent: 'center', gap: 'var(--spacing-2)', marginTop: 'var(--spacing-6)' }}>
                        {Array.from({ length: Number(meta.total_pages) }, (_, index) => index + 1).map((pageNumber) => (
                            <button
                                key={pageNumber}
                                className={`btn ${pageNumber === Number(meta.page || page) ? 'btn-primary' : 'btn-secondary'}`}
                                onClick={() => setPage(pageNumber)}
                                style={{ minWidth: 40 }}
                            >
                                {pageNumber}
                            </button>
                        ))}
                    </div>
                )}
            </div>

            <TaskEditModal
                task={editingTask}
                isOpen={isEditModalOpen}
                onClose={() => {
                    setIsEditModalOpen(false)
                    setEditingTask(null)
                }}
                onSave={handleUpdateTask}
                projects={projects}
            />

            <Modal
                isOpen={isCreateModalOpen}
                onClose={() => setIsCreateModalOpen(false)}
                title="Nova tarefa"
                footer={(
                    <>
                        <Button variant="secondary" onClick={() => setIsCreateModalOpen(false)}>
                            Cancelar
                        </Button>
                        <Button
                            variant="primary"
                            onClick={handleCreateTask}
                            disabled={saving || !newTask.name.trim() || !newTask.project_id}
                        >
                            {saving ? 'Criando...' : 'Criar tarefa'}
                        </Button>
                    </>
                )}
            >
                <form onSubmit={handleCreateTask}>
                    <div style={{ marginBottom: 'var(--spacing-4)' }}>
                        <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                            Nome da tarefa *
                        </label>
                        <Input
                            value={newTask.name}
                            onChange={(event) => {
                                setNewTask((prev) => ({ ...prev, name: event.target.value }))
                                validation.clearFieldError('name')
                            }}
                            placeholder="Digite o nome da tarefa"
                        />
                        {validation.errors.name && (
                            <span style={{ color: 'var(--color-danger-500)', fontSize: '0.75rem' }}>
                                {validation.errors.name}
                            </span>
                        )}
                    </div>

                    <div style={{ marginBottom: 'var(--spacing-4)' }}>
                        <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                            Descrição
                        </label>
                        <textarea
                            value={newTask.description}
                            onChange={(event) => {
                                setNewTask((prev) => ({ ...prev, description: event.target.value }))
                                validation.clearFieldError('description')
                            }}
                            rows={3}
                            style={{
                                width: '100%',
                                padding: 'var(--spacing-2) var(--spacing-3)',
                                border: '1px solid var(--color-gray-300)',
                                borderRadius: 'var(--radius-md)',
                                fontFamily: 'inherit',
                            }}
                        />
                        {validation.errors.description && (
                            <span style={{ color: 'var(--color-danger-500)', fontSize: '0.75rem' }}>
                                {validation.errors.description}
                            </span>
                        )}
                    </div>

                    <div style={{ marginBottom: 'var(--spacing-4)' }}>
                        <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                            Projeto *
                        </label>
                        <select
                            value={newTask.project_id}
                            onChange={(event) => {
                                setNewTask((prev) => ({ ...prev, project_id: event.target.value }))
                                validation.clearFieldError('project_id')
                            }}
                            style={{
                                width: '100%',
                                padding: 'var(--spacing-2) var(--spacing-3)',
                                border: '1px solid var(--color-gray-300)',
                                borderRadius: 'var(--radius-md)',
                                background: 'white',
                            }}
                        >
                            <option value="">Selecione um projeto</option>
                            {projects.map((project) => (
                                <option key={project.id} value={String(project.id)}>
                                    {project.name}
                                </option>
                            ))}
                        </select>
                        {validation.errors.project_id && (
                            <span style={{ color: 'var(--color-danger-500)', fontSize: '0.75rem' }}>
                                {validation.errors.project_id}
                            </span>
                        )}
                    </div>

                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 'var(--spacing-4)' }}>
                        <div>
                            <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                                Prioridade
                            </label>
                            <select
                                value={newTask.priority}
                                onChange={(event) => setNewTask((prev) => ({ ...prev, priority: event.target.value }))}
                                style={{
                                    width: '100%',
                                    padding: 'var(--spacing-2) var(--spacing-3)',
                                    border: '1px solid var(--color-gray-300)',
                                    borderRadius: 'var(--radius-md)',
                                    background: 'white',
                                }}
                            >
                                <option value="0">Baixa</option>
                                <option value="1">Normal</option>
                                <option value="2">Alta</option>
                                <option value="3">Urgente</option>
                            </select>
                        </div>
                        <div>
                            <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                                Prazo
                            </label>
                            <Input
                                type="date"
                                value={newTask.end_date}
                                onChange={(event) => {
                                    setNewTask((prev) => ({ ...prev, end_date: event.target.value }))
                                    validation.clearFieldError('end_date')
                                }}
                            />
                            {validation.errors.end_date && (
                                <span style={{ color: 'var(--color-danger-500)', fontSize: '0.75rem' }}>
                                    {validation.errors.end_date}
                                </span>
                            )}
                        </div>
                    </div>
                </form>
            </Modal>
        </>
    )
}
