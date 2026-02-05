/**
 * Kanban Page
 *
 * Kanban baseado em tarefas por projeto.
 */

import { useState, useEffect, useMemo } from 'react'
import { useSearchParams } from 'react-router-dom'
import Loading from '../components/Loading'
import { getProjects, getTasks, updateTask, deleteTask, createTask, getUsuarios, getUsuario, getCurrentUser, getVinculos, getUnidade, getArvoreUnidades } from '../services/api'
import { KanbanColumn } from '../components/kanban'
import '../components/kanban/KanbanBoard.css'
import Modal from '../components/ui/Modal'
import Button from '../components/ui/Button'
import Input from '../components/ui/Input'
import { useToast } from '../contexts/ToastContext'
import { useValidation } from '../hooks/useValidation'

const COLUMNS = [
    { id: 0, name: 'Backlog' },
    { id: 1, name: 'A Fazer' },
    { id: 2, name: 'Em Andamento' },
    { id: 3, name: 'Concluido' },
]

function Kanban() {
    const toast = useToast()
    const validation = useValidation()
    const [searchParams, setSearchParams] = useSearchParams()

    const [projects, setProjects] = useState([])
    const [selectedProjectId, setSelectedProjectId] = useState(searchParams.get('project') || '')
    const [tasks, setTasks] = useState([])
    const [users, setUsers] = useState([])
    const [currentUser, setCurrentUser] = useState(null)
    const [unitUsers, setUnitUsers] = useState([])
    const [unitUserIds, setUnitUserIds] = useState(new Set())
    const [loading, setLoading] = useState(true)
    const [error, setError] = useState(null)

    const [draggingTask, setDraggingTask] = useState(null)
    const [dragOverColumn, setDragOverColumn] = useState(null)

    const [showTaskModal, setShowTaskModal] = useState(false)
    const [creatingTask, setCreatingTask] = useState(false)
    const [newTask, setNewTask] = useState({
        name: '',
        description: '',
        priority: '1',
        end_date: '',
        owner_id: '',
        status: '0'
    })
    const [showEditModal, setShowEditModal] = useState(false)
    const [savingTask, setSavingTask] = useState(false)
    const [editingTask, setEditingTask] = useState(null)
    const [editTask, setEditTask] = useState({
        id: null,
        name: '',
        description: '',
        priority: '1',
        status: '0',
        percent_complete: '0',
        owner_id: '',
        start_date: '',
        end_date: '',
        duration: ''
    })
    const [filterText, setFilterText] = useState('')
    const [filterOwner, setFilterOwner] = useState('all')
    const [filterPriority, setFilterPriority] = useState('all')
    const [onlyMine, setOnlyMine] = useState(false)
    const [overdueOnly, setOverdueOnly] = useState(false)
    const [hideCompleted, setHideCompleted] = useState(false)

    useEffect(() => {
        loadProjects()
        loadUsers()
        loadCurrentUser()
    }, [])

    useEffect(() => {
        const projectParam = searchParams.get('project')
        if (projectParam !== null && projectParam !== selectedProjectId) {
            setSelectedProjectId(projectParam)
        }
    }, [searchParams])

    useEffect(() => {
        if (!selectedProjectId) {
            setTasks([])
            setLoading(false)
            return
        }
        loadTasks(selectedProjectId)
    }, [selectedProjectId])

    useEffect(() => {
        if (!selectedProjectId) {
            setUnitUsers([])
            setUnitUserIds(new Set())
            return
        }
        const project = projects.find((p) => String(p.id) === String(selectedProjectId))
        const unitId = project?.company?.id || project?.company_id
        if (!unitId) {
            setUnitUsers([])
            setUnitUserIds(new Set())
            return
        }
        loadUnitUsers(unitId)
    }, [selectedProjectId, projects, users])

    useEffect(() => {
        if (filterOwner !== 'all' && !unitUserIds.has(parseInt(filterOwner, 10))) {
            setFilterOwner('all')
        }
    }, [unitUserIds, filterOwner])

    async function loadProjects() {
        try {
            const data = await getProjects({ per_page: 200 })
            setProjects(data.data || [])
        } catch (err) {
            console.error('Erro ao carregar projetos:', err)
        }
    }

    function normalizeUser(user) {
        if (!user) return null
        const id = user.id ?? user.user_id
        if (!id) return null
        const first = user.first_name ?? user.user_first_name ?? user.contact_first_name ?? ''
        const last = user.last_name ?? user.user_last_name ?? user.contact_last_name ?? ''
        const full = user.full_name ?? `${first} ${last}`.trim()
        return {
            ...user,
            id: Number(id),
            full_name: full || user.username || user.user_username || ''
        }
    }

    async function loadUsers() {
        try {
            const data = await getUsuarios()
            const list = (data.data || []).map(normalizeUser).filter(Boolean)
            setUsers(list)
        } catch (err) {
            console.error('Erro ao carregar usuarios:', err)
        }
    }

    async function loadCurrentUser() {
        try {
            const data = await getCurrentUser()
            setCurrentUser(data || null)
        } catch (err) {
            console.error('Erro ao carregar usuario atual:', err)
        }
    }

    async function loadTasks(projectId) {
        try {
            setLoading(true)
            setError(null)
            const data = await getTasks({ project_id: projectId, per_page: 200 })
            setTasks(data.data || [])
        } catch (err) {
            setError('Erro de conexao')
        } finally {
            setLoading(false)
        }
    }

    async function loadUnitUsers(unitId) {
        try {
            const ids = new Set()

            // Busca arvore da unidade para incluir subunidades
            let unitIds = [unitId]
            try {
                const tree = await getArvoreUnidades(unitId)
                const nodes = tree?.data || []
                const collect = (list) => {
                    list.forEach((node) => {
                        if (node?.id) unitIds.push(node.id)
                        if (node?.filhas?.length) collect(node.filhas)
                    })
                }
                collect(Array.isArray(nodes) ? nodes : [])
            } catch (err) {
                // fallback: somente a unidade base
            }

            const uniqueIds = Array.from(new Set(unitIds))
            const vinculosList = await Promise.all(uniqueIds.map(async (id) => {
                try {
                    const data = await getVinculos({ unidade_id: id })
                    return data.data || []
                } catch {
                    return []
                }
            }))
            vinculosList.flat().forEach((v) => {
                if (v?.vinculo_user_id) ids.add(Number(v.vinculo_user_id))
            })

            // Sempre inclui responsaveis das unidades
            const responsaveis = await Promise.all(uniqueIds.map(async (id) => {
                try {
                    const unidade = await getUnidade(id)
                    return (
                        unidade?.data?.responsavel_id ||
                        unidade?.data?.responsavel?.id ||
                        unidade?.responsavel_id ||
                        unidade?.responsavel?.id ||
                        null
                    )
                } catch {
                    return null
                }
            }))
            responsaveis.filter(Boolean).forEach((rid) => ids.add(Number(rid)))

            setUnitUserIds(ids)
            let filtered = users.filter((user) => ids.has(Number(user.id)))
            if (filtered.length < ids.size) {
                const missing = Array.from(ids).filter((id) => !filtered.some((u) => Number(u.id) === Number(id)))
                if (missing.length > 0) {
                    const fetched = await Promise.all(missing.map(async (id) => {
                        try {
                            const data = await getUsuario(id)
                            return normalizeUser(data?.data || null)
                        } catch {
                            return null
                        }
                    }))
                    filtered = [...filtered, ...fetched.filter(Boolean)]
                }
            }
            setUnitUsers(filtered)
        } catch (err) {
            console.error('Erro ao carregar usuarios vinculados:', err)
            setUnitUserIds(new Set())
            setUnitUsers([])
        }
    }

    function handleProjectChange(projectId) {
        setSelectedProjectId(projectId)
        const params = new URLSearchParams(searchParams)
        if (projectId) {
            params.set('project', projectId)
        } else {
            params.delete('project')
        }
        setSearchParams(params)
    }

    const usersById = useMemo(() => {
        const map = new Map()
        users.forEach((user) => {
            map.set(user.id, user)
        })
        return map
    }, [users])

    const filteredTasks = useMemo(() => {
        let items = [...tasks]
        const text = filterText.trim().toLowerCase()

        if (text) {
            items = items.filter((task) => {
                const values = [
                    task.name,
                    task.description,
                    task.project?.name,
                ].filter(Boolean).join(' ').toLowerCase()
                return values.includes(text)
            })
        }

        if (filterOwner !== 'all') {
            const ownerId = parseInt(filterOwner, 10)
            items = items.filter((task) => task.owner_id === ownerId)
        }

        if (filterPriority !== 'all') {
            const priority = parseInt(filterPriority, 10)
            items = items.filter((task) => task.priority === priority)
        }

        if (onlyMine && currentUser?.id) {
            items = items.filter((task) => task.owner_id === currentUser.id)
        }

        if (overdueOnly) {
            items = items.filter((task) => isOverdue(task.end_date, task.percent_complete || 0))
        }

        if (hideCompleted) {
            items = items.filter((task) => task.status < 3 && (task.percent_complete || 0) < 100)
        }

        return items
    }, [tasks, filterText, filterOwner, filterPriority, onlyMine, overdueOnly, hideCompleted, currentUser])

    const columns = useMemo(() => {
        const mapped = COLUMNS.map(col => ({
            ...col,
            tasks: [],
            task_count: 0,
            wip_limit: null,
            is_done: col.id === 3,
            is_backlog: col.id === 0,
            average_progress: 0
        }))

        filteredTasks.forEach(task => {
            const colId = mapStatusToColumn(task.status)
            const column = mapped.find(c => c.id === colId)
            if (!column) return
            const owner = task.owner_id ? usersById.get(task.owner_id) : null
            const ownerName = owner?.full_name || owner?.username || null

            const kanbanTask = {
                id: task.id,
                task_id: task.id,
                order: 0,
                task: {
                    name: task.name,
                    description: task.description || '',
                    priority: mapPriority(task.priority),
                    percent_complete: task.percent_complete || 0,
                    is_overdue: isOverdue(task.end_date, task.percent_complete || 0),
                    assigned_to: task.owner_id || null,
                    assigned_to_name: ownerName,
                    estimated_hours: task.duration || null,
                    comments_count: 0,
                    attachments_count: 0,
                    end_date: task.end_date || null
                }
            }

            column.tasks.push(kanbanTask)
            column.task_count = column.tasks.length
        })

        mapped.forEach(col => {
            if (col.tasks.length > 0) {
                const sum = col.tasks.reduce((acc, t) => acc + (t.task.percent_complete || 0), 0)
                col.average_progress = Math.round((sum / col.tasks.length) * 10) / 10
            }
        })

        return mapped
    }, [filteredTasks, usersById])

    const handleDragStart = (task, columnId) => {
        setDraggingTask({ task, sourceColumnId: columnId })
    }

    const handleDragOver = (columnId) => {
        setDragOverColumn(columnId)
    }

    const handleDragEnd = () => {
        setDraggingTask(null)
        setDragOverColumn(null)
    }

    const handleDrop = async (targetColumnId, targetOrder) => {
        if (!draggingTask) return

        const { task, sourceColumnId } = draggingTask
        if (sourceColumnId === targetColumnId) {
            handleDragEnd()
            return
        }

        const taskId = task.task_id
        const newStatus = mapColumnToStatus(targetColumnId)

        try {
            await updateTask(taskId, { status: newStatus })
            await loadTasks(selectedProjectId)
            toast.success('Tarefa movida!')
        } catch (err) {
            toast.error('Erro ao mover tarefa: ' + err.message)
        } finally {
            handleDragEnd()
        }
    }

    const handleCreateTask = async (e) => {
        e.preventDefault()
        validation.clearErrors()

        const isValid = validation.validateFields({
            name: () => validation.validateRequired(newTask.name, 'Nome da tarefa'),
            description: () => validation.validateMaxLength(newTask.description, 500, 'Descricao'),
            end_date: () => validation.validateFutureDate(newTask.end_date, 'Prazo')
        })

        if (!isValid) return

        try {
            setCreatingTask(true)
            await createTask({
                name: newTask.name,
                description: newTask.description || null,
                project_id: selectedProjectId ? parseInt(selectedProjectId, 10) : null,
                priority: parseInt(newTask.priority, 10),
                end_date: newTask.end_date || null,
                owner_id: newTask.owner_id ? parseInt(newTask.owner_id, 10) : null,
                status: parseInt(newTask.status, 10)
            })
            toast.success('Tarefa criada com sucesso!')
            setShowTaskModal(false)
            setNewTask({ name: '', description: '', priority: '1', end_date: '', owner_id: '', status: '0' })
            validation.clearErrors()
            await loadTasks(selectedProjectId)
        } catch (err) {
            toast.error('Erro ao criar tarefa: ' + err.message)
        } finally {
            setCreatingTask(false)
        }
    }

    const openEditTask = (taskWrapper) => {
        if (!taskWrapper?.task_id) return
        const task = tasks.find((t) => t.id === taskWrapper.task_id)
        if (!task) return
        setEditingTask(task)
        setEditTask({
            id: task.id,
            name: task.name || '',
            description: task.description || '',
            priority: String(task.priority ?? 0),
            status: String(task.status ?? 0),
            percent_complete: String(task.percent_complete ?? 0),
            owner_id: task.owner_id ? String(task.owner_id) : '',
            start_date: task.start_date || '',
            end_date: task.end_date || '',
            duration: task.duration != null ? String(task.duration) : ''
        })
        setShowEditModal(true)
    }

    const handleUpdateTask = async (e) => {
        e.preventDefault()
        if (!editingTask) return
        validation.clearErrors()

        const isValid = validation.validateFields({
            name: () => validation.validateRequired(editTask.name, 'Nome da tarefa'),
            end_date: () => validation.validateFutureDate(editTask.end_date, 'Prazo')
        })

        if (!isValid) return

        try {
            setSavingTask(true)
            await updateTask(editingTask.id, {
                name: editTask.name,
                description: editTask.description || null,
                priority: parseInt(editTask.priority, 10),
                status: parseInt(editTask.status, 10),
                percent_complete: parseInt(editTask.percent_complete || '0', 10),
                owner_id: editTask.owner_id ? parseInt(editTask.owner_id, 10) : null,
                start_date: editTask.start_date || null,
                end_date: editTask.end_date || null,
                duration: editTask.duration ? parseInt(editTask.duration, 10) : null
            })
            toast.success('Tarefa atualizada!')
            setShowEditModal(false)
            setEditingTask(null)
            await loadTasks(selectedProjectId)
        } catch (err) {
            toast.error('Erro ao atualizar tarefa: ' + err.message)
        } finally {
            setSavingTask(false)
        }
    }

    const handleDeleteTask = async () => {
        if (!editingTask) return
        const confirmDelete = window.confirm(`Deseja excluir a tarefa "${editingTask.name}"?`)
        if (!confirmDelete) return
        try {
            setSavingTask(true)
            await deleteTask(editingTask.id)
            toast.success('Tarefa excluida!')
            setShowEditModal(false)
            setEditingTask(null)
            await loadTasks(selectedProjectId)
        } catch (err) {
            toast.error('Erro ao excluir tarefa: ' + err.message)
        } finally {
            setSavingTask(false)
        }
    }

    if (loading) {
        return <Loading fullScreen />
    }

    if (error) {
        return (
            <div style={{ 
                display: 'flex', 
                flexDirection: 'column',
                alignItems: 'center', 
                justifyContent: 'center',
                height: '100%',
                gap: '1rem'
            }}>
                <div style={{ color: '#dc2626', fontSize: '1.125rem' }}>
                    {error}
                </div>
                <button 
                    onClick={() => loadTasks(selectedProjectId)}
                    style={{
                        padding: '0.5rem 1rem',
                        backgroundColor: '#3b82f6',
                        color: 'white',
                        border: 'none',
                        borderRadius: '0.375rem',
                        cursor: 'pointer'
                    }}
                >
                    Tentar novamente
                </button>
            </div>
        )
    }

    return (
        <div className="kanban-page">
            <div className="kanban-header">
                <div className="kanban-header-left">
                    <div className="kanban-title-wrap">
                        <h1 className="kanban-title">Tarefas</h1>
                        <span className="kanban-subtitle">
                            {selectedProjectId
                                ? `${projects.find(p => String(p.id) === String(selectedProjectId))?.name || 'Projeto selecionado'}`
                                : 'Selecione um projeto para ver o quadro'}
                        </span>
                    </div>

                    <select
                        className="kanban-project-select"
                        value={selectedProjectId}
                        onChange={(e) => handleProjectChange(e.target.value)}
                    >
                        <option value="">Selecione um projeto</option>
                        {projects.map(project => (
                            <option key={project.id} value={project.id}>
                                {project.name}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="kanban-header-actions">
                    <Button
                        variant="primary"
                        onClick={() => setShowTaskModal(true)}
                        disabled={!selectedProjectId}
                    >
                        + Nova Tarefa
                    </Button>
                </div>
            </div>

            <div className="kanban-toolbar">
                <div className="kanban-filters">
                    <input
                        className="kanban-filter"
                        type="text"
                        value={filterText}
                        onChange={(e) => setFilterText(e.target.value)}
                        placeholder="Buscar tarefa..."
                    />
                    <select
                        className="kanban-filter"
                        value={filterOwner}
                        onChange={(e) => setFilterOwner(e.target.value)}
                    >
                        <option value="all">Usuarios vinculados</option>
                        {unitUsers.length === 0 && (
                            <option value="" disabled>Nenhum usuario vinculado</option>
                        )}
                        {unitUsers.map((user) => (
                            <option key={user.id} value={user.id}>
                                {user.full_name || user.username}
                            </option>
                        ))}
                    </select>
                    <select
                        className="kanban-filter"
                        value={filterPriority}
                        onChange={(e) => setFilterPriority(e.target.value)}
                    >
                        <option value="all">Todas prioridades</option>
                        <option value="0">Baixa</option>
                        <option value="1">Normal</option>
                        <option value="2">Alta</option>
                        <option value="3">Urgente</option>
                    </select>
                    <label className="kanban-toggle">
                        <input
                            type="checkbox"
                            checked={onlyMine}
                            onChange={(e) => setOnlyMine(e.target.checked)}
                        />
                        Somente minhas
                    </label>
                    <label className="kanban-toggle">
                        <input
                            type="checkbox"
                            checked={overdueOnly}
                            onChange={(e) => setOverdueOnly(e.target.checked)}
                        />
                        Atrasadas
                    </label>
                    <label className="kanban-toggle">
                        <input
                            type="checkbox"
                            checked={hideCompleted}
                            onChange={(e) => setHideCompleted(e.target.checked)}
                        />
                        Ocultar concluidas
                    </label>
                </div>
                <div className="kanban-stats">
                    <span className="kanban-stat">Total: {filteredTasks.length}</span>
                    <span className="kanban-stat">Atrasadas: {filteredTasks.filter((t) => isOverdue(t.end_date, t.percent_complete || 0)).length}</span>
                </div>
            </div>

            {!selectedProjectId ? (
                <div className="kanban-empty-state-wrap">
                    <div className="kanban-empty-card">
                        <h3>Selecione um projeto</h3>
                        <p>Escolha um projeto para visualizar as tarefas no Kanban.</p>
                    </div>
                </div>
            ) : (
                <div className="kanban-board">
                    <div className="kanban-columns">
                        {columns.map((column, index) => (
                            <KanbanColumn
                                key={column.id}
                                column={column}
                                index={index}
                                isDragOver={dragOverColumn === column.id}
                                onDragStart={handleDragStart}
                                onDragOver={handleDragOver}
                                onDragEnd={handleDragEnd}
                                onDrop={handleDrop}
                                onTaskClick={openEditTask}
                            />
                        ))}
                    </div>
                </div>
            )}

            <Modal
                isOpen={showTaskModal}
                onClose={() => setShowTaskModal(false)}
                title="Nova Tarefa"
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setShowTaskModal(false)}>
                            Cancelar
                        </Button>
                        <Button
                            variant="primary"
                            onClick={handleCreateTask}
                            disabled={creatingTask || !newTask.name.trim()}
                        >
                            {creatingTask ? 'Criando...' : 'Criar Tarefa'}
                        </Button>
                    </>
                }
            >
                <form onSubmit={handleCreateTask}>
                    <div style={{ marginBottom: 'var(--spacing-4)' }}>
                        <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                            Nome da Tarefa *
                        </label>
                        <Input
                            value={newTask.name}
                            onChange={(e) => {
                                setNewTask({ ...newTask, name: e.target.value })
                                validation.clearFieldError('name')
                            }}
                            placeholder="Digite o nome da tarefa"
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
                            Descricao
                        </label>
                        <textarea
                            value={newTask.description}
                            onChange={(e) => {
                                setNewTask({ ...newTask, description: e.target.value })
                                validation.clearFieldError('description')
                            }}
                            placeholder="Descricao da tarefa"
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

                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 'var(--spacing-4)' }}>
                        <div>
                            <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                                Prioridade
                            </label>
                            <select
                                value={newTask.priority}
                                onChange={(e) => setNewTask({ ...newTask, priority: e.target.value })}
                                style={{
                                    width: '100%',
                                    padding: 'var(--spacing-2) var(--spacing-3)',
                                    border: '1px solid var(--color-gray-300)',
                                    borderRadius: 'var(--radius-md)',
                                    fontSize: '0.875rem',
                                    background: 'white'
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
                                Status inicial
                            </label>
                            <select
                                value={newTask.status}
                                onChange={(e) => setNewTask({ ...newTask, status: e.target.value })}
                                style={{
                                    width: '100%',
                                    padding: 'var(--spacing-2) var(--spacing-3)',
                                    border: '1px solid var(--color-gray-300)',
                                    borderRadius: 'var(--radius-md)',
                                    fontSize: '0.875rem',
                                    background: 'white'
                                }}
                            >
                                <option value="0">Backlog</option>
                                <option value="1">A Fazer</option>
                                <option value="2">Em Andamento</option>
                                <option value="3">Concluido</option>
                            </select>
                        </div>
                        <div>
                            <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                                Prazo
                            </label>
                            <Input
                                type="date"
                                value={newTask.end_date}
                                onChange={(e) => {
                                    setNewTask({ ...newTask, end_date: e.target.value })
                                    validation.clearFieldError('end_date')
                                }}
                                style={validation.errors.end_date ? { borderColor: 'var(--color-danger-500)' } : {}}
                            />
                            {validation.errors.end_date && (
                                <span style={{ color: 'var(--color-danger-500)', fontSize: '0.75rem', marginTop: 'var(--spacing-1)', display: 'block' }}>
                                    {validation.errors.end_date}
                                </span>
                            )}
                        </div>
                    </div>

                    <div style={{ marginTop: 'var(--spacing-4)' }}>
                        <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                            Responsavel
                        </label>
                        <select
                            value={newTask.owner_id}
                            onChange={(e) => setNewTask({ ...newTask, owner_id: e.target.value })}
                            style={{
                                width: '100%',
                                padding: 'var(--spacing-2) var(--spacing-3)',
                                border: '1px solid var(--color-gray-300)',
                                borderRadius: 'var(--radius-md)',
                                fontSize: '0.875rem',
                                background: 'white'
                            }}
                        >
                            <option value="">Sem responsavel</option>
                            {unitUsers.length === 0 && (
                            <option value="" disabled>Nenhum usuario vinculado</option>
                            )}
                            {unitUsers.map((user) => (
                                <option key={user.id} value={user.id}>
                                    {user.full_name || user.username}
                                </option>
                            ))}
                        </select>
                    </div>
                </form>
            </Modal>

            <Modal
                isOpen={showEditModal}
                onClose={() => setShowEditModal(false)}
                title="Editar Tarefa"
                footer={
                    <>
                        <Button variant="danger" onClick={handleDeleteTask} disabled={savingTask}>
                            Excluir
                        </Button>
                        <Button variant="secondary" onClick={() => setShowEditModal(false)}>
                            Cancelar
                        </Button>
                        <Button
                            variant="primary"
                            onClick={handleUpdateTask}
                            disabled={savingTask || !editTask.name.trim()}
                        >
                            {savingTask ? 'Salvando...' : 'Salvar'}
                        </Button>
                    </>
                }
            >
                <form onSubmit={handleUpdateTask}>
                    <div style={{ marginBottom: 'var(--spacing-4)' }}>
                        <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                            Nome da Tarefa *
                        </label>
                        <Input
                            value={editTask.name}
                            onChange={(e) => {
                                setEditTask({ ...editTask, name: e.target.value })
                                validation.clearFieldError('name')
                            }}
                            placeholder="Digite o nome da tarefa"
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
                            Descricao
                        </label>
                        <textarea
                            value={editTask.description}
                            onChange={(e) => setEditTask({ ...editTask, description: e.target.value })}
                            placeholder="Descricao da tarefa"
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

                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 'var(--spacing-4)' }}>
                        <div>
                            <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                                Status
                            </label>
                            <select
                                value={editTask.status}
                                onChange={(e) => setEditTask({ ...editTask, status: e.target.value })}
                                style={{
                                    width: '100%',
                                    padding: 'var(--spacing-2) var(--spacing-3)',
                                    border: '1px solid var(--color-gray-300)',
                                    borderRadius: 'var(--radius-md)',
                                    fontSize: '0.875rem',
                                    background: 'white'
                                }}
                            >
                                <option value="0">Backlog</option>
                                <option value="1">A Fazer</option>
                                <option value="2">Em Andamento</option>
                                <option value="3">Concluido</option>
                            </select>
                        </div>
                        <div>
                            <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                                Prioridade
                            </label>
                            <select
                                value={editTask.priority}
                                onChange={(e) => setEditTask({ ...editTask, priority: e.target.value })}
                                style={{
                                    width: '100%',
                                    padding: 'var(--spacing-2) var(--spacing-3)',
                                    border: '1px solid var(--color-gray-300)',
                                    borderRadius: 'var(--radius-md)',
                                    fontSize: '0.875rem',
                                    background: 'white'
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
                                Progresso (%)
                            </label>
                            <Input
                                type="number"
                                min="0"
                                max="100"
                                value={editTask.percent_complete}
                                onChange={(e) => setEditTask({ ...editTask, percent_complete: e.target.value })}
                            />
                        </div>
                    </div>

                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 'var(--spacing-4)', marginTop: 'var(--spacing-4)' }}>
                        <div>
                            <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                                Inicio
                            </label>
                            <Input
                                type="date"
                                value={editTask.start_date}
                                onChange={(e) => setEditTask({ ...editTask, start_date: e.target.value })}
                            />
                        </div>
                        <div>
                            <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                                Prazo
                            </label>
                            <Input
                                type="date"
                                value={editTask.end_date}
                                onChange={(e) => {
                                    setEditTask({ ...editTask, end_date: e.target.value })
                                    validation.clearFieldError('end_date')
                                }}
                                style={validation.errors.end_date ? { borderColor: 'var(--color-danger-500)' } : {}}
                            />
                            {validation.errors.end_date && (
                                <span style={{ color: 'var(--color-danger-500)', fontSize: '0.75rem', marginTop: 'var(--spacing-1)', display: 'block' }}>
                                    {validation.errors.end_date}
                                </span>
                            )}
                        </div>
                        <div>
                            <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                                Duracao (dias)
                            </label>
                            <Input
                                type="number"
                                min="0"
                                value={editTask.duration}
                                onChange={(e) => setEditTask({ ...editTask, duration: e.target.value })}
                            />
                        </div>
                    </div>

                    <div style={{ marginTop: 'var(--spacing-4)' }}>
                        <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                            Responsavel
                        </label>
                        <select
                            value={editTask.owner_id}
                            onChange={(e) => setEditTask({ ...editTask, owner_id: e.target.value })}
                            style={{
                                width: '100%',
                                padding: 'var(--spacing-2) var(--spacing-3)',
                                border: '1px solid var(--color-gray-300)',
                                borderRadius: 'var(--radius-md)',
                                fontSize: '0.875rem',
                                background: 'white'
                            }}
                        >
                            <option value="">Sem responsavel</option>
                            {unitUsers.length === 0 && (
                            <option value="" disabled>Nenhum usuario vinculado</option>
                            )}
                            {unitUsers.map((user) => (
                                <option key={user.id} value={user.id}>
                                    {user.full_name || user.username}
                                </option>
                            ))}
                        </select>
                    </div>
                </form>
            </Modal>
        </div>
    )
}

function mapPriority(priority) {
    const value = typeof priority === 'number' ? priority : parseInt(priority || '0', 10)
    return Math.max(1, Math.min(4, value + 1))
}

function mapStatusToColumn(status) {
    const value = typeof status === 'number' ? status : parseInt(status || '0', 10)
    if (value >= 3) return 3
    return Math.max(0, Math.min(3, value))
}

function mapColumnToStatus(columnId) {
    const value = typeof columnId === 'number' ? columnId : parseInt(columnId || '0', 10)
    return Math.max(0, Math.min(3, value))
}

function isOverdue(endDate, percentComplete) {
    if (!endDate || percentComplete >= 100) return false
    return new Date(endDate) < new Date()
}

export default Kanban
