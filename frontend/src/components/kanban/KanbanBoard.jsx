/**
 * Kanban Board Component
 * 
 * Componente principal do quadro Kanban com drag & drop.
 */

import { useState, useEffect, useCallback } from 'react'
import PropTypes from 'prop-types'
import KanbanColumn from './KanbanColumn'
import { 
    getKanbanBoard, 
    moveKanbanTask, 
    createKanbanColumn 
} from '../../services/api'
import './KanbanBoard.css'

function KanbanBoard({ boardId, onTaskClick, onTaskMove }) {
  const [board, setBoard] = useState(null)
  const [columns, setColumns] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [draggingTask, setDraggingTask] = useState(null)
  const [dragOverColumn, setDragOverColumn] = useState(null)

  // Carrega dados do board
  useEffect(() => {
    loadBoard()
  }, [boardId])

  const loadBoard = async () => {
    try {
      setLoading(true)
      const result = await getKanbanBoard(boardId)

      if (result.success) {
        setBoard(result.data.board)
        setColumns(result.data.board.columns || [])
      } else {
        setError(result.message || 'Failed to load board')
      }
    } catch (err) {
      setError('Network error')
    } finally {
      setLoading(false)
    }
  }

  // Handlers de Drag & Drop
  const handleDragStart = useCallback((task, columnId) => {
    setDraggingTask({ task, sourceColumnId: columnId })
  }, [])

  const handleDragOver = useCallback((columnId) => {
    setDragOverColumn(columnId)
  }, [])

  const handleDragEnd = useCallback(() => {
    setDraggingTask(null)
    setDragOverColumn(null)
  }, [])

  const handleDrop = useCallback(async (targetColumnId, targetOrder) => {
    if (!draggingTask) return

    const { task, sourceColumnId } = draggingTask

    // Se soltou no mesmo lugar, não faz nada
    if (sourceColumnId === targetColumnId && task.order === targetOrder) {
      handleDragEnd()
      return
    }

    // Atualiza localmente para feedback imediato
    const updatedColumns = columns.map(col => {
      if (col.id === sourceColumnId) {
        return {
          ...col,
          tasks: col.tasks.filter(t => t.id !== task.id)
        }
      }
      if (col.id === targetColumnId) {
        const newTasks = [...col.tasks]
        newTasks.splice(targetOrder, 0, { ...task, column_id: targetColumnId })
        return { ...col, tasks: newTasks }
      }
      return col
    })

    setColumns(updatedColumns)
    handleDragEnd()

    // Envia para API
    try {
      const result = await moveKanbanTask(task.id, targetColumnId, targetOrder)

      if (!result.success) {
        // Reverte em caso de erro
        loadBoard()
      } else if (onTaskMove) {
        onTaskMove(task, sourceColumnId, targetColumnId)
      }
    } catch (err) {
      loadBoard() // Reverte
    }
  }, [draggingTask, columns, onTaskMove, handleDragEnd])

  // Adiciona nova coluna
  const handleAddColumn = async () => {
    const name = prompt('Column name:')
    if (!name) return

    try {
      const result = await createKanbanColumn(boardId, { name })

      if (result.success) {
        loadBoard()
      }
    } catch (err) {
      console.error('Failed to add column:', err)
    }
  }

  if (loading) {
    return <div className="kanban-loading">Loading board...</div>
  }

  if (error) {
    return <div className="kanban-error">{error}</div>
  }

  if (!board) {
    return <div className="kanban-empty">Board not found</div>
  }

  return (
    <div className="kanban-board">
      <div className="kanban-header">
        <h2 className="kanban-title">{board.name}</h2>
        <div className="kanban-stats">
          <span className="kanban-stat">
            {board.total_tasks} tasks
          </span>
          {board.columns?.map(col => (
            col.wip_limit && (
              <span
                key={col.id}
                className={`kanban-wip ${col.is_at_wip_limit ? 'kanban-wip-limit' : ''}`}
              >
                {col.name}: {col.task_count}/{col.wip_limit}
              </span>
            )
          ))}
        </div>
      </div>

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
            onTaskClick={onTaskClick}
          />
        ))}

        <button
          className="kanban-add-column"
          onClick={handleAddColumn}
        >
          + Add Column
        </button>
      </div>
    </div>
  )
}

KanbanBoard.propTypes = {
  boardId: PropTypes.number.isRequired,
  onTaskClick: PropTypes.func,
  onTaskMove: PropTypes.func,
}

export default KanbanBoard
