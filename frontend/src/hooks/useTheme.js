/**
 * Hook useTheme - Gerenciamento de tema
 * 
 * Permite alternar entre temas claro e escuro
 */

import { useState, useEffect, useCallback } from 'react'

const THEME_KEY = 'dotproject-theme'

export function useTheme() {
  const [theme, setTheme] = useState(() => {
    // Verificar localStorage ou preferência do sistema
    const saved = localStorage.getItem(THEME_KEY)
    if (saved) return saved
    
    // Verificar preferência do sistema
    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
      return 'dark'
    }
    return 'light'
  })

  useEffect(() => {
    // Aplicar tema ao documento
    document.documentElement.setAttribute('data-theme', theme)
    localStorage.setItem(THEME_KEY, theme)
  }, [theme])

  const toggleTheme = useCallback(() => {
    setTheme(prev => prev === 'light' ? 'dark' : 'light')
  }, [])

  const setLightTheme = useCallback(() => setTheme('light'), [])
  const setDarkTheme = useCallback(() => setTheme('dark'), [])

  return {
    theme,
    isDark: theme === 'dark',
    isLight: theme === 'light',
    toggleTheme,
    setLightTheme,
    setDarkTheme,
  }
}

export default useTheme
