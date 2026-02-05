/**
 * Toast Context - Sistema de Notificações
 * 
 * Fornece feedback visual para ações do usuário
 */

import { createContext, useContext, useState, useCallback } from 'react'
import PropTypes from 'prop-types'

const ToastContext = createContext(null)

export const useToast = () => {
    const context = useContext(ToastContext)
    if (!context) {
        throw new Error('useToast must be used within ToastProvider')
    }
    return context
}

export function ToastProvider({ children }) {
    const [toasts, setToasts] = useState([])

    const addToast = useCallback((message, type = 'info', duration = 5000) => {
        const id = Date.now() + Math.random()
        const newToast = { id, message, type, duration }
        
        setToasts(prev => [...prev, newToast])
        
        // Auto remove
        if (duration > 0) {
            setTimeout(() => {
                removeToast(id)
            }, duration)
        }
        
        return id
    }, [])

    const removeToast = useCallback((id) => {
        setToasts(prev => prev.filter(toast => toast.id !== id))
    }, [])

    const success = useCallback((message, duration) => {
        return addToast(message, 'success', duration)
    }, [addToast])

    const error = useCallback((message, duration) => {
        return addToast(message, 'error', duration)
    }, [addToast])

    const warning = useCallback((message, duration) => {
        return addToast(message, 'warning', duration)
    }, [addToast])

    const info = useCallback((message, duration) => {
        return addToast(message, 'info', duration)
    }, [addToast])

    const value = {
        toasts,
        addToast,
        removeToast,
        success,
        error,
        warning,
        info
    }

    return (
        <ToastContext.Provider value={value}>
            {children}
            <ToastContainer toasts={toasts} onRemove={removeToast} />
        </ToastContext.Provider>
    )
}

ToastProvider.propTypes = {
    children: PropTypes.node.isRequired
}

// Toast Container Component
function ToastContainer({ toasts, onRemove }) {
    if (toasts.length === 0) return null

    return (
        <div
            style={{
                position: 'fixed',
                top: 'var(--spacing-4)',
                right: 'var(--spacing-4)',
                zIndex: 9999,
                display: 'flex',
                flexDirection: 'column',
                gap: 'var(--spacing-2)',
                maxWidth: '400px'
            }}
        >
            {toasts.map(toast => (
                <ToastItem key={toast.id} toast={toast} onRemove={onRemove} />
            ))}
        </div>
    )
}

ToastContainer.propTypes = {
    toasts: PropTypes.array.isRequired,
    onRemove: PropTypes.func.isRequired
}

// Individual Toast Item
function ToastItem({ toast, onRemove }) {
    const styles = {
        success: {
            background: '#dcfce7',
            border: '1px solid #86efac',
            color: '#166534'
        },
        error: {
            background: '#fee2e2',
            border: '1px solid #fca5a5',
            color: '#991b1b'
        },
        warning: {
            background: '#fef3c7',
            border: '1px solid #fcd34d',
            color: '#92400e'
        },
        info: {
            background: '#dbeafe',
            border: '1px solid #93c5fd',
            color: '#1e40af'
        }
    }

    const icons = {
        success: '✓',
        error: '✕',
        warning: '⚠',
        info: 'ℹ'
    }

    return (
        <div
            style={{
                padding: 'var(--spacing-3) var(--spacing-4)',
                borderRadius: 'var(--radius-lg)',
                boxShadow: 'var(--shadow-lg)',
                display: 'flex',
                alignItems: 'center',
                gap: 'var(--spacing-3)',
                minWidth: '300px',
                animation: 'slideInRight 0.3s ease',
                ...styles[toast.type]
            }}
        >
            <span style={{ 
                fontSize: '1.25rem', 
                fontWeight: 'bold',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                width: '24px',
                height: '24px',
                borderRadius: '50%',
                background: 'rgba(255,255,255,0.5)'
            }}>
                {icons[toast.type]}
            </span>
            <span style={{ flex: 1, fontSize: '0.875rem', fontWeight: 500 }}>
                {toast.message}
            </span>
            <button
                onClick={() => onRemove(toast.id)}
                style={{
                    background: 'none',
                    border: 'none',
                    cursor: 'pointer',
                    fontSize: '1.25rem',
                    lineHeight: 1,
                    padding: 'var(--spacing-1)',
                    borderRadius: 'var(--radius-md)',
                    opacity: 0.6,
                    transition: 'opacity 0.2s'
                }}
                onMouseEnter={(e) => e.target.style.opacity = 1}
                onMouseLeave={(e) => e.target.style.opacity = 0.6}
            >
                ×
            </button>
            <style>{`
                @keyframes slideInRight {
                    from {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }
            `}</style>
        </div>
    )
}

ToastItem.propTypes = {
    toast: PropTypes.shape({
        id: PropTypes.number.isRequired,
        message: PropTypes.string.isRequired,
        type: PropTypes.oneOf(['success', 'error', 'warning', 'info']).isRequired,
        duration: PropTypes.number.isRequired
    }).isRequired,
    onRemove: PropTypes.func.isRequired
}
