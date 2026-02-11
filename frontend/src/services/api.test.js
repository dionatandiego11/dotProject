/**
 * Testes unitários para API Service
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { 
    setToken, 
    getToken, 
    isAuthenticated, 
    login, 
    logout,
    getAdminOnboardingReadiness
} from './api';

describe('API Service', () => {
    beforeEach(() => {
        // Limpar localStorage antes de cada teste
        localStorage.clear();
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
    });

    describe('Login', () => {
        it('should call API with correct credentials', async () => {
            global.fetch = vi.fn(() =>
                Promise.resolve({
                    ok: true,
                    status: 200,
                    text: () => Promise.resolve(JSON.stringify({
                        token: 'new-token',
                        user: { id: 1, username: 'admin' }
                    }))
                })
            );

            const result = await login('admin', 'password');

            expect(fetch).toHaveBeenCalledWith(
                '/api/v1/auth/login',
                expect.objectContaining({
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username: 'admin', password: 'password' })
                })
            );
            
            expect(result.token).toBe('new-token');
        });

        it('should throw error on failed login', async () => {
            global.fetch = vi.fn(() =>
                Promise.resolve({
                    ok: false,
                    status: 401,
                    text: () => Promise.resolve(JSON.stringify({
                        message: 'Invalid credentials'
                    }))
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
                        error: 'Erro ao carregar dashboard'
                    }))
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
                            checklist: []
                        }
                    }))
                })
            );

            const result = await getAdminOnboardingReadiness();

            expect(fetch).toHaveBeenCalledWith(
                '/api/v1/admin/onboarding/readiness',
                expect.objectContaining({
                    headers: expect.objectContaining({
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer token-admin'
                    })
                })
            );
            expect(result?.data?.progress?.percentage).toBe(50);
        });
    });
});
