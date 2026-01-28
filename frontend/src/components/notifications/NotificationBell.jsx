/**
 * Notification Bell Component
 * 
 * Icone de sino com dropdown de notificacoes.
 */

import { useState, useEffect, useCallback } from 'react'
import PropTypes from 'prop-types'
import { 
    getNotifications, 
    getUnreadNotificationsCount, 
    markNotificationAsRead, 
    markAllNotificationsAsRead 
} from '../../services/api'
import './NotificationBell.css'

function NotificationBell({ onNotificationClick }) {
  const [isOpen, setIsOpen] = useState(false)
  const [notifications, setNotifications] = useState([])
  const [unreadCount, setUnreadCount] = useState(0)
  const [loading, setLoading] = useState(false)

  // Carrega notificacoes
  const loadNotifications = useCallback(async () => {
    try {
      setLoading(true)
      const result = await getNotifications(false, 10)

      if (result.success) {
        setNotifications(result.data.notifications || [])
        setUnreadCount(result.data.unread_count || 0)
      }
    } catch (err) {
      console.error('Failed to load notifications:', err)
    } finally {
      setLoading(false)
    }
  }, [])

  // Carrega contagem nao lida
  const loadUnreadCount = useCallback(async () => {
    try {
      const result = await getUnreadNotificationsCount()

      if (result.success) {
        setUnreadCount(result.data.unread_count || 0)
      }
    } catch (err) {
      console.error('Failed to load count:', err)
    }
  }, [])

  // Atualiza periodicamente
  useEffect(() => {
    loadUnreadCount()
    
    const interval = setInterval(loadUnreadCount, 30000)
    return () => clearInterval(interval)
  }, [loadUnreadCount])

  // Carrega notificacoes ao abrir
  useEffect(() => {
    if (isOpen) {
      loadNotifications()
    }
  }, [isOpen, loadNotifications])

  // Marca como lida
  const markAsRead = async (notificationId, event) => {
    event.stopPropagation()
    
    try {
      const result = await markNotificationAsRead(notificationId)

      if (result.success) {
        setNotifications(prev =>
          prev.map(n =>
            n.id === notificationId ? { ...n, is_read: true } : n
          )
        )
        setUnreadCount(prev => Math.max(0, prev - 1))
      }
    } catch (err) {
      console.error('Failed to mark as read:', err)
    }
  }

  // Marca todas como lidas
  const handleMarkAllAsRead = async () => {
    try {
      const result = await markAllNotificationsAsRead()

      if (result.success) {
        setNotifications(prev =>
          prev.map(n => ({ ...n, is_read: true }))
        )
        setUnreadCount(0)
      }
    } catch (err) {
      console.error('Failed to mark all as read:', err)
    }
  }

  // Fecha ao clicar fora
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (!event.target.closest('.notification-bell-container')) {
        setIsOpen(false)
      }
    }

    document.addEventListener('mousedown', handleClickOutside)
    return () => document.removeEventListener('mousedown', handleClickOutside)
  }, [])

  const handleNotificationClick = (notification) => {
    if (!notification.is_read) {
      markAsRead(notification.id, { stopPropagation: () => {} })
    }
    
    onNotificationClick?.(notification)
    setIsOpen(false)
  }

  return (
    <div className="notification-bell-container">
      <button
        className="notification-bell-button"
        onClick={() => setIsOpen(!isOpen)}
        aria-label="Notifications"
      >
        <svg className="notification-bell-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>
        
        {unreadCount > 0 && (
          <span className="notification-badge">
            {unreadCount > 99 ? '99+' : unreadCount}
          </span>
        )}
      </button>

      {isOpen && (
        <div className="notification-dropdown">
          <div className="notification-header">
            <h3 className="notification-title">Notificacoes</h3>
            {unreadCount > 0 && (
              <button
                className="notification-mark-all"
                onClick={handleMarkAllAsRead}
              >
                Marcar todas
              </button>
            )}
          </div>

          <div className="notification-list">
            {loading && notifications.length === 0 && (
              <div className="notification-loading">Carregando...</div>
            )}

            {!loading && notifications.length === 0 && (
              <div className="notification-empty">
                <span className="notification-empty-icon">🔔</span>
                <p>Nenhuma notificacao</p>
              </div>
            )}

            {notifications.map(notification => (
              <div
                key={notification.id}
                className={`notification-item ${!notification.is_read ? 'notification-unread' : ''}`}
                onClick={() => handleNotificationClick(notification)}
              >
                <div
                  className="notification-icon"
                  style={{ backgroundColor: notification.color + '20', color: notification.color }}
                >
                  {notification.icon}
                </div>

                <div className="notification-content">
                  <h4 className="notification-item-title">{notification.title}</h4>
                  <p className="notification-message">{notification.message}</p>
                  <span className="notification-time">{notification.time_ago}</span>
                </div>

                {!notification.is_read && (
                  <button
                    className="notification-read-btn"
                    onClick={(e) => markAsRead(notification.id, e)}
                    title="Marcar como lida"
                  >
                    <span>●</span>
                  </button>
                )}
              </div>
            ))}
          </div>

          <div className="notification-footer">
            <a href="/notifications" className="notification-view-all">
              Ver todas
            </a>
          </div>
        </div>
      )}
    </div>
  )
}

NotificationBell.propTypes = {
  onNotificationClick: PropTypes.func,
}

export default NotificationBell
