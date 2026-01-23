<?php
/**
 * DotProject Integration Controller
 * 
 * Controller for external integrations (Google, etc).
 * 
 * @package DotProject\Api\Controller
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Api\Controller;

use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Integration\Google\GoogleAuthClient;
use DotProject\Integration\Google\GoogleCalendarSync;
use DotProject\Integration\Google\GoogleDriveSync;

/**
 * Controller de integrações externas
 */
class IntegrationController extends BaseController
{
    /**
     * GET /v1/integrations/google/auth
     * 
     * Initiates Google OAuth flow - returns authorization URL
     */
    public function googleAuth(): Response
    {
        $auth = GoogleAuthClient::fromConfig();

        // Combine calendar and drive scopes
        $scopes = array_merge(
            GoogleCalendarSync::getScopes(),
            GoogleDriveSync::getScopes(),
            ['email', 'profile']
        );

        // Generate state with user ID for security
        $userId = $this->getUserId();
        $state = base64_encode(json_encode([
            'user_id' => $userId,
            'timestamp' => time(),
        ]));

        $authUrl = $auth->getAuthorizationUrl($scopes, $state);

        return $this->json([
            'auth_url' => $authUrl,
            'message' => 'Redirect user to auth_url to authorize',
        ]);
    }

    /**
     * GET /v1/integrations/google/callback
     * 
     * OAuth callback - exchanges code for tokens
     */
    public function googleCallback(): Response
    {
        $code = $this->request->getQueryParam('code');
        $state = $this->request->getQueryParam('state');
        $error = $this->request->getQueryParam('error');

        if ($error) {
            return $this->error('Authorization denied: ' . $error, Response::HTTP_BAD_REQUEST);
        }

        if (!$code) {
            return $this->error('Authorization code not provided', Response::HTTP_BAD_REQUEST);
        }

        // Validate state
        $stateData = json_decode(base64_decode($state ?? ''), true);
        if (!$stateData || !isset($stateData['user_id'])) {
            return $this->error('Invalid state parameter', Response::HTTP_BAD_REQUEST);
        }

        $userId = (int) $stateData['user_id'];

        // Exchange code for tokens
        $auth = GoogleAuthClient::fromConfig();
        $tokens = $auth->exchangeCode($code);

        if ($tokens === null) {
            return $this->error('Failed to exchange authorization code', Response::HTTP_BAD_REQUEST);
        }

        // Save tokens
        $saved = $auth->saveIntegration(
            $userId,
            $tokens['access_token'],
            $tokens['refresh_token'] ?? null,
            $tokens['expires_in'] ?? 3600,
            $tokens['scope'] ?? ''
        );

        if (!$saved) {
            return $this->error('Failed to save integration', Response::HTTP_INTERNAL_ERROR);
        }

        return $this->json([
            'success' => true,
            'message' => 'Google integration connected successfully',
        ]);
    }

    /**
     * GET /v1/integrations/google/status
     * 
     * Check if user has active Google integration
     */
    public function googleStatus(): Response
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->response->unauthorized();
        }

        $auth = GoogleAuthClient::fromConfig();
        $integration = $auth->getIntegration($userId);

        if ($integration === null) {
            return $this->json([
                'connected' => false,
                'message' => 'No Google integration found',
            ]);
        }

        return $this->json([
            'connected' => true,
            'expires_at' => $integration['expires_at'],
            'scope' => $integration['scope'],
        ]);
    }

    /**
     * DELETE /v1/integrations/google
     * 
     * Disconnect Google integration
     */
    public function googleDisconnect(): Response
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->response->unauthorized();
        }

        $auth = GoogleAuthClient::fromConfig();
        $auth->revokeAccess($userId);

        return $this->json([
            'success' => true,
            'message' => 'Google integration disconnected',
        ]);
    }

    /**
     * GET /v1/integrations/google/calendars
     * 
     * List user's Google Calendars
     */
    public function googleCalendars(): Response
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->response->unauthorized();
        }

        $calendar = new GoogleCalendarSync();
        $calendars = $calendar->getCalendars($userId);

        if ($calendars === null) {
            return $this->error('Failed to fetch calendars. Please reconnect Google.', Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'calendars' => $calendars,
        ]);
    }

    /**
     * POST /v1/integrations/google/calendar/sync-task
     * 
     * Sync a task to Google Calendar
     */
    public function syncTaskToCalendar(): Response
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->response->unauthorized();
        }

        $taskId = $this->request->getBodyParam('task_id');
        $calendarId = $this->request->getBodyParam('calendar_id', 'primary');

        if (!$taskId) {
            return $this->response->validationError(['task_id' => 'Task ID is required']);
        }

        $calendar = new GoogleCalendarSync();
        $eventId = $calendar->createEventFromTask($userId, (int) $taskId, $calendarId);

        if ($eventId === null) {
            return $this->error('Failed to create calendar event');
        }

        return $this->json([
            'success' => true,
            'event_id' => $eventId,
            'message' => 'Task synced to calendar',
        ]);
    }

    /**
     * POST /v1/integrations/google/calendar/sync-project
     * 
     * Sync all tasks from a project to Google Calendar
     */
    public function syncProjectToCalendar(): Response
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->response->unauthorized();
        }

        $projectId = $this->request->getBodyParam('project_id');
        $calendarId = $this->request->getBodyParam('calendar_id', 'primary');

        if (!$projectId) {
            return $this->response->validationError(['project_id' => 'Project ID is required']);
        }

        $calendar = new GoogleCalendarSync();
        $stats = $calendar->syncProjectTasks($userId, (int) $projectId, $calendarId);

        return $this->json([
            'success' => true,
            'stats' => $stats,
            'message' => sprintf(
                'Synced project: %d created, %d updated, %d errors',
                $stats['created'],
                $stats['updated'],
                $stats['errors']
            ),
        ]);
    }

    /**
     * GET /v1/integrations/google/drive/files
     * 
     * List files from Google Drive
     */
    public function driveFiles(): Response
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->response->unauthorized();
        }

        $query = $this->request->getQueryParam('q', '');
        $limit = min(50, (int) $this->request->getQueryParam('limit', 20));

        $drive = new GoogleDriveSync();
        $files = $drive->listFiles($userId, $query, $limit);

        if ($files === null) {
            return $this->error('Failed to fetch files. Please reconnect Google.', Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'files' => $files,
        ]);
    }

    /**
     * POST /v1/integrations/google/drive/attach
     * 
     * Attach a Google Drive file to project or task
     */
    public function driveAttach(): Response
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->response->unauthorized();
        }

        $fileId = $this->request->getBodyParam('file_id');
        $projectId = $this->request->getBodyParam('project_id');
        $taskId = $this->request->getBodyParam('task_id');

        if (!$fileId) {
            return $this->response->validationError(['file_id' => 'File ID is required']);
        }

        if (!$projectId && !$taskId) {
            return $this->response->validationError([
                '_' => 'Either project_id or task_id is required',
            ]);
        }

        $drive = new GoogleDriveSync();

        if ($taskId) {
            $success = $drive->attachToTask($userId, (int) $taskId, $fileId);
        } else {
            $success = $drive->attachToProject($userId, (int) $projectId, $fileId);
        }

        if (!$success) {
            return $this->error('Failed to attach file');
        }

        return $this->json([
            'success' => true,
            'message' => 'File attached successfully',
        ]);
    }

    /**
     * GET /v1/integrations/google/drive/project/{id}/files
     * 
     * Get Drive files attached to a project
     */
    public function projectDriveFiles(): Response
    {
        $projectId = (int) $this->request->getParam('id');

        $drive = new GoogleDriveSync();
        $files = $drive->getProjectFiles($projectId);

        return $this->json([
            'files' => $files,
        ]);
    }

    /**
     * GET /v1/integrations/google/drive/task/{id}/files
     * 
     * Get Drive files attached to a task
     */
    public function taskDriveFiles(): Response
    {
        $taskId = (int) $this->request->getParam('id');

        $drive = new GoogleDriveSync();
        $files = $drive->getTaskFiles($taskId);

        return $this->json([
            'files' => $files,
        ]);
    }
}
