/**
 * Kanban Column Component
 * 
 * Componente de coluna do Kanban.
 */

import PropTypes from 'prop-types'
import KanbanTask from './KanbanTask'

function KanbanColumn({
  column,
  index,
  isDragOver,
  onDragStart,
  onDragOver,
  onDragEnd,
  onDrop,
  onTaskClick,
}) {
  const handleDragOver = (e) => {
    e.preventDefault()
    onDragOver(column.id)
  }

  const handleDrop = (e) => {
    e.preventDefault()
    // Calcula ordem baseada na posição do mouse
    const rect = e.currentTarget.getBoundingClientRect()
    const y = e.clientY - rect.top
    const taskHeight = 80 // Aproximado
    const order = Math.floor(y / taskHeight)
    
    onDrop(column.id, Math.max(0, Math.min(order, column.tasks.length)))
  }

  const getColumnStyle = () => ({
    borderTop: `4px solid ${column.color || '#e5e7eb'}`,
  })

  const getWipIndicator = () => {
    if (!column.wip_limit) return null
    
    const percentage = (column.task_count / column.wip_limit) * 100
    let className = 'kanban-wip-indicator'
    if (percentage >= 100) className += ' kanban-wip-exceeded'
    else if (percentage >= 80) className += ' kanban-wip-warning'

    return (
      <span className={className}>
        {column.task_count}/{column.wip_limit}
      </span>
    )
  }

  return (
    <div
      className={`kanban-column ${isDragOver ? 'kanban-column-dragover' : ''}`}
      style={getColumnStyle()}
      onDragOver={handleDragOver}
      onDrop={handleDrop}
      onDragLeave={onDragEnd}
    >
      <div className="kanban-column-header">
        <div className="kanban-column-title">
          <span>{column.name}</span>
          {column.is_done && <span className="kanban-badge-done">✓</span>}
        </div>
        <div className="kanban-column-meta">
          {getWipIndicator()}
          <span className="kanban-task-count">{column.task_count}</span>
        </div>
      </div>

      <div className="kanban-column-tasks">
        {column.tasks?.map((task, taskIndex) => (
          <KanbanTask
            key={task.id}
            task={task}
            columnId={column.id}
            index={taskIndex}
            onDragStart={onDragStart}
            onClick={onTaskClick}
          />
        ))}

        {/* Drop zone vazia */}
        {column.tasks?.length === 0 && (
          <div className="kanban-empty-state">
            Drop tasks here
          </div>
        )}
      </div>

      {column.average_progress > 0 && (
        <div className="kanban-column-footer">
          <div className="kanban-progress-bar">
            <div
              className="kanban-progress-fill"
              style={{ width: `${column.average_progress}%` }}
            />
          </div>
          <span className="kanban-progress-text">
            {column.average_progress}%
          </span>
        </div>
      )}
    </div>
  )
}

KanbanColumn.propTypes = {
  column: PropTypes.shape({
    id: PropTypes.number.isRequired,
    name: PropTypes.string.isRequired,
    color: PropTypes.string,
    wip_limit: PropTypes.number,
    task_count: PropTypes.number,
    is_done: PropTypes.bool,
    average_progress: PropTypes.number,
    tasks: PropTypes.array,
  }).isRequired,
  index: PropTypes.number.isRequired,
  isDragOver: PropTypes.bool,
  onDragStart: PropTypes.func.isRequired,
  onDragOver: PropTypes.func.isRequired,
  onDragEnd: PropTypes.func.isRequired,
  onDrop: PropTypes.func.isRequired,
  onTaskClick: PropTypes.func,
}

export default KanbanColumn
