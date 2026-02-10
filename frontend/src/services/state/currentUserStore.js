import { getCurrentUser } from '../api'

const listeners = new Set()

const state = {
    user: null,
    loading: false,
    error: null,
    loadedAt: null,
    pending: null,
}

function emit() {
    const snapshot = getCurrentUserSnapshot()
    listeners.forEach((listener) => listener(snapshot))
}

export function getCurrentUserSnapshot() {
    return {
        user: state.user,
        loading: state.loading,
        error: state.error,
        loadedAt: state.loadedAt,
    }
}

export function subscribeCurrentUser(listener) {
    listeners.add(listener)
    return () => listeners.delete(listener)
}

export async function loadCurrentUser({ force = false } = {}) {
    if (state.pending && !force) {
        return state.pending
    }

    if (state.user && !force) {
        return state.user
    }

    state.loading = true
    state.error = null
    emit()

    state.pending = (async () => {
        try {
            const user = await getCurrentUser()
            state.user = user
            state.loadedAt = Date.now()
            return user
        } catch (error) {
            state.error = error
            throw error
        } finally {
            state.loading = false
            state.pending = null
            emit()
        }
    })()

    return state.pending
}

export function clearCurrentUser() {
    state.user = null
    state.loading = false
    state.error = null
    state.loadedAt = null
    state.pending = null
    emit()
}
