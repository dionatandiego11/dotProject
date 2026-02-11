import PropTypes from 'prop-types'

const variants = {
    neutral: {
        background: 'var(--color-gray-100)',
        color: 'var(--color-gray-700)',
        dot: 'var(--color-gray-500)',
    },
    info: {
        background: 'var(--color-info-50)',
        color: 'var(--color-info-700)',
        dot: 'var(--color-info-600)',
    },
    success: {
        background: 'var(--color-success-50)',
        color: 'var(--color-success-700)',
        dot: 'var(--color-success-600)',
    },
    warning: {
        background: 'var(--color-warning-50)',
        color: 'var(--color-warning-700)',
        dot: 'var(--color-warning-600)',
    },
    danger: {
        background: 'var(--color-danger-50)',
        color: 'var(--color-danger-700)',
        dot: 'var(--color-danger-600)',
    },
}

const sizes = {
    sm: {
        fontSize: '0.6875rem',
        padding: '0.125rem 0.375rem',
        gap: '0.25rem',
    },
    md: {
        fontSize: '0.75rem',
        padding: '0.1875rem 0.5rem',
        gap: '0.3125rem',
    },
}

export default function Badge({
    children,
    variant = 'neutral',
    size = 'md',
    withDot = false,
    style,
    ...props
}) {
    const palette = variants[variant] || variants.neutral
    const density = sizes[size] || sizes.md

    return (
        <span
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: density.gap,
                borderRadius: 'var(--radius-full)',
                backgroundColor: palette.background,
                color: palette.color,
                fontSize: density.fontSize,
                fontWeight: 600,
                lineHeight: 1,
                padding: density.padding,
                whiteSpace: 'nowrap',
                ...style,
            }}
            {...props}
        >
            {withDot && (
                <span
                    style={{
                        width: '0.375rem',
                        height: '0.375rem',
                        borderRadius: '50%',
                        backgroundColor: palette.dot,
                        display: 'inline-block',
                    }}
                />
            )}
            {children}
        </span>
    )
}

Badge.propTypes = {
    children: PropTypes.node.isRequired,
    variant: PropTypes.oneOf(['neutral', 'info', 'success', 'warning', 'danger']),
    size: PropTypes.oneOf(['sm', 'md']),
    withDot: PropTypes.bool,
    style: PropTypes.object,
}
