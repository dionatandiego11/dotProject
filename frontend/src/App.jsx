import { Routes, Route, Navigate, useLocation } from 'react-router-dom'
import { lazy, Suspense, useState, useEffect } from 'react'
import Loading from './components/Loading'
import { isAuthenticated } from './services/api'
import { ToastProvider } from './contexts/ToastContext'
import ErrorBoundary from './components/ErrorBoundary'
import useCurrentUser from './hooks/useCurrentUser'
import { isAdminUser } from './utils/userAccess'

// Layouts
import MainLayout from './layouts/MainLayout'

// Lazy loading das páginas
const Dashboard = lazy(() => import('./pages/Dashboard'))
const Ppa = lazy(() => import('./pages/Ppa'))
const Programas = lazy(() => import('./pages/Programas'))
const Acoes = lazy(() => import('./pages/Acoes'))
const Projects = lazy(() => import('./pages/Projects'))
const ProjectDetails = lazy(() => import('./pages/ProjectDetails'))
const Etapas = lazy(() => import('./pages/Etapas'))
const Tarefas = lazy(() => import('./pages/Tarefas'))
const Kanban = lazy(() => import('./pages/Kanban'))
const Metas = lazy(() => import('./pages/metas/MetasPage'))
const Login = lazy(() => import('./pages/Login'))
const SetupWizard = lazy(() => import('./pages/SetupWizard'))

// Admin pages
const AdminLayout = lazy(() => import('./pages/admin/AdminLayout'))
const DashboardAdmin = lazy(() => import('./pages/admin/DashboardAdmin'))
const PrefeituraConfig = lazy(() => import('./pages/admin/PrefeituraConfig'))
const NiveisList = lazy(() => import('./pages/admin/NiveisList'))
const UnidadesTree = lazy(() => import('./pages/admin/UnidadesTree'))
const Organograma = lazy(() => import('./pages/admin/Organograma'))
const UsuariosAdmin = lazy(() => import('./pages/admin/UsuariosAdmin'))

function PrivateRoute({ children }) {
    if (!isAuthenticated()) {
        return <Navigate to="/login" replace />
    }
    return children
}

function AdminRoute({ children }) {
    const { user, loading } = useCurrentUser()

    if (loading) {
        return <Loading fullScreen />
    }

    if (!isAdminUser(user)) {
        return <Navigate to="/" replace />
    }

    return children
}

function App() {
    const [loading, setLoading] = useState(true)
    const location = useLocation()

    useEffect(() => {
        setLoading(false)
    }, [])

    if (loading) {
        return <Loading fullScreen />
    }

    // Redirect /app to /
    if (location.pathname.startsWith('/app')) {
        const nextPath = location.pathname.replace(/^\/app/, '') || '/'
        return <Navigate to={`${nextPath}${location.search}${location.hash}`} replace />
    }

    // Redirect /dashboard to / (redundante)
    if (location.pathname === '/dashboard' || location.pathname === '/dashboard/') {
        return <Navigate to="/" replace />
    }

    return (
        <ErrorBoundary>
            <ToastProvider>
                <Routes>
                    {/* Login - Public */}
                    <Route
                        path="/login"
                        element={
                            <Suspense fallback={<Loading fullScreen />}>
                                <Login />
                            </Suspense>
                        }
                    />

                    {/* Setup antigo - compatibilidade */}
                    <Route
                        path="/setup"
                        element={
                            <PrivateRoute>
                                <Navigate to="/admin/setup" replace />
                            </PrivateRoute>
                        }
                    />

                    {/* Protected Routes with MainLayout */}
                    <Route
                        path="/"
                        element={
                            <PrivateRoute>
                                <MainLayout />
                            </PrivateRoute>
                        }
                    >
                        {/* Dashboard Principal (único) */}
                        <Route index element={
                            <Suspense fallback={<Loading />}>
                                <Dashboard />
                            </Suspense>
                        } />

                        {/* Projects */}
                        <Route path="ppa" element={
                            <Suspense fallback={<Loading />}>
                                <Ppa />
                            </Suspense>
                        } />
                        <Route path="programas" element={
                            <Suspense fallback={<Loading />}>
                                <Programas />
                            </Suspense>
                        } />
                        <Route path="acoes" element={
                            <Suspense fallback={<Loading />}>
                                <Acoes />
                            </Suspense>
                        } />

                        {/* Projects */}
                        <Route path="projects" element={
                            <Suspense fallback={<Loading />}>
                                <Projects />
                            </Suspense>
                        } />
                        <Route path="projects/:id" element={
                            <Suspense fallback={<Loading />}>
                                <ProjectDetails />
                            </Suspense>
                        } />

                        {/* Etapas (CRUD macro por projeto) */}
                        <Route path="etapas" element={
                            <Suspense fallback={<Loading />}>
                                <Etapas />
                            </Suspense>
                        } />

                        {/* Tarefas (lista com filtros) */}
                        <Route path="tarefas" element={
                            <Suspense fallback={<Loading />}>
                                <Tarefas />
                            </Suspense>
                        } />
                        <Route path="tasks" element={<Navigate to="/tarefas" replace />} />

                        {/* Kanban */}
                        <Route path="kanban" element={
                            <Suspense fallback={<Loading />}>
                                <Kanban />
                            </Suspense>
                        } />

                        {/* Metas / Indicadores */}
                        <Route path="metas" element={
                            <Suspense fallback={<Loading />}>
                                <Metas />
                            </Suspense>
                        } />

                        {/* Admin — Layout com sidebar + sub-rotas */}
                        <Route path="admin" element={
                            <AdminRoute>
                                <Suspense fallback={<Loading />}>
                                    <AdminLayout />
                                </Suspense>
                            </AdminRoute>
                        }>
                            <Route index element={
                                <Suspense fallback={<Loading />}>
                                    <DashboardAdmin />
                                </Suspense>
                            } />
                            <Route path="prefeitura" element={
                                <Suspense fallback={<Loading />}>
                                    <PrefeituraConfig />
                                </Suspense>
                            } />
                            <Route path="niveis" element={
                                <Suspense fallback={<Loading />}>
                                    <NiveisList />
                                </Suspense>
                            } />
                            <Route path="unidades" element={
                                <Suspense fallback={<Loading />}>
                                    <UnidadesTree />
                                </Suspense>
                            } />
                            <Route path="usuarios" element={
                                <Suspense fallback={<Loading />}>
                                    <UsuariosAdmin />
                                </Suspense>
                            } />
                            <Route path="organograma" element={
                                <Suspense fallback={<Loading />}>
                                    <Organograma />
                                </Suspense>
                            } />
                            <Route path="setup" element={
                                <Suspense fallback={<Loading />}>
                                    <SetupWizard />
                                </Suspense>
                            } />
                        </Route>
                    </Route>

                    {/* Redirect /dashboard/* to / (todos os dashboards agora são em /) */}
                    <Route path="/dashboard/*" element={
                        <PrivateRoute>
                            <Navigate to="/" replace />
                        </PrivateRoute>
                    } />

                    {/* Debug page - public */}
                    <Route path="/debug" element={
                        <div style={{ padding: 40 }}>
                            <h1>🔧 Debug</h1>
                            <p>Token: {localStorage.getItem('dp_token') ? '✅ Presente' : '❌ Ausente'}</p>
                            <button onClick={() => { localStorage.clear(); window.location.reload(); }}>
                                🗑️ Limpar Cache
                            </button>
                            <hr />
                            <a href="/">← Voltar</a>
                        </div>
                    } />

                    {/* 404 */}
                    <Route path="*" element={
                        <div style={{ padding: 40, textAlign: 'center' }}>
                            <h1>404 - Página não encontrada</h1>
                            <a href="/">← Voltar para Home</a>
                        </div>
                    } />
                </Routes>
            </ToastProvider>
        </ErrorBoundary>
    )
}

export default App
