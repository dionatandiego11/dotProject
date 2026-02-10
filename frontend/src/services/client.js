/**
 * Shared API client utilities.
 */

export const API_BASE = '/api.php/v1';

/**
 * Set authentication token
 */
export function setToken(token) {
    if (token) {
        localStorage.setItem('dp_token', token);
    } else {
        localStorage.removeItem('dp_token');
    }
}

/**
 * Get current token
 */
export function getToken() {
    return localStorage.getItem('dp_token');
}

/**
 * Check if user is authenticated
 */
export function isAuthenticated() {
    return !!localStorage.getItem('dp_token');
}

/**
 * Generic API request
 */
export async function apiRequest(endpoint, options = {}) {
    const url = `${API_BASE}${endpoint}`;
    console.log('API Request:', url, options.method || 'GET');

    const headers = {
        'Content-Type': 'application/json',
        ...options.headers,
    };

    const token = getToken();
    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }

    const response = await fetch(url, {
        ...options,
        headers,
    });

    const rawText = await response.text();
    console.log('API Response:', url, response.status, rawText.substring(0, 200));

    let data = null;
    if (rawText) {
        try {
            data = JSON.parse(rawText);
        } catch {
            data = { message: 'Resposta da API não é JSON', raw: rawText };
        }
    }

    if (!response.ok) {
        if (response.status === 401) {
            // Token expired
            setToken(null);
            window.location.href = '/login';
        }

        const message =
            (data && typeof data.message === 'string' && data.message.trim() !== '')
                ? data.message
                : (data && typeof data.error === 'string' && data.error.trim() !== '')
                    ? data.error
                    : 'Request failed';

        throw new Error(message);
    }

    return data;
}
