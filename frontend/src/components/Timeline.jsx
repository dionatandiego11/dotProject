import { memo } from 'react'

const styles = {
    container: {
        padding: 20,
        overflowX: 'auto'
    },
    timeline: {
        display: 'flex',
        alignItems: 'center',
        minWidth: 'max-content',
        padding: '20px 0'
    },
    item: {
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        position: 'relative',
        minWidth: 120
    },
    connector: {
        position: 'absolute',
        top: 20,
        left: '50%',
        width: '100%',
        height: 4,
        zIndex: 0
    },
    circle: {
        width: 40,
        height: 40,
        borderRadius: '50%',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        fontSize: 18,
        fontWeight: 600,
        zIndex: 1,
        boxShadow: '0 2px 8px rgba(0,0,0,0.15)',
        cursor: 'pointer',
        transition: 'transform 0.2s, box-shadow 0.2s'
    },
    circleHover: {
        transform: 'scale(1.1)',
        boxShadow: '0 4px 12px rgba(0,0,0,0.2)'
    },
    label: {
        marginTop: 12,
        fontSize: 13,
        fontWeight: 500,
        textAlign: 'center',
        maxWidth: 100
    },
    date: {
        fontSize: 11,
        color: '#6b7280',
        marginTop: 4
    },
    tooltip: {
        position: 'absolute',
        bottom: '100%',
        left: '50%',
        transform: 'translateX(-50%)',
        background: '#1f2937',
        color: 'white',
        padding: '8px 12px',
        borderRadius: 6,
        fontSize: 12,
        whiteSpace: 'nowrap',
        marginBottom: 8,
        zIndex: 10,
        opacity: 0,
        pointerEvents: 'none',
        transition: 'opacity 0.2s'
    }
}

// Cores por status
const statusColors = {
    concluido: { bg: '#10b981', text: 'white', connector: '#10b981' },
    andamento: { bg: '#f59e0b', text: 'white', connector: '#fbbf24' },
    atrasado: { bg: '#ef4444', text: 'white', connector: '#fca5a5' },
    futuro: { bg: '#e5e7eb', text: '#6b7280', connector: '#e5e7eb' },
    pendente: { bg: '#dbeafe', text: '#1d4ed8', connector: '#93c5fd' }
}

// Ícones por status
const statusIcons = {
    concluido: '✓',
    andamento: '▶',
    atrasado: '!',
    futuro: '○',
    pendente: '◐'
}

function TimelineItem({ etapa, isLast, onClick }) {
    const status = etapa.status || 'futuro'
    const colors = statusColors[status] || statusColors.futuro
    const icon = statusIcons[status] || '○'

    return (
        <div style={styles.item}>
            {!isLast && (
                <div style={{
                    ...styles.connector,
                    background: `linear-gradient(90deg, ${colors.connector} 0%, ${colors.connector} 100%)`
                }} />
            )}
            <div
                style={{
                    ...styles.circle,
                    background: colors.bg,
                    color: colors.text
                }}
                onClick={() => onClick && onClick(etapa)}
                title={etapa.descricao || etapa.nome}
            >
                {icon}
            </div>
            <div style={styles.label}>{etapa.nome}</div>
            {etapa.data_fim && (
                <div style={styles.date}>
                    {new Date(etapa.data_fim).toLocaleDateString('pt-BR')}
                </div>
            )}
        </div>
    )
}

function Timeline({ etapas = [], onEtapaClick, title }) {
    if (!etapas || etapas.length === 0) {
        return (
            <div style={{ ...styles.container, textAlign: 'center', color: '#6b7280' }}>
                <p>Nenhuma etapa definida para este projeto</p>
            </div>
        )
    }

    return (
        <div style={styles.container}>
            {title && <h3 style={{ margin: '0 0 16px', fontSize: 16 }}>{title}</h3>}

            <div style={styles.timeline}>
                {etapas.map((etapa, index) => (
                    <TimelineItem
                        key={etapa.id || index}
                        etapa={etapa}
                        isLast={index === etapas.length - 1}
                        onClick={onEtapaClick}
                    />
                ))}
            </div>

            <div style={{ display: 'flex', gap: 16, marginTop: 16, flexWrap: 'wrap' }}>
                {Object.entries(statusColors).map(([status, colors]) => (
                    <div key={status} style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12 }}>
                        <span style={{
                            width: 12,
                            height: 12,
                            borderRadius: '50%',
                            background: colors.bg
                        }} />
                        <span style={{ color: '#6b7280', textTransform: 'capitalize' }}>{status}</span>
                    </div>
                ))}
            </div>
        </div>
    )
}

// Componente de exemplo para demonstração
export function TimelineDemo() {
    const etapasDemo = [
        { id: 1, nome: 'Planejamento', status: 'concluido', data_fim: '2026-01-15' },
        { id: 2, nome: 'Licitação', status: 'concluido', data_fim: '2026-01-25' },
        { id: 3, nome: 'Execução', status: 'andamento', data_fim: '2026-03-30' },
        { id: 4, nome: 'Fiscalização', status: 'futuro', data_fim: '2026-04-15' },
        { id: 5, nome: 'Entrega', status: 'futuro', data_fim: '2026-05-01' }
    ]

    return (
        <div style={{ padding: 20 }}>
            <h2>📊 Timeline do Projeto</h2>
            <Timeline
                etapas={etapasDemo}
                onEtapaClick={(etapa) => alert(`Etapa: ${etapa.nome}`)}
            />
        </div>
    )
}

export default memo(Timeline)
