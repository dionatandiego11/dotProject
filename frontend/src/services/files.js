import { API_BASE, apiRequest, getToken } from './client';

export async function getTaskFiles(taskId) {
    return apiRequest(`/tasks/${taskId}/files`);
}

export async function uploadTaskFile(taskId, file) {
    const formData = new FormData();
    formData.append('file', file);

    const url = `${API_BASE}/tasks/${taskId}/files`;
    const token = getToken();

    const response = await fetch(url, {
        method: 'POST',
        headers: {
            Authorization: token ? `Bearer ${token}` : '',
        },
        body: formData,
    });

    const data = await response.json();

    if (!response.ok) {
        throw new Error(data.message || 'Upload failed');
    }

    return data;
}

export async function deleteTaskFile(fileId) {
    return apiRequest(`/files/${fileId}`, {
        method: 'DELETE',
    });
}

export function getTaskFileDownloadUrl(fileId) {
    return `${API_BASE}/files/${fileId}/download`;
}
