<?php
/**
 * DotProject Google Drive Sync
 * 
 * Attaches Google Drive files to projects/tasks.
 * 
 * @package DotProject\Integration\Google
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Integration\Google;

use DotProject\Core\Database;

/**
 * Google Drive Sync
 * 
 * Provides file picker integration and file reference storage.
 */
class GoogleDriveSync
{
    private const DRIVE_API_URL = 'https://www.googleapis.com/drive/v3';

    private GoogleAuthClient $auth;
    private Database $db;

    public function __construct(?GoogleAuthClient $auth = null)
    {
        $this->auth = $auth ?? GoogleAuthClient::fromConfig();
        $this->db = Database::getInstance();
    }

    /**
     * Scopes required for Drive access
     * 
     * @return array<string>
     */
    public static function getScopes(): array
    {
        return [
            'https://www.googleapis.com/auth/drive.readonly',
            'https://www.googleapis.com/auth/drive.file',
        ];
    }

    /**
     * List files from user's Drive
     * 
     * @return array<int, array<string, mixed>>|null
     */
    public function listFiles(int $userId, string $query = '', int $limit = 20): ?array
    {
        $token = $this->auth->getValidAccessToken($userId);
        if ($token === null) {
            return null;
        }

        $params = [
            'pageSize' => $limit,
            'fields' => 'files(id,name,mimeType,webViewLink,iconLink,thumbnailLink,size,modifiedTime)',
        ];

        if ($query !== '') {
            $params['q'] = sprintf("name contains '%s'", addslashes($query));
        }

        $endpoint = '/files?' . http_build_query($params);
        $response = $this->apiGet($token, $endpoint);

        if ($response === null || !isset($response['files'])) {
            return null;
        }

        return array_map(function ($file) {
            return [
                'id' => $file['id'],
                'name' => $file['name'],
                'mime_type' => $file['mimeType'],
                'web_link' => $file['webViewLink'] ?? null,
                'icon' => $file['iconLink'] ?? null,
                'thumbnail' => $file['thumbnailLink'] ?? null,
                'size' => $file['size'] ?? 0,
                'modified' => $file['modifiedTime'] ?? null,
            ];
        }, $response['files']);
    }

    /**
     * Get file metadata
     * 
     * @return array<string, mixed>|null
     */
    public function getFile(int $userId, string $fileId): ?array
    {
        $token = $this->auth->getValidAccessToken($userId);
        if ($token === null) {
            return null;
        }

        $params = [
            'fields' => 'id,name,mimeType,webViewLink,iconLink,thumbnailLink,size,modifiedTime,owners',
        ];

        $endpoint = '/files/' . $fileId . '?' . http_build_query($params);
        $response = $this->apiGet($token, $endpoint);

        if ($response === null || !isset($response['id'])) {
            return null;
        }

        return [
            'id' => $response['id'],
            'name' => $response['name'],
            'mime_type' => $response['mimeType'],
            'web_link' => $response['webViewLink'] ?? null,
            'icon' => $response['iconLink'] ?? null,
            'thumbnail' => $response['thumbnailLink'] ?? null,
            'size' => $response['size'] ?? 0,
            'modified' => $response['modifiedTime'] ?? null,
            'owners' => $response['owners'] ?? [],
        ];
    }

    /**
     * Attach a Drive file to a project
     */
    public function attachToProject(int $userId, int $projectId, string $fileId): bool
    {
        $file = $this->getFile($userId, $fileId);
        if ($file === null) {
            return false;
        }

        // Check if already attached
        $existing = $this->db->fetchOne(sprintf(
            "SELECT id FROM %s WHERE project_id = %d AND drive_file_id = %s",
            $this->db->table('google_drive_files'),
            $projectId,
            $this->db->quote($fileId)
        ));

        if ($existing !== null) {
            return true; // Already attached
        }

        $id = $this->db->insert('google_drive_files', [
            'user_id' => $userId,
            'project_id' => $projectId,
            'task_id' => null,
            'drive_file_id' => $fileId,
            'file_name' => $file['name'],
            'mime_type' => $file['mime_type'],
            'web_link' => $file['web_link'],
            'icon_link' => $file['icon'] ?? '',
            'file_size' => (int) $file['size'],
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $id !== false;
    }

    /**
     * Attach a Drive file to a task
     */
    public function attachToTask(int $userId, int $taskId, string $fileId): bool
    {
        $file = $this->getFile($userId, $fileId);
        if ($file === null) {
            return false;
        }

        // Get project from task
        $task = $this->db->fetchOne(sprintf(
            "SELECT task_project FROM %s WHERE task_id = %d",
            $this->db->table('tasks'),
            $taskId
        ));

        $projectId = $task ? (int) $task['task_project'] : 0;

        $id = $this->db->insert('google_drive_files', [
            'user_id' => $userId,
            'project_id' => $projectId,
            'task_id' => $taskId,
            'drive_file_id' => $fileId,
            'file_name' => $file['name'],
            'mime_type' => $file['mime_type'],
            'web_link' => $file['web_link'],
            'icon_link' => $file['icon'] ?? '',
            'file_size' => (int) $file['size'],
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $id !== false;
    }

    /**
     * Get attached files for a project
     * 
     * @return array<int, array<string, mixed>>
     */
    public function getProjectFiles(int $projectId): array
    {
        return $this->db->fetchAll(sprintf(
            "SELECT * FROM %s WHERE project_id = %d ORDER BY created_at DESC",
            $this->db->table('google_drive_files'),
            $projectId
        ));
    }

    /**
     * Get attached files for a task
     * 
     * @return array<int, array<string, mixed>>
     */
    public function getTaskFiles(int $taskId): array
    {
        return $this->db->fetchAll(sprintf(
            "SELECT * FROM %s WHERE task_id = %d ORDER BY created_at DESC",
            $this->db->table('google_drive_files'),
            $taskId
        ));
    }

    /**
     * Remove file attachment
     */
    public function detachFile(int $attachmentId): bool
    {
        return $this->db->delete(
            'google_drive_files',
            sprintf('id = %d', $attachmentId)
        );
    }

    /**
     * API GET request
     */
    private function apiGet(string $token, string $endpoint): ?array
    {
        $url = self::DRIVE_API_URL . $endpoint;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return $response ? json_decode($response, true) : null;
    }
}
