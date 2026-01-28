/**
 * Card Component - Design System
 * 
 * Container de conteúdo reutilizável
 */

import PropTypes from 'prop-types'

function Card({
  children,
  title,
  subtitle,
  padding = 'md',
  shadow = 'md',
  hoverable = false,
  onClick,
  ...props
}) {
  const paddings = {
    none: '0',
    sm: 'var(--spacing-3)',
    md: 'var(--spacing-4)',
    lg: 'var(--spacing-6)',
  }

  const shadows = {
    none: 'none',
    sm: 'var(--shadow-sm)',
    md: 'var(--shadow-md)',
    lg: 'var(--shadow-lg)',
  }

  const cardStyle = {
    backgroundColor: 'var(--color-bg)',
    borderRadius: 'var(--radius-lg)',
    boxShadow: shadows[shadow],
    border: '1px solid var(--color-border)',
    overflow: 'hidden',
    transition: hoverable ? 'all 150ms ease' : undefined,
    cursor: onClick ? 'pointer' : 'default',
  }

  const headerStyle = {
    padding: paddings[padding],
    paddingBottom: title ? 0 : paddings[padding],
    borderBottom: subtitle ? '1px solid var(--color-border)' : 'none',
  }

  const contentStyle = {
    padding: paddings[padding],
  }

  const titleStyle = {
    fontSize: 'var(--font-size-lg)',
    fontWeight: 'var(--font-weight-semibold)',
    color: 'var(--color-text)',
    margin: 0,
  }

  const subtitleStyle = {
    fontSize: 'var(--font-size-sm)',
    color: 'var(--color-text-secondary)',
    margin: 'var(--spacing-1) 0 0 0',
  }

  const handleMouseEnter = (e) => {
    if (hoverable) {
      e.currentTarget.style.boxShadow = 'var(--shadow-lg)'
      e.currentTarget.style.transform = 'translateY(-2px)'
    }
  }

  const handleMouseLeave = (e) => {
    if (hoverable) {
      e.currentTarget.style.boxShadow = shadows[shadow]
      e.currentTarget.style.transform = 'translateY(0)'
    }
  }

  return (
    <div
      style={cardStyle}
      onClick={onClick}
      onMouseEnter={handleMouseEnter}
      onMouseLeave={handleMouseLeave}
      {...props}
    >
      {(title || subtitle) && (
        <div style={headerStyle}>
          {title && <h3 style={titleStyle}>{title}</h3>}
          {subtitle && <p style={subtitleStyle}>{subtitle}</p>}
        </div>
      )}
      <div style={contentStyle}>{children}</div>
    </div>
  )
}

Card.propTypes = {
  children: PropTypes.node.isRequired,
  title: PropTypes.string,
  subtitle: PropTypes.string,
  padding: PropTypes.oneOf(['none', 'sm', 'md', 'lg']),
  shadow: PropTypes.oneOf(['none', 'sm', 'md', 'lg']),
  hoverable: PropTypes.bool,
  onClick: PropTypes.func,
}

export default Card
