/**
 * DotProject Modern Hybrid Theme - App JavaScript
 * 
 * Modern utilities for the dotProject interface.
 * Provides toast notifications, modal dialogs, form helpers, and AJAX utilities.
 */

(function() {
    'use strict';

    // ========================================
    // Toast Notification System
    // ========================================
    window.Toast = {
        container: null,

        init() {
            if (!this.container) {
                this.container = document.createElement('div');
                this.container.className = 'toast-container';
                document.body.appendChild(this.container);
            }
        },

        show(message, type = 'info', duration = 4000) {
            this.init();

            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            
            const icon = this.getIcon(type);
            toast.innerHTML = `
                <span class="toast-icon">${icon}</span>
                <span class="toast-message">${message}</span>
                <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
            `;

            this.container.appendChild(toast);

            // Auto remove
            if (duration > 0) {
                setTimeout(() => {
                    toast.classList.add('toast-exit');
                    setTimeout(() => toast.remove(), 300);
                }, duration);
            }

            return toast;
        },

        getIcon(type) {
            const icons = {
                success: '<svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>',
                warning: '<svg class="w-5 h-5 text-yellow-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>',
                danger: '<svg class="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>',
                info: '<svg class="w-5 h-5 text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>'
            };
            return icons[type] || icons.info;
        },

        success(message, duration) { return this.show(message, 'success', duration); },
        warning(message, duration) { return this.show(message, 'warning', duration); },
        danger(message, duration) { return this.show(message, 'danger', duration); },
        error(message, duration) { return this.show(message, 'danger', duration); },
        info(message, duration) { return this.show(message, 'info', duration); }
    };

    // ========================================
    // Modal Dialog System
    // ========================================
    window.Modal = {
        backdrop: null,

        create(options = {}) {
            const defaults = {
                title: '',
                content: '',
                size: 'md', // sm, md, lg
                closable: true,
                buttons: []
            };
            const opts = { ...defaults, ...options };

            // Create backdrop
            this.backdrop = document.createElement('div');
            this.backdrop.className = 'modal-backdrop';
            
            const sizeClass = {
                sm: 'max-w-sm',
                md: 'max-w-lg',
                lg: 'max-w-2xl',
                xl: 'max-w-4xl'
            }[opts.size] || 'max-w-lg';

            // Build modal HTML
            let buttonsHtml = '';
            if (opts.buttons.length) {
                buttonsHtml = '<div class="modal-footer">';
                opts.buttons.forEach((btn, idx) => {
                    const btnClass = btn.class || 'btn-secondary';
                    buttonsHtml += `<button class="btn ${btnClass}" data-btn-idx="${idx}">${btn.text}</button>`;
                });
                buttonsHtml += '</div>';
            }

            this.backdrop.innerHTML = `
                <div class="modal ${sizeClass}">
                    ${opts.title ? `
                        <div class="modal-header">
                            <h3 class="modal-title">${opts.title}</h3>
                            ${opts.closable ? '<button class="modal-close" data-close>&times;</button>' : ''}
                        </div>
                    ` : ''}
                    <div class="modal-body">${opts.content}</div>
                    ${buttonsHtml}
                </div>
            `;

            document.body.appendChild(this.backdrop);

            // Event handlers
            if (opts.closable) {
                this.backdrop.querySelector('[data-close]')?.addEventListener('click', () => this.close());
                this.backdrop.addEventListener('click', (e) => {
                    if (e.target === this.backdrop) this.close();
                });
            }

            // Button handlers
            opts.buttons.forEach((btn, idx) => {
                const btnEl = this.backdrop.querySelector(`[data-btn-idx="${idx}"]`);
                if (btnEl && btn.onClick) {
                    btnEl.addEventListener('click', () => btn.onClick(this));
                }
            });

            // Show with animation
            requestAnimationFrame(() => {
                this.backdrop.classList.add('active');
            });

            return this;
        },

        close() {
            if (this.backdrop) {
                this.backdrop.classList.remove('active');
                setTimeout(() => {
                    this.backdrop.remove();
                    this.backdrop = null;
                }, 200);
            }
        },

        // Convenience methods
        alert(message, title = 'Aviso') {
            return new Promise((resolve) => {
                this.create({
                    title: title,
                    content: `<p>${message}</p>`,
                    buttons: [{
                        text: 'OK',
                        class: 'btn-primary',
                        onClick: (modal) => {
                            modal.close();
                            resolve(true);
                        }
                    }]
                });
            });
        },

        confirm(message, title = 'Confirmar') {
            return new Promise((resolve) => {
                this.create({
                    title: title,
                    content: `<p>${message}</p>`,
                    buttons: [
                        {
                            text: 'Cancelar',
                            class: 'btn-secondary',
                            onClick: (modal) => {
                                modal.close();
                                resolve(false);
                            }
                        },
                        {
                            text: 'Confirmar',
                            class: 'btn-primary',
                            onClick: (modal) => {
                                modal.close();
                                resolve(true);
                            }
                        }
                    ]
                });
            });
        },

        prompt(message, defaultValue = '', title = 'Entrada') {
            return new Promise((resolve) => {
                const inputId = 'modal-prompt-' + Date.now();
                this.create({
                    title: title,
                    content: `
                        <p class="mb-4">${message}</p>
                        <input type="text" id="${inputId}" class="text" value="${defaultValue}" style="width:100%">
                    `,
                    buttons: [
                        {
                            text: 'Cancelar',
                            class: 'btn-secondary',
                            onClick: (modal) => {
                                modal.close();
                                resolve(null);
                            }
                        },
                        {
                            text: 'OK',
                            class: 'btn-primary',
                            onClick: (modal) => {
                                const value = document.getElementById(inputId)?.value;
                                modal.close();
                                resolve(value);
                            }
                        }
                    ]
                });

                // Focus input
                setTimeout(() => {
                    document.getElementById(inputId)?.focus();
                }, 100);
            });
        }
    };

    // ========================================
    // AJAX Utilities
    // ========================================
    window.Ajax = {
        async request(url, options = {}) {
            const defaults = {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: null
            };
            
            const opts = { ...defaults, ...options };

            // Convert body to FormData if needed
            if (opts.body && !(opts.body instanceof FormData) && typeof opts.body === 'object') {
                const formData = new FormData();
                Object.entries(opts.body).forEach(([key, value]) => {
                    formData.append(key, value);
                });
                opts.body = formData;
            }

            try {
                const response = await fetch(url, opts);
                const contentType = response.headers.get('content-type');
                
                if (contentType?.includes('application/json')) {
                    return await response.json();
                }
                return await response.text();
            } catch (error) {
                console.error('Ajax error:', error);
                throw error;
            }
        },

        get(url, params = {}) {
            const queryString = new URLSearchParams(params).toString();
            const fullUrl = queryString ? `${url}?${queryString}` : url;
            return this.request(fullUrl);
        },

        post(url, data = {}) {
            return this.request(url, {
                method: 'POST',
                body: data
            });
        }
    };

    // ========================================
    // Form Validation
    // ========================================
    window.FormValidator = {
        validate(form) {
            const errors = [];
            const requiredFields = form.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                this.clearError(field);
                
                if (!field.value.trim()) {
                    const label = form.querySelector(`label[for="${field.id}"]`)?.textContent || field.name;
                    errors.push({ field, message: `${label} é obrigatório` });
                    this.showError(field, 'Campo obrigatório');
                }
            });

            // Email validation
            const emailFields = form.querySelectorAll('input[type="email"]');
            emailFields.forEach(field => {
                if (field.value && !this.isValidEmail(field.value)) {
                    errors.push({ field, message: 'Email inválido' });
                    this.showError(field, 'Email inválido');
                }
            });

            return {
                valid: errors.length === 0,
                errors
            };
        },

        isValidEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        },

        showError(field, message) {
            field.classList.add('border-red-500');
            const errorEl = document.createElement('span');
            errorEl.className = 'text-red-500 text-sm mt-1 field-error';
            errorEl.textContent = message;
            field.parentNode.appendChild(errorEl);
        },

        clearError(field) {
            field.classList.remove('border-red-500');
            const error = field.parentNode.querySelector('.field-error');
            if (error) error.remove();
        },

        clearAll(form) {
            form.querySelectorAll('.field-error').forEach(el => el.remove());
            form.querySelectorAll('.border-red-500').forEach(el => el.classList.remove('border-red-500'));
        }
    };

    // ========================================
    // Dropdown Handler
    // ========================================
    window.Dropdown = {
        init() {
            document.addEventListener('click', (e) => {
                const toggle = e.target.closest('[data-dropdown-toggle]');
                
                if (toggle) {
                    e.preventDefault();
                    const dropdown = toggle.closest('.dropdown');
                    dropdown?.classList.toggle('active');
                    return;
                }

                // Close all dropdowns when clicking outside
                if (!e.target.closest('.dropdown')) {
                    document.querySelectorAll('.dropdown.active').forEach(d => d.classList.remove('active'));
                }
            });
        }
    };

    // ========================================
    // Loading Overlay
    // ========================================
    window.Loading = {
        overlay: null,

        show(message = 'Carregando...') {
            if (!this.overlay) {
                this.overlay = document.createElement('div');
                this.overlay.className = 'loading-overlay';
                this.overlay.innerHTML = `
                    <div class="text-center">
                        <div class="spinner spinner-lg mb-4"></div>
                        <p class="text-gray-600">${message}</p>
                    </div>
                `;
                document.body.appendChild(this.overlay);
            }
        },

        hide() {
            if (this.overlay) {
                this.overlay.remove();
                this.overlay = null;
            }
        }
    };

    // ========================================
    // Confirm Delete Enhancement
    // ========================================
    window.confirmDelete = async function(message = 'Tem certeza que deseja excluir?') {
        return await Modal.confirm(message, 'Confirmar Exclusão');
    };

    // ========================================
    // Initialize on DOM Ready
    // ========================================
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize dropdowns
        Dropdown.init();

        // Add confirm to delete links
        document.querySelectorAll('a[href*="dosql"][href*="del"], a[onclick*="delete"]').forEach(link => {
            link.addEventListener('click', async (e) => {
                e.preventDefault();
                const confirmed = await confirmDelete();
                if (confirmed) {
                    window.location.href = link.href;
                }
            });
        });

        // Auto-hide alerts after 5 seconds
        document.querySelectorAll('.alert-auto-hide').forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        });

        console.log('DotProject Modern Theme initialized');
    });

})();
