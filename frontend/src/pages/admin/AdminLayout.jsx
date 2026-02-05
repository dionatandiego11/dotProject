import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import { useState } from 'react'

function AdminLayout() {
    const navigate = useNavigate()
    const [menuAberto, setMenuAberto] = useState(true)

    const menuItems = [
        { path: '/admin', label: 'Painel', icon: '📊' },
        { path: '/admin/unidades', label: 'Unidades Organizacionais', icon: '🏢' },
    ]

    return (
        <div style={{ display: 'flex', minHeight: '100vh' }}>
            {/* Sidebar */}
            <aside style={{
                width: menuAberto ? 250 : 60,
                backgroundColor: '#1f2937',
                color: 'white',
                display: 'flex',
                flexDirection: 'column',
                transition: 'width 0.3s'
            }}>
                {/* Header */}
                <div style={{ padding: 16, borderBottom: '1px solid #374151' }}>
                    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                        {menuAberto && <h2 style={{ margin: 0, fontSize: 16 }}>🏛️ Estrutura</h2>}
                        <button
                            onClick={() => setMenuAberto(!menuAberto)}
                            style={{
                                background: 'none',
                                border: 'none',
                                color: 'white',
                                cursor: 'pointer'
                            }}
                        >
                            {menuAberto ? '◀' : '▶'}
                        </button>
                    </div>
                </div>

                {/* Menu */}
                <nav style={{ flex: 1, padding: 8 }}>
                    {menuItems.map((item) => (
                        <NavLink
                            key={item.path}
                            to={item.path}
                            end={item.path === '/admin'}
                            style={({ isActive }) => ({
                                display: 'flex',
                                alignItems: 'center',
                                gap: 12,
                                padding: 12,
                                borderRadius: 6,
                                color: isActive ? 'white' : '#9ca3af',
                                backgroundColor: isActive ? '#3b82f6' : 'transparent',
                                textDecoration: 'none',
                                marginBottom: 4
                            })}
                        >
                            <span style={{ fontSize: 18 }}>{item.icon}</span>
                            {menuAberto && <span style={{ fontSize: 14 }}>{item.label}</span>}
                        </NavLink>
                    ))}
                </nav>

                {/* Footer */}
                <div style={{ padding: 16, borderTop: '1px solid #374151' }}>
                    <button
                        onClick={() => navigate('/')}
                        style={{
                            background: 'none',
                            border: 'none',
                            color: '#9ca3af',
                            cursor: 'pointer',
                            width: '100%',
                            textAlign: 'left'
                        }}
                    >
                        ← {menuAberto && 'Voltar'}
                    </button>
                </div>
            </aside>

            {/* Content */}
            <main style={{ flex: 1, backgroundColor: '#f9fafb' }}>
                <Outlet />
            </main>
        </div>
    )
}

export default AdminLayout




