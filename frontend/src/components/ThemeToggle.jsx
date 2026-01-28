/**
 * ThemeToggle Component
 * 
 * Botão para alternar entre temas claro/escuro
 */

import { useTheme } from '../hooks/useTheme'

function ThemeToggle() {
  const { isDark, toggleTheme } = useTheme()

  const buttonStyle = {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    width: '2.5rem',
    height: '2.5rem',
    borderRadius: 'var(--radius-md)',
    border: '1px solid var(--color-border)',
    backgroundColor: 'var(--color-bg-elevated)',
    color: 'var(--color-text-secondary)',
    cursor: 'pointer',
    transition: 'all 150ms ease',
  }

  const iconStyle = {
    width: '1.25rem',
    height: '1.25rem',
  }

  // Ícone de sol
  const SunIcon = () => (
    <svg style={iconStyle} fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <circle cx="12" cy="12" r="5" strokeWidth={2} />
      <path strokeWidth={2} strokeLinecap="round" d="M12 1v2m0 18v2M4.22 4.22l1.42 1.42m12.72 12.72l1.42 1.42M1 12h2m18 0h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42" />
    </svg>
  )

  // Ícone de lua
  const MoonIcon = () => (
    <svg style={iconStyle} fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
    </svg>
  )

  return (
    <button
      style={buttonStyle}
      onClick={toggleTheme}
      title={isDark ? 'Mudar para tema claro' : 'Mudar para tema escuro'}
      onMouseEnter={(e) => {
        e.target.style.backgroundColor = 'var(--color-gray-100)'
        e.target.style.color = 'var(--color-text)'
      }}
      onMouseLeave={(e) => {
        e.target.style.backgroundColor = 'var(--color-bg-elevated)'
        e.target.style.color = 'var(--color-text-secondary)'
      }}
    >
      {isDark ? <SunIcon /> : <MoonIcon />}
    </button>
  )
}

export default ThemeToggle
