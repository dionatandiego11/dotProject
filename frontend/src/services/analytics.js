import { apiRequest } from './client';

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

export async function getDashboardAnalytics() {
    return apiRequest('/analytics/dashboard');
}

export async function getProductivityTrend(days = 7) {
    return apiRequest(`/analytics/productivity?days=${days}`);
}
