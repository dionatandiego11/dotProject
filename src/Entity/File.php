<?php
/**
 * DotProject File Entity
 * 
 * Modern entity class for File management.
 * 
 * @package DotProject\Entity
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Entity;

use DotProject\Core\Database;

/**
 * File Entity
 * 
 * @property string|null $file_real_filename
 * @property string|null $file_name
 * @property int|null $file_project
 * @property int|null $file_task
 * @property string|null $file_description
 * @property int|null $file_owner
 * @property string|null $file_date
 * @property int $file_size
 * @property string|null $file_type
 * @property int|null $file_version
 * @property int|null $file_folder
 * @property int|null $file_checkout
 * @property string|null $file_co_reason
 */
class File extends BaseEntity
{
    public static function getTable(): string
    {
        return 'files';
    }

    public static function getPrimaryKey(): string
    {
        return 'file_id';
    }

    protected static function getFillable(): array
    {
        return [
            'file_real_filename',
            'file_project',
            'file_task',
            'file_name',
            'file_parent',
            'file_description',
            'file_type',
            'file_owner',
            'file_date',
            'file_size',
            'file_version',
            'file_icon',
            'file_category',
            'file_folder',
            'file_checkout',
            'file_co_reason',
        ];
    }

    /**
     * Get file name
     */
    public function getName(): ?string
    {
        return $this->getAttribute('file_name');
    }

    /**
     * Set file name
     */
    public function setName(string $name): static
    {
        return $this->setAttribute('file_name', $name);
    }

    /**
     * Get real filename on disk
     */
    public function getRealFilename(): ?string
    {
        return $this->getAttribute('file_real_filename');
    }

    /**
     * Get file size in bytes
     */
    public function getSize(): int
    {
        return (int) ($this->getAttribute('file_size') ?? 0);
    }

    /**
     * Get human readable file size
     */
    public function getHumanSize(): string
    {
        $bytes = $this->getSize();
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get MIME type
     */
    public function getMimeType(): ?string
    {
        return $this->getAttribute('file_type');
    }

    /**
     * Get project ID
     */
    public function getProjectId(): ?int
    {
        $project = $this->getAttribute('file_project');
        return $project !== null ? (int) $project : null;
    }

    /**
     * Get task ID
     */
    public function getTaskId(): ?int
    {
        $task = $this->getAttribute('file_task');
        return $task !== null ? (int) $task : null;
    }

    /**
     * Get version number
     */
    public function getVersion(): int
    {
        return (int) ($this->getAttribute('file_version') ?? 1);
    }

    /**
     * Check if file is checked out
     */
    public function isCheckedOut(): bool
    {
        return !empty($this->getAttribute('file_checkout'));
    }

    /**
     * Get file extension
     */
    public function getExtension(): string
    {
        $name = $this->getName() ?? '';
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        return strtolower($ext);
    }

    /**
     * Check if file is an image
     */
    public function isImage(): bool
    {
        $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];
        return in_array($this->getExtension(), $imageExts, true);
    }

    /**
     * Check if file is a document
     */
    public function isDocument(): bool
    {
        $docExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv'];
        return in_array($this->getExtension(), $docExts, true);
    }

    /**
     * Find files by project
     * 
     * @param int $projectId
     * @return array<int, static>
     */
    public static function findByProject(int $projectId): array
    {
        return static::findAll(
            sprintf('file_project = %d', $projectId),
            'file_name ASC'
        );
    }

    /**
     * Find files by task
     * 
     * @param int $taskId
     * @return array<int, static>
     */
    public static function findByTask(int $taskId): array
    {
        return static::findAll(
            sprintf('file_task = %d', $taskId),
            'file_name ASC'
        );
    }

    /**
     * Find recent files
     * 
     * @param int $limit
     * @return array<int, static>
     */
    public static function findRecent(int $limit = 10): array
    {
        $db = Database::getInstance();

        $sql = sprintf(
            "SELECT * FROM `%s` ORDER BY file_date DESC LIMIT %d",
            $db->table('files'),
            $limit
        );

        $rows = $db->fetchAll($sql);
        return array_map(fn($row) => static::fromArray($row), $rows);
    }

    /**
     * Get total size of files in a project
     */
    public static function getTotalSizeByProject(int $projectId): int
    {
        $db = Database::getInstance();

        $sql = sprintf(
            "SELECT SUM(file_size) FROM `%s` WHERE file_project = %d",
            $db->table('files'),
            $projectId
        );

        return (int) ($db->fetchValue($sql) ?? 0);
    }
}
