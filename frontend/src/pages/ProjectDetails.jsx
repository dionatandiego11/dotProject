import { useState, useEffect } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { getProject, getProjectTasks } from '../services/api'
import Timeline from '../components/Timeline'
import Loading from '../components/Loading'
import Button from '../components/ui/Button'
import { useToast } from '../contexts/ToastContext'

function ProjectDetails() {
    const { id } = useParams()
    const navigate = useNavigate()
    const toast = useToast()

    const [project, setProject] = useState(null)
    const [loading, setLoading] = useState(true)
    const [error, setError] = useState(null)
    const [tasks, setTasks] = useState([])
    const [tasksLoading, setTasksLoading] = useState(false)
    const [tasksError, setTasksError] = useState(null)

    useEffect(() => {
        loadProjectDetails()
    }, [id])

    async function loadProjectDetails() {
        try {
            setLoading(true)
            const data = await getProject(id)

            // Tratamento caso a API retorne { data: ... }
            const projectData = data.data || data
            if (!projectData) {
                throw new Error('Projeto nao encontrado')
            }

            // Mock de etapas para a Timeline (enquanto backend não envia)
            // TODO: Integrar com endpoint real de etapas do fluxo
            if (!Array.isArray(projectData.etapas) || projectData.etapas.length === 0) {
                projectData.etapas = [
                    { id: 1, nome: 'Planejamento', status: 'concluido', data_fim: '2026-01-10' },
                    { id: 2, nome: 'Aprovação', status: 'concluido', data_fim: '2026-01-20' },
                    { id: 3, nome: 'Licitação', status: 'andamento', data_fim: null },
                    { id: 4, nome: 'Execução', status: 'futuro', data_fim: null },
                    { id: 5, nome: 'Entrega', status: 'futuro', data_fim: null },
                ]
            }

            setProject(projectData)
            await loadProjectTasks(projectData.id || id)
        } catch (err) {
            console.error(err)
            setError(err.message || 'Erro ao carregar projeto')
            toast.error('Não foi possível carregar os detalhes do projeto')
        } finally {
            setLoading(false)
        }
    }

    async function loadProjectTasks(projectId) {
        try {
            setTasksLoading(true)
            setTasksError(null)
            const data = await getProjectTasks(projectId, { per_page: 200 })
            setTasks(data.data || [])
        } catch (err) {
            console.error(err)
            setTasksError('Erro ao carregar tarefas do projeto')
        } finally {
            setTasksLoading(false)
        }
    }

    if (loading) return <Loading />

    if (error) {
        return (
            <div className="card">
                <div className="card-body" style={{ textAlign: 'center', padding: 40 }}>
                    <h2 style={{ color: 'var(--color-danger-500)' }}>Erro</h2>
                    <p>{error}</p>
                    <Button variant="secondary" onClick={() => navigate('/projects')}>
                        Voltar para Lista
                    </Button>
                </div>
            </div>
        )
    }

    if (!project) {
        return (
            <div className="card">
                <div className="card-body" style={{ textAlign: 'center', padding: 40 }}>
                    <p>Sem dados do projeto.</p>
                    <Button variant="secondary" onClick={() => navigate('/projects')}>
                        Voltar para Lista
                    </Button>
                </div>
            </div>
        )
    }

    return (
        <div style={{ maxWidth: 1200, margin: '0 auto' }}>
            {/* Header com Navegação */}
            <div style={{ marginBottom: 20 }}>
                <Button variant="ghost" onClick={() => navigate('/projects')} style={{ paddingLeft: 0 }}>
                    ← Voltar para Projetos
                </Button>
            </div>

            {/* Cabeçalho do Projeto */}
            <div className="card" style={{ marginBottom: 24 }}>
                <div className="card-body">
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                        <div>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 8 }}>
                                <h1 style={{ margin: 0, fontSize: '1.5rem' }}>{project.name}</h1>
                                {project.short_name && (
                                    <span className="badge badge-info">{project.short_name}</span>
                                )}
                            </div>
                            <p style={{ color: 'var(--color-gray-500)', margin: 0 }}>
                                Unidade Responsável: {project.company?.name || 'Não definida'}
                            </p>
                        </div>
                        <div style={{ textAlign: 'right' }}>
                            <div className={`badge badge-${getStatusBadge(project.status)}`} style={{ fontSize: '0.875rem' }}>
                                {getStatusLabel(project.status)}
                            </div>
                            <div style={{ marginTop: 8, fontSize: '0.875rem', color: 'var(--color-gray-500)' }}>
                                {project.percent_complete}% Concluído
                            </div>
                        </div>
                    </div>

                    {project.description && (
                        <div style={{ marginTop: 20, padding: 16, background: 'var(--color-gray-50)', borderRadius: 8 }}>
                            <h3 style={{ fontSize: '0.875rem', textTransform: 'uppercase', color: 'var(--color-gray-500)', marginTop: 0 }}>
                                Descrição
                            </h3>
                            <p style={{ margin: 0, lineHeight: 1.5 }}>{project.description}</p>
                        </div>
                    )}
                </div>
            </div>

            {/* TIMELINE VISUAL */}
            <div className="card" style={{ marginBottom: 24 }}>
                <div className="card-header">
                    <h2 className="card-title">Linha do Tempo de Execução</h2>
                </div>
                <div className="card-body">
                    <Timeline
                        etapas={project.etapas}
                        title="Etapas do Ciclo de Vida"
                    />
                </div>
            </div>

            {/* TAREFAS DO PROJETO */}
            <div className="card" style={{ marginBottom: 24 }}>
                <div className="card-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <h2 className="card-title">Tarefas do Projeto</h2>
                    <Button variant="ghost" onClick={() => navigate(`/kanban?project=${project.id}`)} style={{ padding: 0 }}>
                        Ver todas as tarefas
                    </Button>
                </div>
                <div className="card-body" style={{ padding: 0 }}>
                    {tasksLoading ? (
                        <div style={{ padding: 'var(--spacing-6)', textAlign: 'center' }}>
                            Carregando...
                        </div>
                    ) : tasksError ? (
                        <div style={{ padding: 'var(--spacing-6)', textAlign: 'center', color: 'var(--color-danger-500)' }}>
                            {tasksError}
                        </div>
                    ) : tasks.length === 0 ? (
                        <div style={{ padding: 'var(--spacing-6)', textAlign: 'center', color: 'var(--color-gray-500)' }}>
                            Nenhuma tarefa vinculada a este projeto.
                        </div>
                    ) : (
                        <table className="table">
                            <thead>
                                <tr>
                                    <th>Tarefa</th>
                                    <th>Status</th>
                                    <th>Prioridade</th>
                                    <th>Prazo</th>
                                    <th>Progresso</th>
                                </tr>
                            </thead>
                            <tbody>
                                {tasks.map(task => (
                                    <tr key={task.id}>
                                        <td>
                                            <strong>{task.name}</strong>
                                            {task.milestone && (
                                                <span className="badge badge-info" style={{ marginLeft: 'var(--spacing-2)' }}>
                                                    Marco
                                                </span>
                                            )}
                                        </td>
                                        <td>
                                            <span className={`badge badge-${getTaskStatusBadge(task.status)}`}>
                                                {getTaskStatusLabel(task.status)}
                                            </span>
                                        </td>
                                        <td>
                                            <span style={{
                                                color: getTaskPriorityColor(task.priority),
                                                fontWeight: 500
                                            }}>
                                                {getTaskPriorityLabel(task.priority)}
                                            </span>
                                        </td>
                                        <td>
                                            {task.end_date ? (
                                                <span>
                                                    {new Date(task.end_date).toLocaleDateString('pt-BR')}
                                                </span>
                                            ) : (
                                                <span style={{ color: 'var(--color-gray-400)' }}>-</span>
                                            )}
                                        </td>
                                        <td>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--spacing-2)' }}>
                                                <div style={{
                                                    height: 6,
                                                    width: 90,
                                                    background: 'var(--color-gray-200)',
                                                    borderRadius: 999,
                                                    overflow: 'hidden'
                                                }}>
                                                    <div style={{
                                                        height: '100%',
                                                        width: `${task.percent_complete || 0}%`,
                                                        background: (task.percent_complete || 0) === 100 ? 'var(--color-success-500)' : 'var(--color-primary-500)'
                                                    }} />
                                                </div>
                                                <span style={{ fontSize: '0.75rem', color: 'var(--color-gray-600)' }}>
                                                    {task.percent_complete || 0}%
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            </div>

            {/* Detalhes Adicionais (Grid) */}
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 24 }}>
                <div className="card">
                    <div className="card-header">
                        <h3 className="card-title">Informações Financeiras</h3>
                    </div>
                    <div className="card-body">
                        <InfoRow label="Orçamento Previsto" value={formatCurrency(project.budget || 0)} />
                        <InfoRow label="Valor Executado" value={formatCurrency(project.actual_budget || 0)} />
                        {/* Mock de saldo */}
                        <InfoRow label="Saldo" value={formatCurrency((project.budget || 0) - (project.actual_budget || 0))} isBold />
                    </div>
                </div>

                <div className="card">
                    <div className="card-header">
                        <h3 className="card-title">Prazos e Datas</h3>
                    </div>
                    <div className="card-body">
                        <InfoRow label="Data de Início" value={formatDate(project.start_date)} />
                        <InfoRow label="Data de Término" value={formatDate(project.end_date)} />
                        <InfoRow label="Data Real de Término" value={formatDate(project.actual_end_date) || '-'} />
                    </div>
                </div>
            </div>
        </div>
    )
}

// Componentes Auxiliares Locais
function InfoRow({ label, value, isBold = false }) {
    return (
        <div style={{
            display: 'flex',
            justifyContent: 'space-between',
            padding: '12px 0',
            borderBottom: '1px solid var(--color-gray-100)'
        }}>
            <span style={{ color: 'var(--color-gray-600)' }}>{label}</span>
            <span style={{ fontWeight: isBold ? 600 : 400, color: 'var(--color-gray-900)' }}>{value}</span>
        </div>
    )
}

// Helpers
function getStatusLabel(status) {
    const labels = {
        0: 'Não definido', 1: 'Proposto', 2: 'Em planejamento',
        3: 'Em progresso', 4: 'Em espera', 5: 'Completo', 6: 'Arquivado'
    }
    return labels[status] || 'Desconhecido'
}

function getStatusBadge(status) {
    const badges = {
        0: 'info', 1: 'info', 2: 'warning',
        3: 'success', 4: 'warning', 5: 'success', 6: 'info'
    }
    return badges[status] || 'info'
}

function getTaskStatusLabel(status) {
    const labels = {
        0: 'Backlog',
        1: 'A Fazer',
        2: 'Em andamento',
        3: 'Concluido',
        4: 'Em espera',
        5: 'Cancelado',
        6: 'Arquivado',
        7: 'Revisao'
    }
    return labels[status] || 'Indefinido'
}

function getTaskStatusBadge(status) {
    const badges = {
        0: 'info',
        1: 'warning',
        2: 'warning',
        3: 'success',
        4: 'warning',
        5: 'danger',
        6: 'info',
        7: 'info'
    }
    return badges[status] || 'info'
}

function getTaskPriorityLabel(priority) {
    const labels = { 0: 'Baixa', 1: 'Normal', 2: 'Alta', 3: 'Urgente' }
    return labels[priority] || 'Normal'
}

function getTaskPriorityColor(priority) {
    const colors = {
        0: 'var(--color-gray-400)',
        1: 'var(--color-primary-500)',
        2: 'var(--color-warning-500)',
        3: 'var(--color-danger-500)'
    }
    return colors[priority] || 'var(--color-gray-400)'
}

function formatCurrency(value) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value)
}

function formatDate(dateString) {
    if (!dateString) return null
    return new Date(dateString).toLocaleDateString('pt-BR')
}

export default ProjectDetails

