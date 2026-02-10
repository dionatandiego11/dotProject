import { apiRequest } from './client';

export async function getProjects(params = {}) {
    const queryString = new URLSearchParams(params).toString();
    const endpoint = queryString ? `/projects?${queryString}` : '/projects';
    return apiRequest(endpoint);
}

export async function getProject(id) {
    return apiRequest(`/projects/${id}`);
}

export async function createProject(data) {
    return apiRequest('/projects', {
        method: 'POST',
        body: JSON.stringify(data),
    });
}

export async function updateProject(id, data) {
    return apiRequest(`/projects/${id}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    });
}

export async function updateProjectStatus(id, data) {
    return apiRequest(`/projects/${id}/status`, {
        method: 'PUT',
        body: JSON.stringify(data),
    });
}

export async function deleteProject(id) {
    return apiRequest(`/projects/${id}`, {
        method: 'DELETE',
    });
}

export async function getProjectTasks(projectId, params = {}) {
    const queryString = new URLSearchParams(params).toString();
    const endpoint = queryString
        ? `/projects/${projectId}/tasks?${queryString}`
        : `/projects/${projectId}/tasks`;
    return apiRequest(endpoint);
}

export async function getProjectStatusHistory(projectId, params = {}) {
    const queryString = new URLSearchParams(params).toString();
    const endpoint = queryString
        ? `/projects/${projectId}/status-history?${queryString}`
        : `/projects/${projectId}/status-history`;
    return apiRequest(endpoint);
}
