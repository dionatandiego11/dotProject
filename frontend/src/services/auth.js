import { apiRequest, clearAuthTokens, setRefreshToken, setToken } from './client';

export async function login(username, password) {
    const data = await apiRequest('/auth/login', {
        method: 'POST',
        body: JSON.stringify({ username, password }),
    });

    if (data.token) {
        setToken(data.token);
        if (data.refresh_token) {
            setRefreshToken(data.refresh_token);
        }
    }

    return data;
}

export async function logout() {
    clearAuthTokens();
}

export async function getCurrentUser() {
    return apiRequest('/auth/me');
}
