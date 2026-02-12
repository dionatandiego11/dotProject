import * as apiClient from './client';
import * as authService from './auth';
import * as projectsService from './projects';
import * as tasksService from './tasks';
import * as analyticsService from './analytics';
import * as integrationsService from './integrations';
import * as kanbanService from './kanban';
import * as notificationsService from './notifications';
import * as filesService from './files';
import * as dashboardService from './dashboard';
import * as adminService from './admin';

export * from './client';
export * from './auth';
export * from './projects';
export * from './tasks';
export * from './analytics';
export * from './integrations';
export * from './kanban';
export * from './notifications';
export * from './files';
export * from './dashboard';
export * from './admin';

export const services = Object.freeze({
    auth: authService,
    projects: projectsService,
    tasks: tasksService,
    analytics: analyticsService,
    integrations: integrationsService,
    kanban: kanbanService,
    notifications: notificationsService,
    files: filesService,
    dashboard: dashboardService,
    admin: adminService,
});

export const api = Object.freeze({
    ...apiClient,
    ...authService,
    ...projectsService,
    ...tasksService,
    ...analyticsService,
    ...integrationsService,
    ...kanbanService,
    ...notificationsService,
    ...filesService,
    ...dashboardService,
    ...adminService,
    client: apiClient,
    services,
});

export default api;
