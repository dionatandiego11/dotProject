import { useState, useEffect, lazy, Suspense } from 'react'
import { getCurrentUser, getDashboardByProfile } from '../services/api'
import Loading from '../components/Loading'

// Lazy load dos dashboards específicos
const DashboardPrefeito = lazy(() => import('./dashboard/DashboardPrefeito'))
const DashboardSecretario = lazy(() => import('./dashboard/DashboardSecretario'))
const DashboardCoordenador = lazy(() => import('./dashboard/DashboardCoordenador'))
const DashboardTecnico = lazy(() => import('./dashboard/DashboardTecnico'))
const DashboardControlador = lazy(() => import('./dashboard/DashboardControlador'))
const DashboardGeral = lazy(() => import('./DashboardGeral'))

function Dashboard() {
    const [user, setUser] = useState(null)
    const [profile, setProfile] = useState(null)
    const [loading, setLoading] = useState(true)

    useEffect(() => {
        loadUserProfile()
    }, [])

    async function loadUserProfile() {
        try {
            setLoading(true)
            const userData = await getCurrentUser()
            setUser(userData)
            
            // Detectar perfil do usuário
            const userProfile = detectProfile(userData)
            setProfile(userProfile)
        } catch (error) {
            console.error('Failed to load user profile:', error)
        } finally {
            setLoading(false)
        }
    }

    function detectProfile(userData) {
        // Prioridade: profile > role > cargo > nivel_acesso
        if (userData.profile) return userData.profile.toLowerCase()
        if (userData.role) return userData.role.toLowerCase()
        if (userData.cargo) return userData.cargo.toLowerCase()
        if (userData.nivel_acesso) {
            // Mapear nível de acesso para perfil
            const nivel = parseInt(userData.nivel_acesso)
            switch(nivel) {
                case 1: return 'prefeito'
                case 2: return 'secretario'
                case 3: return 'coordenador'
                case 4: return 'tecnico'
                case 5: return 'controlador'
                default: return 'usuario'
            }
        }
        return 'usuario'
    }

    if (loading) {
        return <Loading fullScreen />
    }

    // Renderizar dashboard baseado no perfil
    const renderDashboard = () => {
        switch(profile) {
            case 'prefeito':
                return <DashboardPrefeito />
            case 'secretario':
                return <DashboardSecretario />
            case 'coordenador':
                return <DashboardCoordenador />
            case 'tecnico':
                return <DashboardTecnico />
            case 'controlador':
                return <DashboardControlador />
            default:
                return <DashboardGeral />
        }
    }

    return (
        <Suspense fallback={<Loading fullScreen />}>
            {renderDashboard()}
        </Suspense>
    )
}

export default Dashboard
