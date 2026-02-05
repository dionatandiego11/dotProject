/**
 * Dashboard Principal - Router e Menu de Seleção
 * 
 * Exibe opções para acessar os dashboards específicos por perfil
 * ou renderiza o dashboard específico baseado na URL.
 */

import { useNavigate, Routes, Route } from 'react-router-dom'
import { lazy, Suspense } from 'react'
import Loading from '../../components/Loading'

// Lazy loading dos dashboards específicos
const DashboardPrefeito = lazy(() => import('./DashboardPrefeito'))
const DashboardSecretario = lazy(() => import('./DashboardSecretario'))
const DashboardCoordenador = lazy(() => import('./DashboardCoordenador'))
const DashboardTecnico = lazy(() => import('./DashboardTecnico'))
const DashboardControlador = lazy(() => import('./DashboardControlador'))

function DashboardMenu() {
    const navigate = useNavigate()

    const dashboards = [
        { 
            perfil: 'prefeito', 
            icone: '🏛️', 
            nome: 'Prefeito',
            descricao: 'Visão executiva geral da prefeitura',
            cor: '#1e40af'
        },
        { 
            perfil: 'secretario', 
            icone: '📋', 
            nome: 'Secretário',
            descricao: 'Visão da secretaria e seus projetos',
            cor: '#0f766e'
        },
        { 
            perfil: 'coordenador', 
            icone: '👤', 
            nome: 'Coordenador',
            descricao: 'Gestão de projetos e equipes',
            cor: '#7c3aed'
        },
        { 
            perfil: 'tecnico', 
            icone: '👷', 
            nome: 'Técnico',
            descricao: 'Acompanhamento de tarefas e atividades',
            cor: '#0369a1'
        },
        { 
            perfil: 'controlador', 
            icone: '🔍', 
            nome: 'Controlador',
            descricao: 'Fiscalização e análise de projetos',
            cor: '#be123c'
        },
    ]

    return (
        <div style={{ padding: 32, maxWidth: 1200, margin: '0 auto' }}>
            <div style={{ textAlign: 'center', marginBottom: 48 }}>
                <div style={{ fontSize: 64, marginBottom: 16 }}>🏛️</div>
                <h1 style={{ 
                    fontSize: 32, 
                    fontWeight: 700, 
                    color: '#111827',
                    marginBottom: 8 
                }}>
                    Dashboards
                </h1>
                <p style={{ fontSize: 18, color: '#6b7280' }}>
                    Selecione o perfil de visualização desejado
                </p>
            </div>

            <div style={{ 
                display: 'grid', 
                gridTemplateColumns: 'repeat(auto-fill, minmax(300px, 1fr))',
                gap: 24 
            }}>
                {dashboards.map((item) => (
                    <button
                        key={item.perfil}
                        onClick={() => navigate(`/dashboard/${item.perfil}`)}
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            alignItems: 'center',
                            padding: 32,
                            backgroundColor: 'white',
                            border: '2px solid #e5e7eb',
                            borderRadius: 16,
                            cursor: 'pointer',
                            transition: 'all 0.2s',
                            textAlign: 'center',
                        }}
                        onMouseEnter={(e) => {
                            e.currentTarget.style.borderColor = item.cor
                            e.currentTarget.style.transform = 'translateY(-4px)'
                            e.currentTarget.style.boxShadow = '0 12px 24px rgba(0,0,0,0.1)'
                        }}
                        onMouseLeave={(e) => {
                            e.currentTarget.style.borderColor = '#e5e7eb'
                            e.currentTarget.style.transform = 'translateY(0)'
                            e.currentTarget.style.boxShadow = 'none'
                        }}
                    >
                        <div style={{ 
                            fontSize: 56, 
                            marginBottom: 16 
                        }}>
                            {item.icone}
                        </div>
                        <h3 style={{ 
                            fontSize: 20, 
                            fontWeight: 600, 
                            color: item.cor,
                            marginBottom: 8 
                        }}>
                            {item.nome}
                        </h3>
                        <p style={{ 
                            fontSize: 14, 
                            color: '#6b7280',
                            lineHeight: 1.5 
                        }}>
                            {item.descricao}
                        </p>
                        <div style={{
                            marginTop: 16,
                            padding: '8px 16px',
                            backgroundColor: item.cor + '15',
                            color: item.cor,
                            borderRadius: 8,
                            fontSize: 14,
                            fontWeight: 500,
                        }}>
                            Acessar Dashboard →
                        </div>
                    </button>
                ))}
            </div>
        </div>
    )
}

function DashboardRouter() {
    // Rotas relativas a /dashboard
    // Quando o pai usa path="/dashboard/*", as rotas filhas usam caminhos relativos
    return (
        <Routes>
            <Route index element={<DashboardMenu />} />
            <Route 
                path="prefeito" 
                element={
                    <Suspense fallback={<Loading />}>
                        <DashboardPrefeito />
                    </Suspense>
                } 
            />
            <Route 
                path="secretario" 
                element={
                    <Suspense fallback={<Loading />}>
                        <DashboardSecretario />
                    </Suspense>
                } 
            />
            <Route 
                path="coordenador" 
                element={
                    <Suspense fallback={<Loading />}>
                        <DashboardCoordenador />
                    </Suspense>
                } 
            />
            <Route 
                path="tecnico" 
                element={
                    <Suspense fallback={<Loading />}>
                        <DashboardTecnico />
                    </Suspense>
                } 
            />
            <Route 
                path="controlador" 
                element={
                    <Suspense fallback={<Loading />}>
                        <DashboardControlador />
                    </Suspense>
                } 
            />
            <Route path="*" element={<DashboardMenu />} />
        </Routes>
    )
}

export default DashboardRouter
