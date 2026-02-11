import { useMemo, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import Loading from '../../components/Loading'
import { getTask, updateTask, deleteTask, createTask, moveKanbanTask } from '../../services/api'
import { KanbanColumn } from '../../components/kanban'
import '../../components/kanban/KanbanBoard.css'
import Modal from '../../components/ui/Modal'
import Button from '../../components/ui/Button'
import { useToast } from '../../contexts/ToastContext'
import { useValidation } from '../../hooks/useValidation'
import useKanbanBoard from './useKanbanBoard'
import useKanbanDragDrop from './useKanbanDragDrop'
import KanbanHeader from './KanbanHeader'
import KanbanTaskForm from './KanbanTaskForm'
import { buildStatusMaps } from './kanbanUtils'

const EMPTY_NEW_TASK = {
    name: '',
    description: '',
    priority: '1',
    end_date: '',
    owner_id: '',
    status: '0',
}

const EMPTY_EDIT_TASK = {
    id: null,
    name: '',
    description: '',
    priority: '1',
    status: '0',
    percent_complete: '0',
    owner_id: '',
    start_date: '',
    end_date: '',
    duration: '',
}

export default function KanbanPage() {
    const toast = useToast()
    const validation = useValidation()
    const [searchParams, setSearchParams] = useSearchParams()

    const kanban = useKanbanBoard({ searchParams, setSearchParams })
    const {
        projects,
        selectedProjectId,
        board,
        columns,
        setColumns,
        unitUsers,
        loading,
        error,
        loadBoard,
        handleProjectChange,
        filteredColumns,
        filteredStats,
        filterText,
        setFilterText,
        filterOwner,
        setFilterOwner,
        filterPriority,
        setFilterPriority,
        onlyMine,
        setOnlyMine,
        overdueOnly,
        setOverdueOnly,
        hideCompleted,
        setHideCompleted,
    } = kanban

    const [showTaskModal, setShowTaskModal] = useState(false)
    const [creatingTask, setCreatingTask] = useState(false)
    const [newTask, setNewTask] = useState({ ...EMPTY_NEW_TASK })

    const [showEditModal, setShowEditModal] = useState(false)
    const [savingTask, setSavingTask] = useState(false)
    const [editingTask, setEditingTask] = useState(null)
    const [editTask, setEditTask] = useState({ ...EMPTY_EDIT_TASK })

    const statusByColumnId = useMemo(() => buildStatusMaps(columns).statusByColumn, [columns])
    const selectedProjectName = useMemo(() => {
        if (!selectedProjectId) return ''
        return projects.find((project) => String(project.id) === String(selectedProjectId))?.name || ''
    }, [projects, selectedProjectId])

    const dragDrop = useKanbanDragDrop({
        columns,
        filteredColumns,
        setColumns,
        selectedProjectId,
        loadBoard,
        statusByColumnId,
        moveKanbanTaskApi: moveKanbanTask,
        updateTaskApi: updateTask,
        toast,
    })

    async function moveKanbanTaskToStatus(taskId, desiredStatus, columnsData) {
        const normalizedStatus = Number.isNaN(desiredStatus) ? null : desiredStatus
        if (!taskId || normalizedStatus === null || normalizedStatus === undefined) return

        const { columnByStatus } = buildStatusMaps(columnsData || [])
        const targetColumnId = columnByStatus.get(normalizedStatus)
        if (!targetColumnId) return

        const targetColumn = (columnsData || []).find((col) => col.id === targetColumnId)
        const allTasks = (columnsData || []).flatMap((col) => col.tasks || [])
        const kanbanTask = allTasks.find((task) => task.task_id === taskId)
        if (!kanbanTask || kanbanTask.column_id === targetColumnId) return

        const currentMaxOrder = (targetColumn?.tasks || []).reduce((max, task) => {
            const order = Number(task?.order ?? 0)
            return order > max ? order : max
        }, -1)
        const appendOrder = currentMaxOrder + 1

        await moveKanbanTask(kanbanTask.id, targetColumnId, appendOrder)
    }

    async function handleCreateTask(event) {
        event.preventDefault()
        validation.clearErrors()

        const isValid = validation.validateFields({
            name: () => validation.validateRequired(newTask.name, 'Nome da tarefa'),
            description: () => validation.validateMaxLength(newTask.description, 500, 'Descricao'),
            end_date: () => validation.validateFutureDate(newTask.end_date, 'Prazo'),
        })
        if (!isValid) return

        try {
            setCreatingTask(true)
            const desiredStatus = parseInt(newTask.status, 10)
            const createResult = await createTask({
                name: newTask.name,
                description: newTask.description || null,
                project_id: selectedProjectId ? parseInt(selectedProjectId, 10) : null,
                priority: parseInt(newTask.priority, 10),
                end_date: newTask.end_date || null,
                owner_id: newTask.owner_id ? parseInt(newTask.owner_id, 10) : null,
                status: desiredStatus,
            })
            const createdId = createResult?.id || createResult?.data?.id || null

            toast.success('Tarefa criada com sucesso!')
            setShowTaskModal(false)
            setNewTask({ ...EMPTY_NEW_TASK })
            validation.clearErrors()

            const boardResult = await loadBoard(selectedProjectId)
            if (createdId && boardResult?.columns?.length && desiredStatus !== 0) {
                try {
                    await moveKanbanTaskToStatus(createdId, desiredStatus, boardResult.columns)
                    await loadBoard(selectedProjectId)
                } catch (_) {
                    toast.error('Tarefa criada, mas nao foi possivel mover no Kanban.')
                }
            }
        } catch (err) {
            toast.error('Erro ao criar tarefa: ' + err.message)
        } finally {
            setCreatingTask(false)
        }
    }

    async function openEditTask(taskWrapper) {
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
                duration: task.duration != null ? String(task.duration) : '',
            })
            setShowEditModal(true)
        } catch (err) {
            toast.error('Erro ao carregar tarefa: ' + err.message)
        }
    }

    async function handleUpdateTask(event) {
        event.preventDefault()
        if (!editingTask) return
        validation.clearErrors()

        const isValid = validation.validateFields({
            name: () => validation.validateRequired(editTask.name, 'Nome da tarefa'),
            end_date: () => validation.validateFutureDate(editTask.end_date, 'Prazo'),
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
                status: nextStatus,
                percent_complete: parseInt(editTask.percent_complete || '0', 10),
                owner_id: editTask.owner_id ? parseInt(editTask.owner_id, 10) : null,
                start_date: editTask.start_date || null,
                end_date: editTask.end_date || null,
                duration: editTask.duration ? parseInt(editTask.duration, 10) : null,
            })

            toast.success('Tarefa atualizada!')
            setShowEditModal(false)
            setEditingTask(null)

            const boardResult = await loadBoard(selectedProjectId)
            if (boardResult?.columns?.length && nextStatus !== previousStatus) {
                try {
                    await moveKanbanTaskToStatus(editingTask.id, nextStatus, boardResult.columns)
                    await loadBoard(selectedProjectId)
                } catch (_) {
                    toast.error('Tarefa atualizada, mas nao foi possivel mover no Kanban.')
                }
            }
        } catch (err) {
            toast.error('Erro ao atualizar tarefa: ' + err.message)
        } finally {
            setSavingTask(false)
        }
    }

    async function handleDeleteTask() {
        if (!editingTask) return
        if (!window.confirm(`Deseja excluir a tarefa "${editingTask.name}"?`)) return

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
                gap: '1rem',
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
                        cursor: 'pointer',
                    }}
                >
                    Tentar novamente
                </button>
            </div>
        )
    }

    return (
        <div className="kanban-page">
            <KanbanHeader
                selectedProjectId={selectedProjectId}
                selectedProjectName={selectedProjectName}
                boardName={board?.name || ''}
                projects={projects}
                onProjectChange={handleProjectChange}
                onCreateTask={() => setShowTaskModal(true)}
                filterText={filterText}
                onFilterTextChange={setFilterText}
                filterOwner={filterOwner}
                onFilterOwnerChange={setFilterOwner}
                filterPriority={filterPriority}
                onFilterPriorityChange={setFilterPriority}
                onlyMine={onlyMine}
                onOnlyMineChange={setOnlyMine}
                overdueOnly={overdueOnly}
                onOverdueOnlyChange={setOverdueOnly}
                hideCompleted={hideCompleted}
                onHideCompletedChange={setHideCompleted}
                unitUsers={unitUsers}
                stats={filteredStats}
            />

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
                                isDragOver={dragDrop.dragOverColumn === column.id}
                                onDragStart={dragDrop.handleDragStart}
                                onDragOver={dragDrop.handleDragOver}
                                onDragEnd={dragDrop.handleDragEnd}
                                onDrop={dragDrop.handleDrop}
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
                footer={(
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
                )}
            >
                <KanbanTaskForm
                    mode="create"
                    task={newTask}
                    setTask={setNewTask}
                    unitUsers={unitUsers}
                    validation={validation}
                    onSubmit={handleCreateTask}
                />
            </Modal>

            <Modal
                isOpen={showEditModal}
                onClose={() => setShowEditModal(false)}
                title="Editar Tarefa"
                footer={(
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
                )}
            >
                <KanbanTaskForm
                    mode="edit"
                    task={editTask}
                    setTask={setEditTask}
                    unitUsers={unitUsers}
                    validation={validation}
                    onSubmit={handleUpdateTask}
                />
            </Modal>
        </div>
    )
}
