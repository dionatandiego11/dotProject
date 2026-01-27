/**
 * dotProject API Service
 * 
 * Handles all API calls to the backend.
 */

const API_BASE = '/api/v1';

// Store token in memory
let authToken = localStorage.getItem('dp_token');

/**
 * Set authentication token
 */
export function setToken(token) {
    authToken = token;
    if (token) {
        localStorage.setItem('dp_token', token);
    } else {
        localStorage.removeItem('dp_token');
    }
}

/**
 * Get current token
 */
export function getToken() {
    // Sempre lê do localStorage para garantir valor atualizado
    return localStorage.getItem('dp_token');
}

/**
 * Check if user is authenticated
 */
export function isAuthenticated() {
    // Sempre lê do localStorage para garantir valor atualizado
    return !!localStorage.getItem('dp_token');
}

/**
 * Generic API request
 */
async function apiRequest(endpoint, options = {}) {
    const url = `${API_BASE}${endpoint}`;

    const headers = {
        'Content-Type': 'application/json',
        ...options.headers,
    };

    const token = getToken();
    if (token) {
        headers['Authorization'] = `Bearer ${token}`;
    }

    try {
        const response = await fetch(url, {
            ...options,
            headers,
        });

        const rawText = await response.text();
        let data = null;
        if (rawText) {
            try {
                data = JSON.parse(rawText);
            } catch {
                data = { message: 'Resposta da API não é JSON', raw: rawText };
            }
        }

        if (!response.ok) {
            if (response.status === 401) {
                // Token expired
                setToken(null);
                window.location.href = '/login';
            }
            throw new Error((data && data.message) ? data.message : 'Request failed');
        }

        return data;
    } catch (err) {
        throw err;
    }
}

// ===========================================
// AUTH ENDPOINTS
// ===========================================

export async function login(username, password) {
    const data = await apiRequest('/auth/login', {
        method: 'POST',
        body: JSON.stringify({ username, password }),
    });

    if (data.token) {
        setToken(data.token);
        if (data.refresh_token) {
            localStorage.setItem('dp_refresh_token', data.refresh_token);
        }
    }

    return data;
}

export async function logout() {
    setToken(null);
    localStorage.removeItem('dp_refresh_token');
}

export async function getCurrentUser() {
    return apiRequest('/auth/me');
}

// ===========================================
// PROJECTS ENDPOINTS
// ===========================================

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

// ===========================================
// TASKS ENDPOINTS
// ===========================================

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

// ===========================================
// ANALYTICS ENDPOINTS
// ===========================================

export async function getDashboard(userId = null) {
    const params = userId ? `?user_id=${userId}` : '';
    return apiRequest(`/analytics/dashboard${params}`);
}

export async function getProjectsHealth() {
    return apiRequest('/analytics/projects-health');
}

export async function getCompletionTrend(days = 30) {
    return apiRequest(`/analytics/completion-trend?days=${days}`);
}

export async function getTeamPerformance() {
    return apiRequest('/analytics/team-performance');
}

export async function getVelocity(weeks = 8) {
    return apiRequest(`/analytics/velocity?weeks=${weeks}`);
}

export async function getProjectBurndown(projectId) {
    return apiRequest(`/analytics/projects/${projectId}/burndown`);
}

export async function getProjectStatistics(projectId) {
    return apiRequest(`/analytics/projects/${projectId}/statistics`);
}

// ===========================================
// GOOGLE INTEGRATION ENDPOINTS
// ===========================================

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

export default {
    // Auth
    login,
    logout,
    getCurrentUser,
    isAuthenticated,
    setToken,
    getToken,

    // Projects
    getProjects,
    getProject,
    createProject,
    updateProject,
    deleteProject,
    getProjectTasks,

    // Tasks
    getTasks,
    getTask,
    createTask,
    updateTask,
    deleteTask,

    // Analytics
    getDashboard,
    getProjectsHealth,
    getCompletionTrend,
    getTeamPerformance,
    getVelocity,
    getProjectBurndown,
    getProjectStatistics,

    // Google
    getGoogleAuthUrl,
    getGoogleStatus,
    disconnectGoogle,
    getGoogleCalendars,
    syncTaskToCalendar,
    getGoogleDriveFiles,
};
