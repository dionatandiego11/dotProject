/**
 * Modal Component - Design System
 * 
 * Diálogo modal reutilizável
 */

import PropTypes from 'prop-types'
import { useEffect } from 'react'
import Button from './Button'

function Modal({
  isOpen,
  onClose,
  title,
  children,
  footer,
  size = 'md',
  closeOnOverlay = true,
}) {
  const sizes = {
    sm: { maxWidth: '400px' },
    md: { maxWidth: '500px' },
    lg: { maxWidth: '800px' },
    xl: { maxWidth: '1000px' },
  }

  useEffect(() => {
    if (isOpen) {
      document.body.style.overflow = 'hidden'
    } else {
      document.body.style.overflow = ''
    }
    return () => {
      document.body.style.overflow = ''
    }
  }, [isOpen])

  useEffect(() => {
    const handleEscape = (e) => {
      if (e.key === 'Escape' && isOpen) {
        onClose()
      }
    }
    document.addEventListener('keydown', handleEscape)
    return () => document.removeEventListener('keydown', handleEscape)
  }, [isOpen, onClose])

  if (!isOpen) return null

  const overlayStyle = {
    position: 'fixed',
    inset: 0,
    backgroundColor: 'rgb(0 0 0 / 0.5)',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    zIndex: 500,
    padding: 'var(--spacing-4)',
    animation: 'fadeIn 150ms ease',
  }

  const modalStyle = {
    backgroundColor: 'var(--color-bg)',
    borderRadius: 'var(--radius-xl)',
    boxShadow: 'var(--shadow-xl)',
    width: '100%',
    maxWidth: sizes[size].maxWidth,
    maxHeight: '90vh',
    overflow: 'auto',
    animation: 'slideIn 200ms ease',
  }

  const headerStyle = {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: 'var(--spacing-4) var(--spacing-6)',
    borderBottom: '1px solid var(--color-border)',
  }

  const titleStyle = {
    fontSize: 'var(--font-size-xl)',
    fontWeight: 'var(--font-weight-semibold)',
    color: 'var(--color-text)',
    margin: 0,
  }

  const contentStyle = {
    padding: 'var(--spacing-6)',
  }

  const footerStyle = {
    display: 'flex',
    justifyContent: 'flex-end',
    gap: 'var(--spacing-3)',
    padding: 'var(--spacing-4) var(--spacing-6)',
    borderTop: '1px solid var(--color-border)',
  }

  const closeButtonStyle = {
    background: 'none',
    border: 'none',
    fontSize: '1.5rem',
    color: 'var(--color-gray-400)',
    cursor: 'pointer',
    padding: 'var(--spacing-1)',
    lineHeight: 1,
    borderRadius: 'var(--radius-md)',
    transition: 'all 150ms ease',
  }

  return (
    <div
      style={overlayStyle}
      onClick={closeOnOverlay ? onClose : undefined}
    >
      <div style={modalStyle} onClick={(e) => e.stopPropagation()}>
        <div style={headerStyle}>
          {title && <h2 style={titleStyle}>{title}</h2>}
          <button
            style={closeButtonStyle}
            onClick={onClose}
            onMouseEnter={(e) => {
              e.target.style.color = 'var(--color-gray-600)'
              e.target.style.backgroundColor = 'var(--color-gray-100)'
            }}
            onMouseLeave={(e) => {
              e.target.style.color = 'var(--color-gray-400)'
              e.target.style.backgroundColor = 'transparent'
            }}
          >
            ×
          </button>
        </div>
        <div style={contentStyle}>{children}</div>
        {footer && <div style={footerStyle}>{footer}</div>}
      </div>
      <style>{`
        @keyframes fadeIn {
          from { opacity: 0; }
          to { opacity: 1; }
        }
        @keyframes slideIn {
          from { 
            opacity: 0;
            transform: translateY(-20px) scale(0.95);
          }
          to { 
            opacity: 1;
            transform: translateY(0) scale(1);
          }
        }
      `}</style>
    </div>
  )
}

Modal.propTypes = {
  isOpen: PropTypes.bool.isRequired,
  onClose: PropTypes.func.isRequired,
  title: PropTypes.string,
  children: PropTypes.node.isRequired,
  footer: PropTypes.node,
  size: PropTypes.oneOf(['sm', 'md', 'lg', 'xl']),
  closeOnOverlay: PropTypes.bool,
}

export default Modal
