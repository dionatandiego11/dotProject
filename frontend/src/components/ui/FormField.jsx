import PropTypes from 'prop-types'

function resolveControlStyles(error, hasLeftAddon) {
    return {
        width: '100%',
        border: `1px solid ${error ? 'var(--color-danger-500)' : 'var(--color-gray-300)'}`,
        borderRadius: 'var(--radius-md)',
        background: 'var(--color-bg)',
        color: 'var(--color-text)',
        fontSize: '0.875rem',
        padding: hasLeftAddon ? '0.5rem 0.75rem 0.5rem 2rem' : '0.5rem 0.75rem',
        outline: 'none',
    }
}

export default function FormField({
    as = 'input',
    label,
    required = false,
    error,
    helper,
    options = [],
    leftAddon,
    fullWidth = true,
    style,
    ...props
}) {
    const controlStyle = resolveControlStyles(error, Boolean(leftAddon))
    const shared = {
        ...props,
        style: { ...controlStyle, ...(props.style || {}) },
    }

    const renderControl = () => {
        if (as === 'select') {
            return (
                <select {...shared}>
                    {options.map((option) => {
                        if (typeof option === 'string' || typeof option === 'number') {
                            return (
                                <option key={String(option)} value={String(option)}>
                                    {String(option)}
                                </option>
                            )
                        }
                        return (
                            <option key={String(option.value)} value={String(option.value)} disabled={Boolean(option.disabled)}>
                                {option.label}
                            </option>
                        )
                    })}
                </select>
            )
        }

        if (as === 'textarea') {
            return <textarea {...shared} />
        }

        return <input {...shared} />
    }

    return (
        <div style={{ display: 'grid', gap: '0.25rem', width: fullWidth ? '100%' : 'auto', ...style }}>
            {label && (
                <label style={{ fontSize: '0.8125rem', fontWeight: 600, color: error ? 'var(--color-danger-600)' : 'var(--color-gray-700)' }}>
                    {label}{required ? ' *' : ''}
                </label>
            )}

            <div style={{ position: 'relative', width: '100%' }}>
                {leftAddon && (
                    <span
                        style={{
                            position: 'absolute',
                            left: '0.625rem',
                            top: '50%',
                            transform: 'translateY(-50%)',
                            color: 'var(--color-gray-500)',
                            fontSize: '0.8125rem',
                            pointerEvents: 'none',
                        }}
                    >
                        {leftAddon}
                    </span>
                )}
                {renderControl()}
            </div>

            {(error || helper) && (
                <span style={{ fontSize: '0.75rem', color: error ? 'var(--color-danger-600)' : 'var(--color-gray-500)' }}>
                    {error || helper}
                </span>
            )}
        </div>
    )
}

FormField.propTypes = {
    as: PropTypes.oneOf(['input', 'select', 'textarea']),
    label: PropTypes.string,
    required: PropTypes.bool,
    error: PropTypes.string,
    helper: PropTypes.string,
    options: PropTypes.arrayOf(
        PropTypes.oneOfType([
            PropTypes.string,
            PropTypes.number,
            PropTypes.shape({
                value: PropTypes.oneOfType([PropTypes.string, PropTypes.number]).isRequired,
                label: PropTypes.string.isRequired,
                disabled: PropTypes.bool,
            }),
        ])
    ),
    leftAddon: PropTypes.node,
    fullWidth: PropTypes.bool,
    style: PropTypes.object,
}
