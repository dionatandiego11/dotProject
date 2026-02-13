import { NavLink, Outlet, useNavigate, useLocation } from 'react-router-dom'
import { useState, useEffect } from 'react'
import ErrorBoundary from '../../components/ErrorBoundary'

const menuItems = [
    { path: '/admin', label: 'Painel', icon: '📊', end: true },
    { path: '/admin/prefeitura', label: 'Prefeitura', icon: '🏛️' },
    { path: '/admin/niveis', label: 'Níveis Hierárquicos', icon: '📐' },
    { path: '/admin/unidades', label: 'Unidades', icon: '🏢' },
    { path: '/admin/usuarios', label: 'Usuários', icon: '👤' },
    { path: '/admin/organograma', label: 'Organograma', icon: '🗂️' },
]

const SIDEBAR_KEY = 'admin_sidebar_open'

function AdminLayout() {
    const navigate = useNavigate()
    const location = useLocation()
    const [menuAberto, setMenuAberto] = useState(() => {
        const saved = localStorage.getItem(SIDEBAR_KEY)
        return saved !== null ? saved === 'true' : true
    })

    useEffect(() => {
        localStorage.setItem(SIDEBAR_KEY, String(menuAberto))
    }, [menuAberto])

    const isSetup = location.pathname === '/admin/setup'

    return (
        <div style={{ display: 'flex', minHeight: '100vh', background: '#f8fafc' }}>
            {/* Sidebar */}
            <aside style={{
                width: menuAberto ? 260 : 64,
                background: 'linear-gradient(180deg, #0f172a 0%, #1e293b 100%)',
                color: 'white',
                display: 'flex',
                flexDirection: 'column',
                transition: 'width 0.25s cubic-bezier(0.4, 0, 0.2, 1)',
                flexShrink: 0,
                position: 'sticky',
                top: 0,
                height: '100vh',
                overflow: 'hidden',
            }}>
                {/* Header */}
                <div style={{
                    padding: menuAberto ? '20px 16px' : '20px 12px',
                    borderBottom: '1px solid rgba(255,255,255,0.08)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: menuAberto ? 'space-between' : 'center',
                    gap: 8,
                }}>
                    {menuAberto && (
                        <div>
                            <div style={{ fontSize: 15, fontWeight: 700, letterSpacing: '-0.01em' }}>
                                ⚙️ Administração
                            </div>
                            <div style={{ fontSize: 11, color: '#94a3b8', marginTop: 2 }}>
                                Configuração do sistema
                            </div>
                        </div>
                    )}
                    <button
                        onClick={() => setMenuAberto(!menuAberto)}
                        style={{
                            background: 'rgba(255,255,255,0.06)',
                            border: '1px solid rgba(255,255,255,0.1)',
                            color: '#94a3b8',
                            cursor: 'pointer',
                            borderRadius: 6,
                            width: 32,
                            height: 32,
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            fontSize: 14,
                            flexShrink: 0,
                            transition: 'background 0.2s',
                        }}
                        title={menuAberto ? 'Recolher menu' : 'Expandir menu'}
                    >
                        {menuAberto ? '◀' : '▶'}
                    </button>
                </div>

                {/* Menu items */}
                <nav style={{ flex: 1, padding: '12px 8px', overflowY: 'auto' }}>
                    {menuItems.map((item) => (
                        <NavLink
                            key={item.path}
                            to={item.path}
                            end={item.end}
                            style={({ isActive }) => ({
                                display: 'flex',
                                alignItems: 'center',
                                gap: 12,
                                padding: menuAberto ? '10px 12px' : '10px 0',
                                borderRadius: 8,
                                color: isActive ? 'white' : '#94a3b8',
                                backgroundColor: isActive ? '#3b82f6' : 'transparent',
                                textDecoration: 'none',
                                marginBottom: 2,
                                fontSize: 13,
                                fontWeight: isActive ? 600 : 400,
                                transition: 'all 0.15s',
                                justifyContent: menuAberto ? 'flex-start' : 'center',
                                whiteSpace: 'nowrap',
                            })}
                            title={!menuAberto ? item.label : undefined}
                        >
                            <span style={{ fontSize: 17, flexShrink: 0 }}>{item.icon}</span>
                            {menuAberto && <span>{item.label}</span>}
                        </NavLink>
                    ))}

                    {/* Divider + Setup link */}
                    <div style={{
                        borderTop: '1px solid rgba(255,255,255,0.06)',
                        margin: '12px 0 8px',
                    }} />
                    <NavLink
                        to="/admin/setup"
                        style={({ isActive }) => ({
                            display: 'flex',
                            alignItems: 'center',
                            gap: 12,
                            padding: menuAberto ? '10px 12px' : '10px 0',
                            borderRadius: 8,
                            color: isActive ? '#fbbf24' : '#64748b',
                            backgroundColor: isActive ? 'rgba(251,191,36,0.1)' : 'transparent',
                            textDecoration: 'none',
                            fontSize: 13,
                            fontWeight: isActive ? 600 : 400,
                            justifyContent: menuAberto ? 'flex-start' : 'center',
                        })}
                        title={!menuAberto ? 'Setup Wizard' : undefined}
                    >
                        <span style={{ fontSize: 17, flexShrink: 0 }}>🧙</span>
                        {menuAberto && <span>Setup Wizard</span>}
                    </NavLink>
                </nav>

                {/* Footer */}
                <div style={{
                    padding: menuAberto ? '12px 16px' : '12px 8px',
                    borderTop: '1px solid rgba(255,255,255,0.06)',
                }}>
                    <button
                        onClick={() => navigate('/')}
                        style={{
                            background: 'rgba(255,255,255,0.04)',
                            border: '1px solid rgba(255,255,255,0.08)',
                            color: '#94a3b8',
                            cursor: 'pointer',
                            width: '100%',
                            textAlign: menuAberto ? 'left' : 'center',
                            padding: '8px 12px',
                            borderRadius: 8,
                            fontSize: 13,
                            transition: 'background 0.2s',
                        }}
                    >
                        ← {menuAberto && 'Voltar ao sistema'}
                    </button>
                </div>
            </aside>

            {/* Content */}
            <main style={{
                flex: 1,
                minWidth: 0,
                padding: isSetup ? 0 : '24px 32px',
                overflowY: 'auto',
            }}>
                <ErrorBoundary>
                    <Outlet />
                </ErrorBoundary>
            </main>
        </div>
    )
}

export default AdminLayout
