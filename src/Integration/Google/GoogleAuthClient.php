<?php
/**
 * DotProject Google Auth Client
 * 
 * OAuth2 client for Google API authentication.
 * 
 * @package DotProject\Integration\Google
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Integration\Google;

use DotProject\Core\Database;

/**
 * Google OAuth2 Client
 * 
 * Handles OAuth2 flow for Google APIs.
 * Requires Google Client ID and Secret in configuration.
 */
class GoogleAuthClient
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const USERINFO_URL = 'https://www.googleapis.com/oauth2/v2/userinfo';

    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private Database $db;

    public function __construct(
        string $clientId,
        string $clientSecret,
        string $redirectUri
    ) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->redirectUri = $redirectUri;
        $this->db = Database::getInstance();
    }

    /**
     * Create instance from configuration
     */
    public static function fromConfig(): self
    {
        global $dPconfig;

        return new self(
            $dPconfig['google_client_id'] ?? '',
            $dPconfig['google_client_secret'] ?? '',
            $dPconfig['google_redirect_uri'] ?? (DP_BASE_URL . '/api.php/v1/integrations/google/callback')
        );
    }

    /**
     * Generate OAuth2 authorization URL
     * 
     * @param array<string> $scopes OAuth scopes to request
     */
    public function getAuthorizationUrl(array $scopes, string $state = ''): string
    {
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'access_type' => 'offline', // Request refresh token
            'prompt' => 'consent',
        ];

        if ($state !== '') {
            $params['state'] = $state;
        }

        return self::AUTH_URL . '?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for tokens
     * 
     * @return array<string, mixed>|null Token data or null on failure
     */
    public function exchangeCode(string $code): ?array
    {
        $data = [
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code',
        ];

        $response = $this->httpPost(self::TOKEN_URL, $data);

        if ($response === null || isset($response['error'])) {
            return null;
        }

        return $response;
    }

    /**
     * Refresh an access token
     * 
     * @return array<string, mixed>|null New token data or null on failure
     */
    public function refreshToken(string $refreshToken): ?array
    {
        $data = [
            'refresh_token' => $refreshToken,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'refresh_token',
        ];

        $response = $this->httpPost(self::TOKEN_URL, $data);

        if ($response === null || isset($response['error'])) {
            return null;
        }

        return $response;
    }

    /**
     * Get user info from Google
     * 
     * @return array<string, mixed>|null User data or null on failure
     */
    public function getUserInfo(string $accessToken): ?array
    {
        return $this->httpGet(self::USERINFO_URL, $accessToken);
    }

    /**
     * Save integration tokens to database
     */
    public function saveIntegration(
        int $userId,
        string $accessToken,
        ?string $refreshToken,
        int $expiresIn,
        string $scope
    ): bool {
        $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

        // Check if integration already exists
        $existing = $this->db->fetchOne(sprintf(
            "SELECT integration_id FROM %s WHERE user_id = %d AND provider = 'google'",
            $this->db->table('integrations'),
            $userId
        ));

        if ($existing !== null) {
            // Update existing
            return $this->db->update(
                'integrations',
                [
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken ?? '',
                    'expires_at' => $expiresAt,
                    'scope' => $scope,
                    'enabled' => 1,
                ],
                sprintf("integration_id = %d", (int) $existing['integration_id'])
            );
        }

        // Insert new
        $id = $this->db->insert('integrations', [
            'user_id' => $userId,
            'provider' => 'google',
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken ?? '',
            'expires_at' => $expiresAt,
            'scope' => $scope,
            'enabled' => 1,
        ]);

        return $id !== false;
    }

    /**
     * Get stored integration for a user
     * 
     * @return array<string, mixed>|null
     */
    public function getIntegration(int $userId): ?array
    {
        return $this->db->fetchOne(sprintf(
            "SELECT * FROM %s WHERE user_id = %d AND provider = 'google' AND enabled = 1",
            $this->db->table('integrations'),
            $userId
        ));
    }

    /**
     * Get valid access token (refreshing if needed)
     */
    public function getValidAccessToken(int $userId): ?string
    {
        $integration = $this->getIntegration($userId);

        if ($integration === null) {
            return null;
        }

        // Check if expired
        $expiresAt = strtotime($integration['expires_at']);

        if ($expiresAt <= time() + 60) { // Refresh if expires within 1 minute
            if (empty($integration['refresh_token'])) {
                return null;
            }

            $newTokens = $this->refreshToken($integration['refresh_token']);

            if ($newTokens === null) {
                return null;
            }

            // Update stored tokens
            $this->saveIntegration(
                $userId,
                $newTokens['access_token'],
                $newTokens['refresh_token'] ?? $integration['refresh_token'],
                $newTokens['expires_in'],
                $integration['scope']
            );

            return $newTokens['access_token'];
        }

        return $integration['access_token'];
    }

    /**
     * Revoke integration access
     */
    public function revokeAccess(int $userId): bool
    {
        return $this->db->update(
            'integrations',
            ['enabled' => 0],
            sprintf("user_id = %d AND provider = 'google'", $userId)
        );
    }

    /**
     * HTTP POST request
     * 
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    private function httpPost(string $url, array $data): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $response === false) {
            return null;
        }

        return json_decode($response, true);
    }

    /**
     * HTTP GET request with Bearer token
     * 
     * @return array<string, mixed>|null
     */
    private function httpGet(string $url, string $accessToken): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
            ],
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $response === false) {
            return null;
        }

        return json_decode($response, true);
    }
}
