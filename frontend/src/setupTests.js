/**
 * Configuração inicial para testes
 */

import '@testing-library/jest-dom';

// Mock do localStorage
global.localStorage = {
    store: {},
    getItem(key) {
        return this.store[key] || null;
    },
    setItem(key, value) {
        this.store[key] = String(value);
    },
    removeItem(key) {
        delete this.store[key];
    },
    clear() {
        this.store = {};
    },
};

// Mock do window.location
Object.defineProperty(window, 'location', {
    writable: true,
    value: {
        href: '',
        assign: vi.fn(),
        replace: vi.fn(),
    },
});
