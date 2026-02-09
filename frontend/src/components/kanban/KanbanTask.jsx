/**
 * Kanban Task Component
 * 
 * Componente de tarefa no Kanban (card).
 */

import PropTypes from 'prop-types'

function KanbanTask({ task, columnId, index, onDragStart, onDragEnd, onClick }) {
  const { task: taskData } = task

  const handleDragStart = (e) => {
    e.dataTransfer.effectAllowed = 'move'
    onDragStart(task, columnId)
  }

  const handleDragEnd = () => {
    onDragEnd?.()
  }

  const getPriorityColor = (priority) => {
    const colors = {
      1: '#10B981', // low - green
      2: '#3B82F6', // medium - blue
      3: '#F59E0B', // high - orange
      4: '#EF4444', // urgent - red
    }
    return colors[priority] || '#6B7280'
  }

  const getPriorityLabel = (priority) => {
    const labels = {
      1: 'Baixa',
      2: 'Normal',
      3: 'Alta',
      4: 'Urgente',
    }
    return labels[priority] || 'Indefinida'
  }

  const formatTimeInColumn = (timeStr) => {
    if (!timeStr) return ''
    return `\u2022 ${timeStr}`
  }

  const formatDate = (value) => {
    if (!value) return ''
    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return value
    return date.toLocaleDateString('pt-BR')
  }

  const isOverdue = taskData?.is_overdue
  const progress = taskData?.percent_complete || 0

  return (
    <div
      className={`kanban-task ${isOverdue ? 'kanban-task-overdue' : ''}`}
      draggable
      onDragStart={handleDragStart}
      onDragEnd={handleDragEnd}
      onClick={() => onClick?.(task)}
    >
      {/* Prioridade */}
      <div className="kanban-task-header">
        <span
          className="kanban-task-priority"
          style={{ backgroundColor: getPriorityColor(taskData?.priority) }}
        >
          {getPriorityLabel(taskData?.priority)}
        </span>
        
        {task.time_in_column && (
          <span className="kanban-task-time">
            {formatTimeInColumn(task.time_in_column)}
          </span>
        )}
      </div>

      {/* Título */}
      <h4 className="kanban-task-title">{taskData?.name}</h4>

      {/* Descrição (se houver) */}
      {taskData?.description && (
        <p className="kanban-task-description">
          {taskData.description.substring(0, 100)}
          {taskData.description.length > 100 && '...'}
        </p>
      )}

      {/* Meta info */}
      <div className="kanban-task-meta">
        {/* Progresso */}
        <div className="kanban-task-progress">
          <div className="kanban-task-progress-bar">
            <div
              className="kanban-task-progress-fill"
              style={{ width: `${progress}%` }}
            />
          </div>
          <span className="kanban-task-progress-text">{progress}%</span>
        </div>

        {/* Assignee */}
        {taskData?.assigned_to && (
          <div className="kanban-task-assignee" title={taskData.assigned_to_name || ''}>
            <span className="kanban-avatar">
              {taskData.assigned_to_name?.charAt(0) || '?'}
            </span>
            <span className="kanban-assignee-name">
              {taskData.assigned_to_name || 'Responsavel'}
            </span>
          </div>
        )}

        {/* Tags/indicadores */}
        <div className="kanban-task-indicators">
          {taskData?.estimated_hours && (
            <span className="kanban-indicator" title="Horas estimadas">
              {taskData.estimated_hours}h
            </span>
          )}
          
          {taskData?.comments_count > 0 && (
            <span className="kanban-indicator" title="Comentarios">
              {taskData.comments_count} c
            </span>
          )}

          {taskData?.attachments_count > 0 && (
            <span className="kanban-indicator" title="Anexos">
              {taskData.attachments_count} a
            </span>
          )}
        </div>
      </div>

      {taskData?.end_date && (
        <div className="kanban-task-due">
          Prazo: {formatDate(taskData.end_date)}
        </div>
      )}

      {/* Overdue badge */}
      {isOverdue && (
        <div className="kanban-task-overdue-badge">
          Atrasada
        </div>
      )}
    </div>
  )
}

KanbanTask.propTypes = {
  task: PropTypes.shape({
    id: PropTypes.number.isRequired,
    task_id: PropTypes.number.isRequired,
    time_in_column: PropTypes.string,
    task: PropTypes.shape({
      name: PropTypes.string,
      description: PropTypes.string,
      priority: PropTypes.number,
      percent_complete: PropTypes.number,
      is_overdue: PropTypes.bool,
      assigned_to: PropTypes.number,
      assigned_to_name: PropTypes.string,
      estimated_hours: PropTypes.number,
      comments_count: PropTypes.number,
      attachments_count: PropTypes.number,
      end_date: PropTypes.string,
    }),
  }).isRequired,
  columnId: PropTypes.number.isRequired,
  index: PropTypes.number.isRequired,
  onDragStart: PropTypes.func.isRequired,
  onDragEnd: PropTypes.func,
  onClick: PropTypes.func,
}

export default KanbanTask
