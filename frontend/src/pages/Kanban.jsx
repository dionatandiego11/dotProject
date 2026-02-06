/**
 * Kanban Page
 *
 * Kanban baseado em board por projeto.
 */

import { useState, useEffect, useMemo, useRef } from 'react'
import { useSearchParams } from 'react-router-dom'
import Loading from '../components/Loading'
import { getProjects, getTask, updateTask, deleteTask, createTask, getUsuarios, getUsuario, getCurrentUser, getVinculos, getUnidade, getArvoreUnidades, getKanbanBoards, getKanbanBoard, createKanbanBoard, moveKanbanTask } from '../services/api'
import { KanbanColumn } from '../components/kanban'
import '../components/kanban/KanbanBoard.css'
import Modal from '../components/ui/Modal'
import Button from '../components/ui/Button'
import Input from '../components/ui/Input'
import { useToast } from '../contexts/ToastContext'
import { useValidation } from '../hooks/useValidation'

function Kanban() {
    const toast = useToast()
    const validation = useValidation()
    const [searchParams, setSearchParams] = useSearchParams()

    const [projects, setProjects] = useState([])
    const [selectedProjectId, setSelectedProjectId] = useState(searchParams.get('project') || '')
    const [board, setBoard] = useState(null)
    const [columns, setColumns] = useState([])
    const [users, setUsers] = useState([])
    const [currentUser, setCurrentUser] = useState(null)
    const [unitUsers, setUnitUsers] = useState([])
    const [unitUserIds, setUnitUserIds] = useState(new Set())
    const [loading, setLoading] = useState(true)
    const [error, setError] = useState(null)
    const vinculosCache = useRef(new Map())

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
            setBoard(null)
            setColumns([])
            setLoading(false)
            return
        }
        loadBoard(selectedProjectId)
    }, [selectedProjectId, projects])

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

    async function loadBoard(projectId) {
        try {
            setLoading(true)
            setError(null)

            const boardsResult = await getKanbanBoards(projectId)
            if (boardsResult?.success === false) {
                throw new Error(boardsResult?.message || 'Falha ao carregar boards')
            }
            const boards = boardsResult?.data?.boards || boardsResult?.boards || []
            const projectBoard = boards.find((b) => String(b?.project_id) === String(projectId))

            let resolvedBoardId = projectBoard?.id || null
            if (!resolvedBoardId) {
                const project = projects.find((p) => String(p.id) === String(projectId))
                const createResult = await createKanbanBoard({
                    name: project?.name ? `Kanban - ${project.name}` : 'Kanban',
                    project_id: parseInt(projectId, 10),
                })
                if (!createResult?.success) {
                    throw new Error(createResult?.message || 'Falha ao criar board')
                }
                resolvedBoardId = createResult?.data?.id || createResult?.data?.board_id || null
            }

            if (!resolvedBoardId) {
                setBoard(null)
                setColumns([])
                return null
            }

            const boardResult = await getKanbanBoard(resolvedBoardId)
            if (!boardResult?.success) {
                throw new Error(boardResult?.message || 'Falha ao carregar board')
            }

            const boardData = boardResult?.data?.board || boardResult?.data || null
            const columnsData = boardResult?.data?.columns || boardData?.columns || []
            const normalizedColumns = (columnsData || [])
                .map((col) => ({
                    ...col,
                    tasks: [...(col.tasks || [])].sort((a, b) => (a.order || 0) - (b.order || 0)),
                }))
                .sort((a, b) => (a.order || 0) - (b.order || 0))

            setBoard(boardData)
            setColumns(normalizedColumns)
            return { board: boardData, columns: normalizedColumns }
        } catch (err) {
            const message = err instanceof Error && err.message
                ? err.message
                : 'Erro de conexao'
            setError(message)
            setBoard(null)
            setColumns([])
            return null
        } finally {
            setLoading(false)
        }
    }

    async function loadUnitUsers(unitId) {
        try {
            const ids = new Set()
            const responsavelIds = new Set()
            let treeLoaded = false

            // Busca arvore da unidade para incluir subunidades
            let unitIds = [unitId]
            try {
                const tree = await getArvoreUnidades(unitId)
                const nodes = Array.isArray(tree?.data) ? tree.data : []
                const collect = (list) => {
                    list.forEach((node) => {
                        if (node?.id) unitIds.push(node.id)
                        const respId = node?.responsavel_id ?? node?.responsavel?.id
                        if (respId) responsavelIds.add(Number(respId))
                        if (node?.filhas?.length) collect(node.filhas)
                    })
                }
                if (nodes.length > 0) {
                    treeLoaded = true
                    collect(nodes)
                }
            } catch (err) {
                // fallback: somente a unidade base
            }

            const uniqueIds = Array.from(new Set(unitIds))
            const vinculosList = await Promise.all(uniqueIds.map(async (id) => {
                if (vinculosCache.current.has(id)) {
                    return vinculosCache.current.get(id)
                }
                try {
                    const data = await getVinculos({ unidade_id: id })
                    const list = data.data || []
                    vinculosCache.current.set(id, list)
                    return list
                } catch {
                    return []
                }
            }))
            vinculosList.flat().forEach((v) => {
                if (v?.vinculo_user_id) ids.add(Number(v.vinculo_user_id))
            })

            if (!treeLoaded) {
                try {
                    const unidade = await getUnidade(unitId)
                    const fallbackResp = (
                        unidade?.data?.responsavel_id ||
                        unidade?.data?.responsavel?.id ||
                        unidade?.responsavel_id ||
                        unidade?.responsavel?.id ||
                        null
                    )
                    if (fallbackResp) responsavelIds.add(Number(fallbackResp))
                } catch {
                    // ignore
                }
            }

            responsavelIds.forEach((rid) => ids.add(rid))

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

    const statusByColumnId = useMemo(() => buildStatusMaps(columns).statusByColumn, [columns])

    const filteredColumns = useMemo(() => {
        const text = filterText.trim().toLowerCase()
        const ownerId = filterOwner !== 'all' ? parseInt(filterOwner, 10) : null
        const priority = filterPriority !== 'all' ? parseInt(filterPriority, 10) : null
        const onlyMineId = onlyMine && currentUser?.id ? Number(currentUser.id) : null

        return (columns || []).map((column) => {
            const tasks = (column.tasks || []).filter((item) => {
                const taskData = item?.task || {}
                if (text) {
                    const values = [
                        taskData.name,
                        taskData.description,
                        taskData.assigned_to_name,
                    ].filter(Boolean).join(' ').toLowerCase()
                    if (!values.includes(text)) return false
                }

                if (ownerId !== null && Number(taskData.assigned_to) !== ownerId) return false
                if (priority !== null && Number(taskData.priority) !== priority + 1) return false
                if (onlyMineId !== null && Number(taskData.assigned_to) !== onlyMineId) return false
                if (overdueOnly && !taskData.is_overdue) return false
                if (hideCompleted && (column.is_done || Number(taskData.percent_complete || 0) >= 100)) return false

                return true
            })

            const taskCount = tasks.length
            const avgProgress = taskCount > 0
                ? Math.round((tasks.reduce((acc, t) => acc + (t.task?.percent_complete || 0), 0) / taskCount) * 10) / 10
                : 0

            return {
                ...column,
                tasks,
                task_count: taskCount,
                average_progress: avgProgress,
            }
        })
    }, [columns, filterText, filterOwner, filterPriority, onlyMine, overdueOnly, hideCompleted, currentUser])

    const filteredStats = useMemo(() => {
        const totals = filteredColumns.reduce((acc, column) => {
            acc.total += column.task_count || 0
            if (column.tasks?.length) {
                acc.overdue += column.tasks.filter((t) => t?.task?.is_overdue).length
            }
            return acc
        }, { total: 0, overdue: 0 })

        return totals
    }, [filteredColumns])

    const resolveTargetOrder = (targetColumnId, targetOrder) => {
        const fullColumn = columns.find((col) => col.id === targetColumnId)
        if (!fullColumn) return targetOrder

        const filteredColumn = filteredColumns.find((col) => col.id === targetColumnId)
        if (!filteredColumn) {
            return Math.max(0, Math.min(targetOrder, fullColumn.tasks?.length || 0))
        }

        if (targetOrder >= (filteredColumn.tasks?.length || 0)) {
            return fullColumn.tasks?.length || 0
        }

        const nextVisible = filteredColumn.tasks?.[targetOrder]
        if (!nextVisible) return fullColumn.tasks?.length || 0

        const fullIndex = (fullColumn.tasks || []).findIndex((t) => t.id === nextVisible.id)
        return fullIndex === -1 ? (fullColumn.tasks?.length || 0) : fullIndex
    }

    const moveTaskLocally = (list, task, sourceColumnId, targetColumnId, targetOrder) => {
        const next = list.map((col) => ({
            ...col,
            tasks: [...(col.tasks || [])],
        }))

        let movedTask = null
        const sourceColumn = next.find((col) => col.id === sourceColumnId)
        if (sourceColumn) {
            const sourceIndex = sourceColumn.tasks.findIndex((t) => t.id === task.id)
            if (sourceIndex >= 0) {
                movedTask = sourceColumn.tasks.splice(sourceIndex, 1)[0]
            }
        }

        if (!movedTask) return list

        const targetColumn = next.find((col) => col.id === targetColumnId)
        if (!targetColumn) return list

        const insertAt = Math.max(0, Math.min(targetOrder, targetColumn.tasks.length))
        targetColumn.tasks.splice(insertAt, 0, { ...movedTask, column_id: targetColumnId })

        ;[sourceColumn, targetColumn].forEach((col) => {
            if (!col) return
            col.tasks = col.tasks.map((t, idx) => ({ ...t, order: idx }))
            col.task_count = col.tasks.length
        })

        return next
    }

    const handleDragStart = (task, columnId) => {
        setDraggingTask({ task, sourceColumnId: Number(columnId) })
    }

    const handleDragOver = (columnId) => {
        setDragOverColumn(Number(columnId))
    }

    const handleDragEnd = () => {
        setDraggingTask(null)
        setDragOverColumn(null)
    }

    const handleDrop = async (targetColumnId, targetOrder) => {
        if (!draggingTask) return

        const normalizedTargetColumnId = Number(targetColumnId)
        const { task, sourceColumnId } = draggingTask
        const resolvedOrder = resolveTargetOrder(normalizedTargetColumnId, targetOrder)
        const currentIndex = (columns.find((col) => col.id === sourceColumnId)?.tasks || [])
            .findIndex((t) => t.id === task.id)
        let adjustedOrder = resolvedOrder

        if (sourceColumnId === normalizedTargetColumnId && currentIndex !== -1 && resolvedOrder > currentIndex) {
            adjustedOrder = Math.max(0, resolvedOrder - 1)
        }

        if (sourceColumnId === normalizedTargetColumnId && currentIndex === adjustedOrder) {
            handleDragEnd()
            return
        }

        setColumns(moveTaskLocally(columns, task, sourceColumnId, normalizedTargetColumnId, adjustedOrder))
        handleDragEnd()

        try {
            const result = await moveKanbanTask(task.id, normalizedTargetColumnId, adjustedOrder)
            if (!result?.success) {
                throw new Error(result?.message || 'Falha ao mover tarefa')
            }

            const mappedStatus = statusByColumnId.get(normalizedTargetColumnId)
            if (mappedStatus !== undefined && task?.task_id) {
                updateTask(task.task_id, { status: mappedStatus }).catch(() => {})
            }

            toast.success('Tarefa movida!')
        } catch (err) {
            toast.error('Erro ao mover tarefa: ' + err.message)
            await loadBoard(selectedProjectId)
        }
    }

    const moveKanbanTaskToStatus = async (taskId, desiredStatus, columnsData) => {
        const normalizedStatus = Number.isNaN(desiredStatus) ? null : desiredStatus
        if (!taskId || normalizedStatus === null || normalizedStatus === undefined) return
        const { columnByStatus } = buildStatusMaps(columnsData || [])
        const targetColumnId = columnByStatus.get(normalizedStatus)
        if (!targetColumnId) return

        const targetColumn = (columnsData || []).find((col) => col.id === targetColumnId)
        const allTasks = (columnsData || []).flatMap((col) => col.tasks || [])
        const kanbanTask = allTasks.find((t) => t.task_id === taskId)

        if (!kanbanTask || kanbanTask.column_id === targetColumnId) return

        const currentMaxOrder = (targetColumn?.tasks || []).reduce((max, t) => {
            const order = Number(t?.order ?? 0)
            return order > max ? order : max
        }, -1)
        const appendOrder = currentMaxOrder + 1

        await moveKanbanTask(kanbanTask.id, targetColumnId, appendOrder)
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
            const createResult = await createTask({
                name: newTask.name,
                description: newTask.description || null,
                project_id: selectedProjectId ? parseInt(selectedProjectId, 10) : null,
                priority: parseInt(newTask.priority, 10),
                end_date: newTask.end_date || null,
                owner_id: newTask.owner_id ? parseInt(newTask.owner_id, 10) : null,
                status: parseInt(newTask.status, 10)
            })
            const createdId = createResult?.id || createResult?.data?.id || null
            toast.success('Tarefa criada com sucesso!')
            setShowTaskModal(false)
            setNewTask({ name: '', description: '', priority: '1', end_date: '', owner_id: '', status: '0' })
            validation.clearErrors()

            const boardResult = await loadBoard(selectedProjectId)
            const desiredStatus = parseInt(newTask.status, 10)
            if (createdId && boardResult?.columns?.length && desiredStatus !== 0) {
                try {
                    await moveKanbanTaskToStatus(createdId, desiredStatus, boardResult.columns)
                    await loadBoard(selectedProjectId)
                } catch (err) {
                    toast.error('Tarefa criada, mas nao foi possivel mover no Kanban.')
                }
            }
        } catch (err) {
            toast.error('Erro ao criar tarefa: ' + err.message)
        } finally {
            setCreatingTask(false)
        }
    }

    const openEditTask = async (taskWrapper) => {
        if (!taskWrapper?.task_id) return
        try {
            const data = await getTask(taskWrapper.task_id)
            const task = data?.data || data
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
        } catch (err) {
            toast.error('Erro ao carregar tarefa: ' + err.message)
        }
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
            const previousStatus = typeof editingTask.status === 'number'
                ? editingTask.status
                : parseInt(editingTask.status || '0', 10)
            const nextStatus = parseInt(editTask.status, 10)
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
            const boardResult = await loadBoard(selectedProjectId)
            if (boardResult?.columns?.length && nextStatus !== previousStatus) {
                try {
                    await moveKanbanTaskToStatus(editingTask.id, nextStatus, boardResult.columns)
                    await loadBoard(selectedProjectId)
                } catch (err) {
                    toast.error('Tarefa atualizada, mas nao foi possivel mover no Kanban.')
                }
            }
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
            await loadBoard(selectedProjectId)
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
                    onClick={() => loadBoard(selectedProjectId)}
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
                                ? `${projects.find(p => String(p.id) === String(selectedProjectId))?.name || 'Projeto selecionado'}${board?.name ? ` | ${board.name}` : ''}`
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
                    <span className="kanban-stat">Total: {filteredStats.total}</span>
                    <span className="kanban-stat">Atrasadas: {filteredStats.overdue}</span>
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
                        {filteredColumns.map((column, index) => (
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

function buildStatusMaps(columns) {
    const statusByColumn = new Map()
    const columnByStatus = new Map()
    const sorted = [...(columns || [])].sort((a, b) => (a.order || 0) - (b.order || 0))
    const firstActive = sorted.find((col) => !col.is_backlog && !col.is_done)

    sorted.forEach((col) => {
        const columnId = Number(col.id)
        let status = 2
        if (col.is_backlog) status = 0
        else if (col.is_done) status = 3
        else if (firstActive && col.id === firstActive.id) status = 1

        statusByColumn.set(columnId, status)
        if (!columnByStatus.has(status)) {
            columnByStatus.set(status, columnId)
        }
    })

    return { statusByColumn, columnByStatus }
}

export default Kanban
