import { useEffect, useMemo, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import {
    getProjects,
    getProjetoEtapas,
    updateProjetoEtapa,
    concluirProjetoEtapa,
} from '../../services/api'
import Button from '../../components/ui/Button'
import { useToast } from '../../contexts/ToastContext'

const ETAPA_ESTADOS_CONCLUIDOS = new Set(['Concluida', 'Concluida_Com_Atraso'])

function formatDate(value) {
    if (!value) {
        return '-'
    }

    const date = new Date(value)
    if (Number.isNaN(date.getTime())) {
        return String(value)
    }

    return date.toLocaleDateString('pt-BR')
}

function asInputDate(value) {
    if (!value) {
        return ''
    }

    const date = new Date(value)
    if (Number.isNaN(date.getTime())) {
        return ''
    }

    return date.toISOString().slice(0, 10)
}

function isEtapaConcluida(estado) {
    return ETAPA_ESTADOS_CONCLUIDOS.has(String(estado || ''))
}

export default function EtapasPage() {
    const toast = useToast()
    const [searchParams, setSearchParams] = useSearchParams()
    const [projects, setProjects] = useState([])
    const [loadingProjects, setLoadingProjects] = useState(false)
    const [loadingEtapas, setLoadingEtapas] = useState(false)
    const [error, setError] = useState('')
    const [etapas, setEtapas] = useState([])
    const [etapaDates, setEtapaDates] = useState({})
    const [busyKey, setBusyKey] = useState('')

    const projectIdFromQuery = searchParams.get('project') || ''
    const selectedProjectId = projectIdFromQuery

    const selectedProject = useMemo(() => {
        return projects.find((project) => Number(project.id) === Number(selectedProjectId)) || null
    }, [projects, selectedProjectId])

    const summary = useMemo(() => {
        const total = etapas.length
        const concluidas = etapas.filter((etapa) => isEtapaConcluida(etapa.estado)).length
        const atrasadas = etapas.filter((etapa) => Number(etapa.dias_atraso || 0) > 0).length
        const mediaExecucao = total > 0
            ? etapas.reduce((acc, etapa) => acc + Number(etapa.percent_conclusao || 0), 0) / total
            : 0

        return {
            total,
            concluidas,
            atrasadas,
            mediaExecucao,
        }
    }, [etapas])

    useEffect(() => {
        loadProjects()
    }, [])

    useEffect(() => {
        if (!selectedProjectId) {
            setEtapas([])
            setEtapaDates({})
            return
        }

        loadEtapas(selectedProjectId)
    }, [selectedProjectId])

    async function loadProjects() {
        try {
            setLoadingProjects(true)
            const response = await getProjects({ per_page: 200 })
            const list = Array.isArray(response?.data) ? response.data : []
            setProjects(list)

            if (!projectIdFromQuery && list.length > 0) {
                setSearchParams({ project: String(list[0].id) }, { replace: true })
            }
        } catch (err) {
            setProjects([])
            setError(err?.message || 'Falha ao carregar projetos.')
        } finally {
            setLoadingProjects(false)
        }
    }

    async function loadEtapas(projectId) {
        try {
            setLoadingEtapas(true)
            setError('')
            const response = await getProjetoEtapas(projectId)
            const rows = Array.isArray(response?.data) ? response.data : []
            setEtapas(rows)

            const nextDates = {}
            for (const etapa of rows) {
                nextDates[String(etapa.numero)] = asInputDate(etapa.data_prevista_fim)
            }
            setEtapaDates(nextDates)
        } catch (err) {
            setEtapas([])
            setError(err?.message || 'Falha ao carregar etapas do projeto.')
        } finally {
            setLoadingEtapas(false)
        }
    }

    async function handleSaveEtapa(etapa) {
        if (!selectedProjectId || !etapa?.numero) {
            return
        }

        const etapaKey = String(etapa.numero)
        const key = `save:${etapaKey}`
        const dataPrevistaFim = etapaDates[etapaKey] || null

        try {
            setBusyKey(key)
            await updateProjetoEtapa(selectedProjectId, etapa.numero, {
                data_prevista_fim: dataPrevistaFim,
            })
            toast.success(`Etapa ${etapa.numero} atualizada.`)
            await loadEtapas(selectedProjectId)
        } catch (err) {
            toast.error(err?.message || 'Falha ao atualizar etapa.')
        } finally {
            setBusyKey('')
        }
    }

    async function handleConcluirEtapa(etapa) {
        if (!selectedProjectId || !etapa?.numero) {
            return
        }

        const key = `finish:${etapa.numero}`
        const payload = {}
        const diasAtraso = Number(etapa?.dias_atraso || 0)

        if (diasAtraso > 0) {
            const justificativa = window.prompt(
                `A etapa ${etapa.numero} está atrasada em ${diasAtraso} dias. Informe a justificativa:`,
                ''
            )
            if (!justificativa || !justificativa.trim()) {
                toast.error('Justificativa de atraso é obrigatória para concluir a etapa.')
                return
            }
            payload.justificativa_atraso = justificativa.trim()
        }

        try {
            setBusyKey(key)
            await concluirProjetoEtapa(selectedProjectId, etapa.numero, payload)
            toast.success(`Etapa ${etapa.numero} concluída.`)
            await loadEtapas(selectedProjectId)
        } catch (err) {
            toast.error(err?.message || 'Falha ao concluir etapa.')
        } finally {
            setBusyKey('')
        }
    }

    return (
        <div className="page-content">
            <div className="topbar">
                <h1 className="page-title">Etapas</h1>
                <div style={{ display: 'flex', gap: 'var(--spacing-3)', alignItems: 'center' }}>
                    <select
                        value={selectedProjectId}
                        onChange={(event) => {
                            const value = event.target.value
                            if (!value) {
                                setSearchParams({}, { replace: true })
                                return
                            }
                            setSearchParams({ project: value }, { replace: true })
                        }}
                        disabled={loadingProjects}
                        style={{
                            minWidth: 260,
                            padding: 'var(--spacing-2) var(--spacing-3)',
                            border: '1px solid var(--color-gray-300)',
                            borderRadius: 'var(--radius-md)',
                            background: 'white',
                        }}
                    >
                        <option value="">Selecione um projeto</option>
                        {projects.map((project) => (
                            <option key={project.id} value={String(project.id)}>
                                {project.name}
                            </option>
                        ))}
                    </select>
                    <Button
                        variant="secondary"
                        onClick={() => selectedProjectId && loadEtapas(selectedProjectId)}
                        disabled={!selectedProjectId || loadingEtapas}
                    >
                        Atualizar
                    </Button>
                </div>
            </div>

            {error && (
                <div className="card" style={{ marginBottom: 'var(--spacing-4)' }}>
                    <div className="card-body" style={{ color: 'var(--color-danger-600)' }}>
                        {error}
                    </div>
                </div>
            )}

            <div className="card" style={{ marginBottom: 'var(--spacing-4)' }}>
                <div className="card-header">
                    <h2 className="card-title">
                        Resumo das etapas {selectedProject ? `- ${selectedProject.name}` : ''}
                    </h2>
                </div>
                <div
                    className="card-body"
                    style={{
                        display: 'grid',
                        gap: 'var(--spacing-4)',
                        gridTemplateColumns: 'repeat(auto-fit, minmax(160px, 1fr))',
                    }}
                >
                    <div><strong>{summary.total}</strong><div style={{ color: 'var(--color-gray-500)' }}>Total</div></div>
                    <div><strong>{summary.concluidas}</strong><div style={{ color: 'var(--color-gray-500)' }}>Concluídas</div></div>
                    <div><strong>{summary.atrasadas}</strong><div style={{ color: 'var(--color-gray-500)' }}>Atrasadas</div></div>
                    <div><strong>{summary.mediaExecucao.toFixed(2)}%</strong><div style={{ color: 'var(--color-gray-500)' }}>Execução média</div></div>
                </div>
            </div>

            <div className="card">
                <div className="card-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <h2 className="card-title">CRUD de Etapas</h2>
                    <span style={{ fontSize: '0.875rem', color: 'var(--color-gray-500)' }}>
                        {etapas.length} etapas
                    </span>
                </div>
                <div className="card-body" style={{ padding: 0 }}>
                    {!selectedProjectId ? (
                        <div style={{ padding: 'var(--spacing-6)', color: 'var(--color-gray-500)' }}>
                            Selecione um projeto para gerenciar as etapas.
                        </div>
                    ) : loadingEtapas ? (
                        <div style={{ padding: 'var(--spacing-6)', color: 'var(--color-gray-500)' }}>
                            Carregando etapas...
                        </div>
                    ) : etapas.length === 0 ? (
                        <div style={{ padding: 'var(--spacing-6)', color: 'var(--color-gray-500)' }}>
                            Nenhuma etapa disponível para este projeto.
                        </div>
                    ) : (
                        <table className="table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Etapa</th>
                                    <th>Estado</th>
                                    <th>Prazo previsto</th>
                                    <th>Prazo real</th>
                                    <th>Atraso</th>
                                    <th>Execução</th>
                                    <th>Tarefas</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                {etapas.map((etapa) => {
                                    const etapaNumero = Number(etapa.numero || 0)
                                    const etapaKey = String(etapaNumero)
                                    const saving = busyKey === `save:${etapaKey}`
                                    const concluding = busyKey === `finish:${etapaKey}`
                                    const concluded = isEtapaConcluida(etapa.estado)
                                    const disabled = saving || concluding

                                    return (
                                        <tr key={etapaKey}>
                                            <td>{etapaNumero}</td>
                                            <td><strong>{etapa.nome || '-'}</strong></td>
                                            <td>
                                                <span style={{ color: etapa.cor_status || 'inherit' }}>
                                                    {etapa.estado || '-'}
                                                </span>
                                            </td>
                                            <td>
                                                <input
                                                    type="date"
                                                    value={etapaDates[etapaKey] || ''}
                                                    onChange={(event) => {
                                                        const nextDate = event.target.value
                                                        setEtapaDates((prev) => ({
                                                            ...prev,
                                                            [etapaKey]: nextDate,
                                                        }))
                                                    }}
                                                    disabled={disabled}
                                                    style={{
                                                        padding: 'var(--spacing-1) var(--spacing-2)',
                                                        border: '1px solid var(--color-gray-300)',
                                                        borderRadius: 'var(--radius-md)',
                                                        width: 140,
                                                    }}
                                                />
                                            </td>
                                            <td>{formatDate(etapa.data_real_fim)}</td>
                                            <td>
                                                {Number(etapa.dias_atraso || 0) > 0
                                                    ? `${etapa.dias_atraso} dias`
                                                    : '-'}
                                            </td>
                                            <td>{Number(etapa.percent_conclusao || 0).toFixed(2)}%</td>
                                            <td>
                                                {Number(etapa.tarefas_concluidas || 0)}/{Number(etapa.total_tarefas || 0)}
                                            </td>
                                            <td>
                                                <button
                                                    type="button"
                                                    className="btn btn-secondary"
                                                    style={{ padding: 'var(--spacing-1) var(--spacing-2)' }}
                                                    onClick={() => handleSaveEtapa(etapa)}
                                                    disabled={disabled}
                                                >
                                                    {saving ? 'Salvando...' : 'Salvar'}
                                                </button>
                                                <button
                                                    type="button"
                                                    className="btn btn-secondary"
                                                    style={{
                                                        padding: 'var(--spacing-1) var(--spacing-2)',
                                                        marginLeft: 'var(--spacing-2)',
                                                    }}
                                                    onClick={() => handleConcluirEtapa(etapa)}
                                                    disabled={disabled || concluded}
                                                >
                                                    {concluding ? 'Concluindo...' : concluded ? 'Concluída' : 'Concluir'}
                                                </button>
                                            </td>
                                        </tr>
                                    )
                                })}
                            </tbody>
                        </table>
                    )}
                </div>
            </div>
        </div>
    )
}
