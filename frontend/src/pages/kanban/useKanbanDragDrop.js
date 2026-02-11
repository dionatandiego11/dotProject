import { useState } from 'react'
import { normalizeProgressForStatus } from './kanbanUtils'

export default function useKanbanDragDrop({
    columns,
    filteredColumns,
    setColumns,
    selectedProjectId,
    loadBoard,
    statusByColumnId,
    moveKanbanTaskApi,
    updateTaskApi,
    toast,
}) {
    const [draggingTask, setDraggingTask] = useState(null)
    const [dragOverColumn, setDragOverColumn] = useState(null)

    function resolveTargetOrder(targetColumnId, targetOrder) {
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

        const fullIndex = (fullColumn.tasks || []).findIndex((task) => task.id === nextVisible.id)
        return fullIndex === -1 ? (fullColumn.tasks?.length || 0) : fullIndex
    }

    function moveTaskLocally(list, task, sourceColumnId, targetColumnId, targetOrder, taskPatch = null) {
        const next = list.map((column) => ({
            ...column,
            tasks: [...(column.tasks || [])],
        }))

        let movedTask = null
        const sourceColumn = next.find((column) => column.id === sourceColumnId)
        if (sourceColumn) {
            const sourceIndex = sourceColumn.tasks.findIndex((item) => item.id === task.id)
            if (sourceIndex >= 0) {
                movedTask = sourceColumn.tasks.splice(sourceIndex, 1)[0]
            }
        }

        if (!movedTask) return list

        const targetColumn = next.find((column) => column.id === targetColumnId)
        if (!targetColumn) return list

        const insertAt = Math.max(0, Math.min(targetOrder, targetColumn.tasks.length))
        const patchedTask = taskPatch
            ? { ...movedTask, task: { ...(movedTask.task || {}), ...taskPatch } }
            : movedTask
        targetColumn.tasks.splice(insertAt, 0, { ...patchedTask, column_id: targetColumnId })

        ;[sourceColumn, targetColumn].forEach((column) => {
            if (!column) return
            column.tasks = column.tasks.map((item, index) => ({ ...item, order: index }))
            column.task_count = column.tasks.length
        })

        return next
    }

    function handleDragStart(task, columnId) {
        setDraggingTask({ task, sourceColumnId: Number(columnId) })
    }

    function handleDragOver(columnId) {
        setDragOverColumn(Number(columnId))
    }

    function handleDragEnd() {
        setDraggingTask(null)
        setDragOverColumn(null)
    }

    async function handleDrop(targetColumnId, targetOrder) {
        if (!draggingTask) return

        const normalizedTargetColumnId = Number(targetColumnId)
        const { task, sourceColumnId } = draggingTask
        const mappedStatus = statusByColumnId.get(normalizedTargetColumnId)
        const mappedPercent = mappedStatus !== undefined
            ? normalizeProgressForStatus(mappedStatus, task?.task?.percent_complete)
            : Number(task?.task?.percent_complete || 0)
        const resolvedOrder = resolveTargetOrder(normalizedTargetColumnId, targetOrder)
        const currentIndex = (columns.find((col) => col.id === sourceColumnId)?.tasks || [])
            .findIndex((item) => item.id === task.id)
        let adjustedOrder = resolvedOrder

        if (sourceColumnId === normalizedTargetColumnId && currentIndex !== -1 && resolvedOrder > currentIndex) {
            adjustedOrder = Math.max(0, resolvedOrder - 1)
        }

        if (sourceColumnId === normalizedTargetColumnId && currentIndex === adjustedOrder) {
            handleDragEnd()
            return
        }

        setColumns(moveTaskLocally(
            columns,
            task,
            sourceColumnId,
            normalizedTargetColumnId,
            adjustedOrder,
            { percent_complete: mappedPercent },
        ))
        handleDragEnd()

        try {
            const result = await moveKanbanTaskApi(task.id, normalizedTargetColumnId, adjustedOrder)
            if (!result?.success) {
                throw new Error(result?.message || 'Falha ao mover tarefa')
            }

            if (mappedStatus !== undefined && task?.task_id) {
                await updateTaskApi(task.task_id, {
                    status: mappedStatus,
                    percent_complete: mappedPercent,
                })
            }

            toast.success('Tarefa movida!')
        } catch (err) {
            toast.error('Erro ao mover tarefa: ' + err.message)
            await loadBoard(selectedProjectId)
        }
    }

    return {
        dragOverColumn,
        handleDragStart,
        handleDragOver,
        handleDragEnd,
        handleDrop,
    }
}
