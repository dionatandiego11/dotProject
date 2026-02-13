import { defineConfig } from '@playwright/test'

const baseURL = process.env.E2E_BASE_URL || 'http://127.0.0.1:5173'
const shouldManageWebServer = process.env.E2E_SKIP_WEBSERVER !== '1'

export default defineConfig({
    testDir: './e2e',
    timeout: 30000,
    fullyParallel: false,
    retries: process.env.CI ? 1 : 0,
    reporter: 'list',
    use: {
        baseURL,
        headless: true,
        trace: 'retain-on-failure',
    },
    webServer: shouldManageWebServer
        ? {
            command: 'npm run dev -- --host 127.0.0.1 --port 5173',
            url: baseURL,
            reuseExistingServer: true,
            timeout: 120000,
        }
        : undefined,
})
