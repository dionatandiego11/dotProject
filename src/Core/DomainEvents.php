<?php
/**
 * DotProject Domain Events
 * 
 * Eventos de dominio para o sistema dotProject.
 * Permitem desacoplamento entre modulos e notificacoes.
 * 
 * @package DotProject\Core
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Core;

/**
 * Constantes de eventos de dominio
 */
final class DomainEvents
{
    // Project Events
    public const PROJECT_CREATED = 'project.created';
    public const PROJECT_UPDATED = 'project.updated';
    public const PROJECT_DELETED = 'project.deleted';
    public const PROJECT_ARCHIVED = 'project.archived';
    public const PROJECT_STATUS_CHANGED = 'project.status_changed';
    public const PROJECT_PROGRESS_UPDATED = 'project.progress_updated';
    
    // Task Events
    public const TASK_CREATED = 'task.created';
    public const TASK_UPDATED = 'task.updated';
    public const TASK_DELETED = 'task.deleted';
    public const TASK_STATUS_CHANGED = 'task.status_changed';
    public const TASK_PROGRESS_UPDATED = 'task.progress_updated';
    public const TASK_COMPLETED = 'task.completed';
    public const TASK_OVERDUE = 'task.overdue';
    
    // Assignment Events
    public const TASK_ASSIGNED = 'task.assigned';
    public const TASK_UNASSIGNED = 'task.unassigned';
    
    // Dependency Events
    public const TASK_DEPENDENCY_ADDED = 'task.dependency_added';
    public const TASK_DEPENDENCY_REMOVED = 'task.dependency_removed';
    
    // User Events
    public const USER_CREATED = 'user.created';
    public const USER_UPDATED = 'user.updated';
    public const USER_DELETED = 'user.deleted';
    public const USER_LOGIN = 'user.login';
    public const USER_LOGOUT = 'user.logout';
    public const USER_PASSWORD_CHANGED = 'user.password_changed';
    
    // File Events
    public const FILE_UPLOADED = 'file.uploaded';
    public const FILE_DELETED = 'file.deleted';
    public const FILE_CHECKED_OUT = 'file.checked_out';
    public const FILE_CHECKED_IN = 'file.checked_in';
    
    // Calendar Events
    public const CALENDAR_EVENT_CREATED = 'calendar.event_created';
    public const CALENDAR_EVENT_UPDATED = 'calendar.event_updated';
    public const CALENDAR_EVENT_DELETED = 'calendar.event_deleted';
    
    // Company Events
    public const COMPANY_CREATED = 'company.created';
    public const COMPANY_UPDATED = 'company.updated';
    
    // Notification Events
    public const NOTIFICATION_SENT = 'notification.sent';
    public const NOTIFICATION_READ = 'notification.read';
    
    // System Events
    public const ERROR_OCCURRED = 'system.error';
    public const CACHE_CLEARED = 'system.cache_cleared';
}
