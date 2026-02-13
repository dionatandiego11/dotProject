/**
 * ProjectTable — renders the projects list table with quick status actions.
 */

import { useNavigate } from 'react-router-dom'
import {
    getStatusLabelById,
    getNumericProjectStatus,
    getAllowedProjectStatuses,
} from './projectStatusUtils'
import ProjectStatusBadge from './ProjectStatusBadge'

export default function ProjectTable({
    projects,
    onEdit,
    onDelete,
    quickStatusSelectionByProjectId,
    quickStatusLoadingByProjectId,
    onQuickStatusSelect,
    onApplyQuickStatus
}) {
    const navigate = useNavigate()

    function getProjectUnitName(project) {
        return project?.unidade?.nome || project?.company?.name || '-'
    }

    function getProjectActions(project) {
        if (Array.isArray(project?.acoes) && project.acoes.length > 0) {
            return project.acoes.map((acao) => ({
                id: acao?.id,
                nome: acao?.nome || null,
            }))
        }

        if (project?.acao_id || project?.acao?.id) {
            return [{
                id: project.acao_id ?? project.acao?.id,
                nome: project.acao?.nome || null,
            }]
        }

        return []
    }

    return (
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
                    const quickStatusValue = quickStatusSelectionByProjectId[String(project.id)] ?? ''
                    const quickStatusLoading = Boolean(quickStatusLoadingByProjectId[String(project.id)])
                    const projectActions = getProjectActions(project)
                    const projectActionPreview = projectActions.slice(0, 2)
                    const projectActionOverflow = projectActions.length - projectActionPreview.length

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
                                        {(project.programa?.nome || project.programa_id) && (
                                            <div style={{ fontSize: '0.75rem', color: 'var(--color-primary-600)' }}>
                                                Programa: {project.programa?.nome || `#${project.programa_id}`}
                                            </div>
                                        )}
                                        {projectActions.length > 0 && (
                                            <div style={{ fontSize: '0.75rem', color: 'var(--color-gray-600)' }}>
                                                Ações: {projectActionPreview.map((acao) => acao.nome || `#${acao.id}`).join(', ')}
                                                {projectActionOverflow > 0 ? ` (+${projectActionOverflow})` : ''}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </td>
                            <td>{getProjectUnitName(project)}</td>
                            <td>
                                <ProjectStatusBadge status={project.status} />
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
                                    onClick={() => navigate(`/etapas?project=${project.id}`)}
                                >
                                    Etapas
                                </button>
                                <button
                                    type="button"
                                    className="btn btn-secondary"
                                    style={{ padding: 'var(--spacing-1) var(--spacing-2)', marginLeft: 'var(--spacing-2)' }}
                                    onClick={() => onEdit(project)}
                                >
                                    Editar
                                </button>
                                <button
                                    type="button"
                                    className="btn btn-secondary"
                                    style={{ padding: 'var(--spacing-1) var(--spacing-2)', marginLeft: 'var(--spacing-2)', color: 'var(--color-danger-600)' }}
                                    onClick={() => onDelete(project)}
                                >
                                    Apagar
                                </button>

                                {quickStatusOptions.length > 0 && (
                                    <div style={{ display: 'flex', gap: 'var(--spacing-2)', marginTop: 'var(--spacing-2)' }}>
                                        <select
                                            value={quickStatusValue}
                                            onChange={(e) => onQuickStatusSelect(project.id, e.target.value)}
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
                                            onClick={() => onApplyQuickStatus(project)}
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
    )
}
