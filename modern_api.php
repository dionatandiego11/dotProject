<?php
/**
 * Modern DotProject Integration Example
 * 
 * This file demonstrates how to use the modern PSR-4 components
 * alongside the legacy dotProject system.
 * 
 * Include this file in legacy modules to access modern services.
 * 
 * @package DotProject
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

// Ensure bootstrap is loaded
if (!class_exists('DotProject\Core\Application')) {
    require_once __DIR__ . '/bootstrap.php';
}

use DotProject\Core\Application;
use DotProject\Core\Database;
use DotProject\Core\Event;
use DotProject\Core\EventDispatcher;
use DotProject\Core\Events;
use DotProject\Entity\Project;
use DotProject\Entity\Task;
use DotProject\Entity\User;
use DotProject\Entity\Contact;
use DotProject\Entity\Company;
use DotProject\Entity\Department;
use DotProject\Entity\File;
use DotProject\Service\ProjectService;
use DotProject\Service\TaskService;
use DotProject\Service\ValidationService;
use DotProject\Repository\ProjectRepository;
use DotProject\Repository\TaskRepository;

/**
 * Modern API Facade
 * 
 * Provides a simple interface to access all modern services
 * from legacy code without needing to understand namespaces.
 */
class ModernAPI
{
    private static ?ModernAPI $instance = null;

    private ProjectService $projectService;
    private TaskService $taskService;
    private ValidationService $validationService;
    private ProjectRepository $projectRepository;
    private TaskRepository $taskRepository;
    private EventDispatcher $events;

    private function __construct()
    {
        $this->projectService = new ProjectService();
        $this->taskService = new TaskService();
        $this->validationService = new ValidationService();
        $this->projectRepository = new ProjectRepository();
        $this->taskRepository = new TaskRepository();
        $this->events = EventDispatcher::getInstance();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ==================== PROJECT METHODS ====================

    /**
     * Get active projects
     * 
     * @param int|null $companyId Optional company filter
     * @return array<int, Project>
     */
    public function getActiveProjects(?int $companyId = null): array
    {
        return $this->projectService->getActiveProjects($companyId);
    }

    /**
     * Get project by ID
     */
    public function getProject(int $id): ?Project
    {
        return $this->projectRepository->find($id);
    }

    /**
     * Get project statistics
     * 
     * @return array<string, mixed>
     */
    public function getProjectStats(int $projectId): array
    {
        return $this->projectService->getProjectStatistics($projectId);
    }

    /**
     * Search projects
     * 
     * @return array<int, Project>
     */
    public function searchProjects(string $query): array
    {
        return $this->projectRepository->search($query);
    }

    /**
     * Create project with validation
     * 
     * @param array<string, mixed> $data
     * @return array{success: bool, project?: Project, errors?: array<string, string>}
     */
    public function createProject(array $data): array
    {
        // Validate
        $validation = $this->validationService->validateProject($data);
        if ($validation->fails()) {
            return ['success' => false, 'errors' => $validation->errors()];
        }

        // Dispatch before event
        $event = new Event(Events::PROJECT_BEFORE_CREATE, ['data' => $data]);
        $this->events->dispatch(Events::PROJECT_BEFORE_CREATE, $event);

        if ($event->isPropagationStopped()) {
            return ['success' => false, 'errors' => ['event' => 'Creation cancelled by event listener']];
        }

        // Create
        $project = $this->projectService->createProject($data);

        if ($project === null) {
            return ['success' => false, 'errors' => ['database' => 'Failed to create project']];
        }

        // Dispatch after event  
        $this->events->dispatch(Events::PROJECT_AFTER_CREATE, new Event(Events::PROJECT_AFTER_CREATE, [
            'project' => $project
        ]));

        return ['success' => true, 'project' => $project];
    }

    // ==================== TASK METHODS ====================

    /**
     * Get tasks for a project
     * 
     * @return array<int, Task>
     */
    public function getProjectTasks(int $projectId): array
    {
        return $this->taskRepository->findByProject($projectId);
    }

    /**
     * Get task by ID
     */
    public function getTask(int $id): ?Task
    {
        return $this->taskRepository->find($id);
    }

    /**
     * Get overdue tasks
     * 
     * @return array<int, Task>
     */
    public function getOverdueTasks(?int $projectId = null): array
    {
        return $this->taskRepository->findOverdue($projectId);
    }

    /**
     * Get upcoming tasks
     * 
     * @return array<int, Task>
     */
    public function getUpcomingTasks(int $days = 7, ?int $userId = null): array
    {
        return $this->taskRepository->findUpcoming($days, $userId);
    }

    /**
     * Update task progress
     */
    public function updateTaskProgress(int $taskId, int $percent): bool
    {
        $oldPercent = 0;
        $task = $this->getTask($taskId);
        if ($task) {
            $oldPercent = $task->getPercentComplete();
        }

        $result = $this->taskService->updateProgress($taskId, $percent);

        if ($result && $percent === 100 && $oldPercent < 100) {
            $this->events->dispatch(Events::TASK_COMPLETED, new Event(Events::TASK_COMPLETED, [
                'task_id' => $taskId
            ]));
        }

        return $result;
    }

    /**
     * Create task with validation
     * 
     * @param array<string, mixed> $data
     * @return array{success: bool, task?: Task, errors?: array<string, string>}
     */
    public function createTask(array $data): array
    {
        // Validate
        $validation = $this->validationService->validateTask($data);
        if ($validation->fails()) {
            return ['success' => false, 'errors' => $validation->errors()];
        }

        // Dispatch before event
        $event = new Event(Events::TASK_BEFORE_CREATE, ['data' => $data]);
        $this->events->dispatch(Events::TASK_BEFORE_CREATE, $event);

        if ($event->isPropagationStopped()) {
            return ['success' => false, 'errors' => ['event' => 'Creation cancelled by event listener']];
        }

        // Create
        $task = $this->taskService->createTask($data);

        if ($task === null) {
            return ['success' => false, 'errors' => ['database' => 'Failed to create task']];
        }

        // Dispatch after event
        $this->events->dispatch(Events::TASK_AFTER_CREATE, new Event(Events::TASK_AFTER_CREATE, [
            'task' => $task
        ]));

        return ['success' => true, 'task' => $task];
    }

    // ==================== ENTITY ACCESS ====================

    /**
     * Find any entity by ID
     */
    public function findUser(int $id): ?User
    {
        return User::find($id);
    }

    public function findCompany(int $id): ?Company
    {
        return Company::find($id);
    }

    public function findContact(int $id): ?Contact
    {
        return Contact::find($id);
    }

    public function findDepartment(int $id): ?Department
    {
        return Department::find($id);
    }

    public function findFile(int $id): ?File
    {
        return File::find($id);
    }

    // ==================== EVENTS ====================

    /**
     * Register an event listener
     */
    public function on(string $eventName, callable $callback, int $priority = 0): self
    {
        $this->events->on($eventName, $callback, $priority);
        return $this;
    }

    /**
     * Dispatch an event
     */
    public function dispatch(string $eventName, array $data = []): Event
    {
        return $this->events->dispatch($eventName, new Event($eventName, $data));
    }

    // ==================== VALIDATION ====================

    /**
     * Validate data with fluent interface
     */
    public function validate(array $data): ValidationService
    {
        return $this->validationService->validate($data);
    }
}

// Global helper function
if (!function_exists('modernApi')) {
    /**
     * Get Modern API instance
     */
    function modernApi(): ModernAPI
    {
        return ModernAPI::getInstance();
    }
}
