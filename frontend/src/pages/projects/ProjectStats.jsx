import { useMemo } from 'react'
import { getNumericProjectStatus } from './projectStatusUtils'

export default function ProjectStats({ projects }) {
    const stats = useMemo(() => {
        return projects.reduce((acc, project) => {
            const status = getNumericProjectStatus(project)
            acc.total += 1
            if (status === 5) acc.completed += 1
            else if (status === 6) acc.archived += 1
            else acc.active += 1
            return acc
        }, { total: 0, active: 0, completed: 0, archived: 0 })
    }, [projects])

    const itemStyle = {
        padding: 'var(--spacing-2) var(--spacing-3)',
        border: '1px solid var(--color-gray-200)',
        borderRadius: 'var(--radius-md)',
        background: 'var(--color-gray-50)',
        fontSize: '0.875rem',
    }

    return (
        <div className="card" style={{ marginBottom: 'var(--spacing-4)' }}>
            <div className="card-body" style={{ display: 'flex', gap: 'var(--spacing-3)', flexWrap: 'wrap' }}>
                <span style={itemStyle}>Total: <strong>{stats.total}</strong></span>
                <span style={itemStyle}>Ativos: <strong>{stats.active}</strong></span>
                <span style={itemStyle}>Concluidos: <strong>{stats.completed}</strong></span>
                <span style={itemStyle}>Arquivados: <strong>{stats.archived}</strong></span>
            </div>
        </div>
    )
}
