import { apiRequest } from './client';

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
