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
        proxy: {
            '/api': {
                target: 'https://nginx:443',
                changeOrigin: true,
                secure: false,
                ws: true,
                configure: (proxy, _options) => {
                    proxy.on('error', (err, _req, _res) => {
                        console.log('proxy error', err);
                    });
                    proxy.on('proxyReq', (proxyReq, req, _res) => {
                        console.log('Sending Request to the Target:', req.method, req.url);
                    });
                    proxy.on('proxyRes', (proxyRes, req, _res) => {
                        console.log('Received Response from the Target:', proxyRes.statusCode, req.url);
                    });
                }
            }
        }
    },
    build: {
        outDir: 'dist',
        sourcemap: true,
        
        // Otimizações de build
        minify: 'terser',
        terserOptions: {
            compress: {
                drop_console: true,
                drop_debugger: true,
            },
        },
        
        // Code splitting
        rollupOptions: {
            output: {
                manualChunks: {
                    // Vendor separado
                    'vendor-react': ['react', 'react-dom', 'react-router-dom'],
                    
                    // Grupos dinâmicos por tamanho
                    'vendor-charts': ['recharts'],
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
