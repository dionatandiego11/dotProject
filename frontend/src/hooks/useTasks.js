import { useCallback, useEffect, useState } from 'react'
import {
    getTasks,
    getTask,
    createTask,
    updateTask,
    deleteTask,
} from '../services/api'

export default function useTasks({ autoLoad = true, params = {} } = {}) {
    const [tasks, setTasks] = useState([])
    const [loading, setLoading] = useState(false)
    const [error, setError] = useState(null)

    const fetchTasks = useCallback(async (overrideParams = null) => {
        try {
            setLoading(true)
            setError(null)
            const response = await getTasks(overrideParams || params)
            setTasks(response?.data ?? response ?? [])
        } catch (err) {
            setError(err)
        } finally {
            setLoading(false)
        }
    }, [params])

    const fetchTask = useCallback(async (id) => {
        return getTask(id)
    }, [])

    const create = useCallback(async (data) => {
        const response = await createTask(data)
        await fetchTasks()
        return response
    }, [fetchTasks])

    const update = useCallback(async (id, data) => {
        const response = await updateTask(id, data)
        await fetchTasks()
        return response
    }, [fetchTasks])

    const remove = useCallback(async (id) => {
        const response = await deleteTask(id)
        await fetchTasks()
        return response
    }, [fetchTasks])

    useEffect(() => {
        if (autoLoad) {
            fetchTasks()
        }
    }, [autoLoad, fetchTasks])

    return {
        tasks,
        loading,
        error,
        fetchTasks,
        fetchTask,
        create,
        update,
        remove,
    }
}
