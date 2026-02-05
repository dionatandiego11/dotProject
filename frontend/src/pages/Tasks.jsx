import { useState, useEffect } from 'react'
import { getTasks, updateTask, createTask, getProjects } from '../services/api'
import Modal from '../components/ui/Modal'
import Button from '../components/ui/Button'
import Input from '../components/ui/Input'
import TaskEditModal from '../components/TaskEditModal'
import { useToast } from '../contexts/ToastContext'
import { useValidation } from '../hooks/useValidation'

function Tasks() {
    const toast = useToast()
    const validation = useValidation()
    const [tasks, setTasks] = useState([])
    const [meta, setMeta] = useState({})
    const [loading, setLoading] = useState(true)
    const [error, setError] = useState(null)
    const [filter, setFilter] = useState('all') // all, overdue, completed
    
    // Modal states
    const [isModalOpen, setIsModalOpen] = useState(false)
    const [isEditModalOpen, setIsEditModalOpen] = useState(false)
    const [editingTask, setEditingTask] = useState(null)
    const [projects, setProjects] = useState([])
    const [newTask, setNewTask] = useState({
        name: '',
        description: '',
        project_id: '',
        priority: '1',
        end_date: ''
    })
    const [creating, setCreating] = useState(false)

    useEffect(() => {
        loadTasks()
    }, [filter])

    async function loadTasks(page = 1) {
        try {
            setLoading(true)
            const params = { page }

            if (filter === 'overdue') {
                params.overdue = 'true'
            }

            const data = await getTasks(params)
            setTasks(data.data || [])
            setMeta(data.meta || {})
        } catch (err) {
            setError(err.message)
        } finally {
            setLoading(false)
        }
    }

    async function handleProgressChange(taskId, percent) {
        try {
            await updateTask(taskId, { percent_complete: percent })
            toast.success('Progresso atualizado!')
            // Reload tasks
            loadTasks()
        } catch (err) {
            toast.error('Erro ao atualizar progresso: ' + err.message)
        }
    }

    function getPriorityLabel(priority) {
        const labels = { 0: 'Baixa', 1: 'Normal', 2: 'Alta', 3: 'Urgente' }
        return labels[priority] || 'Normal'
    }

    function getPriorityColor(priority) {
        const colors = {
            0: 'var(--color-gray-400)',
            1: 'var(--color-primary-500)',
            2: 'var(--color-warning-500)',
            3: 'var(--color-danger-500)'
        }
        return colors[priority] || 'var(--color-gray-400)'
    }

    function isOverdue(endDate, percentComplete) {
        if (!endDate || percentComplete >= 100) return false
        return new Date(endDate) < new Date()
    }

    async function loadProjects() {
        try {
            const data = await getProjects({ per_page: 100 })
            setProjects(data.data || [])
        } catch (err) {
            console.error('Failed to load projects:', err)
        }
    }

    async function handleCreateTask(e) {
        e.preventDefault()
        validation.clearErrors()
        
        // Validações
        const isValid = validation.validateFields({
            name: () => validation.validateRequired(newTask.name, 'Nome da tarefa'),
            description: () => validation.validateMaxLength(newTask.description, 500, 'Descrição'),
            end_date: () => validation.validateFutureDate(newTask.end_date, 'Prazo'),
            project_id: () => validation.validateRequired(newTask.project_id, 'Projeto')
        })
        
        if (!isValid) return
        
        try {
            setCreating(true)
            await createTask({
                name: newTask.name,
                description: newTask.description,
                project_id: newTask.project_id ? parseInt(newTask.project_id, 10) : null,
                priority: parseInt(newTask.priority),
                end_date: newTask.end_date || null
            })
            toast.success('Tarefa criada com sucesso!')
            setIsModalOpen(false)
            setNewTask({ name: '', description: '', project_id: '', priority: '1', end_date: '' })
            validation.clearErrors()
            loadTasks()
        } catch (err) {
            toast.error('Erro ao criar tarefa: ' + err.message)
        } finally {
            setCreating(false)
        }
    }

    function openModal() {
        loadProjects()
        setIsModalOpen(true)
    }

    function openEditModal(task) {
        setEditingTask(task)
        setIsEditModalOpen(true)
    }

    async function handleUpdateTask(updatedData) {
        if (!editingTask) return
        await updateTask(editingTask.id, updatedData)
        setIsEditModalOpen(false)
        setEditingTask(null)
        loadTasks()
    }

    return (
        <>
            <div className="topbar">
                <h1 className="page-title">Tarefas</h1>
                <div style={{ display: 'flex', gap: 'var(--spacing-3)' }}>
                    <select
                        value={filter}
                        onChange={(e) => setFilter(e.target.value)}
                        style={{
                            padding: 'var(--spacing-2) var(--spacing-3)',
                            border: '1px solid var(--color-gray-300)',
                            borderRadius: 'var(--radius-md)',
                            fontSize: '0.875rem',
                            background: 'white'
                        }}
                    >
                        <option value="all">Todas</option>
                        <option value="overdue">Atrasadas</option>
                    </select>
                    <button className="btn btn-primary" onClick={openModal}>+ Nova Tarefa</button>
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
                        <h2 className="card-title">
                            {filter === 'overdue' ? 'Tarefas Atrasadas' : 'Todas as Tarefas'}
                        </h2>
                        <span style={{ fontSize: '0.875rem', color: 'var(--color-gray-500)' }}>
                            {meta.total || 0} tarefas
                        </span>
                    </div>
                    <div className="card-body" style={{ padding: 0 }}>
                        {loading ? (
                            <div style={{ padding: 'var(--spacing-6)', textAlign: 'center' }}>
                                Carregando...
                            </div>
                        ) : tasks.length === 0 ? (
                            <div style={{ padding: 'var(--spacing-6)', textAlign: 'center', color: 'var(--color-gray-500)' }}>
                                Nenhuma tarefa encontrada
                            </div>
                        ) : (
                            <table className="table">
                                <thead>
                                    <tr>
                                        <th>Tarefa</th>
                                        <th>Projeto</th>
                                        <th>Prioridade</th>
                                        <th>Prazo</th>
                                        <th>Progresso</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {tasks.map(task => {
                                        const overdue = isOverdue(task.end_date, task.percent_complete)

                                        return (
                                            <tr key={task.id} style={overdue ? { background: '#fef2f2' } : {}}>
                                                <td>
                                                    <div>
                                                        <strong>{task.name}</strong>
                                                        {task.milestone && (
                                                            <span className="badge badge-info" style={{ marginLeft: 'var(--spacing-2)' }}>
                                                                Marco
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td>
                                                    <span style={{ color: 'var(--color-gray-600)' }}>
                                                        {task.project?.name || '-'}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span style={{
                                                        color: getPriorityColor(task.priority),
                                                        fontWeight: 500
                                                    }}>
                                                        {getPriorityLabel(task.priority)}
                                                    </span>
                                                </td>
                                                <td>
                                                    {task.end_date ? (
                                                        <span style={overdue ? { color: 'var(--color-danger-500)', fontWeight: 500 } : {}}>
                                                            {new Date(task.end_date).toLocaleDateString('pt-BR')}
                                                            {overdue && ' (atrasada)'}
                                                        </span>
                                                    ) : (
                                                        <span style={{ color: 'var(--color-gray-400)' }}>-</span>
                                                    )}
                                                </td>
                                                <td>
                                                    <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--spacing-2)' }}>
                                                        <input
                                                            type="range"
                                                            min="0"
                                                            max="100"
                                                            step="10"
                                                            value={task.percent_complete}
                                                            onChange={(e) => handleProgressChange(task.id, parseInt(e.target.value))}
                                                            style={{ width: 80 }}
                                                        />
                                                        <span style={{
                                                            fontSize: '0.75rem',
                                                            minWidth: 35,
                                                            color: task.percent_complete === 100 ? 'var(--color-success-500)' : 'inherit'
                                                        }}>
                                                            {task.percent_complete}%
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

                {/* Pagination */}
                {meta.total_pages > 1 && (
                    <div style={{
                        display: 'flex',
                        justifyContent: 'center',
                        gap: 'var(--spacing-2)',
                        marginTop: 'var(--spacing-6)'
                    }}>
                        {Array.from({ length: Math.min(5, meta.total_pages) }, (_, i) => i + 1).map(page => (
                            <button
                                key={page}
                                className={`btn ${page === meta.page ? 'btn-primary' : 'btn-secondary'}`}
                                onClick={() => loadTasks(page)}
                                style={{ minWidth: 40 }}
                            >
                                {page}
                            </button>
                        ))}
                    </div>
                )}
            </div>

            {/* Edit Task Modal */}
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

            {/* Create Task Modal */}
            <Modal
                isOpen={isModalOpen}
                onClose={() => setIsModalOpen(false)}
                title="Nova Tarefa"
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setIsModalOpen(false)}>
                            Cancelar
                        </Button>
                        <Button 
                            variant="primary" 
                            onClick={handleCreateTask}
                            disabled={creating || !newTask.name.trim() || !newTask.project_id}
                        >
                            {creating ? 'Criando...' : 'Criar Tarefa'}
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
                            Descrição
                            {newTask.description && (
                                <span style={{ 
                                    fontSize: '0.75rem', 
                                    color: newTask.description.length > 450 ? 'var(--color-warning-500)' : 'var(--color-gray-400)',
                                    marginLeft: 'var(--spacing-2)',
                                    fontWeight: 'normal'
                                }}>
                                    ({newTask.description.length}/500)
                                </span>
                            )}
                        </label>
                        <textarea
                            value={newTask.description}
                            onChange={(e) => {
                                setNewTask({ ...newTask, description: e.target.value })
                                validation.clearFieldError('description')
                            }}
                            placeholder="Descrição da tarefa"
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
                    
                    <div style={{ marginBottom: 'var(--spacing-4)' }}>
                        <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                            Projeto *
                        </label>
                        <select
                            value={newTask.project_id}
                            onChange={(e) => {
                                setNewTask({ ...newTask, project_id: e.target.value })
                                validation.clearFieldError('project_id')
                            }}
                            style={{
                                width: '100%',
                                padding: 'var(--spacing-2) var(--spacing-3)',
                                border: validation.errors.project_id ? '1px solid var(--color-danger-500)' : '1px solid var(--color-gray-300)',
                                borderRadius: 'var(--radius-md)',
                                fontSize: '0.875rem',
                                background: 'white'
                            }}
                        >
                            <option value="">Selecione um projeto</option>
                            {projects.map(project => (
                                <option key={project.id} value={project.id}>
                                    {project.name}
                                </option>
                            ))}
                        </select>
                        {validation.errors.project_id && (
                            <span style={{ color: 'var(--color-danger-500)', fontSize: '0.75rem', marginTop: 'var(--spacing-1)', display: 'block' }}>
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
                </form>
            </Modal>
        </>
    )
}

export default Tasks

