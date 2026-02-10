import { useEffect, useMemo, useState } from 'react'
import { Outlet, NavLink, useNavigate } from 'react-router-dom'
import { logout, getAdminOnboardingReadiness } from '../services/api'
import useCurrentUser from '../hooks/useCurrentUser'
import { isAdminUser } from '../utils/userAccess'

function MainLayout() {
    const { user, clear } = useCurrentUser()
    const navigate = useNavigate()
    const [showSetupEntry, setShowSetupEntry] = useState(true)

    async function handleLogout() {
        await logout()
        clear()
        navigate('/login')
    }

    const userProfile = user?.profile || user?.role || 'usuario'

    const isAdmin = isAdminUser(user)

    useEffect(() => {
        let isActive = true

        if (!isAdmin) {
            setShowSetupEntry(false)
            return () => {
                isActive = false
            }
        }

        async function loadReadiness() {
            try {
                const response = await getAdminOnboardingReadiness()
                const progress = response?.data?.progress || {}
                const completed = Number(progress.completed || 0)
                const total = Number(progress.total || 0)
                const pending = total > 0 && completed < total

                if (isActive) {
                    setShowSetupEntry(pending)
                }
            } catch (_) {
                if (isActive) {
                    // Mantém acesso ao setup caso a consulta de status falhe.
                    setShowSetupEntry(true)
                }
            }
        }

        loadReadiness()

        return () => {
            isActive = false
        }
    }, [isAdmin])

    const adminItems = useMemo(() => {
        const items = [
            { path: '/admin/unidades', label: 'Estrutura' },
            { path: '/admin/usuarios', label: 'Usuários' },
        ]

        if (showSetupEntry) {
            items.unshift({ path: '/admin/setup', label: 'Setup inicial' })
        }

        return items
    }, [showSetupEntry])

    return (
        <div style={{ display: 'flex', minHeight: '100vh', backgroundColor: '#f3f4f6' }}>
            {/* Sidebar */}
            <aside style={{
                width: 260,
                backgroundColor: '#1f2937',
                color: 'white',
                display: 'flex',
                flexDirection: 'column',
                position: 'fixed',
                height: '100vh',
                overflowY: 'auto',
                zIndex: 100
            }}>
                {/* Header */}
                <div style={{ padding: '20px', borderBottom: '1px solid #374151' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                        <div style={{
                            width: 40,
                            height: 40,
                            backgroundColor: '#3b82f6',
                            borderRadius: 8,
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            fontSize: 20,
                            fontWeight: 'bold'
                        }}>
                            DP
                        </div>
                        <div>
                            <h1 style={{ margin: 0, fontSize: 18, fontWeight: 600 }}>dotProject</h1>
                            <p style={{ margin: 0, fontSize: 12, color: '#9ca3af' }}>Prefeituras</p>
                        </div>
                    </div>
                </div>

                {/* Navigation */}
                <nav style={{ flex: 1, padding: '16px 12px' }}>
                    {/* Dashboard (unico) */}
                    <NavLink
                        to="/"
                        style={({ isActive }) => ({
                            display: 'flex',
                            alignItems: 'center',
                            gap: 12,
                            padding: '12px 16px',
                            borderRadius: 8,
                            color: isActive ? 'white' : '#9ca3af',
                            backgroundColor: isActive ? '#3b82f6' : 'transparent',
                            textDecoration: 'none',
                            marginBottom: 4,
                            transition: 'all 0.2s'
                        })}
                    >
                        <span style={{ fontSize: 14, fontWeight: 500 }}>Dashboard</span>
                    </NavLink>

                    {/* Projetos */}
                    <NavLink
                        to="/projects"
                        style={({ isActive }) => ({
                            display: 'flex',
                            alignItems: 'center',
                            gap: 12,
                            padding: '12px 16px',
                            borderRadius: 8,
                            color: isActive ? 'white' : '#9ca3af',
                            backgroundColor: isActive ? '#3b82f6' : 'transparent',
                            textDecoration: 'none',
                            marginBottom: 4,
                            transition: 'all 0.2s'
                        })}
                    >
                        <span style={{ fontSize: 14, fontWeight: 500 }}>Projetos</span>
                    </NavLink>

                    {/* Kanban */}
                    <NavLink
                        to="/kanban"
                        style={({ isActive }) => ({
                            display: 'flex',
                            alignItems: 'center',
                            gap: 12,
                            padding: '12px 16px',
                            borderRadius: 8,
                            color: isActive ? 'white' : '#9ca3af',
                            backgroundColor: isActive ? '#3b82f6' : 'transparent',
                            textDecoration: 'none',
                            marginBottom: 4,
                            transition: 'all 0.2s'
                        })}
                    >
                        <span style={{ fontSize: 14, fontWeight: 500 }}>Tarefas</span>
                    </NavLink>

                    {/* Organograma (visivel para todos) */}
                    <NavLink
                        to="/admin/organograma"
                        style={({ isActive }) => ({
                            display: 'flex',
                            alignItems: 'center',
                            gap: 12,
                            padding: '12px 16px',
                            borderRadius: 8,
                            color: isActive ? 'white' : '#9ca3af',
                            backgroundColor: isActive ? '#3b82f6' : 'transparent',
                            textDecoration: 'none',
                            marginBottom: 4,
                            transition: 'all 0.2s'
                        })}
                    >
                        <span style={{ fontSize: 14, fontWeight: 500 }}>Organograma</span>
                    </NavLink>

                    {/* Unidades Organizacionais - apenas para admins */}
                    {isAdmin && (
                        <>
                            <div style={{
                                height: 1,
                                backgroundColor: '#374151',
                                margin: '16px 0'
                            }} />
                            {adminItems.map((item) => (
                                <NavLink
                                    key={item.path}
                                    to={item.path}
                                    style={({ isActive }) => ({
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 12,
                                        padding: '12px 16px',
                                        borderRadius: 8,
                                        color: isActive ? 'white' : '#9ca3af',
                                        backgroundColor: isActive ? '#3b82f6' : 'transparent',
                                        textDecoration: 'none',
                                        marginBottom: 4,
                                        transition: 'all 0.2s'
                                    })}
                                >
                                    <span style={{ fontSize: 14, fontWeight: 500 }}>{item.label}</span>
                                </NavLink>
                            ))}
                        </>
                    )}
                </nav>

                {/* User Section */}
                <div style={{
                    padding: '16px',
                    borderTop: '1px solid #374151',
                    backgroundColor: '#111827'
                }}>
                    {user ? (
                        <div>
                            <div style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 12,
                                marginBottom: 12
                            }}>
                                <div style={{
                                    width: 36,
                                    height: 36,
                                    backgroundColor: '#3b82f6',
                                    borderRadius: '50%',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    fontSize: 14,
                                    fontWeight: 'bold'
                                }}>
                                    {user.first_name?.[0] || user.username?.[0] || 'U'}
                                </div>
                                <div style={{ flex: 1, minWidth: 0 }}>
                                    <div style={{
                                        fontWeight: 500,
                                        fontSize: 14,
                                        overflow: 'hidden',
                                        textOverflow: 'ellipsis',
                                        whiteSpace: 'nowrap'
                                    }}>
                                        {user.first_name} {user.last_name}
                                    </div>
                                    <div style={{
                                        fontSize: 12,
                                        color: '#9ca3af'
                                    }}>
                                        {userProfile ? `Perfil: ${userProfile}` : user.username}
                                    </div>
                                </div>
                            </div>
                            <button
                                onClick={handleLogout}
                                style={{
                                    width: '100%',
                                    padding: '8px 12px',
                                    backgroundColor: 'transparent',
                                    border: '1px solid #4b5563',
                                    borderRadius: 6,
                                    color: '#9ca3af',
                                    fontSize: 13,
                                    cursor: 'pointer',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    gap: 6,
                                    transition: 'all 0.2s'
                                }}
                                onMouseEnter={(e) => {
                                    e.currentTarget.style.backgroundColor = '#374151'
                                    e.currentTarget.style.color = 'white'
                                }}
                                onMouseLeave={(e) => {
                                    e.currentTarget.style.backgroundColor = 'transparent'
                                    e.currentTarget.style.color = '#9ca3af'
                                }}
                            >
                                <span>Sair</span>
                            </button>
                        </div>
                    ) : (
                        <div style={{ color: '#9ca3af', fontSize: 14, textAlign: 'center' }}>
                            Carregando...
                        </div>
                    )}
                </div>
            </aside>

            {/* Main Content */}
            <main style={{
                flex: 1,
                marginLeft: 260,
                minHeight: '100vh'
            }}>
                <Outlet />
            </main>
        </div>
    )
}

export default MainLayout
