/**
 * Componente de loading com spinner
 */

function Loading({ fullScreen = false }) {
    const style = fullScreen ? {
        position: 'fixed',
        top: 0,
        left: 0,
        right: 0,
        bottom: 0,
        background: 'var(--color-bg, #ffffff)',
        zIndex: 9999
    } : {
        padding: 'var(--spacing-8, 2rem)'
    }

    return (
        <div style={{
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
            justifyContent: 'center',
            minHeight: fullScreen ? '100vh' : '200px',
            ...style
        }}>
            <div style={{
                width: '48px',
                height: '48px',
                border: '4px solid var(--color-gray-200, #e5e7eb)',
                borderTopColor: 'var(--color-primary-600, #2563eb)',
                borderRadius: '50%',
                animation: 'spin 1s linear infinite'
            }} />
            <p style={{
                marginTop: 'var(--spacing-4, 1rem)',
                color: 'var(--color-gray-600, #4b5563)',
                fontSize: '0.875rem'
            }}>
                Carregando...
            </p>
            <style>{`
                @keyframes spin {
                    to { transform: rotate(360deg); }
                }
            `}</style>
        </div>
    )
}

export default Loading
