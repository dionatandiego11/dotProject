/**
 * Shared API client utilities.
 */

export const API_BASE = '/api/v1';
const SHOULD_LOG_API = import.meta.env.DEV && import.meta.env.VITE_DEBUG_API === 'true';
const DEFAULT_ERROR_MESSAGE = 'Request failed';
const TOKEN_STORAGE_KEY = 'dp_token';
const REFRESH_TOKEN_STORAGE_KEY = 'dp_refresh_token';

let refreshPromise = null;

function isPlainObject(value) {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function resolveRequestMethod(options = {}) {
    return (options.method || 'GET').toUpperCase();
}

function isAuthEndpoint(endpoint) {
    return endpoint === '/auth/login' || endpoint === '/auth/refresh';
}

function toHeaderObject(headersInit) {
    if (!headersInit) {
        return {};
    }

    if (typeof Headers !== 'undefined' && headersInit instanceof Headers) {
        return Object.fromEntries(headersInit.entries());
    }

    if (Array.isArray(headersInit)) {
        return Object.fromEntries(headersInit);
    }

    if (isPlainObject(headersInit)) {
        return { ...headersInit };
    }

    return {};
}

function hasHeader(headers, targetName) {
    const normalizedTarget = targetName.toLowerCase();
    return Object.keys(headers).some((key) => key.toLowerCase() === normalizedTarget);
}

function isFormDataBody(body) {
    return typeof FormData !== 'undefined' && body instanceof FormData;
}

function buildHeaders(options = {}) {
    const headers = toHeaderObject(options.headers);
    const token = getToken();

    if (!hasHeader(headers, 'Content-Type') && !isFormDataBody(options.body)) {
        headers['Content-Type'] = 'application/json';
    }

    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }

    return headers;
}

function redirectToLogin() {
    if (typeof window === 'undefined' || !window.location) {
        return;
    }

    if (window.location.pathname === '/login') {
        return;
    }

    window.location.href = '/login';
}

function shouldAttemptTokenRefresh({ endpoint, options, responseStatus }) {
    if (responseStatus !== 401) {
        return false;
    }

    if (isAuthEndpoint(endpoint)) {
        return false;
    }

    if (options.skipAuthRefresh === true) {
        return false;
    }

    return Boolean(getRefreshToken());
}

function parsePayload(rawText) {
    if (!rawText) {
        return null;
    }

    try {
        return JSON.parse(rawText);
    } catch {
        return { message: 'Resposta da API nao e JSON', raw: rawText };
    }
}

function pickString(value) {
    return typeof value === 'string' && value.trim() !== '' ? value.trim() : null;
}

function resolveMessage(payload, fallback = DEFAULT_ERROR_MESSAGE) {
    if (!isPlainObject(payload)) {
        return fallback;
    }

    return (
        pickString(payload.message) ||
        pickString(payload.error) ||
        pickString(payload?.meta?.message) ||
        fallback
    );
}

function resolveCode(payload) {
    if (!isPlainObject(payload)) {
        return null;
    }

    return (
        pickString(payload.code) ||
        pickString(payload.error_code) ||
        pickString(payload?.meta?.code) ||
        null
    );
}

function resolveDetails(payload) {
    if (!isPlainObject(payload)) {
        return null;
    }

    if (payload.details !== undefined) {
        return payload.details;
    }

    if (payload.errors !== undefined) {
        return payload.errors;
    }

    return null;
}

function resolveData(payload) {
    if (isPlainObject(payload) && Object.prototype.hasOwnProperty.call(payload, 'data')) {
        return payload.data;
    }

    return payload;
}

function createSuccessContract({ payload, response, request }) {
    return {
        ok: true,
        success: true,
        status: response.status,
        message: resolveMessage(payload, null),
        code: resolveCode(payload),
        data: resolveData(payload),
        payload,
        endpoint: request.endpoint,
        url: request.url,
        method: request.method,
    };
}

function createErrorContract({ payload, response, request, fallbackMessage = DEFAULT_ERROR_MESSAGE }) {
    return {
        ok: false,
        success: false,
        status: response?.status ?? 0,
        message: resolveMessage(payload, fallbackMessage),
        code: resolveCode(payload),
        details: resolveDetails(payload),
        payload,
        endpoint: request.endpoint,
        url: request.url,
        method: request.method,
    };
}

export class ApiClientError extends Error {
    constructor(contract, cause) {
        super(contract?.message || DEFAULT_ERROR_MESSAGE);
        this.name = 'ApiClientError';
        this.ok = false;
        this.success = false;
        this.status = contract?.status ?? 0;
        this.code = contract?.code ?? null;
        this.details = contract?.details ?? null;
        this.payload = contract?.payload ?? null;
        this.endpoint = contract?.endpoint ?? null;
        this.url = contract?.url ?? null;
        this.method = contract?.method ?? null;
        this.contract = contract;
        this.isApiClientError = true;
        if (cause) {
            this.cause = cause;
        }
    }
}

export function isApiClientError(error) {
    return error instanceof ApiClientError || Boolean(error?.isApiClientError);
}

/**
 * Set authentication token
 */
export function setToken(token) {
    if (token) {
        localStorage.setItem(TOKEN_STORAGE_KEY, token);
    } else {
        localStorage.removeItem(TOKEN_STORAGE_KEY);
    }
}

/**
 * Get current token
 */
export function getToken() {
    return localStorage.getItem(TOKEN_STORAGE_KEY);
}

/**
 * Check if user is authenticated
 */
export function isAuthenticated() {
    return !!getToken();
}

export function setRefreshToken(token) {
    if (token) {
        localStorage.setItem(REFRESH_TOKEN_STORAGE_KEY, token);
    } else {
        localStorage.removeItem(REFRESH_TOKEN_STORAGE_KEY);
    }
}

export function getRefreshToken() {
    return localStorage.getItem(REFRESH_TOKEN_STORAGE_KEY);
}

export function clearAuthTokens() {
    setToken(null);
    setRefreshToken(null);
}

async function refreshAccessToken() {
    if (refreshPromise !== null) {
        return refreshPromise;
    }

    const refreshToken = getRefreshToken();
    const endpoint = '/auth/refresh';
    const method = 'POST';
    const url = `${API_BASE}${endpoint}`;

    if (!refreshToken) {
        throw new ApiClientError(
            createErrorContract({
                payload: null,
                response: { status: 401 },
                request: { endpoint, url, method },
                fallbackMessage: 'Session expired',
            })
        );
    }

    refreshPromise = (async () => {
        let response;
        try {
            response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    refresh_token: refreshToken,
                }),
            });
        } catch (cause) {
            throw new ApiClientError(
                createErrorContract({
                    payload: null,
                    response: null,
                    request: { endpoint, url, method },
                    fallbackMessage: 'Session refresh request failed',
                }),
                cause
            );
        }

        const payload = parsePayload(await response.text());
        if (!response.ok) {
            throw new ApiClientError(
                createErrorContract({
                    payload,
                    response,
                    request: { endpoint, url, method },
                    fallbackMessage: 'Session expired',
                })
            );
        }

        const token = isPlainObject(payload) ? pickString(payload.token) : null;
        const nextRefreshToken = isPlainObject(payload) ? pickString(payload.refresh_token) : null;
        if (!token) {
            throw new ApiClientError(
                createErrorContract({
                    payload,
                    response,
                    request: { endpoint, url, method },
                    fallbackMessage: 'Refresh token response missing access token',
                })
            );
        }

        setToken(token);
        if (nextRefreshToken) {
            setRefreshToken(nextRefreshToken);
        }

        return {
            token,
            refreshToken: nextRefreshToken,
        };
    })();

    try {
        return await refreshPromise;
    } finally {
        refreshPromise = null;
    }
}

/**
 * Generic API request returning canonical contract.
 */
export async function apiRequestContract(endpoint, options = {}) {
    const method = resolveRequestMethod(options);
    const url = `${API_BASE}${endpoint}`;

    if (SHOULD_LOG_API) {
        console.debug('API Request:', url, method);
    }

    const headers = buildHeaders(options);
    const requestOptions = {
        ...options,
        headers,
    };

    let response;
    try {
        response = await fetch(url, requestOptions);
    } catch (cause) {
        throw new ApiClientError(
            createErrorContract({
                payload: null,
                response: null,
                request: { endpoint, url, method },
                fallbackMessage: cause instanceof Error && cause.message
                    ? cause.message
                    : 'Network request failed',
            }),
            cause
        );
    }

    const rawText = await response.text();
    let payload = parsePayload(rawText);

    if (SHOULD_LOG_API) {
        console.debug('API Response:', url, response.status);
    }

    if (!response.ok && shouldAttemptTokenRefresh({ endpoint, options, responseStatus: response.status })) {
        try {
            await refreshAccessToken();
        } catch (refreshError) {
            clearAuthTokens();
            redirectToLogin();

            if (isApiClientError(refreshError)) {
                throw refreshError;
            }

            throw new ApiClientError(
                createErrorContract({
                    payload: null,
                    response: null,
                    request: { endpoint, url, method },
                    fallbackMessage: 'Session expired',
                }),
                refreshError
            );
        }

        let retryResponse;
        try {
            retryResponse = await fetch(url, {
                ...options,
                headers: buildHeaders(options),
            });
        } catch (cause) {
            throw new ApiClientError(
                createErrorContract({
                    payload: null,
                    response: null,
                    request: { endpoint, url, method },
                    fallbackMessage: cause instanceof Error && cause.message
                        ? cause.message
                        : 'Network request failed',
                }),
                cause
            );
        }

        payload = parsePayload(await retryResponse.text());

        if (SHOULD_LOG_API) {
            console.debug('API Retry Response:', url, retryResponse.status);
        }

        if (retryResponse.ok) {
            return createSuccessContract({
                payload,
                response: retryResponse,
                request: { endpoint, url, method },
            });
        }

        response = retryResponse;
    }

    if (!response.ok) {
        if (response.status === 401 && !isAuthEndpoint(endpoint)) {
            clearAuthTokens();
            redirectToLogin();
        }

        throw new ApiClientError(
            createErrorContract({
                payload,
                response,
                request: { endpoint, url, method },
            })
        );
    }

    return createSuccessContract({
        payload,
        response,
        request: { endpoint, url, method },
    });
}

/**
 * Generic API request returning payload for backwards compatibility.
 */
export async function apiRequest(endpoint, options = {}) {
    const result = await apiRequestContract(endpoint, options);
    return result.payload;
}
