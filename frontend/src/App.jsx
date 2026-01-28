import { Routes, Route, Navigate, useLocation } from 'react-router-dom'
import { lazy, Suspense, useState, useEffect } from 'react'
import Loading from './components/Loading'
import { isAuthenticated } from './services/api'

// Lazy loading das páginas
const Layout = lazy(() => import('./components/Layout'))
const Dashboard = lazy(() => import('./pages/Dashboard'))
const Projects = lazy(() => import('./pages/Projects'))
const Tasks = lazy(() => import('./pages/Tasks'))
const Kanban = lazy(() => import('./pages/Kanban'))
const Login = lazy(() => import('./pages/Login'))

function PrivateRoute({ children }) {
    if (!isAuthenticated()) {
        return <Navigate to="/login" replace />
    }
    return children
}

function App() {
    const [loading, setLoading] = useState(true)
    const location = useLocation()

    useEffect(() => {
        // Check auth on mount
        setLoading(false)
    }, [])

    if (loading) {
        return <Loading fullScreen />
    }

    if (location.pathname.startsWith('/app')) {
        const nextPath = location.pathname.replace(/^\/app/, '') || '/'
        return <Navigate to={`${nextPath}${location.search}${location.hash}`} replace />
    }

    return (
        <Routes>
            <Route 
                path="/login" 
                element={
                    <Suspense fallback={<Loading fullScreen />}>
                        <Login />
                    </Suspense>
                } 
            />

            <Route 
                path="/" 
                element={
                    <PrivateRoute>
                        <Suspense fallback={<Loading />}>
                            <Layout />
                        </Suspense>
                    </PrivateRoute>
                }
            >
                <Route index element={
                    <Suspense fallback={<Loading />}>
                        <Dashboard />
                    </Suspense>
                } />
                <Route path="projects" element={
                    <Suspense fallback={<Loading />}>
                        <Projects />
                    </Suspense>
                } />
                <Route path="tasks" element={
                    <Suspense fallback={<Loading />}>
                        <Tasks />
                    </Suspense>
                } />
                <Route path="kanban" element={
                    <Suspense fallback={<Loading />}>
                        <Kanban />
                    </Suspense>
                } />
            </Route>
        </Routes>
    )
}

export default App
