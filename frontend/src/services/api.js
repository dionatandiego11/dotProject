/**
 * dotProject API Service
 * 
 * Handles all API calls to the backend.
 */

const API_BASE = '/api.php/v1';

/**
 * Set authentication token
 */
export function setToken(token) {
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
    console.log('API Request:', url, options.method || 'GET');

    const headers = {
        'Content-Type': 'application/json',
        ...options.headers,
    };

    const token = getToken();
    if (token) {
        headers['Authorization'] = `Bearer ${token}`;
    }

    const response = await fetch(url, {
        ...options,
        headers,
    });

    const rawText = await response.text();
    console.log('API Response:', url, response.status, rawText.substring(0, 200));
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

// ===========================================
// KANBAN ENDPOINTS
// ===========================================

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

// ===========================================
// NOTIFICATIONS ENDPOINTS
// ===========================================

export async function getNotifications(unreadOnly = false, limit = 50) {
    const params = new URLSearchParams();
    if (unreadOnly) params.append('unread', 'true');
    if (limit) params.append('limit', limit.toString());
    const query = params.toString() ? `?${params.toString()}` : '';
    return apiRequest(`/notifications${query}`);
}

export async function getUnreadNotificationsCount() {
    return apiRequest('/notifications/unread-count');
}

export async function markNotificationAsRead(notificationId) {
    return apiRequest(`/notifications/${notificationId}/read`, {
        method: 'POST',
    });
}

export async function markAllNotificationsAsRead() {
    return apiRequest('/notifications/mark-all-read', {
        method: 'POST',
    });
}

// ===========================================
// FILE ATTACHMENTS ENDPOINTS
// ===========================================

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
            'Authorization': token ? `Bearer ${token}` : '',
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

// ===========================================
// ANALYTICS ENDPOINTS
// ===========================================

export async function getDashboardAnalytics() {
    return apiRequest('/analytics/dashboard');
}

export async function getProductivityTrend(days = 7) {
    return apiRequest(`/analytics/productivity?days=${days}`);
}

// ===========================================
// DASHBOARD POR PERFIL ENDPOINTS
// ===========================================

/**
 * Dashboard auto-detectado pelo perfil do usuário
 */
export async function getDashboardByProfile() {
    return apiRequest('/dashboard');
}

/**
 * Dashboard do Prefeito - Visão Executiva
 */
export async function getDashboardPrefeito() {
    return apiRequest('/dashboard/prefeito');
}

/**
 * Dashboard do Secretário - Visão da Secretaria
 */
export async function getDashboardSecretario() {
    return apiRequest('/dashboard/secretario');
}

/**
 * Dashboard do Coordenador - Visão de Projetos
 */
export async function getDashboardCoordenador() {
    return apiRequest('/dashboard/coordenador');
}

/**
 * Dashboard do Técnico - Visão de Tarefas
 */
export async function getDashboardTecnico() {
    return apiRequest('/dashboard/tecnico');
}

/**
 * Dashboard do Controlador - Visão de Fiscalização
 */
export async function getDashboardControlador() {
    return apiRequest('/dashboard/controlador');
}

/**
 * Alertas do usuário logado
 */
export async function getDashboardAlertas() {
    return apiRequest('/dashboard/alertas');
}

/**
 * Marcar alerta como lido
 */
export async function marcarAlertaLido(alertaId) {
    return apiRequest(`/dashboard/alertas/${alertaId}/lido`, {
        method: 'PUT',
    });
}

/**
 * Marcar todos os alertas como lidos
 */
export async function marcarTodosAlertasLidos() {
    return apiRequest('/dashboard/alertas/lidos', {
        method: 'PUT',
    });
}

// ===========================================
// ADMINISTRAÇÃO - NÍVEIS HIERÁRQUICOS
// ===========================================

export async function getNiveis() {
    return apiRequest('/admin/niveis');
}

export async function getNivel(id) {
    return apiRequest(`/admin/niveis/${id}`);
}

export async function createNivel(data) {
    return apiRequest('/admin/niveis', {
        method: 'POST',
        body: JSON.stringify(data),
    });
}

export async function updateNivel(id, data) {
    return apiRequest(`/admin/niveis/${id}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    });
}

export async function deleteNivel(id) {
    return apiRequest(`/admin/niveis/${id}`, {
        method: 'DELETE',
    });
}

export async function reordenarNiveis(ordens) {
    return apiRequest('/admin/niveis/reordenar', {
        method: 'PUT',
        body: JSON.stringify({ ordens }),
    });
}

// ===========================================
// ADMINISTRAÇÃO - UNIDADES ORGANIZACIONAIS
// ===========================================

export async function getUnidades(params = {}) {
    const queryString = new URLSearchParams(params).toString();
    const endpoint = queryString ? `/admin/unidades?${queryString}` : '/admin/unidades';
    return apiRequest(endpoint);
}

export async function getArvoreUnidades(raizId = null) {
    const params = raizId ? `?raiz_id=${raizId}` : '';
    return apiRequest(`/admin/unidades/arvore${params}`);
}

export async function getUnidade(id) {
    return apiRequest(`/admin/unidades/${id}`);
}

export async function createUnidade(data) {
    return apiRequest('/admin/unidades', {
        method: 'POST',
        body: JSON.stringify(data),
    });
}

export async function updateUnidade(id, data) {
    return apiRequest(`/admin/unidades/${id}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    });
}

export async function deleteUnidade(id) {
    return apiRequest(`/admin/unidades/${id}`, {
        method: 'DELETE',
    });
}

export async function moverUnidade(id, paiId) {
    return apiRequest(`/admin/unidades/${id}/mover`, {
        method: 'PUT',
        body: JSON.stringify({ pai_id: paiId }),
    });
}

export async function getSubordinadas(id) {
    return apiRequest(`/admin/unidades/${id}/subordinadas`);
}

export async function getUsuarios(params = {}) {
    const queryString = new URLSearchParams(params).toString();
    const endpoint = queryString ? `/admin/usuarios?${queryString}` : '/admin/usuarios';
    return apiRequest(endpoint);
}

export async function getUsuario(id) {
    return apiRequest(`/admin/usuarios/${id}`);
}

export async function createUsuario(data) {
    return apiRequest('/admin/usuarios', {
        method: 'POST',
        body: JSON.stringify(data),
    });
}

export async function updateUsuario(id, data) {
    return apiRequest(`/admin/usuarios/${id}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    });
}

export async function deleteUsuario(id) {
    return apiRequest(`/admin/usuarios/${id}`, {
        method: 'DELETE',
    });
}

// ===========================================
// ADMINISTRAÇÃO - VÍNCULOS
// ===========================================

export async function getVinculos(params = {}) {
    const queryString = new URLSearchParams(params).toString();
    const endpoint = queryString ? `/admin/vinculos?${queryString}` : '/admin/vinculos';
    return apiRequest(endpoint);
}

export async function createVinculo(data) {
    return apiRequest('/admin/vinculos', {
        method: 'POST',
        body: JSON.stringify(data),
    });
}

export async function updateVinculo(id, data) {
    return apiRequest(`/admin/vinculos/${id}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    });
}

export async function deleteVinculo(id) {
    return apiRequest(`/admin/vinculos/${id}`, {
        method: 'DELETE',
    });
}

export async function definirVinculoPrincipal(id) {
    return apiRequest(`/admin/vinculos/${id}/principal`, {
        method: 'PUT',
    });
}

// ===========================================
// ADMINISTRAÇÃO - PERMISSÕES
// ===========================================

export async function getMatrizPermissoes() {
    return apiRequest('/admin/permissoes/matriz');
}

export async function updateMatrizPermissoes(data) {
    return apiRequest('/admin/permissoes/matriz', {
        method: 'PUT',
        body: JSON.stringify(data),
    });
}

export async function getOrganograma() {
    return apiRequest('/admin/organograma');
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

    // Files
    getTaskFiles,
    uploadTaskFile,
    deleteTaskFile,
    getTaskFileDownloadUrl,

    // Analytics
    getDashboardAnalytics,
    getProductivityTrend,

    // Admin - Níveis
    getNiveis,
    getNivel,
    createNivel,
    updateNivel,
    deleteNivel,
    reordenarNiveis,

    // Admin - Unidades
    getUnidades,
    getArvoreUnidades,
    getUnidade,
    createUnidade,
    updateUnidade,
    deleteUnidade,
    moverUnidade,
    getSubordinadas,
    getUsuarios,
    getUsuario,
    createUsuario,
    updateUsuario,
    deleteUsuario,

    // Admin - Vínculos
    getVinculos,
    createVinculo,
    updateVinculo,
    deleteVinculo,
    definirVinculoPrincipal,

    // Admin - Permissões
    getMatrizPermissoes,
    updateMatrizPermissoes,
    getOrganograma
};
