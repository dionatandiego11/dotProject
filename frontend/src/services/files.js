import { API_BASE, apiRequest } from './client';

export async function getTaskFiles(taskId) {
    return apiRequest(`/tasks/${taskId}/files`);
}

export async function uploadTaskFile(taskId, file) {
    const formData = new FormData();
    formData.append('file', file);

    return apiRequest(`/tasks/${taskId}/files`, {
        method: 'POST',
        body: formData,
    });
}

export async function deleteTaskFile(fileId) {
    return apiRequest(`/files/${fileId}`, {
        method: 'DELETE',
    });
}

export function getTaskFileDownloadUrl(fileId) {
    return `${API_BASE}/files/${fileId}/download`;
}
