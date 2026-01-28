import { Outlet, NavLink, useNavigate } from 'react-router-dom'
import { useState, useEffect } from 'react'
import { getCurrentUser, logout } from '../services/api'
import { NotificationBell } from './notifications'

function Layout() {
    const [user, setUser] = useState(null)
    const navigate = useNavigate()

    useEffect(() => {
        loadUser()
    }, [])

    async function loadUser() {
        try {
            const data = await getCurrentUser()
            setUser(data)
        } catch (error) {
            console.error('Failed to load user:', error)
        }
    }

    function handleLogout() {
        logout()
        navigate('/login')
    }

    const handleNotificationClick = (notification) => {
        // Navega para a entidade da notificação
        if (notification.entity_type === 'task' && notification.entity_id) {
            navigate(`/tasks/${notification.entity_id}`)
        } else if (notification.entity_type === 'project' && notification.entity_id) {
            navigate(`/projects/${notification.entity_id}`)
        }
    }

    return (
        <div className="app-layout">
            {/* Sidebar */}
            <aside className="sidebar">
                <div className="sidebar-header">
                    <div className="sidebar-logo">DP</div>
                    <span className="sidebar-title">dotProject</span>
                </div>

                <nav className="sidebar-nav">
                    <div className="nav-section">
                        <div className="nav-section-title">Principal</div>
                        <NavLink to="/" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <rect x="3" y="3" width="7" height="7" rx="1" />
                                <rect x="14" y="3" width="7" height="7" rx="1" />
                                <rect x="3" y="14" width="7" height="7" rx="1" />
                                <rect x="14" y="14" width="7" height="7" rx="1" />
                            </svg>
                            Dashboard
                        </NavLink>
                        <NavLink to="/projects" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <path d="M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z" />
                            </svg>
                            Projetos
                        </NavLink>
                        <NavLink to="/tasks" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <polyline points="9 11 12 14 22 4" />
                                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
                            </svg>
                            Tarefas
                        </NavLink>
                        <NavLink to="/kanban" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <rect x="3" y="3" width="6" height="18" rx="2" />
                                <rect x="10" y="3" width="6" height="18" rx="2" />
                                <rect x="17" y="3" width="4" height="18" rx="2" />
                            </svg>
                            Kanban
                        </NavLink>
                    </div>
                </nav>

                {/* User Section */}
                <div style={{
                    padding: 'var(--spacing-4)',
                    borderTop: '1px solid var(--color-gray-200)'
                }}>
                    {user && (
                        <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--spacing-3)' }}>
                            <div className="user-avatar">
                                {user.first_name?.[0] || 'U'}
                            </div>
                            <div style={{ flex: 1, minWidth: 0 }}>
                                <div style={{
                                    fontWeight: 500,
                                    fontSize: '0.875rem',
                                    overflow: 'hidden',
                                    textOverflow: 'ellipsis',
                                    whiteSpace: 'nowrap'
                                }}>
                                    {user.first_name} {user.last_name}
                                </div>
                                <button
                                    onClick={handleLogout}
                                    style={{
                                        background: 'none',
                                        border: 'none',
                                        color: 'var(--color-danger-500)',
                                        fontSize: '0.75rem',
                                        cursor: 'pointer',
                                        padding: 0
                                    }}
                                >
                                    Sair
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </aside>

            {/* Main Content */}
            <main className="main-content">
                {/* Header com NotificationBell */}
                <header style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    padding: '1rem 1.5rem',
                    borderBottom: '1px solid var(--color-gray-200)',
                    backgroundColor: 'white',
                }}>
                    <div>
                        {/* Breadcrumbs ou título da página podem ir aqui */}
                    </div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
                        <NotificationBell onNotificationClick={handleNotificationClick} />
                    </div>
                </header>

                {/* Page Content */}
                <div style={{ padding: '1.5rem', flex: 1, overflow: 'auto' }}>
                    <Outlet />
                </div>
            </main>
        </div>
    )
}

export default Layout
