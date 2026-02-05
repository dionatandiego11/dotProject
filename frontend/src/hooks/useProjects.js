import { useCallback, useEffect, useState } from 'react'
import {
    getProjects,
    getProject,
    createProject,
    updateProject,
    deleteProject,
    getProjectTasks,
} from '../services/api'

export default function useProjects({ autoLoad = true, params = {} } = {}) {
    const [projects, setProjects] = useState([])
    const [loading, setLoading] = useState(false)
    const [error, setError] = useState(null)

    const fetchProjects = useCallback(async (overrideParams = null) => {
        try {
            setLoading(true)
            setError(null)
            const response = await getProjects(overrideParams || params)
            setProjects(response?.data ?? response ?? [])
        } catch (err) {
            setError(err)
        } finally {
            setLoading(false)
        }
    }, [params])

    const fetchProject = useCallback(async (id) => {
        return getProject(id)
    }, [])

    const create = useCallback(async (data) => {
        const response = await createProject(data)
        await fetchProjects()
        return response
    }, [fetchProjects])

    const update = useCallback(async (id, data) => {
        const response = await updateProject(id, data)
        await fetchProjects()
        return response
    }, [fetchProjects])

    const remove = useCallback(async (id) => {
        const response = await deleteProject(id)
        await fetchProjects()
        return response
    }, [fetchProjects])

    const fetchTasks = useCallback(async (projectId, projectParams = {}) => {
        return getProjectTasks(projectId, projectParams)
    }, [])

    useEffect(() => {
        if (autoLoad) {
            fetchProjects()
        }
    }, [autoLoad, fetchProjects])

    return {
        projects,
        loading,
        error,
        fetchProjects,
        fetchProject,
        create,
        update,
        remove,
        fetchTasks,
    }
}
