import { useState, useEffect } from 'react'
import { getTasks, updateTask } from '../services/api'

function Tasks() {
    const [tasks, setTasks] = useState([])
    const [meta, setMeta] = useState({})
    const [loading, setLoading] = useState(true)
    const [error, setError] = useState(null)
    const [filter, setFilter] = useState('all') // all, overdue, completed

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
            // Reload tasks
            loadTasks()
        } catch (err) {
            console.error('Failed to update task:', err)
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
                    <button className="btn btn-primary">+ Nova Tarefa</button>
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
                                                    <button className="btn btn-secondary" style={{ padding: 'var(--spacing-1) var(--spacing-2)' }}>
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
        </>
    )
}

export default Tasks
