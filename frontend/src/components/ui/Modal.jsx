/**
 * Modal Component - Design System
 * 
 * Diálogo modal reutilizável
 */

import PropTypes from 'prop-types'
import { useEffect, useId, useRef } from 'react'

function Modal({
  isOpen,
  onClose,
  title,
  children,
  footer,
  size = 'md',
  closeOnOverlay = true,
  closeOnEscape = true,
}) {
  const titleId = useId()
  const dialogRef = useRef(null)
  const closeButtonRef = useRef(null)
  const lastFocusedElementRef = useRef(null)

  const sizes = {
    sm: { maxWidth: '400px' },
    md: { maxWidth: '500px' },
    lg: { maxWidth: '800px' },
    xl: { maxWidth: '1000px' },
  }

  useEffect(() => {
    if (isOpen) {
      document.body.style.overflow = 'hidden'
      if (document.activeElement instanceof HTMLElement) {
        lastFocusedElementRef.current = document.activeElement
      }

      const focusTimer = window.setTimeout(() => {
        closeButtonRef.current?.focus()
      }, 0)

      return () => {
        window.clearTimeout(focusTimer)
        document.body.style.overflow = ''
      }
    } else {
      document.body.style.overflow = ''
      lastFocusedElementRef.current?.focus()
    }
  }, [isOpen])

  useEffect(() => {
    const handleEscape = (e) => {
      if (e.key === 'Escape' && isOpen && closeOnEscape) {
        onClose()
      }

      if (e.key === 'Tab' && isOpen && dialogRef.current) {
        const focusables = dialogRef.current.querySelectorAll(
          'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        )
        if (focusables.length === 0) return

        const first = focusables[0]
        const last = focusables[focusables.length - 1]

        if (e.shiftKey && document.activeElement === first) {
          e.preventDefault()
          last.focus()
        } else if (!e.shiftKey && document.activeElement === last) {
          e.preventDefault()
          first.focus()
        }
      }
    }
    document.addEventListener('keydown', handleEscape)
    return () => document.removeEventListener('keydown', handleEscape)
  }, [isOpen, onClose, closeOnEscape])

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
      <div
        ref={dialogRef}
        style={modalStyle}
        onClick={(e) => e.stopPropagation()}
        role="dialog"
        aria-modal="true"
        aria-labelledby={title ? titleId : undefined}
      >
        <div style={headerStyle}>
          {title && <h2 id={titleId} style={titleStyle}>{title}</h2>}
          <button
            ref={closeButtonRef}
            style={closeButtonStyle}
            onClick={onClose}
            aria-label="Fechar modal"
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
  closeOnEscape: PropTypes.bool,
}

export default Modal
