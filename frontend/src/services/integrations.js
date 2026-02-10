import { apiRequest } from './client';

export async function getGoogleAuthUrl() {
    return apiRequest('/integrations/google/auth');
}

export async function getGoogleStatus() {
    return apiRequest('/integrations/google/status');
}

export async function disconnectGoogle() {
    return apiRequest('/integrations/google', {
        method: 'DELETE',
    });
}

export async function getGoogleCalendars() {
    return apiRequest('/integrations/google/calendars');
}

export async function syncTaskToCalendar(taskId, calendarId = 'primary') {
    return apiRequest('/integrations/google/calendar/sync-task', {
        method: 'POST',
        body: JSON.stringify({ task_id: taskId, calendar_id: calendarId }),
    });
}

export async function getGoogleDriveFiles(query = '') {
    const params = query ? `?q=${encodeURIComponent(query)}` : '';
    return apiRequest(`/integrations/google/drive/files${params}`);
}
