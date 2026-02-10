import { apiRequest } from './client';

export async function getKanbanBoards(projectId = null) {
    const params = projectId ? `?project_id=${projectId}` : '';
    return apiRequest(`/kanban/boards${params}`);
}

export async function getKanbanBoard(boardId) {
    return apiRequest(`/kanban/boards/${boardId}`);
}

export async function createKanbanBoard(data) {
    return apiRequest('/kanban/boards', {
        method: 'POST',
        body: JSON.stringify(data),
    });
}

export async function createKanbanColumn(boardId, data) {
    return apiRequest(`/kanban/boards/${boardId}/columns`, {
        method: 'POST',
        body: JSON.stringify(data),
    });
}

export async function updateKanbanColumn(columnId, data) {
    return apiRequest(`/kanban/columns/${columnId}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    });
}

export async function moveKanbanTask(taskId, columnId, order) {
    return apiRequest(`/kanban/tasks/${taskId}/move`, {
        method: 'PUT',
        body: JSON.stringify({ column_id: columnId, order }),
    });
}

export async function getKanbanAnalytics(boardId) {
    return apiRequest(`/kanban/boards/${boardId}/analytics`);
}
