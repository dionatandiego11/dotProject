import { apiRequest } from './client';

// Admin - hierarchy levels
export async function getNiveis() {
    return apiRequest('/admin/niveis');
}

export async function getNivel(id) {
    return apiRequest(`/admin/niveis/${id}`);
}

export async function createNivel(data) {
    return apiRequest('/admin/niveis', {
        method: 'POST',
        body: JSON.stringify(data),
    });
}

export async function updateNivel(id, data) {
    return apiRequest(`/admin/niveis/${id}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    });
}

export async function deleteNivel(id) {
    return apiRequest(`/admin/niveis/${id}`, {
        method: 'DELETE',
    });
}

export async function reordenarNiveis(ordens) {
    return apiRequest('/admin/niveis/reordenar', {
        method: 'PUT',
        body: JSON.stringify({ ordens }),
    });
}

// Admin - organizational units
export async function getUnidades(params = {}) {
    const queryString = new URLSearchParams(params).toString();
    const endpoint = queryString ? `/admin/unidades?${queryString}` : '/admin/unidades';
    return apiRequest(endpoint);
}

export async function getArvoreUnidades(raizId = null) {
    const params = raizId ? `?raiz_id=${raizId}` : '';
    return apiRequest(`/admin/unidades/arvore${params}`);
}

export async function getUnidade(id) {
    return apiRequest(`/admin/unidades/${id}`);
}

export async function createUnidade(data) {
    return apiRequest('/admin/unidades', {
        method: 'POST',
        body: JSON.stringify(data),
    });
}

export async function updateUnidade(id, data) {
    return apiRequest(`/admin/unidades/${id}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    });
}

export async function deleteUnidade(id) {
    return apiRequest(`/admin/unidades/${id}`, {
        method: 'DELETE',
    });
}

export async function moverUnidade(id, paiId) {
    return apiRequest(`/admin/unidades/${id}/mover`, {
        method: 'PUT',
        body: JSON.stringify({ pai_id: paiId }),
    });
}

export async function getSubordinadas(id) {
    return apiRequest(`/admin/unidades/${id}/subordinadas`);
}

// Admin - users
export async function getUsuarios(params = {}) {
    const queryString = new URLSearchParams(params).toString();
    const endpoint = queryString ? `/admin/usuarios?${queryString}` : '/admin/usuarios';
    return apiRequest(endpoint);
}

export async function getUsuario(id) {
    return apiRequest(`/admin/usuarios/${id}`);
}

export async function createUsuario(data) {
    return apiRequest('/admin/usuarios', {
        method: 'POST',
        body: JSON.stringify(data),
    });
}

export async function updateUsuario(id, data) {
    return apiRequest(`/admin/usuarios/${id}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    });
}

export async function deleteUsuario(id) {
    return apiRequest(`/admin/usuarios/${id}`, {
        method: 'DELETE',
    });
}

// Admin - links
export async function getVinculos(params = {}) {
    const queryString = new URLSearchParams(params).toString();
    const endpoint = queryString ? `/admin/vinculos?${queryString}` : '/admin/vinculos';
    return apiRequest(endpoint);
}

export async function createVinculo(data) {
    return apiRequest('/admin/vinculos', {
        method: 'POST',
        body: JSON.stringify(data),
    });
}

export async function updateVinculo(id, data) {
    return apiRequest(`/admin/vinculos/${id}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    });
}

export async function deleteVinculo(id) {
    return apiRequest(`/admin/vinculos/${id}`, {
        method: 'DELETE',
    });
}

export async function definirVinculoPrincipal(id) {
    return apiRequest(`/admin/vinculos/${id}/principal`, {
        method: 'PUT',
    });
}

// Admin - permissions and readiness
export async function getMatrizPermissoes() {
    return apiRequest('/admin/permissoes/matriz');
}

export async function updateMatrizPermissoes(data) {
    return apiRequest('/admin/permissoes/matriz', {
        method: 'PUT',
        body: JSON.stringify(data),
    });
}

export async function getOrganograma() {
    return apiRequest('/admin/organograma');
}

export async function getAdminOnboardingReadiness() {
    return apiRequest('/admin/onboarding/readiness');
}

// Admin - setup wizard
export async function getSetupTemplates() {
    return apiRequest('/admin/setup/templates');
}

export async function setupPrefeitura(data) {
    return apiRequest('/admin/setup', {
        method: 'POST',
        body: JSON.stringify(data),
    });
}
