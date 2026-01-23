import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { login } from '../services/api'

function Login() {
    const [username, setUsername] = useState('')
    const [password, setPassword] = useState('')
    const [loading, setLoading] = useState(false)
    const [error, setError] = useState(null)
    const navigate = useNavigate()

    async function handleSubmit(e) {
        e.preventDefault()
        setError(null)

        if (!username || !password) {
            setError('Preencha usuário e senha')
            return
        }

        try {
            setLoading(true)
            await login(username, password)
            navigate('/')
        } catch (err) {
            setError(err.message || 'Falha no login')
        } finally {
            setLoading(false)
        }
    }

    return (
        <div style={{
            minHeight: '100vh',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            background: 'linear-gradient(135deg, var(--color-primary-600) 0%, var(--color-primary-700) 100%)'
        }}>
            <div style={{
                background: 'white',
                borderRadius: 'var(--radius-xl)',
                padding: 'var(--spacing-8)',
                width: '100%',
                maxWidth: 400,
                boxShadow: 'var(--shadow-lg)'
            }}>
                {/* Logo */}
                <div style={{ textAlign: 'center', marginBottom: 'var(--spacing-6)' }}>
                    <div style={{
                        width: 64,
                        height: 64,
                        background: 'var(--color-primary-600)',
                        borderRadius: 'var(--radius-lg)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        margin: '0 auto var(--spacing-4)',
                        color: 'white',
                        fontSize: '1.5rem',
                        fontWeight: 700
                    }}>
                        DP
                    </div>
                    <h1 style={{
                        fontSize: '1.5rem',
                        fontWeight: 700,
                        color: 'var(--color-gray-900)',
                        marginBottom: 'var(--spacing-1)'
                    }}>
                        dotProject
                    </h1>
                    <p style={{ color: 'var(--color-gray-500)' }}>
                        Gestão de Projetos
                    </p>
                </div>

                {/* Error */}
                {error && (
                    <div style={{
                        background: '#fee2e2',
                        color: '#991b1b',
                        padding: 'var(--spacing-3)',
                        borderRadius: 'var(--radius-md)',
                        marginBottom: 'var(--spacing-4)',
                        fontSize: '0.875rem'
                    }}>
                        {error}
                    </div>
                )}

                {/* Form */}
                <form onSubmit={handleSubmit}>
                    <div style={{ marginBottom: 'var(--spacing-4)' }}>
                        <label style={{
                            display: 'block',
                            marginBottom: 'var(--spacing-2)',
                            fontSize: '0.875rem',
                            fontWeight: 500,
                            color: 'var(--color-gray-700)'
                        }}>
                            Usuário
                        </label>
                        <input
                            type="text"
                            value={username}
                            onChange={(e) => setUsername(e.target.value)}
                            placeholder="Digite seu usuário"
                            style={{
                                width: '100%',
                                padding: 'var(--spacing-3)',
                                border: '1px solid var(--color-gray-300)',
                                borderRadius: 'var(--radius-md)',
                                fontSize: '1rem',
                                outline: 'none'
                            }}
                            autoFocus
                        />
                    </div>

                    <div style={{ marginBottom: 'var(--spacing-6)' }}>
                        <label style={{
                            display: 'block',
                            marginBottom: 'var(--spacing-2)',
                            fontSize: '0.875rem',
                            fontWeight: 500,
                            color: 'var(--color-gray-700)'
                        }}>
                            Senha
                        </label>
                        <input
                            type="password"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            placeholder="Digite sua senha"
                            style={{
                                width: '100%',
                                padding: 'var(--spacing-3)',
                                border: '1px solid var(--color-gray-300)',
                                borderRadius: 'var(--radius-md)',
                                fontSize: '1rem',
                                outline: 'none'
                            }}
                        />
                    </div>

                    <button
                        type="submit"
                        disabled={loading}
                        style={{
                            width: '100%',
                            padding: 'var(--spacing-3)',
                            background: loading ? 'var(--color-gray-400)' : 'var(--color-primary-600)',
                            color: 'white',
                            border: 'none',
                            borderRadius: 'var(--radius-md)',
                            fontSize: '1rem',
                            fontWeight: 500,
                            cursor: loading ? 'not-allowed' : 'pointer',
                            transition: 'background 0.15s ease'
                        }}
                    >
                        {loading ? 'Entrando...' : 'Entrar'}
                    </button>
                </form>

                <p style={{
                    textAlign: 'center',
                    marginTop: 'var(--spacing-4)',
                    fontSize: '0.75rem',
                    color: 'var(--color-gray-400)'
                }}>
                    dotProject v1.0 - Modernizado
                </p>
            </div>
        </div>
    )
}

export default Login
