import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { getAdminOnboardingReadiness } from '../../services/admin'

const cardStyle = (bg, border) => ({
    padding: '20px 24px',
    background: bg,
    borderRadius: 12,
    border: `1px solid ${border}`,
    cursor: 'pointer',
    transition: 'transform 0.15s, box-shadow 0.15s',
    display: 'flex',
    flexDirection: 'column',
    gap: 8,
})

function StatCard({ icon, label, value, bg, border, onClick }) {
    return (
        <div
            style={cardStyle(bg, border)}
            onClick={onClick}
            onMouseEnter={(e) => { e.currentTarget.style.transform = 'translateY(-2px)'; e.currentTarget.style.boxShadow = '0 4px 12px rgba(0,0,0,0.08)' }}
            onMouseLeave={(e) => { e.currentTarget.style.transform = ''; e.currentTarget.style.boxShadow = '' }}
        >
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                <span style={{ fontSize: 28 }}>{icon}</span>
                <span style={{ fontSize: 32, fontWeight: 700, color: '#1e293b' }}>{value}</span>
            </div>
            <div style={{ fontSize: 14, color: '#475569', fontWeight: 500 }}>{label}</div>
        </div>
    )
}

function DashboardAdmin() {
    const navigate = useNavigate()
    const [stats, setStats] = useState(null)
    const [loading, setLoading] = useState(true)
    const [error, setError] = useState(null)

    useEffect(() => {
        let cancelled = false
        getAdminOnboardingReadiness()
            .then((data) => {
                if (!cancelled) setStats(data)
            })
            .catch((err) => {
                if (!cancelled) setError(err.message || 'Erro ao carregar dados')
            })
            .finally(() => {
                if (!cancelled) setLoading(false)
            })
        return () => { cancelled = true }
    }, [])

    const isFirstTime = stats && (stats.niveis_ativos || 0) === 0 && (stats.unidades_ativas || 0) === 0

    if (loading) {
        return (
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', minHeight: 400 }}>
                <div style={{ textAlign: 'center', color: '#64748b' }}>
                    <div style={{ fontSize: 32, marginBottom: 8 }}>⏳</div>
                    <div>Carregando painel...</div>
                </div>
            </div>
        )
    }

    return (
        <div>
            {/* Header */}
            <div style={{ marginBottom: 32 }}>
                <h1 style={{ margin: 0, fontSize: 24, fontWeight: 700, color: '#0f172a' }}>
                    Painel Administrativo
                </h1>
                <p style={{ margin: '4px 0 0', color: '#64748b', fontSize: 15 }}>
                    Gerencie a estrutura organizacional da sua prefeitura
                </p>
            </div>

            {/* First-time banner */}
            {isFirstTime && (
                <div style={{
                    background: 'linear-gradient(135deg, #eff6ff 0%, #e0f2fe 100%)',
                    border: '1px solid #93c5fd',
                    borderRadius: 12,
                    padding: '24px 28px',
                    marginBottom: 28,
                    display: 'flex',
                    alignItems: 'center',
                    gap: 20,
                }}>
                    <span style={{ fontSize: 40 }}>🧙</span>
                    <div style={{ flex: 1 }}>
                        <div style={{ fontWeight: 700, fontSize: 16, color: '#1e40af', marginBottom: 4 }}>
                            Bem-vindo! Configure sua prefeitura
                        </div>
                        <div style={{ fontSize: 14, color: '#3b82f6' }}>
                            Use o Setup Wizard para configurar níveis, unidades e usuários em poucos passos.
                        </div>
                    </div>
                    <button
                        onClick={() => navigate('/admin/setup')}
                        style={{
                            background: '#3b82f6',
                            color: 'white',
                            border: 'none',
                            borderRadius: 8,
                            padding: '10px 24px',
                            fontWeight: 600,
                            cursor: 'pointer',
                            fontSize: 14,
                            whiteSpace: 'nowrap',
                        }}
                    >
                        Iniciar Setup →
                    </button>
                </div>
            )}

            {error && (
                <div style={{
                    background: '#fef2f2',
                    border: '1px solid #fca5a5',
                    borderRadius: 8,
                    padding: 16,
                    marginBottom: 24,
                    color: '#991b1b',
                    fontSize: 14,
                }}>
                    ⚠️ {error}
                </div>
            )}

            {/* Stats grid */}
            <div style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
                gap: 16,
                marginBottom: 32,
            }}>
                <StatCard
                    icon="📐"
                    label="Níveis Hierárquicos"
                    value={stats?.niveis_ativos ?? '—'}
                    bg="#f0f9ff"
                    border="#bae6fd"
                    onClick={() => navigate('/admin/niveis')}
                />
                <StatCard
                    icon="🏢"
                    label="Unidades Ativas"
                    value={stats?.unidades_ativas ?? '—'}
                    bg="#f0fdf4"
                    border="#bbf7d0"
                    onClick={() => navigate('/admin/unidades')}
                />
                <StatCard
                    icon="👤"
                    label="Vínculos Ativos"
                    value={stats?.vinculos_ativos ?? '—'}
                    bg="#faf5ff"
                    border="#e9d5ff"
                    onClick={() => navigate('/admin/usuarios')}
                />
            </div>

            {/* Quick actions */}
            <h2 style={{ fontSize: 16, fontWeight: 600, color: '#334155', marginBottom: 16 }}>
                Ações Rápidas
            </h2>
            <div style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fit, minmax(250px, 1fr))',
                gap: 12,
            }}>
                {[
                    { icon: '🏛️', label: 'Dados da Prefeitura', desc: 'Nome, CNPJ, cidade e estado', path: '/admin/prefeitura' },
                    { icon: '🏢', label: 'Cadastrar Unidade', desc: 'Adicionar secretaria ou departamento', path: '/admin/unidades' },
                    { icon: '👤', label: 'Novo Usuário', desc: 'Vincular servidor a uma unidade', path: '/admin/usuarios' },
                    { icon: '🗂️', label: 'Ver Organograma', desc: 'Visualizar estrutura completa', path: '/admin/organograma' },
                    { icon: '🧙', label: 'Reconfigurar Setup', desc: 'Executar o wizard novamente', path: '/admin/setup' },
                ].map((action) => (
                    <div
                        key={action.path}
                        onClick={() => navigate(action.path)}
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 14,
                            padding: '14px 16px',
                            background: 'white',
                            border: '1px solid #e2e8f0',
                            borderRadius: 10,
                            cursor: 'pointer',
                            transition: 'border-color 0.15s',
                        }}
                        onMouseEnter={(e) => { e.currentTarget.style.borderColor = '#93c5fd' }}
                        onMouseLeave={(e) => { e.currentTarget.style.borderColor = '#e2e8f0' }}
                    >
                        <span style={{ fontSize: 24 }}>{action.icon}</span>
                        <div>
                            <div style={{ fontWeight: 600, fontSize: 14, color: '#1e293b' }}>{action.label}</div>
                            <div style={{ fontSize: 12, color: '#94a3b8' }}>{action.desc}</div>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    )
}

export default DashboardAdmin
