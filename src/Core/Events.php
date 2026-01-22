<?php
/**
 * DotProject Events Constants
 * 
 * Defines all system events that can be hooked into.
 * 
 * @package DotProject\Core
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Core;

/**
 * System Events
 * 
 * Constants for all dispatchable events in the system.
 */
final class Events
{
    // Project Events
    public const PROJECT_BEFORE_CREATE = 'project.before_create';
    public const PROJECT_AFTER_CREATE = 'project.after_create';
    public const PROJECT_BEFORE_UPDATE = 'project.before_update';
    public const PROJECT_AFTER_UPDATE = 'project.after_update';
    public const PROJECT_BEFORE_DELETE = 'project.before_delete';
    public const PROJECT_AFTER_DELETE = 'project.after_delete';
    public const PROJECT_STATUS_CHANGED = 'project.status_changed';

    // Task Events
    public const TASK_BEFORE_CREATE = 'task.before_create';
    public const TASK_AFTER_CREATE = 'task.after_create';
    public const TASK_BEFORE_UPDATE = 'task.before_update';
    public const TASK_AFTER_UPDATE = 'task.after_update';
    public const TASK_BEFORE_DELETE = 'task.before_delete';
    public const TASK_AFTER_DELETE = 'task.after_delete';
    public const TASK_PROGRESS_CHANGED = 'task.progress_changed';
    public const TASK_ASSIGNED = 'task.assigned';
    public const TASK_UNASSIGNED = 'task.unassigned';
    public const TASK_COMPLETED = 'task.completed';
    public const TASK_OVERDUE = 'task.overdue';

    // User Events
    public const USER_BEFORE_CREATE = 'user.before_create';
    public const USER_AFTER_CREATE = 'user.after_create';
    public const USER_BEFORE_UPDATE = 'user.before_update';
    public const USER_AFTER_UPDATE = 'user.after_update';
    public const USER_BEFORE_DELETE = 'user.before_delete';
    public const USER_AFTER_DELETE = 'user.after_delete';
    public const USER_LOGIN = 'user.login';
    public const USER_LOGOUT = 'user.logout';
    public const USER_PASSWORD_CHANGED = 'user.password_changed';

    // Company Events
    public const COMPANY_BEFORE_CREATE = 'company.before_create';
    public const COMPANY_AFTER_CREATE = 'company.after_create';
    public const COMPANY_BEFORE_UPDATE = 'company.before_update';
    public const COMPANY_AFTER_UPDATE = 'company.after_update';
    public const COMPANY_BEFORE_DELETE = 'company.before_delete';
    public const COMPANY_AFTER_DELETE = 'company.after_delete';

    // File Events
    public const FILE_BEFORE_UPLOAD = 'file.before_upload';
    public const FILE_AFTER_UPLOAD = 'file.after_upload';
    public const FILE_BEFORE_DELETE = 'file.before_delete';
    public const FILE_AFTER_DELETE = 'file.after_delete';
    public const FILE_DOWNLOAD = 'file.download';

    // System Events
    public const SYSTEM_INIT = 'system.init';
    public const SYSTEM_SHUTDOWN = 'system.shutdown';
    public const SYSTEM_ERROR = 'system.error';
    public const SYSTEM_MAINTENANCE = 'system.maintenance';

    // Notification Events
    public const NOTIFICATION_SEND = 'notification.send';
    public const NOTIFICATION_EMAIL = 'notification.email';

    /**
     * Private constructor to prevent instantiation
     */
    private function __construct()
    {
    }
}
