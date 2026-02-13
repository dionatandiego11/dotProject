import { apiRequest } from './client'

function toQuery(params = {}) {
    const entries = Object.entries(params).filter(([, value]) => (
        value !== null &&
        value !== undefined &&
        String(value).trim() !== ''
    ))

    if (entries.length === 0) {
        return ''
    }

    return `?${new URLSearchParams(entries).toString()}`
}

export async function getPpas(params = {}) {
    return apiRequest(`/ppas${toQuery(params)}`)
}

export async function getPpa(id) {
    return apiRequest(`/ppas/${id}`)
}

export async function createPpa(data) {
    return apiRequest('/ppas', {
        method: 'POST',
        body: JSON.stringify(data),
    })
}

export async function updatePpa(id, data) {
    return apiRequest(`/ppas/${id}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    })
}

export async function deletePpa(id) {
    return apiRequest(`/ppas/${id}`, {
        method: 'DELETE',
    })
}

export async function getProgramas(params = {}) {
    return apiRequest(`/programas${toQuery(params)}`)
}

export async function getPrograma(id) {
    return apiRequest(`/programas/${id}`)
}

export async function createPrograma(data) {
    return apiRequest('/programas', {
        method: 'POST',
        body: JSON.stringify(data),
    })
}

export async function updatePrograma(id, data) {
    return apiRequest(`/programas/${id}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    })
}

export async function deletePrograma(id) {
    return apiRequest(`/programas/${id}`, {
        method: 'DELETE',
    })
}

export async function getAcoes(params = {}) {
    return apiRequest(`/acoes${toQuery(params)}`)
}

export async function getAcao(id) {
    return apiRequest(`/acoes/${id}`)
}

export async function createAcao(data) {
    return apiRequest('/acoes', {
        method: 'POST',
        body: JSON.stringify(data),
    })
}

export async function updateAcao(id, data) {
    return apiRequest(`/acoes/${id}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    })
}

export async function deleteAcao(id) {
    return apiRequest(`/acoes/${id}`, {
        method: 'DELETE',
    })
}
