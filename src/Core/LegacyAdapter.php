<?php
/**
 * Legacy Adapter
 * 
 * Adapter para facilitar a migração de código legado para o novo padrão.
 * Permite usar repositories modernos com código legado existente.
 * 
 * @package DotProject\Core
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Core;

use DotProject\Repository\ProjectRepository;
use DotProject\Repository\TaskRepository;
use DotProject\Repository\UserRepository;
use DotProject\Repository\CalendarEventRepository;
use DotProject\Repository\FileRepository;
use DotProject\Repository\ModuleRepository;
use DotProject\Repository\ConfigRepository;
use DotProject\Repository\SysValRepository;
use DotProject\Entity\ProjectEntity;
use DotProject\Entity\TaskEntity;
use DotProject\Entity\UserEntity;
use DotProject\Entity\CalendarEventEntity;
use DotProject\Entity\FileEntity;
use DotProject\Entity\ModuleEntity;
use DotProject\Entity\ConfigEntity;
use DotProject\Entity\SysValEntity;

/**
 * Adapter para migração gradual do código legado
 */
class LegacyAdapter
{
    private ProjectRepository $projectRepository;
    private TaskRepository $taskRepository;
    private UserRepository $userRepository;
    private CalendarEventRepository $calendarRepository;
    private FileRepository $fileRepository;
    private ModuleRepository $moduleRepository;
    private ConfigRepository $configRepository;
    private SysValRepository $sysValRepository;
    private FeatureFlag $features;

    public function __construct()
    {
        $this->projectRepository = new ProjectRepository();
        $this->taskRepository = new TaskRepository();
        $this->userRepository = new UserRepository();
        $this->calendarRepository = new CalendarEventRepository();
        $this->fileRepository = new FileRepository();
        $this->moduleRepository = new ModuleRepository();
        $this->configRepository = new ConfigRepository();
        $this->sysValRepository = new SysValRepository();
        $this->features = FeatureFlag::getInstance();
    }

    /**
     * Busca projeto compatível com código legado
     * 
     * Retorna array no formato antigo para compatibilidade
     * 
     * @return array<string, mixed>|null
     */
    public function getProjectLegacy(int $projectId, ?int $userId = null): ?array
    {
        // Verificar se deve usar novo repository
        if ($this->features->isEnabled('modern_api_only', $userId)) {
            $entity = $this->projectRepository->find($projectId);
            
            if ($entity === null) {
                return null;
            }
            
            return $this->entityToLegacy($entity);
        }

        // Fallback para query legada
        $db = Database::getInstance();
        return $db->fetchOne(
            "SELECT * FROM dotp_projects WHERE project_id = ?",
            [$projectId]
        );
    }

    /**
     * Lista projetos compatível com código legado
     * 
     * @return array<array<string, mixed>>
     */
    public function getProjectsLegacy(array $filters = [], ?int $userId = null): array
    {
        if ($this->features->isEnabled('modern_api_only', $userId)) {
            $criteria = [];
            
            if (isset($filters['project_status'])) {
                $criteria['project_status'] = $filters['project_status'];
            }
            
            if (isset($filters['project_owner'])) {
                $criteria['project_owner'] = $filters['project_owner'];
            }
            
            $entities = $this->projectRepository->findBy($criteria);
            
            return array_map([$this, 'entityToLegacy'], $entities);
        }

        // Query legada
        $db = Database::getInstance();
        $where = ['1=1'];
        $params = [];
        
        foreach ($filters as $key => $value) {
            $where[] = "{$key} = ?";
            $params[] = $value;
        }
        
        $sql = "SELECT * FROM dotp_projects WHERE " . implode(' AND ', $where);
        return $db->fetchAll($sql, $params);
    }

    /**
     * Cria projeto usando novo sistema ou legado
     */
    public function createProjectLegacy(array $data, ?int $userId = null): bool
    {
        if ($this->features->isEnabled('modern_api_only', $userId)) {
            $entity = $this->legacyToEntity($data);
            return $this->projectRepository->save($entity);
        }

        // Insert legado
        $db = Database::getInstance();
        return $db->insert('dotp_projects', $data);
    }

    /**
     * Atualiza projeto usando novo sistema ou legado
     */
    public function updateProjectLegacy(int $projectId, array $data, ?int $userId = null): bool
    {
        if ($this->features->isEnabled('modern_api_only', $userId)) {
            $entity = $this->projectRepository->find($projectId);
            
            if ($entity === null) {
                return false;
            }
            
            // Atualizar campos
            if (isset($data['project_name'])) {
                $entity->setName($data['project_name']);
            }
            if (isset($data['project_status'])) {
                $entity->setStatus((int) $data['project_status']);
            }
            if (isset($data['project_percent_complete'])) {
                $entity->setPercentComplete((int) $data['project_percent_complete']);
            }
            
            return $this->projectRepository->save($entity);
        }

        // Update legado
        $db = Database::getInstance();
        return $db->update('dotp_projects', $data, "project_id = {$projectId}");
    }

    /**
     * Deleta projeto
     */
    public function deleteProjectLegacy(int $projectId, ?int $userId = null): bool
    {
        if ($this->features->isEnabled('modern_api_only', $userId)) {
            return $this->projectRepository->delete($projectId);
        }

        $db = Database::getInstance();
        return $db->delete('dotp_projects', "project_id = {$projectId}");
    }

    /**
     * Converte entidade moderna para formato legado
     * 
     * @return array<string, mixed>
     */
    private function entityToLegacy(ProjectEntity $entity): array
    {
        return [
            'project_id' => $entity->getId(),
            'project_name' => $entity->getName(),
            'project_short_name' => $entity->getShortName(),
            'project_description' => $entity->getDescription(),
            'project_start_date' => $entity->getStartDate()?->format('Y-m-d'),
            'project_end_date' => $entity->getEndDate()?->format('Y-m-d'),
            'project_actual_end_date' => $entity->getActualEndDate()?->format('Y-m-d'),
            'project_status' => $entity->getStatus(),
            'project_priority' => $entity->getPriority(),
            'project_percent_complete' => $entity->getPercentComplete(),
            'project_owner' => $entity->getOwnerId(),
            'project_company' => $entity->getCompanyId(),
            'project_color_identifier' => $entity->getColorIdentifier(),
            'project_url' => $entity->getUrl(),
            // Campos virtuais
            'is_active' => $entity->isActive(),
            'is_overdue' => $entity->isOverdue(),
            'days_remaining' => $entity->getDaysRemaining(),
        ];
    }

    /**
     * Converte dados legados para entidade moderna
     */
    private function legacyToEntity(array $data): ProjectEntity
    {
        $entity = new ProjectEntity();
        
        if (isset($data['project_id'])) {
            $entity->setId((int) $data['project_id']);
        }
        
        $entity->setName($data['project_name']);
        $entity->setShortName($data['project_short_name'] ?? null);
        $entity->setDescription($data['project_description'] ?? null);
        $entity->setStatus((int) ($data['project_status'] ?? 0));
        $entity->setPriority((int) ($data['project_priority'] ?? 3));
        $entity->setPercentComplete((int) ($data['project_percent_complete'] ?? 0));
        $entity->setOwnerId($data['project_owner'] ? (int) $data['project_owner'] : null);
        $entity->setCompanyId($data['project_company'] ? (int) $data['project_company'] : null);
        $entity->setColorIdentifier($data['project_color_identifier'] ?? null);
        $entity->setUrl($data['project_url'] ?? null);
        
        return $entity;
    }

    // ==========================================
    // TASKS
    // ==========================================

    /**
     * Busca tarefa compatível com código legado
     * @return array<string, mixed>|null
     */
    public function getTaskLegacy(int $taskId, ?int $userId = null): ?array
    {
        if ($this->features->isEnabled('modern_tasks', $userId)) {
            $entity = $this->taskRepository->find($taskId);
            return $entity ? $this->taskEntityToLegacy($entity) : null;
        }

        $db = Database::getInstance();
        return $db->fetchOne("SELECT * FROM dotp_tasks WHERE task_id = ?", [$taskId]);
    }

    /**
     * Lista tarefas compatível com código legado
     * @return array<array<string, mixed>>
     */
    public function getTasksLegacy(array $filters = [], ?int $userId = null): array
    {
        if ($this->features->isEnabled('modern_tasks', $userId)) {
            $entities = $this->taskRepository->findBy($filters);
            return array_map([$this, 'taskEntityToLegacy'], $entities);
        }

        $db = Database::getInstance();
        $where = ['1=1'];
        $params = [];
        
        foreach ($filters as $key => $value) {
            $where[] = "$key = ?";
            $params[] = $value;
        }
        
        $sql = "SELECT * FROM dotp_tasks WHERE " . implode(' AND ', $where);
        return $db->fetchAll($sql, $params);
    }

    /**
     * Converte TaskEntity para formato legado
     * @return array<string, mixed>
     */
    private function taskEntityToLegacy(TaskEntity $entity): array
    {
        return [
            'task_id' => $entity->getId(),
            'task_name' => $entity->getName(),
            'task_description' => $entity->getDescription(),
            'task_project' => $entity->getProjectId(),
            'task_parent' => $entity->getParentTaskId(),
            'task_assigned_to' => $entity->getAssignedTo(),
            'task_owner' => $entity->getOwnerId(),
            'task_status' => $entity->getStatus(),
            'task_priority' => $entity->getPriority(),
            'task_percent_complete' => $entity->getPercentComplete(),
            'task_hours' => $entity->getEstimatedHours(),
            'task_actual_hours' => $entity->getActualHours(),
            'task_start_date' => $entity->getStartDate()?->format('Y-m-d'),
            'task_end_date' => $entity->getEndDate()?->format('Y-m-d'),
            'is_active' => $entity->isActive(),
            'is_completed' => $entity->isCompleted(),
            'is_overdue' => $entity->isOverdue(),
            'days_remaining' => $entity->getDaysRemaining(),
        ];
    }

    // ==========================================
    // USERS
    // ==========================================

    /**
     * Busca usuário compatível com código legado
     * @return array<string, mixed>|null
     */
    public function getUserLegacy(int $userId, ?int $currentUserId = null): ?array
    {
        if ($this->features->isEnabled('modern_users', $currentUserId)) {
            $entity = $this->userRepository->find($userId);
            return $entity ? $this->userEntityToLegacy($entity) : null;
        }

        $db = Database::getInstance();
        return $db->fetchOne("SELECT u.*, c.contact_first_name, c.contact_last_name, c.contact_email 
                              FROM dotp_users u 
                              LEFT JOIN dotp_contacts c ON c.contact_id = u.user_contact 
                              WHERE u.user_id = ?", [$userId]);
    }

    /**
     * Busca usuário por username
     * @return array<string, mixed>|null
     */
    public function getUserByUsernameLegacy(string $username, ?int $currentUserId = null): ?array
    {
        if ($this->features->isEnabled('modern_users', $currentUserId)) {
            $entity = $this->userRepository->findByUsername($username);
            return $entity ? $this->userEntityToLegacy($entity) : null;
        }

        $db = Database::getInstance();
        return $db->fetchOne("SELECT u.*, c.contact_first_name, c.contact_last_name, c.contact_email 
                              FROM dotp_users u 
                              LEFT JOIN dotp_contacts c ON c.contact_id = u.user_contact 
                              WHERE u.user_username = ?", [$username]);
    }

    /**
     * Converte UserEntity para formato legado
     * @return array<string, mixed>
     */
    private function userEntityToLegacy(UserEntity $entity): array
    {
        return [
            'user_id' => $entity->getId(),
            'user_username' => $entity->getUsername(),
            'user_contact' => $entity->getContactId(),
            'user_company' => $entity->getCompanyId(),
            'user_department' => $entity->getDepartmentId(),
            'user_status' => $entity->getStatus(),
            'contact_first_name' => $entity->getFirstName(),
            'contact_last_name' => $entity->getLastName(),
            'contact_email' => $entity->getEmail(),
            'full_name' => $entity->getFullName(),
            'is_active' => $entity->isActive(),
        ];
    }

    // ==========================================
    // CALENDAR
    // ==========================================

    /**
     * Busca eventos do calendário compatível com código legado
     * @return array<array<string, mixed>>
     */
    public function getCalendarEventsLegacy(int $userId, string $start, string $end, ?int $currentUserId = null): array
    {
        if ($this->features->isEnabled('modern_calendar', $currentUserId)) {
            $entities = $this->calendarRepository->findByUserAndPeriod(
                $userId, 
                new \DateTime($start), 
                new \DateTime($end)
            );
            return array_map([$this, 'calendarEntityToLegacy'], $entities);
        }

        $db = Database::getInstance();
        return $db->fetchAll(
            "SELECT * FROM dotp_events 
             WHERE event_owner = ? 
             AND event_start_date BETWEEN ? AND ?
             AND event_status = 0
             ORDER BY event_start_date",
            [$userId, $start, $end]
        );
    }

    /**
     * Converte CalendarEventEntity para formato legado
     * @return array<string, mixed>
     */
    private function calendarEntityToLegacy(CalendarEventEntity $entity): array
    {
        return [
            'event_id' => $entity->getId(),
            'event_title' => $entity->getTitle(),
            'event_description' => $entity->getDescription(),
            'event_owner' => $entity->getUserId(),
            'event_project' => $entity->getProjectId(),
            'event_task' => $entity->getTaskId(),
            'event_start_date' => $entity->getStartDate()->format('Y-m-d H:i:s'),
            'event_end_date' => $entity->getEndDate()?->format('Y-m-d H:i:s'),
            'event_all_day' => $entity->isAllDay(),
            'event_color' => $entity->getColor(),
            'event_type' => $entity->getType(),
            'event_status' => $entity->getStatus(),
        ];
    }

    // ==========================================
    // FILES
    // ==========================================

    /**
     * Busca arquivo compatível com código legado
     * @return array<string, mixed>|null
     */
    public function getFileLegacy(int $fileId, ?int $userId = null): ?array
    {
        if ($this->features->isEnabled('modern_files', $userId)) {
            $entity = $this->fileRepository->findById($fileId);
            return $entity ? $this->fileEntityToLegacy($entity) : null;
        }

        $db = Database::getInstance();
        return $db->fetchOne("SELECT * FROM dotp_files WHERE file_id = ?", [$fileId]);
    }

    /**
     * Lista arquivos compatível com código legado
     * @return array<array<string, mixed>>
     */
    public function getFilesLegacy(array $filters = [], ?int $userId = null): array
    {
        if ($this->features->isEnabled('modern_files', $userId)) {
            if (isset($filters['file_project'])) {
                $entities = $this->fileRepository->findByProject(
                    $filters['file_project'],
                    $filters['file_folder'] ?? null
                );
            } elseif (isset($filters['file_task'])) {
                $entities = $this->fileRepository->findByTask($filters['file_task']);
            } else {
                $entities = $this->fileRepository->findBy($filters);
            }
            return array_map([$this, 'fileEntityToLegacy'], $entities);
        }

        $db = Database::getInstance();
        $where = ['1=1'];
        $params = [];
        
        foreach ($filters as $key => $value) {
            $where[] = "$key = ?";
            $params[] = $value;
        }
        
        $sql = "SELECT * FROM dotp_files WHERE " . implode(' AND ', $where) . " ORDER BY file_date DESC";
        return $db->fetchAll($sql, $params);
    }

    /**
     * Converte FileEntity para formato legado
     * @return array<string, mixed>
     */
    private function fileEntityToLegacy(FileEntity $entity): array
    {
        return [
            'file_id' => $entity->getId(),
            'file_name' => $entity->getName(),
            'file_real_filename' => $entity->getRealFilename(),
            'file_description' => $entity->getDescription(),
            'file_project' => $entity->getProjectId(),
            'file_task' => $entity->getTaskId(),
            'file_folder' => $entity->getFolderId(),
            'file_owner' => $entity->getOwnerId(),
            'file_date' => $entity->getDate()?->format('Y-m-d H:i:s'),
            'file_size' => $entity->getSize(),
            'file_size_formatted' => $entity->getFormattedSize(),
            'file_type' => $entity->getType(),
            'file_version' => $entity->getVersion(),
            'file_checkout' => $entity->getCheckout(),
            'file_co_reason' => $entity->getCheckoutReason(),
            'is_checked_out' => $entity->isCheckedOut(),
            'is_image' => $entity->isImage(),
        ];
    }

    // ==========================================
    // ADMIN
    // ==========================================

    /**
     * Busca módulos compatível com código legado
     * @return array<array<string, mixed>>
     */
    public function getModulesLegacy(?int $userId = null): array
    {
        if ($this->features->isEnabled('modern_admin', $userId)) {
            $entities = $this->moduleRepository->findMenuModules();
            return array_map([$this, 'moduleEntityToLegacy'], $entities);
        }

        $db = Database::Instance();
        return $db->fetchAll(
            "SELECT * FROM dotp_modules WHERE mod_active = 1 AND mod_ui_active = 1 ORDER BY mod_ui_order"
        );
    }

    /**
     * Busca configuração compatível com código legado
     * @return array<string, mixed>|null
     */
    public function getConfigLegacy(string $name, ?int $userId = null): ?array
    {
        if ($this->features->isEnabled('modern_admin', $userId)) {
            $entity = $this->configRepository->findByName($name);
            return $entity ? $this->configEntityToLegacy($entity) : null;
        }

        $db = Database::getInstance();
        return $db->fetchOne("SELECT * FROM dotp_config WHERE config_name = ?", [$name]);
    }

    /**
     * Busca sysval compatível com código legado
     * @return array<string, mixed>|null
     */
    public function getSysValLegacy(string $title, ?int $userId = null): ?array
    {
        if ($this->features->isEnabled('modern_admin', $userId)) {
            $entity = $this->sysValRepository->findByTitle($title);
            return $entity ? $this->sysValEntityToLegacy($entity) : null;
        }

        $db = Database::getInstance();
        return $db->fetchOne("SELECT * FROM dotp_sysvals WHERE sysval_title = ?", [$title]);
    }

    /**
     * Converte ModuleEntity para formato legado
     * @return array<string, mixed>
     */
    private function moduleEntityToLegacy(ModuleEntity $entity): array
    {
        return [
            'mod_id' => $entity->getId(),
            'mod_name' => $entity->getName(),
            'mod_directory' => $entity->getDirectory(),
            'mod_version' => $entity->getVersion(),
            'mod_type' => $entity->getType(),
            'mod_active' => $entity->isActive(),
            'mod_ui_name' => $entity->getUiName(),
            'mod_ui_icon' => $entity->getUiIcon(),
            'mod_ui_order' => $entity->getUiOrder(),
            'mod_ui_active' => $entity->isUiActive(),
            'mod_description' => $entity->getDescription(),
            'is_menu_visible' => $entity->isMenuVisible(),
        ];
    }

    /**
     * Converte ConfigEntity para formato legado
     * @return array<string, mixed>
     */
    private function configEntityToLegacy(ConfigEntity $entity): array
    {
        return [
            'config_id' => $entity->getId(),
            'config_name' => $entity->getName(),
            'config_value' => $entity->getValue(),
            'config_group' => $entity->getGroup(),
            'config_type' => $entity->getType(),
            'typed_value' => $entity->getTypedValue(),
        ];
    }

    /**
     * Converte SysValEntity para formato legado
     * @return array<string, mixed>
     */
    private function sysValEntityToLegacy(SysValEntity $entity): array
    {
        return [
            'sysval_id' => $entity->getId(),
            'sysval_key_id' => $entity->getKeyId(),
            'sysval_title' => $entity->getTitle(),
            'sysval_value' => $entity->getValue(),
            'parsed_values' => $entity->getParsedValues(),
        ];
    }

    /**
     * Verifica se um módulo está migrado
     */
    public static function isMigrated(string $module): bool
    {
        $migrated = [
            'projects' => true,
            'tasks' => true,
            'users' => true,
            'calendar' => true,
            'files' => true,
            'admin' => true,
        ];
        
        return $migrated[$module] ?? false;
    }

    /**
     * Retorna lista de módulos e status de migração
     * 
     * @return array<string, array>
     */
    public static function getMigrationStatus(): array
    {
        return [
            'projects' => [
                'migrated' => true,
                'modern_controller' => 'ProjectController',
                'legacy_module' => 'projects',
                'repository' => ProjectRepository::class,
                'entity' => ProjectEntity::class,
            ],
            'tasks' => [
                'migrated' => true,
                'modern_controller' => 'TaskController',
                'legacy_module' => 'tasks',
                'repository' => TaskRepository::class,
                'entity' => TaskEntity::class,
            ],
            'users' => [
                'migrated' => true,
                'modern_controller' => 'AuthController',
                'legacy_module' => 'admin',
                'repository' => UserRepository::class,
                'entity' => UserEntity::class,
            ],
            'calendar' => [
                'migrated' => true,
                'modern_controller' => 'CalendarController',
                'legacy_module' => 'calendar',
                'repository' => CalendarEventRepository::class,
                'entity' => CalendarEventEntity::class,
            ],
            'files' => [
                'migrated' => true,
                'modern_controller' => 'FileController',
                'legacy_module' => 'files',
                'repository' => FileRepository::class,
                'entity' => FileEntity::class,
            ],
            'admin' => [
                'migrated' => true,
                'modern_controller' => 'Admin/* controllers',
                'legacy_module' => 'admin',
                'repository' => [ModuleRepository::class, ConfigRepository::class, SysValRepository::class],
                'entity' => [ModuleEntity::class, ConfigEntity::class, SysValEntity::class],
            ],
        ];
    }
}
