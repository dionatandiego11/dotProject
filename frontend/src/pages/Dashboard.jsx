import { lazy, Suspense, useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import useCurrentUser from '../hooks/useCurrentUser'
import Loading from '../components/Loading'
import { getAdminOnboardingReadiness } from '../services/api'
import { detectProfile, isAdminUser } from '../utils/userAccess'

// Lazy load dos dashboards específicos
const DashboardPrefeito = lazy(() => import('./dashboard/DashboardPrefeito'))
const DashboardSecretario = lazy(() => import('./dashboard/DashboardSecretario'))
const DashboardCoordenador = lazy(() => import('./dashboard/DashboardCoordenador'))
const DashboardTecnico = lazy(() => import('./dashboard/DashboardTecnico'))
const DashboardControlador = lazy(() => import('./dashboard/DashboardControlador'))
const DashboardGeral = lazy(() => import('./DashboardGeral'))

function Dashboard() {
    const navigate = useNavigate()
    const { user, loading } = useCurrentUser()
    const profile = detectProfile(user)
    const [setupStatus, setSetupStatus] = useState({
        checked: false,
        pending: false,
        completed: 0,
        total: 0,
        nextStep: '',
    })

    useEffect(() => {
        let isActive = true

        if (loading || !isAdminUser(user)) {
            setSetupStatus({
                checked: true,
                pending: false,
                completed: 0,
                total: 0,
                nextStep: '',
            })
            return () => {
                isActive = false
            }
        }

        async function loadReadiness() {
            try {
                const response = await getAdminOnboardingReadiness()
                const data = response?.data || {}
                const progress = data.progress || {}
                const completed = Number(progress.completed || 0)
                const total = Number(progress.total || 0)
                const pending = total > 0 && completed < total
                const nextStep = Array.isArray(data.next_steps) && data.next_steps.length > 0
                    ? data.next_steps[0]
                    : ''

                if (!isActive) return

                setSetupStatus({
                    checked: true,
                    pending,
                    completed,
                    total,
                    nextStep,
                })
            } catch (_) {
                if (!isActive) return

                setSetupStatus({
                    checked: true,
                    pending: false,
                    completed: 0,
                    total: 0,
                    nextStep: '',
                })
            }
        }

        loadReadiness()

        return () => {
            isActive = false
        }
    }, [loading, user])

    if (loading) {
        return <Loading fullScreen />
    }

    // Renderizar dashboard baseado no perfil
    const renderDashboard = () => {
        switch (profile) {
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
        <>
            {isAdminUser(user) && setupStatus.checked && setupStatus.pending && (
                <div style={{ padding: '16px 24px 0' }}>
                    <div style={{
                        backgroundColor: '#fff7ed',
                        border: '1px solid #fdba74',
                        borderRadius: 12,
                        padding: 16,
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'center',
                        gap: 16,
                        flexWrap: 'wrap',
                    }}>
                        <div>
                            <div style={{ fontWeight: 700, color: '#9a3412', marginBottom: 4 }}>
                                Setup administrativo pendente
                            </div>
                            <div style={{ color: '#7c2d12', fontSize: 14 }}>
                                {setupStatus.completed}/{setupStatus.total} etapas concluídas.
                                {setupStatus.nextStep ? ` Próximo foco: ${setupStatus.nextStep}` : ''}
                            </div>
                        </div>
                        <button
                            onClick={() => navigate('/admin/setup')}
                            style={{
                                backgroundColor: '#ea580c',
                                color: 'white',
                                border: 'none',
                                borderRadius: 8,
                                padding: '10px 14px',
                                fontSize: 14,
                                fontWeight: 600,
                                cursor: 'pointer',
                            }}
                        >
                            Abrir setup
                        </button>
                    </div>
                </div>
            )}

            <Suspense fallback={<Loading fullScreen />}>
                {renderDashboard()}
            </Suspense>
        </>
    )
}

export default Dashboard
