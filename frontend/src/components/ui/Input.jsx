/**
 * Input Component - Design System
 * 
 * Campo de entrada reutilizável
 */

import PropTypes from 'prop-types'
import { forwardRef } from 'react'

const Input = forwardRef(function Input(
  {
    label,
    error,
    helper,
    icon: Icon,
    fullWidth = false,
    size = 'md',
    ...props
  },
  ref
) {
  const sizes = {
    sm: { padding: 'var(--spacing-2) var(--spacing-3)', fontSize: 'var(--font-size-sm)' },
    md: { padding: 'var(--spacing-2) var(--spacing-3)', fontSize: 'var(--font-size-base)' },
    lg: { padding: 'var(--spacing-3) var(--spacing-4)', fontSize: 'var(--font-size-lg)' },
  }

  const sizeStyle = sizes[size]

  const containerStyle = {
    display: 'flex',
    flexDirection: 'column',
    gap: 'var(--spacing-1)',
    width: fullWidth ? '100%' : 'auto',
  }

  const labelStyle = {
    fontSize: 'var(--font-size-sm)',
    fontWeight: 'var(--font-weight-medium)',
    color: error ? 'var(--color-danger-600)' : 'var(--color-gray-700)',
  }

  const inputContainerStyle = {
    position: 'relative',
    display: 'flex',
    alignItems: 'center',
  }

  const inputStyle = {
    width: '100%',
    padding: Icon ? `var(--spacing-2) var(--spacing-3) var(--spacing-2) var(--spacing-10)` : sizeStyle.padding,
    fontSize: sizeStyle.fontSize,
    fontFamily: 'var(--font-family-sans)',
    color: 'var(--color-gray-900)',
    backgroundColor: 'var(--color-bg)',
    border: `1px solid ${error ? 'var(--color-danger-500)' : 'var(--color-gray-300)'}`,
    borderRadius: 'var(--radius-md)',
    outline: 'none',
    transition: 'all 150ms ease',
  }

  const iconStyle = {
    position: 'absolute',
    left: 'var(--spacing-3)',
    color: 'var(--color-gray-400)',
    pointerEvents: 'none',
  }

  const helperStyle = {
    fontSize: 'var(--font-size-sm)',
    color: error ? 'var(--color-danger-600)' : 'var(--color-gray-500)',
  }

  return (
    <div style={containerStyle}>
      {label && <label style={labelStyle}>{label}</label>}
      <div style={inputContainerStyle}>
        {Icon && <Icon style={iconStyle} size={20} />}
        <input
          ref={ref}
          style={inputStyle}
          onFocus={(e) => {
            e.target.style.borderColor = error ? 'var(--color-danger-500)' : 'var(--color-primary-500)'
            e.target.style.boxShadow = `0 0 0 3px ${error ? 'var(--color-danger-100)' : 'var(--color-primary-100)'}`
          }}
          onBlur={(e) => {
            e.target.style.borderColor = error ? 'var(--color-danger-500)' : 'var(--color-gray-300)'
            e.target.style.boxShadow = 'none'
          }}
          {...props}
        />
      </div>
      {(error || helper) && (
        <span style={helperStyle}>{error || helper}</span>
      )}
    </div>
  )
})

Input.propTypes = {
  label: PropTypes.string,
  error: PropTypes.string,
  helper: PropTypes.string,
  icon: PropTypes.elementType,
  fullWidth: PropTypes.bool,
  size: PropTypes.oneOf(['sm', 'md', 'lg']),
}

export default Input
