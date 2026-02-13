import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('./client', () => ({
    apiRequest: vi.fn(),
    API_BASE: '/api/v1',
}));

import { apiRequest } from './client';
import * as adminService from './admin';
import * as analyticsService from './analytics';
import * as dashboardService from './dashboard';
import * as filesService from './files';
import * as integrationsService from './integrations';
import * as kanbanService from './kanban';
import * as notificationsService from './notifications';
import * as projectsService from './projects';
import * as tasksService from './tasks';

describe('Service modules', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        apiRequest.mockResolvedValue({ ok: true });
    });

    it('builds project list query params', async () => {
        await projectsService.getProjects({ page: 2, per_page: 20, search: 'obra' });

        expect(apiRequest).toHaveBeenCalledWith('/projects?page=2&per_page=20&search=obra');
    });

    it('calls project status history endpoint', async () => {
        await projectsService.getProjectStatusHistory(15, { limit: 10 });

        expect(apiRequest).toHaveBeenCalledWith('/projects/15/status-history?limit=10');
    });

    it('calls project audit log endpoint', async () => {
        await projectsService.getProjectAuditLog(15, { page: 2, per_page: 5 });

        expect(apiRequest).toHaveBeenCalledWith('/projects/15/audit-log?page=2&per_page=5');
    });

    it('sends JSON payload for task update', async () => {
        await tasksService.updateTask(99, { name: 'Nova tarefa' });

        expect(apiRequest).toHaveBeenCalledWith('/tasks/99', {
            method: 'PUT',
            body: JSON.stringify({ name: 'Nova tarefa' }),
        });
    });

    it('builds analytics endpoints with query defaults', async () => {
        await analyticsService.getCompletionTrend();
        await analyticsService.getVelocity(12);

        expect(apiRequest).toHaveBeenNthCalledWith(1, '/analytics/completion-trend?days=30');
        expect(apiRequest).toHaveBeenNthCalledWith(2, '/analytics/velocity?weeks=12');
    });

    it('sends sync payload for Google Calendar integration', async () => {
        await integrationsService.syncTaskToCalendar(22, 'municipio');

        expect(apiRequest).toHaveBeenCalledWith('/integrations/google/calendar/sync-task', {
            method: 'POST',
            body: JSON.stringify({ task_id: 22, calendar_id: 'municipio' }),
        });
    });

    it('sends move payload for kanban task', async () => {
        await kanbanService.moveKanbanTask(5, 11, 3);

        expect(apiRequest).toHaveBeenCalledWith('/kanban/tasks/5/move', {
            method: 'PUT',
            body: JSON.stringify({ column_id: 11, order: 3 }),
        });
    });

    it('builds unread notifications endpoint with limit', async () => {
        await notificationsService.getNotifications(true, 25);

        expect(apiRequest).toHaveBeenCalledWith('/notifications?unread=true&limit=25');
    });

    it('uses canonical files download URL', () => {
        expect(filesService.getTaskFileDownloadUrl(77)).toBe('/api/v1/files/77/download');
    });

    it('uploads task file through apiRequest with FormData', async () => {
        const file = new File(['abc'], 'a.txt', { type: 'text/plain' });
        await filesService.uploadTaskFile(31, file);

        const [endpoint, options] = apiRequest.mock.calls[0];
        expect(endpoint).toBe('/tasks/31/files');
        expect(options.method).toBe('POST');
        expect(options.body).toBeInstanceOf(FormData);
    });

    it('calls dashboard alert actions', async () => {
        await dashboardService.marcarAlertaLido(17);
        await dashboardService.marcarTodosAlertasLidos();

        expect(apiRequest).toHaveBeenNthCalledWith(1, '/dashboard/alertas/17/lido', {
            method: 'PUT',
        });
        expect(apiRequest).toHaveBeenNthCalledWith(2, '/dashboard/alertas/lidos', {
            method: 'PUT',
        });
    });

    it('builds admin endpoints with query params and payloads', async () => {
        await adminService.getUnidades({ ativa: 1, page: 3 });
        await adminService.reordenarNiveis([{ id: 1, ordem: 2 }]);

        expect(apiRequest).toHaveBeenNthCalledWith(1, '/admin/unidades?ativa=1&page=3');
        expect(apiRequest).toHaveBeenNthCalledWith(2, '/admin/niveis/reordenar', {
            method: 'PUT',
            body: JSON.stringify({ ordens: [{ id: 1, ordem: 2 }] }),
        });
    });
});
