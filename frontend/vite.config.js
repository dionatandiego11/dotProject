import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { visualizer } from 'rollup-plugin-visualizer'

// https://vitejs.dev/config/
export default defineConfig(({ mode }) => ({
    base: '/',
    plugins: [
        react(),
        // Visualizador de bundle (apenas em análise)
        mode === 'analyze' && visualizer({
            open: true,
            gzipSize: true,
            brotliSize: true,
        }),
    ],
    server: {
        port: 5173,
        host: '0.0.0.0',
        strictPort: true,
        watch: {
            usePolling: true,
        },
        // Proxy para API - usa nginx:80 (dentro do Docker)
        // O frontend roda como container, então usa o nome do serviço nginx
        proxy: {
            '/api.php': {
                target: process.env.VITE_API_URL || 'http://nginx:80',
                changeOrigin: true,
                secure: false,
                ws: true,
            },
            '/api': {
                target: process.env.VITE_API_URL || 'http://nginx:80',
                changeOrigin: true,
                secure: false,
                ws: true,
            }
        }
    },
    build: {
        outDir: 'dist',
        sourcemap: true,

        // Otimizações de build (usando esbuild padrão)
        minify: 'esbuild',

        // Code splitting
        rollupOptions: {
            output: {
                manualChunks: {
                    // Vendor separado
                    'vendor-react': ['react', 'react-dom', 'react-router-dom'],
                },
                // Nomenclatura de arquivos otimizada
                entryFileNames: 'assets/[name]-[hash].js',
                chunkFileNames: 'assets/[name]-[hash].js',
                assetFileNames: (assetInfo) => {
                    const info = assetInfo.name.split('.')
                    const ext = info[info.length - 1]
                    if (/png|jpe?g|svg|gif|tiff|bmp|ico/i.test(ext)) {
                        return 'assets/images/[name]-[hash][extname]'
                    }
                    if (/css/i.test(ext)) {
                        return 'assets/css/[name]-[hash][extname]'
                    }
                    return 'assets/[name]-[hash][extname]'
                },
            },
        },

        // Tamanho de aviso para chunks
        chunkSizeWarningLimit: 500,

        // CSS otimizado
        cssCodeSplit: true,

        // Pré-carregamento de assets
        assetsInlineLimit: 4096, // 4kb
    },

    // Otimização de dependências
    optimizeDeps: {
        include: ['react', 'react-dom', 'react-router-dom'],
        exclude: [],
    },

    // Preview config
    preview: {
        port: 4173,
        strictPort: true,
    },
}))
