import { apiRequest } from './client';

/**
 * Dashboard auto-detected by user profile.
 */
export async function getDashboardByProfile() {
    return apiRequest('/dashboard');
}

/**
 * Dashboard runtime status and resolved profile.
 */
export async function getDashboardStatus() {
    return apiRequest('/dashboard/status');
}

/**
 * Mayor dashboard.
 */
export async function getDashboardPrefeito() {
    return apiRequest('/dashboard/prefeito');
}

/**
 * Secretary dashboard.
 */
export async function getDashboardSecretario() {
    return apiRequest('/dashboard/secretario');
}

/**
 * Coordinator dashboard.
 */
export async function getDashboardCoordenador() {
    return apiRequest('/dashboard/coordenador');
}

/**
 * Technician dashboard.
 */
export async function getDashboardTecnico() {
    return apiRequest('/dashboard/tecnico');
}

/**
 * Controller dashboard.
 */
export async function getDashboardControlador() {
    return apiRequest('/dashboard/controlador');
}

/**
 * Logged user alerts.
 */
export async function getDashboardAlertas() {
    return apiRequest('/dashboard/alertas');
}

/**
 * Mark alert as read.
 */
export async function marcarAlertaLido(alertaId) {
    return apiRequest(`/dashboard/alertas/${alertaId}/lido`, {
        method: 'PUT',
    });
}

/**
 * Mark all alerts as read.
 */
export async function marcarTodosAlertasLidos() {
    return apiRequest('/dashboard/alertas/lidos', {
        method: 'PUT',
    });
}
