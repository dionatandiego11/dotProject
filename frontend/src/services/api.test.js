/**
 * Unit tests for API services.
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import {
    setToken,
    getToken,
    setRefreshToken,
    getRefreshToken,
    clearAuthTokens,
    isAuthenticated,
    login,
    logout,
    getAdminOnboardingReadiness,
    apiRequest,
    apiRequestContract,
    ApiClientError,
    isApiClientError,
    uploadTaskFile,
} from './api';

describe('API Service', () => {
    beforeEach(() => {
        localStorage.clear();
        vi.restoreAllMocks();
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    describe('Token Management', () => {
        it('should set and get token', () => {
            const mockToken = 'test-token-123';
            setToken(mockToken);

            expect(getToken()).toBe(mockToken);
            expect(localStorage.getItem('dp_token')).toBe(mockToken);
        });

        it('should remove token when set to null', () => {
            setToken('test-token');
            setToken(null);

            expect(getToken()).toBeNull();
            expect(localStorage.getItem('dp_token')).toBeNull();
        });

        it('should return false when not authenticated', () => {
            expect(isAuthenticated()).toBe(false);
        });

        it('should return true when token exists', () => {
            localStorage.setItem('dp_token', 'valid-token');
            expect(isAuthenticated()).toBe(true);
        });

        it('should set and clear refresh token', () => {
            setRefreshToken('refresh-token-123');
            expect(getRefreshToken()).toBe('refresh-token-123');

            setRefreshToken(null);
            expect(getRefreshToken()).toBeNull();
        });

        it('should clear both auth tokens via helper', () => {
            setToken('access-token');
            setRefreshToken('refresh-token');

            clearAuthTokens();

            expect(getToken()).toBeNull();
            expect(getRefreshToken()).toBeNull();
        });
    });

    describe('Login', () => {
        it('should call API with correct credentials', async () => {
            global.fetch = vi.fn(() =>
                Promise.resolve({
                    ok: true,
                    status: 200,
                    text: () => Promise.resolve(JSON.stringify({
                        token: 'new-token',
                        user: { id: 1, username: 'admin' },
                    })),
                })
            );

            const result = await login('admin', 'password');

            expect(fetch).toHaveBeenCalledWith(
                '/api/v1/auth/login',
                expect.objectContaining({
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username: 'admin', password: 'password' }),
                })
            );

            expect(result.token).toBe('new-token');
        });

        it('should throw error on failed login', async () => {
            global.fetch = vi.fn(() =>
                Promise.resolve({
                    ok: false,
                    status: 400,
                    text: () => Promise.resolve(JSON.stringify({
                        message: 'Invalid credentials',
                    })),
                })
            );

            await expect(login('admin', 'wrong')).rejects.toThrow('Invalid credentials');
        });

        it('should fallback to error field when message is missing', async () => {
            global.fetch = vi.fn(() =>
                Promise.resolve({
                    ok: false,
                    status: 400,
                    text: () => Promise.resolve(JSON.stringify({
                        error: 'Erro ao carregar dashboard',
                    })),
                })
            );

            await expect(login('admin', 'wrong')).rejects.toThrow('Erro ao carregar dashboard');
        });
    });

    describe('Logout', () => {
        it('should clear token on logout', () => {
            setToken('test-token');
            expect(isAuthenticated()).toBe(true);

            logout();

            expect(isAuthenticated()).toBe(false);
            expect(localStorage.getItem('dp_refresh_token')).toBeNull();
        });
    });

    describe('Admin onboarding readiness', () => {
        it('should request onboarding readiness endpoint', async () => {
            setToken('token-admin');
            global.fetch = vi.fn(() =>
                Promise.resolve({
                    ok: true,
                    status: 200,
                    text: () => Promise.resolve(JSON.stringify({
                        data: {
                            progress: { completed: 1, total: 2, percentage: 50 },
                            checklist: [],
                        },
                    })),
                })
            );

            const result = await getAdminOnboardingReadiness();

            expect(fetch).toHaveBeenCalledWith(
                '/api/v1/admin/onboarding/readiness',
                expect.objectContaining({
                    headers: expect.objectContaining({
                        'Content-Type': 'application/json',
                        Authorization: 'Bearer token-admin',
                    }),
                })
            );
            expect(result?.data?.progress?.percentage).toBe(50);
        });
    });

    describe('Client contract', () => {
        it('should return canonical success contract via apiRequestContract', async () => {
            global.fetch = vi.fn(() =>
                Promise.resolve({
                    ok: true,
                    status: 200,
                    text: () => Promise.resolve(JSON.stringify({
                        message: 'ok',
                        code: 'DASHBOARD_OK',
                        data: { value: 123 },
                    })),
                })
            );

            const result = await apiRequestContract('/dashboard');

            expect(result).toMatchObject({
                ok: true,
                success: true,
                status: 200,
                message: 'ok',
                code: 'DASHBOARD_OK',
                data: { value: 123 },
                payload: {
                    message: 'ok',
                    code: 'DASHBOARD_OK',
                    data: { value: 123 },
                },
                endpoint: '/dashboard',
                url: '/api/v1/dashboard',
                method: 'GET',
            });
        });

        it('should keep apiRequest backward-compatible and return payload', async () => {
            global.fetch = vi.fn(() =>
                Promise.resolve({
                    ok: true,
                    status: 200,
                    text: () => Promise.resolve(JSON.stringify({
                        success: true,
                        data: { id: 10 },
                    })),
                })
            );

            const result = await apiRequest('/projects/10');

            expect(result).toEqual({
                success: true,
                data: { id: 10 },
            });
        });

        it('should normalize api errors into ApiClientError', async () => {
            global.fetch = vi.fn(() =>
                Promise.resolve({
                    ok: false,
                    status: 422,
                    text: () => Promise.resolve(JSON.stringify({
                        error: 'Validation failed',
                        code: 'VALIDATION_ERROR',
                        errors: { name: ['required'] },
                    })),
                })
            );

            let thrown;
            try {
                await apiRequest('/projects', { method: 'POST' });
            } catch (error) {
                thrown = error;
            }

            expect(thrown).toBeInstanceOf(ApiClientError);
            expect(isApiClientError(thrown)).toBe(true);
            expect(thrown.message).toBe('Validation failed');
            expect(thrown.status).toBe(422);
            expect(thrown.code).toBe('VALIDATION_ERROR');
            expect(thrown.details).toEqual({ name: ['required'] });
            expect(thrown.endpoint).toBe('/projects');
            expect(thrown.method).toBe('POST');
        });

        it('should refresh token and retry request on 401', async () => {
            setToken('expired-access');
            setRefreshToken('valid-refresh');

            global.fetch = vi
                .fn()
                .mockResolvedValueOnce({
                    ok: false,
                    status: 401,
                    text: () => Promise.resolve(JSON.stringify({ message: 'Token expired' })),
                })
                .mockResolvedValueOnce({
                    ok: true,
                    status: 200,
                    text: () => Promise.resolve(JSON.stringify({
                        token: 'new-access',
                        refresh_token: 'new-refresh',
                    })),
                })
                .mockResolvedValueOnce({
                    ok: true,
                    status: 200,
                    text: () => Promise.resolve(JSON.stringify({ data: { id: 1 } })),
                });

            const payload = await apiRequest('/projects/1');

            expect(payload).toEqual({ data: { id: 1 } });
            expect(getToken()).toBe('new-access');
            expect(getRefreshToken()).toBe('new-refresh');

            expect(fetch).toHaveBeenNthCalledWith(
                1,
                '/api/v1/projects/1',
                expect.objectContaining({
                    headers: expect.objectContaining({
                        Authorization: 'Bearer expired-access',
                    }),
                })
            );

            expect(fetch).toHaveBeenNthCalledWith(
                2,
                '/api/v1/auth/refresh',
                expect.objectContaining({
                    method: 'POST',
                    body: JSON.stringify({ refresh_token: 'valid-refresh' }),
                })
            );

            expect(fetch).toHaveBeenNthCalledWith(
                3,
                '/api/v1/projects/1',
                expect.objectContaining({
                    headers: expect.objectContaining({
                        Authorization: 'Bearer new-access',
                    }),
                })
            );
        });

        it('should clear auth tokens when refresh fails after 401', async () => {
            setToken('expired-access');
            setRefreshToken('expired-refresh');

            global.fetch = vi
                .fn()
                .mockResolvedValueOnce({
                    ok: false,
                    status: 401,
                    text: () => Promise.resolve(JSON.stringify({ message: 'Token expired' })),
                })
                .mockResolvedValueOnce({
                    ok: false,
                    status: 401,
                    text: () => Promise.resolve(JSON.stringify({ message: 'Invalid refresh token' })),
                });

            await expect(apiRequest('/dashboard')).rejects.toThrow('Invalid refresh token');
            expect(getToken()).toBeNull();
            expect(getRefreshToken()).toBeNull();
        });

        it('should normalize network failures as ApiClientError', async () => {
            global.fetch = vi.fn(() => Promise.reject(new TypeError('Failed to fetch')));

            let thrown;
            try {
                await apiRequest('/dashboard');
            } catch (error) {
                thrown = error;
            }

            expect(thrown).toBeInstanceOf(ApiClientError);
            expect(isApiClientError(thrown)).toBe(true);
            expect(thrown.status).toBe(0);
            expect(thrown.message).toBe('Failed to fetch');
            expect(thrown.endpoint).toBe('/dashboard');
            expect(thrown.method).toBe('GET');
        });

        it('should handle non-JSON payloads with fallback object', async () => {
            global.fetch = vi.fn(() =>
                Promise.resolve({
                    ok: true,
                    status: 200,
                    text: () => Promise.resolve('plain text payload'),
                })
            );

            const result = await apiRequest('/health');

            expect(result).toEqual({
                message: 'Resposta da API nao e JSON',
                raw: 'plain text payload',
            });
        });

        it('should not force Content-Type for FormData uploads', async () => {
            global.fetch = vi.fn(() =>
                Promise.resolve({
                    ok: true,
                    status: 201,
                    text: () => Promise.resolve(JSON.stringify({ success: true })),
                })
            );

            const file = new File(['hello'], 'test.txt', { type: 'text/plain' });
            await uploadTaskFile(42, file);

            const [, requestOptions] = fetch.mock.calls[0];
            expect(requestOptions.method).toBe('POST');
            expect(requestOptions.body).toBeInstanceOf(FormData);
            expect(requestOptions.headers['Content-Type']).toBeUndefined();
        });
    });
});
