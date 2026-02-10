import { apiRequest } from './client';

export async function getTasks(params = {}) {
    const queryString = new URLSearchParams(params).toString();
    const endpoint = queryString ? `/tasks?${queryString}` : '/tasks';
    return apiRequest(endpoint);
}

export async function getTask(id) {
    return apiRequest(`/tasks/${id}`);
}

export async function createTask(data) {
    return apiRequest('/tasks', {
        method: 'POST',
        body: JSON.stringify(data),
    });
}

export async function updateTask(id, data) {
    return apiRequest(`/tasks/${id}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    });
}

export async function deleteTask(id) {
    return apiRequest(`/tasks/${id}`, {
        method: 'DELETE',
    });
}
