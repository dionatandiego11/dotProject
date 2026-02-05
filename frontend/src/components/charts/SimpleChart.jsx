/**
 * Simple Chart Components
 * 
 * Gráficos simples usando apenas CSS/SVG (sem biblioteca externa)
 */

import PropTypes from 'prop-types'

// Bar Chart Component
export function BarChart({ data, maxValue, height = 150, color = 'var(--color-primary-500)' }) {
    const max = maxValue || Math.max(...data.map(d => d.value), 1)
    
    return (
        <div style={{ height, display: 'flex', alignItems: 'flex-end', gap: '8px', padding: '10px 0' }}>
            {data.map((item, index) => {
                const percentage = (item.value / max) * 100
                return (
                    <div key={index} style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center' }}>
                        <div
                            style={{
                                width: '100%',
                                height: `${percentage}%`,
                                backgroundColor: color,
                                borderRadius: '4px 4px 0 0',
                                minHeight: item.value > 0 ? '4px' : '0',
                                transition: 'height 0.5s ease',
                                opacity: item.highlight ? 1 : 0.7
                            }}
                            title={`${item.label}: ${item.value}`}
                        />
                        <span style={{ fontSize: '0.7rem', color: 'var(--color-gray-500)', marginTop: '4px', textAlign: 'center' }}>
                            {item.label}
                        </span>
                    </div>
                )
            })}
        </div>
    )
}

BarChart.propTypes = {
    data: PropTypes.arrayOf(PropTypes.shape({
        label: PropTypes.string.isRequired,
        value: PropTypes.number.isRequired,
        highlight: PropTypes.bool
    })).isRequired,
    maxValue: PropTypes.number,
    height: PropTypes.number,
    color: PropTypes.string
}

// Pie/Donut Chart Component
export function DonutChart({ data, size = 120, strokeWidth = 20 }) {
    const total = data.reduce((sum, item) => sum + item.value, 0)
    const radius = (size - strokeWidth) / 2
    const circumference = 2 * Math.PI * radius
    let currentOffset = 0
    
    return (
        <svg width={size} height={size} style={{ transform: 'rotate(-90deg)' }}>
            {data.map((item, index) => {
                const percentage = total > 0 ? item.value / total : 0
                const dashLength = circumference * percentage
                const gapLength = circumference - dashLength
                const offset = currentOffset
                currentOffset += dashLength
                
                return (
                    <circle
                        key={index}
                        cx={size / 2}
                        cy={size / 2}
                        r={radius}
                        fill="none"
                        stroke={item.color}
                        strokeWidth={strokeWidth}
                        strokeDasharray={`${dashLength} ${gapLength}`}
                        strokeDashoffset={-offset}
                        style={{ transition: 'all 0.5s ease' }}
                    />
                )
            })}
        </svg>
    )
}

DonutChart.propTypes = {
    data: PropTypes.arrayOf(PropTypes.shape({
        value: PropTypes.number.isRequired,
        color: PropTypes.string.isRequired
    })).isRequired,
    size: PropTypes.number,
    strokeWidth: PropTypes.number
}

// Progress Ring Component
export function ProgressRing({ progress, size = 80, strokeWidth = 8, color = 'var(--color-primary-500)' }) {
    const radius = (size - strokeWidth) / 2
    const circumference = 2 * Math.PI * radius
    const offset = circumference - (progress / 100) * circumference
    
    // Color based on progress
    let strokeColor = color
    if (progress < 30) strokeColor = 'var(--color-danger-500)'
    else if (progress < 70) strokeColor = 'var(--color-warning-500)'
    else strokeColor = 'var(--color-success-500)'
    
    return (
        <div style={{ position: 'relative', width: size, height: size }}>
            <svg width={size} height={size} style={{ transform: 'rotate(-90deg)' }}>
                <circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    fill="none"
                    stroke="var(--color-gray-200)"
                    strokeWidth={strokeWidth}
                />
                <circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    fill="none"
                    stroke={strokeColor}
                    strokeWidth={strokeWidth}
                    strokeLinecap="round"
                    strokeDasharray={circumference}
                    strokeDashoffset={offset}
                    style={{ transition: 'stroke-dashoffset 0.5s ease' }}
                />
            </svg>
            <div style={{
                position: 'absolute',
                top: '50%',
                left: '50%',
                transform: 'translate(-50%, -50%)',
                fontSize: '1rem',
                fontWeight: 'bold',
                color: strokeColor
            }}>
                {Math.round(progress)}%
            </div>
        </div>
    )
}

ProgressRing.propTypes = {
    progress: PropTypes.number.isRequired,
    size: PropTypes.number,
    strokeWidth: PropTypes.number,
    color: PropTypes.string
}

// Mini Trend Chart (Sparkline)
export function Sparkline({ data, width = 100, height = 30, color = 'var(--color-primary-500)' }) {
    if (data.length < 2) return <div style={{ width, height }} />
    
    const max = Math.max(...data, 1)
    const min = Math.min(...data, 0)
    const range = max - min || 1
    
    const points = data.map((value, index) => {
        const x = (index / (data.length - 1)) * width
        const y = height - ((value - min) / range) * height
        return `${x},${y}`
    }).join(' ')
    
    return (
        <svg width={width} height={height}>
            <polyline
                fill="none"
                stroke={color}
                strokeWidth="2"
                points={points}
                style={{ transition: 'all 0.5s ease' }}
            />
        </svg>
    )
}

Sparkline.propTypes = {
    data: PropTypes.arrayOf(PropTypes.number).isRequired,
    width: PropTypes.number,
    height: PropTypes.number,
    color: PropTypes.string
}

// Stats Card Component
export function StatCard({ title, value, subtitle, trend, trendUp, icon, color = 'var(--color-primary-500)' }) {
    return (
        <div style={{
            background: 'white',
            borderRadius: 'var(--radius-lg)',
            padding: 'var(--spacing-5)',
            border: '1px solid var(--color-gray-200)',
            boxShadow: 'var(--shadow-sm)'
        }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 'var(--spacing-3)' }}>
                <span style={{ fontSize: '0.875rem', color: 'var(--color-gray-500)' }}>{title}</span>
                {icon && <span style={{ fontSize: '1.5rem' }}>{icon}</span>}
            </div>
            <div style={{ fontSize: '1.75rem', fontWeight: 'bold', color: 'var(--color-gray-900)', marginBottom: 'var(--spacing-1)' }}>
                {value}
            </div>
            {(subtitle || trend !== undefined) && (
                <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--spacing-2)', fontSize: '0.75rem' }}>
                    {trend !== undefined && (
                        <span style={{
                            color: trendUp ? 'var(--color-success-500)' : 'var(--color-danger-500)',
                            fontWeight: 500
                        }}>
                            {trendUp ? '↑' : '↓'} {Math.abs(trend)}%
                        </span>
                    )}
                    {subtitle && <span style={{ color: 'var(--color-gray-400)' }}>{subtitle}</span>}
                </div>
            )}
        </div>
    )
}

StatCard.propTypes = {
    title: PropTypes.string.isRequired,
    value: PropTypes.oneOfType([PropTypes.string, PropTypes.number]).isRequired,
    subtitle: PropTypes.string,
    trend: PropTypes.number,
    trendUp: PropTypes.bool,
    icon: PropTypes.string,
    color: PropTypes.string
}

export default { BarChart, DonutChart, ProgressRing, Sparkline, StatCard }
