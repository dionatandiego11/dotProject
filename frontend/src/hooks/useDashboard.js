import { useCallback, useEffect, useState } from 'react'
import {
    getDashboardByProfile,
    getDashboardPrefeito,
    getDashboardSecretario,
    getDashboardCoordenador,
    getDashboardTecnico,
    getDashboardControlador,
    getDashboardAlertas,
    marcarAlertaLido,
    marcarTodosAlertasLidos,
} from '../services/api'

const dashboardFetchers = {
    prefeito: getDashboardPrefeito,
    secretario: getDashboardSecretario,
    coordenador: getDashboardCoordenador,
    tecnico: getDashboardTecnico,
    controlador: getDashboardControlador,
}

export default function useDashboard({ perfil = 'auto', autoLoad = true } = {}) {
    const [data, setData] = useState(null)
    const [alertas, setAlertas] = useState(null)
    const [loading, setLoading] = useState(false)
    const [error, setError] = useState(null)

    const fetchDashboard = useCallback(async (perfilOverride = null) => {
        const perfilAtual = perfilOverride || perfil
        try {
            setLoading(true)
            setError(null)

            if (perfilAtual === 'auto') {
                const response = await getDashboardByProfile()
                setData(response?.data ?? response)
            } else {
                const fetcher = dashboardFetchers[perfilAtual] || getDashboardTecnico
                const response = await fetcher()
                setData(response?.data ?? response)
            }
        } catch (err) {
            setError(err)
        } finally {
            setLoading(false)
        }
    }, [perfil])

    const fetchAlertas = useCallback(async () => {
        try {
            setLoading(true)
            setError(null)
            const response = await getDashboardAlertas()
            setAlertas(response?.data ?? response)
        } catch (err) {
            setError(err)
        } finally {
            setLoading(false)
        }
    }, [])

    const marcarLido = useCallback(async (alertaId) => {
        await marcarAlertaLido(alertaId)
        await fetchAlertas()
    }, [fetchAlertas])

    const marcarTodosLidos = useCallback(async () => {
        await marcarTodosAlertasLidos()
        await fetchAlertas()
    }, [fetchAlertas])

    useEffect(() => {
        if (autoLoad) {
            fetchDashboard()
        }
    }, [autoLoad, fetchDashboard])

    return {
        data,
        alertas,
        loading,
        error,
        fetchDashboard,
        fetchAlertas,
        marcarLido,
        marcarTodosLidos,
    }
}
