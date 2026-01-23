<?php
/**
 * DotProject Google Calendar Sync
 * 
 * Synchronizes tasks/events with Google Calendar.
 * 
 * @package DotProject\Integration\Google
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Integration\Google;

use DotProject\Core\Database;

/**
 * Google Calendar Sync
 * 
 * Creates and syncs events in Google Calendar based on task dates.
 */
class GoogleCalendarSync
{
    private const CALENDAR_API_URL = 'https://www.googleapis.com/calendar/v3';

    private GoogleAuthClient $auth;
    private Database $db;

    public function __construct(?GoogleAuthClient $auth = null)
    {
        $this->auth = $auth ?? GoogleAuthClient::fromConfig();
        $this->db = Database::getInstance();
    }

    /**
     * Scopes required for Calendar access
     * 
     * @return array<string>
     */
    public static function getScopes(): array
    {
        return [
            'https://www.googleapis.com/auth/calendar',
            'https://www.googleapis.com/auth/calendar.events',
        ];
    }

    /**
     * Get list of user's calendars
     * 
     * @return array<int, array<string, mixed>>|null
     */
    public function getCalendars(int $userId): ?array
    {
        $token = $this->auth->getValidAccessToken($userId);
        if ($token === null) {
            return null;
        }

        $response = $this->apiGet($token, '/users/me/calendarList');

        if ($response === null || !isset($response['items'])) {
            return null;
        }

        return array_map(function ($cal) {
            return [
                'id' => $cal['id'],
                'summary' => $cal['summary'],
                'primary' => $cal['primary'] ?? false,
                'backgroundColor' => $cal['backgroundColor'] ?? null,
            ];
        }, $response['items']);
    }

    /**
     * Create an event from a task
     * 
     * @return string|null Event ID or null on failure
     */
    public function createEventFromTask(
        int $userId,
        int $taskId,
        ?string $calendarId = 'primary'
    ): ?string {
        $token = $this->auth->getValidAccessToken($userId);
        if ($token === null) {
            return null;
        }

        // Get task data
        $task = $this->db->fetchOne(sprintf(
            "SELECT t.*, p.project_name 
             FROM %s t 
             LEFT JOIN %s p ON t.task_project = p.project_id
             WHERE t.task_id = %d",
            $this->db->table('tasks'),
            $this->db->table('projects'),
            $taskId
        ));

        if ($task === null) {
            return null;
        }

        // Build event data
        $event = [
            'summary' => $task['task_name'],
            'description' => sprintf(
                "Project: %s\n\n%s",
                $task['project_name'] ?? 'N/A',
                $task['task_description'] ?? ''
            ),
            'start' => [
                'date' => $task['task_start_date'],
            ],
            'end' => [
                'date' => $task['task_end_date'] ?: $task['task_start_date'],
            ],
            'extendedProperties' => [
                'private' => [
                    'dotproject_task_id' => (string) $taskId,
                    'dotproject_project_id' => (string) $task['task_project'],
                ],
            ],
        ];

        // If task has specific times, use dateTime instead of date
        if (strpos($task['task_start_date'], ':') !== false) {
            $event['start'] = ['dateTime' => $task['task_start_date'], 'timeZone' => 'America/Sao_Paulo'];
            $event['end'] = ['dateTime' => $task['task_end_date'], 'timeZone' => 'America/Sao_Paulo'];
        }

        $response = $this->apiPost($token, "/calendars/{$calendarId}/events", $event);

        if ($response === null || !isset($response['id'])) {
            return null;
        }

        // Store event mapping
        $this->saveEventMapping($userId, $taskId, $response['id'], $calendarId);

        return $response['id'];
    }

    /**
     * Update an existing event
     */
    public function updateEventFromTask(
        int $userId,
        int $taskId
    ): bool {
        $token = $this->auth->getValidAccessToken($userId);
        if ($token === null) {
            return false;
        }

        // Get event mapping
        $mapping = $this->getEventMapping($userId, $taskId);
        if ($mapping === null) {
            return false;
        }

        // Get task data
        $task = $this->db->fetchOne(sprintf(
            "SELECT t.*, p.project_name 
             FROM %s t 
             LEFT JOIN %s p ON t.task_project = p.project_id
             WHERE t.task_id = %d",
            $this->db->table('tasks'),
            $this->db->table('projects'),
            $taskId
        ));

        if ($task === null) {
            return false;
        }

        $event = [
            'summary' => $task['task_name'],
            'description' => sprintf(
                "Project: %s\nProgress: %d%%\n\n%s",
                $task['project_name'] ?? 'N/A',
                (int) $task['task_percent_complete'],
                $task['task_description'] ?? ''
            ),
            'start' => ['date' => $task['task_start_date']],
            'end' => ['date' => $task['task_end_date'] ?: $task['task_start_date']],
        ];

        $calendarId = $mapping['calendar_id'];
        $eventId = $mapping['event_id'];

        $response = $this->apiPut(
            $token,
            "/calendars/{$calendarId}/events/{$eventId}",
            $event
        );

        return $response !== null && isset($response['id']);
    }

    /**
     * Delete an event
     */
    public function deleteEvent(int $userId, int $taskId): bool
    {
        $token = $this->auth->getValidAccessToken($userId);
        if ($token === null) {
            return false;
        }

        $mapping = $this->getEventMapping($userId, $taskId);
        if ($mapping === null) {
            return true; // No event to delete
        }

        $calendarId = $mapping['calendar_id'];
        $eventId = $mapping['event_id'];

        $success = $this->apiDelete($token, "/calendars/{$calendarId}/events/{$eventId}");

        if ($success) {
            $this->deleteEventMapping($userId, $taskId);
        }

        return $success;
    }

    /**
     * Sync all tasks for a project to calendar
     * 
     * @return array<string, int> Stats about synced events
     */
    public function syncProjectTasks(int $userId, int $projectId, string $calendarId = 'primary'): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'errors' => 0];

        $tasks = $this->db->fetchAll(sprintf(
            "SELECT task_id FROM %s WHERE task_project = %d",
            $this->db->table('tasks'),
            $projectId
        ));

        foreach ($tasks as $task) {
            $taskId = (int) $task['task_id'];
            $mapping = $this->getEventMapping($userId, $taskId);

            if ($mapping !== null) {
                // Update existing
                if ($this->updateEventFromTask($userId, $taskId)) {
                    $stats['updated']++;
                } else {
                    $stats['errors']++;
                }
            } else {
                // Create new
                if ($this->createEventFromTask($userId, $taskId, $calendarId) !== null) {
                    $stats['created']++;
                } else {
                    $stats['errors']++;
                }
            }
        }

        return $stats;
    }

    /**
     * Save task-event mapping
     */
    private function saveEventMapping(int $userId, int $taskId, string $eventId, string $calendarId): void
    {
        // This would ideally use a dedicated table, using extended properties instead
        $this->db->query(sprintf(
            "INSERT INTO %s (user_id, task_id, event_id, calendar_id, created_at) 
             VALUES (%d, %d, %s, %s, NOW())
             ON DUPLICATE KEY UPDATE event_id = VALUES(event_id), calendar_id = VALUES(calendar_id)",
            $this->db->table('google_calendar_events'),
            $userId,
            $taskId,
            $this->db->quote($eventId),
            $this->db->quote($calendarId)
        ));
    }

    /**
     * Get task-event mapping
     * 
     * @return array<string, mixed>|null
     */
    private function getEventMapping(int $userId, int $taskId): ?array
    {
        return $this->db->fetchOne(sprintf(
            "SELECT * FROM %s WHERE user_id = %d AND task_id = %d",
            $this->db->table('google_calendar_events'),
            $userId,
            $taskId
        ));
    }

    /**
     * Delete task-event mapping
     */
    private function deleteEventMapping(int $userId, int $taskId): void
    {
        $this->db->delete(
            'google_calendar_events',
            sprintf("user_id = %d AND task_id = %d", $userId, $taskId)
        );
    }

    /**
     * API GET request
     */
    private function apiGet(string $token, string $endpoint): ?array
    {
        $url = self::CALENDAR_API_URL . $endpoint;

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

    /**
     * API POST request
     */
    private function apiPost(string $token, string $endpoint, array $data): ?array
    {
        $url = self::CALENDAR_API_URL . $endpoint;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return $response ? json_decode($response, true) : null;
    }

    /**
     * API PUT request
     */
    private function apiPut(string $token, string $endpoint, array $data): ?array
    {
        $url = self::CALENDAR_API_URL . $endpoint;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return $response ? json_decode($response, true) : null;
    }

    /**
     * API DELETE request
     */
    private function apiDelete(string $token, string $endpoint): bool
    {
        $url = self::CALENDAR_API_URL . $endpoint;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
            ],
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode >= 200 && $httpCode < 300;
    }
}
