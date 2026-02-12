/**
 * ErrorBoundary Component
 * 
 * Captura erros de runtime no React, evitando crash total da aplicação.
 * Exibe uma tela de fallback amigável com opção de recarregar.
 */

import { Component } from 'react'
import PropTypes from 'prop-types'

class ErrorBoundary extends Component {
    constructor(props) {
        super(props)
        this.state = {
            hasError: false,
            error: null,
            errorInfo: null,
        }
    }

    static getDerivedStateFromError(error) {
        return { hasError: true, error }
    }

    componentDidCatch(error, errorInfo) {
        this.setState({ errorInfo })

        // Log para futura integração com Sentry/similar
        console.error('[ErrorBoundary]', error, errorInfo)
    }

    handleReload = () => {
        this.setState({ hasError: false, error: null, errorInfo: null })
    }

    handleFullReload = () => {
        window.location.reload()
    }

    render() {
        if (this.state.hasError) {
            // Se o caller passou um fallback customizado, usar
            if (this.props.fallback) {
                return this.props.fallback
            }

            const isDev = import.meta.env?.DEV

            return (
                <div style={styles.container}>
                    <div style={styles.card}>
                        <div style={styles.iconWrapper}>
                            <svg
                                width="48"
                                height="48"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="var(--color-danger-500, #EF4444)"
                                strokeWidth="2"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                            >
                                <circle cx="12" cy="12" r="10" />
                                <line x1="12" y1="8" x2="12" y2="12" />
                                <line x1="12" y1="16" x2="12.01" y2="16" />
                            </svg>
                        </div>

                        <h2 style={styles.title}>Algo deu errado</h2>
                        <p style={styles.message}>
                            Ocorreu um erro inesperado. Tente novamente ou recarregue a página.
                        </p>

                        {isDev && this.state.error && (
                            <details style={styles.details}>
                                <summary style={styles.summary}>Detalhes do erro (dev)</summary>
                                <pre style={styles.pre}>
                                    {this.state.error.toString()}
                                    {this.state.errorInfo?.componentStack}
                                </pre>
                            </details>
                        )}

                        <div style={styles.actions}>
                            <button
                                onClick={this.handleReload}
                                style={styles.buttonPrimary}
                                onMouseEnter={(e) => {
                                    e.target.style.backgroundColor = 'var(--color-primary-700, #1D4ED8)'
                                }}
                                onMouseLeave={(e) => {
                                    e.target.style.backgroundColor = 'var(--color-primary-600, #2563EB)'
                                }}
                            >
                                Tentar novamente
                            </button>
                            <button
                                onClick={this.handleFullReload}
                                style={styles.buttonSecondary}
                                onMouseEnter={(e) => {
                                    e.target.style.backgroundColor = 'var(--color-gray-200, #E5E7EB)'
                                }}
                                onMouseLeave={(e) => {
                                    e.target.style.backgroundColor = 'var(--color-gray-100, #F3F4F6)'
                                }}
                            >
                                Recarregar página
                            </button>
                        </div>
                    </div>
                </div>
            )
        }

        return this.props.children
    }
}

const styles = {
    container: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        minHeight: '100vh',
        padding: 'var(--spacing-4, 1rem)',
        backgroundColor: 'var(--color-gray-50, #F9FAFB)',
        fontFamily: 'var(--font-family-sans, system-ui, -apple-system, sans-serif)',
    },
    card: {
        maxWidth: '480px',
        width: '100%',
        backgroundColor: '#ffffff',
        borderRadius: 'var(--radius-lg, 12px)',
        boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1)',
        padding: 'var(--spacing-8, 2rem)',
        textAlign: 'center',
    },
    iconWrapper: {
        marginBottom: 'var(--spacing-4, 1rem)',
    },
    title: {
        fontSize: 'var(--font-size-xl, 1.25rem)',
        fontWeight: 'var(--font-weight-semibold, 600)',
        color: 'var(--color-gray-900, #111827)',
        margin: '0 0 var(--spacing-2, 0.5rem) 0',
    },
    message: {
        fontSize: 'var(--font-size-base, 1rem)',
        color: 'var(--color-gray-500, #6B7280)',
        margin: '0 0 var(--spacing-6, 1.5rem) 0',
        lineHeight: 1.5,
    },
    details: {
        textAlign: 'left',
        marginBottom: 'var(--spacing-6, 1.5rem)',
        borderRadius: 'var(--radius-md, 8px)',
        backgroundColor: 'var(--color-gray-50, #F9FAFB)',
        border: '1px solid var(--color-gray-200, #E5E7EB)',
        overflow: 'hidden',
    },
    summary: {
        padding: 'var(--spacing-3, 0.75rem)',
        cursor: 'pointer',
        fontSize: 'var(--font-size-sm, 0.875rem)',
        color: 'var(--color-gray-600, #4B5563)',
        fontWeight: 'var(--font-weight-medium, 500)',
    },
    pre: {
        padding: 'var(--spacing-3, 0.75rem)',
        fontSize: '0.75rem',
        color: 'var(--color-danger-700, #B91C1C)',
        whiteSpace: 'pre-wrap',
        wordBreak: 'break-word',
        margin: 0,
        maxHeight: '200px',
        overflow: 'auto',
    },
    actions: {
        display: 'flex',
        gap: 'var(--spacing-3, 0.75rem)',
        justifyContent: 'center',
    },
    buttonPrimary: {
        padding: 'var(--spacing-2, 0.5rem) var(--spacing-4, 1rem)',
        backgroundColor: 'var(--color-primary-600, #2563EB)',
        color: '#ffffff',
        border: 'none',
        borderRadius: 'var(--radius-md, 8px)',
        fontSize: 'var(--font-size-sm, 0.875rem)',
        fontWeight: 'var(--font-weight-medium, 500)',
        cursor: 'pointer',
        transition: 'background-color 150ms ease',
    },
    buttonSecondary: {
        padding: 'var(--spacing-2, 0.5rem) var(--spacing-4, 1rem)',
        backgroundColor: 'var(--color-gray-100, #F3F4F6)',
        color: 'var(--color-gray-700, #374151)',
        border: '1px solid var(--color-gray-300, #D1D5DB)',
        borderRadius: 'var(--radius-md, 8px)',
        fontSize: 'var(--font-size-sm, 0.875rem)',
        fontWeight: 'var(--font-weight-medium, 500)',
        cursor: 'pointer',
        transition: 'background-color 150ms ease',
    },
}

ErrorBoundary.propTypes = {
    children: PropTypes.node.isRequired,
    fallback: PropTypes.node,
}

export default ErrorBoundary
