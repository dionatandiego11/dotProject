/**
 * Testes para página de Login
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import Login from './Login';

// Mock do módulo de API
vi.mock('../services/api', () => ({
    login: vi.fn(),
    isAuthenticated: vi.fn(() => false)
}));

import { login } from '../services/api';

describe('Login Page', () => {
    it('should render login form', () => {
        render(
            <BrowserRouter>
                <Login />
            </BrowserRouter>
        );

        expect(screen.getByPlaceholderText('Digite seu usuário')).toBeInTheDocument();
        expect(screen.getByPlaceholderText('Digite sua senha')).toBeInTheDocument();
        expect(screen.getByText('Entrar')).toBeInTheDocument();
    });

    it('should show error for empty fields', async () => {
        render(
            <BrowserRouter>
                <Login />
            </BrowserRouter>
        );

        fireEvent.click(screen.getByText('Entrar'));

        await waitFor(() => {
            expect(screen.getByText('Preencha usuário e senha')).toBeInTheDocument();
        });
    });

    it('should call login API with credentials', async () => {
        login.mockResolvedValueOnce({
            token: 'test-token',
            user: { id: 1, username: 'admin' }
        });

        render(
            <BrowserRouter>
                <Login />
            </BrowserRouter>
        );

        fireEvent.change(screen.getByPlaceholderText('Digite seu usuário'), {
            target: { value: 'admin' }
        });
        fireEvent.change(screen.getByPlaceholderText('Digite sua senha'), {
            target: { value: 'admin123' }
        });
        fireEvent.click(screen.getByText('Entrar'));

        await waitFor(() => {
            expect(login).toHaveBeenCalledWith('admin', 'admin123');
        });
    });

    it('should display error message on login failure', async () => {
        login.mockRejectedValueOnce(new Error('Credenciais inválidas'));

        render(
            <BrowserRouter>
                <Login />
            </BrowserRouter>
        );

        fireEvent.change(screen.getByPlaceholderText('Digite seu usuário'), {
            target: { value: 'admin' }
        });
        fireEvent.change(screen.getByPlaceholderText('Digite sua senha'), {
            target: { value: 'wrongpass' }
        });
        fireEvent.click(screen.getByText('Entrar'));

        await waitFor(() => {
            expect(screen.getByText('Credenciais inválidas')).toBeInTheDocument();
        });
    });
});
