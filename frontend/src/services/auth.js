import { apiRequest, setToken } from './client';

export async function login(username, password) {
    const data = await apiRequest('/auth/login', {
        method: 'POST',
        body: JSON.stringify({ username, password }),
    });

    if (data.token) {
        setToken(data.token);
        if (data.refresh_token) {
            localStorage.setItem('dp_refresh_token', data.refresh_token);
        }
    }

    return data;
}

export async function logout() {
    setToken(null);
    localStorage.removeItem('dp_refresh_token');
}

export async function getCurrentUser() {
    return apiRequest('/auth/me');
}
