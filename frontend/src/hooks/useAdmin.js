import { useCallback, useEffect, useState } from 'react'
import {
    getNiveis,
    getNivel,
    createNivel,
    updateNivel,
    deleteNivel,
    reordenarNiveis,
    getUnidades,
    getUnidade,
    createUnidade,
    updateUnidade,
    deleteUnidade,
    moverUnidade,
    getSubordinadas,
} from '../services/api'

export default function useAdmin({ autoLoad = true } = {}) {
    const [niveis, setNiveis] = useState([])
    const [unidades, setUnidades] = useState([])
    const [loading, setLoading] = useState(false)
    const [error, setError] = useState(null)

    const fetchNiveis = useCallback(async () => {
        try {
            setLoading(true)
            setError(null)
            const response = await getNiveis()
            setNiveis(response?.data ?? response ?? [])
        } catch (err) {
            setError(err)
        } finally {
            setLoading(false)
        }
    }, [])

    const fetchUnidades = useCallback(async (params = {}) => {
        try {
            setLoading(true)
            setError(null)
            const response = await getUnidades(params)
            setUnidades(response?.data ?? response ?? [])
        } catch (err) {
            setError(err)
        } finally {
            setLoading(false)
        }
    }, [])

    const fetchNivel = useCallback(async (id) => getNivel(id), [])
    const fetchUnidade = useCallback(async (id) => getUnidade(id), [])

    const createNivelItem = useCallback(async (data) => {
        const response = await createNivel(data)
        await fetchNiveis()
        return response
    }, [fetchNiveis])

    const updateNivelItem = useCallback(async (id, data) => {
        const response = await updateNivel(id, data)
        await fetchNiveis()
        return response
    }, [fetchNiveis])

    const deleteNivelItem = useCallback(async (id) => {
        const response = await deleteNivel(id)
        await fetchNiveis()
        return response
    }, [fetchNiveis])

    const reorderNiveis = useCallback(async (ordens) => {
        const response = await reordenarNiveis(ordens)
        await fetchNiveis()
        return response
    }, [fetchNiveis])

    const createUnidadeItem = useCallback(async (data) => {
        const response = await createUnidade(data)
        await fetchUnidades()
        return response
    }, [fetchUnidades])

    const updateUnidadeItem = useCallback(async (id, data) => {
        const response = await updateUnidade(id, data)
        await fetchUnidades()
        return response
    }, [fetchUnidades])

    const deleteUnidadeItem = useCallback(async (id) => {
        const response = await deleteUnidade(id)
        await fetchUnidades()
        return response
    }, [fetchUnidades])

    const moveUnidade = useCallback(async (id, paiId) => {
        const response = await moverUnidade(id, paiId)
        await fetchUnidades()
        return response
    }, [fetchUnidades])

    const fetchSubordinadas = useCallback(async (id) => getSubordinadas(id), [])

    useEffect(() => {
        if (autoLoad) {
            fetchNiveis()
            fetchUnidades()
        }
    }, [autoLoad, fetchNiveis, fetchUnidades])

    return {
        niveis,
        unidades,
        loading,
        error,
        fetchNiveis,
        fetchNivel,
        createNivel: createNivelItem,
        updateNivel: updateNivelItem,
        deleteNivel: deleteNivelItem,
        reordenarNiveis: reorderNiveis,
        fetchUnidades,
        fetchUnidade,
        createUnidade: createUnidadeItem,
        updateUnidade: updateUnidadeItem,
        deleteUnidade: deleteUnidadeItem,
        moverUnidade: moveUnidade,
        fetchSubordinadas,
    }
}
