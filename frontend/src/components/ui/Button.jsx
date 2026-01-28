/**
 * Button Component - Design System
 * 
 * Botão reutilizável com múltiplas variantes e tamanhos
 */

import PropTypes from 'prop-types'

const variants = {
  primary: {
    background: 'var(--color-primary-600)',
    color: '#ffffff',
    hover: 'var(--color-primary-700)',
    border: 'transparent',
  },
  secondary: {
    background: 'var(--color-gray-200)',
    color: 'var(--color-gray-900)',
    hover: 'var(--color-gray-300)',
    border: 'transparent',
  },
  outline: {
    background: 'transparent',
    color: 'var(--color-primary-600)',
    hover: 'var(--color-primary-50)',
    border: 'var(--color-primary-600)',
  },
  ghost: {
    background: 'transparent',
    color: 'var(--color-gray-600)',
    hover: 'var(--color-gray-100)',
    border: 'transparent',
  },
  danger: {
    background: 'var(--color-danger-600)',
    color: '#ffffff',
    hover: 'var(--color-danger-700)',
    border: 'transparent',
  },
}

const sizes = {
  sm: {
    padding: 'var(--spacing-2) var(--spacing-3)',
    fontSize: 'var(--font-size-sm)',
    height: '2rem',
  },
  md: {
    padding: 'var(--spacing-2) var(--spacing-4)',
    fontSize: 'var(--font-size-base)',
    height: '2.5rem',
  },
  lg: {
    padding: 'var(--spacing-3) var(--spacing-6)',
    fontSize: 'var(--font-size-lg)',
    height: '3rem',
  },
}

function Button({
  children,
  variant = 'primary',
  size = 'md',
  disabled = false,
  loading = false,
  fullWidth = false,
  type = 'button',
  onClick,
  icon: Icon,
  ...props
}) {
  const variantStyle = variants[variant]
  const sizeStyle = sizes[size]

  const baseStyles = {
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 'var(--spacing-2)',
    fontFamily: 'var(--font-family-sans)',
    fontWeight: 'var(--font-weight-medium)',
    borderRadius: 'var(--radius-md)',
    border: `1px solid ${variantStyle.border}`,
    backgroundColor: variantStyle.background,
    color: variantStyle.color,
    fontSize: sizeStyle.fontSize,
    padding: sizeStyle.padding,
    height: sizeStyle.height,
    width: fullWidth ? '100%' : 'auto',
    cursor: disabled || loading ? 'not-allowed' : 'pointer',
    opacity: disabled || loading ? 0.6 : 1,
    transition: 'all 150ms ease',
    whiteSpace: 'nowrap',
  }

  const handleMouseEnter = (e) => {
    if (!disabled && !loading) {
      e.target.style.backgroundColor = variantStyle.hover
    }
  }

  const handleMouseLeave = (e) => {
    e.target.style.backgroundColor = variantStyle.background
  }

  return (
    <button
      type={type}
      disabled={disabled || loading}
      onClick={onClick}
      onMouseEnter={handleMouseEnter}
      onMouseLeave={handleMouseLeave}
      style={baseStyles}
      {...props}
    >
      {loading && (
        <span
          style={{
            width: '1em',
            height: '1em',
            border: '2px solid currentColor',
            borderRightColor: 'transparent',
            borderRadius: '50%',
            animation: 'spin 1s linear infinite',
          }}
        />
      )}
      {Icon && !loading && <Icon size={16} />}
      {children}
      <style>{`
        @keyframes spin {
          to { transform: rotate(360deg); }
        }
      `}</style>
    </button>
  )
}

Button.propTypes = {
  children: PropTypes.node.isRequired,
  variant: PropTypes.oneOf(['primary', 'secondary', 'outline', 'ghost', 'danger']),
  size: PropTypes.oneOf(['sm', 'md', 'lg']),
  disabled: PropTypes.bool,
  loading: PropTypes.bool,
  fullWidth: PropTypes.bool,
  type: PropTypes.oneOf(['button', 'submit', 'reset']),
  onClick: PropTypes.func,
  icon: PropTypes.elementType,
}

export default Button
