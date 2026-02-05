/**
 * Componentes reutilizáveis para Dashboards de Prefeitura
 * 
 * @module dashboard
 */

import { useState, memo } from 'react'

// ===========================================
// STAT CARD - Card de estatísticas
// ===========================================

export const StatCard = memo(function StatCard({
    titulo,
    valor,
    variacao = null,
    icone = '📊',
    cor = 'blue',
    subtitulo = null,
    onClick = null
}) {
    const cores = {
        blue: { bg: '#eff6ff', border: '#3b82f6', texto: '#1e40af' },
        green: { bg: '#f0fdf4', border: '#22c55e', texto: '#166534' },
        yellow: { bg: '#fefce8', border: '#eab308', texto: '#854d0e' },
        red: { bg: '#fef2f2', border: '#ef4444', texto: '#991b1b' },
        purple: { bg: '#faf5ff', border: '#a855f7', texto: '#6b21a8' },
        gray: { bg: '#f9fafb', border: '#6b7280', texto: '#374151' },
    }

    const corAtual = cores[cor] || cores.blue

    return (
        <div
            onClick={onClick}
            style={{
                backgroundColor: corAtual.bg,
                border: `2px solid ${corAtual.border}`,
                borderRadius: 12,
                padding: '20px 24px',
                cursor: onClick ? 'pointer' : 'default',
                transition: 'transform 0.2s, box-shadow 0.2s',
            }}
            onMouseEnter={(e) => {
                if (onClick) {
                    e.currentTarget.style.transform = 'translateY(-2px)'
                    e.currentTarget.style.boxShadow = '0 4px 12px rgba(0,0,0,0.1)'
                }
            }}
            onMouseLeave={(e) => {
                e.currentTarget.style.transform = 'translateY(0)'
                e.currentTarget.style.boxShadow = 'none'
            }}
        >
            <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 8 }}>
                <span style={{ fontSize: 24 }}>{icone}</span>
                <span style={{ fontSize: 14, color: '#6b7280', fontWeight: 500 }}>{titulo}</span>
            </div>
            <div style={{ display: 'flex', alignItems: 'baseline', gap: 8 }}>
                <span style={{
                    fontSize: 32,
                    fontWeight: 700,
                    color: corAtual.texto
                }}>
                    {valor}
                </span>
                {variacao !== null && (
                    <span style={{
                        fontSize: 14,
                        color: variacao >= 0 ? '#22c55e' : '#ef4444',
                        fontWeight: 500
                    }}>
                        {variacao >= 0 ? '+' : ''}{variacao}
                    </span>
                )}
            </div>
            {subtitulo && (
                <div style={{ fontSize: 12, color: '#9ca3af', marginTop: 4 }}>
                    {subtitulo}
                </div>
            )}
        </div>
    )
})

// ===========================================
// PROGRESS BAR - Barra de progresso
// ===========================================

export const ProgressBar = memo(function ProgressBar({
    valor,
    total = 100,
    titulo = null,
    subtitulo = null,
    cor = '#3b82f6',
    mostrarPorcentagem = true
}) {
    // Proteção contra divisão por zero (sugestão ChatGPT)
    const porcentagem = total <= 0 ? 0 : Math.min(100, Math.max(0, (valor / total) * 100))

    return (
        <div style={{ marginBottom: 16 }}>
            {titulo && (
                <div style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    marginBottom: 8
                }}>
                    <span style={{ fontWeight: 600, color: '#374151' }}>{titulo}</span>
                    {mostrarPorcentagem && (
                        <span style={{ fontWeight: 600, color: cor }}>
                            {porcentagem.toFixed(0)}%
                        </span>
                    )}
                </div>
            )}
            <div style={{
                width: '100%',
                height: 12,
                backgroundColor: '#e5e7eb',
                borderRadius: 6,
                overflow: 'hidden'
            }}>
                <div style={{
                    width: `${porcentagem}%`,
                    height: '100%',
                    backgroundColor: cor,
                    borderRadius: 6,
                    transition: 'width 0.5s ease-out'
                }} />
            </div>
            {subtitulo && (
                <div style={{ fontSize: 12, color: '#9ca3af', marginTop: 4 }}>
                    {subtitulo}
                </div>
            )}
        </div>
    )
})

// ===========================================
// ALERTA CARD - Card de alertas
// ===========================================

export function AlertaCard({
    alerta,
    onMarcarLido,
    onClick
}) {
    const [loading, setLoading] = useState(false)

    const prioridades = {
        critica: { icone: '🔴', cor: '#fef2f2', border: '#ef4444' },
        alta: { icone: '🟠', cor: '#fff7ed', border: '#f97316' },
        media: { icone: '🟡', cor: '#fefce8', border: '#eab308' },
        baixa: { icone: '🟢', cor: '#f0fdf4', border: '#22c55e' },
    }

    const prioridade = prioridades[alerta.prioridade] || prioridades.media

    const handleMarcarLido = async (e) => {
        e.stopPropagation()
        if (loading || alerta.lido) return
        setLoading(true)
        try {
            await onMarcarLido(alerta.id)
        } finally {
            setLoading(false)
        }
    }

    return (
        <div
            onClick={onClick}
            style={{
                backgroundColor: alerta.lido ? '#f9fafb' : prioridade.cor,
                border: `1px solid ${alerta.lido ? '#e5e7eb' : prioridade.border}`,
                borderRadius: 8,
                padding: 16,
                marginBottom: 8,
                cursor: onClick ? 'pointer' : 'default',
                opacity: alerta.lido ? 0.7 : 1,
                transition: 'all 0.2s',
            }}
        >
            <div style={{ display: 'flex', alignItems: 'flex-start', gap: 12 }}>
                <span style={{ fontSize: 16 }}>{prioridade.icone}</span>
                <div style={{ flex: 1 }}>
                    <div style={{
                        fontWeight: 600,
                        color: '#374151',
                        marginBottom: 4,
                        textDecoration: alerta.lido ? 'line-through' : 'none'
                    }}>
                        {alerta.titulo}
                    </div>
                    {alerta.descricao && (
                        <div style={{ fontSize: 13, color: '#6b7280', marginBottom: 8 }}>
                            {alerta.descricao}
                        </div>
                    )}
                    <div style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 12,
                        fontSize: 12,
                        color: '#9ca3af'
                    }}>
                        <span>📅 {new Date(alerta.data_criacao).toLocaleDateString('pt-BR')}</span>
                        {alerta.projeto && <span>📁 {alerta.projeto}</span>}
                    </div>
                </div>
                {!alerta.lido && onMarcarLido && (
                    <button
                        onClick={handleMarcarLido}
                        disabled={loading}
                        style={{
                            backgroundColor: 'transparent',
                            border: '1px solid #d1d5db',
                            borderRadius: 6,
                            padding: '6px 12px',
                            fontSize: 12,
                            color: '#6b7280',
                            cursor: loading ? 'wait' : 'pointer',
                        }}
                    >
                        {loading ? '...' : '✓'}
                    </button>
                )}
            </div>
        </div>
    )
}

// ===========================================
// SECTION HEADER - Cabeçalho de seção
// ===========================================

export function SectionHeader({ titulo, icone = null, acao = null }) {
    return (
        <div style={{
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
            marginBottom: 16,
            paddingBottom: 12,
            borderBottom: '2px solid #e5e7eb'
        }}>
            <h3 style={{
                margin: 0,
                fontSize: 16,
                fontWeight: 600,
                color: '#374151',
                display: 'flex',
                alignItems: 'center',
                gap: 8
            }}>
                {icone && <span>{icone}</span>}
                {titulo}
            </h3>
            {acao}
        </div>
    )
}

// ===========================================
// STATUS BADGE - Badge de status
// ===========================================

export const StatusBadge = memo(function StatusBadge({ status }) {
    const statusConfig = {
        em_dia: { texto: 'Em dia', cor: '#22c55e', bg: '#f0fdf4', icone: '🟢' },
        atencao: { texto: 'Atenção', cor: '#eab308', bg: '#fefce8', icone: '🟡' },
        travado: { texto: 'Travado', cor: '#ef4444', bg: '#fef2f2', icone: '🔴' },
        concluido: { texto: 'Concluído', cor: '#6b7280', bg: '#f3f4f6', icone: '✅' },
        suspenso: { texto: 'Suspenso', cor: '#374151', bg: '#f3f4f6', icone: '⚫' },
    }

    const config = statusConfig[status] || statusConfig.em_dia

    return (
        <span style={{
            display: 'inline-flex',
            alignItems: 'center',
            gap: 4,
            padding: '4px 8px',
            backgroundColor: config.bg,
            color: config.cor,
            borderRadius: 12,
            fontSize: 12,
            fontWeight: 500
        }}>
            <span>{config.icone}</span>
            {config.texto}
        </span>
    )
})

// ===========================================
// DATA TABLE - Tabela simples
// ===========================================

export function DataTable({
    colunas,
    dados,
    onRowClick = null,
    emptyMessage = 'Nenhum dado encontrado'
}) {
    if (!dados || dados.length === 0) {
        return (
            <div style={{
                textAlign: 'center',
                padding: 40,
                color: '#9ca3af',
                backgroundColor: '#f9fafb',
                borderRadius: 8
            }}>
                {emptyMessage}
            </div>
        )
    }

    return (
        <div style={{ overflowX: 'auto' }}>
            <table style={{
                width: '100%',
                borderCollapse: 'collapse',
                fontSize: 14
            }}>
                <thead>
                    <tr style={{ backgroundColor: '#f9fafb' }}>
                        {colunas.map((col, idx) => (
                            <th
                                key={idx}
                                style={{
                                    padding: '12px 16px',
                                    textAlign: 'left',
                                    fontWeight: 600,
                                    color: '#374151',
                                    borderBottom: '2px solid #e5e7eb'
                                }}
                            >
                                {col.titulo}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {dados.map((row, rowIdx) => (
                        <tr
                            key={row.id || row.nome || rowIdx}
                            onClick={() => onRowClick && onRowClick(row)}
                            style={{
                                cursor: onRowClick ? 'pointer' : 'default',
                                transition: 'background-color 0.2s'
                            }}
                            onMouseEnter={(e) => {
                                e.currentTarget.style.backgroundColor = '#f9fafb'
                            }}
                            onMouseLeave={(e) => {
                                e.currentTarget.style.backgroundColor = 'transparent'
                            }}
                        >
                            {colunas.map((col, colIdx) => (
                                <td
                                    key={colIdx}
                                    style={{
                                        padding: '12px 16px',
                                        borderBottom: '1px solid #e5e7eb',
                                        color: '#374151'
                                    }}
                                >
                                    {col.render ? col.render(row) : row[col.campo]}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    )
}

export default {
    StatCard,
    ProgressBar,
    AlertaCard,
    SectionHeader,
    StatusBadge,
    DataTable
}
