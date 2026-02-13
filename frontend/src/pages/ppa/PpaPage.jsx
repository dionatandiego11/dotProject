import { useEffect, useMemo, useState } from 'react'
import {
    getPpas,
    createPpa,
    updatePpa,
    deletePpa,
    getProgramas,
} from '../../services/api'
import { useToast } from '../../contexts/ToastContext'
import Modal from '../../components/ui/Modal'
import Button from '../../components/ui/Button'
import Input from '../../components/ui/Input'

const EMPTY_FORM = {
    nome: '',
    periodo_inicio: '',
    periodo_fim: '',
    estado: 'Rascunho',
    objetivo_geral: '',
}

const ESTADOS_PPA = ['Rascunho', 'Aprovado', 'Publicado', 'Encerrado']

const SAUDE_CONFIG = {
    em_dia: { label: 'Em dia', color: '#16a34a', bg: '#f0fdf4' },
    atencao: { label: 'Atenção', color: '#d97706', bg: '#fffbeb' },
    critico: { label: 'Crítico', color: '#dc2626', bg: '#fef2f2' },
    impedido: { label: 'Impedido', color: '#6b7280', bg: '#f3f4f6' },
}

function SaudeBadge({ status }) {
    const cfg = SAUDE_CONFIG[status] || SAUDE_CONFIG.em_dia
    return (
        <span style={{
            display: 'inline-block',
            padding: '2px 10px',
            borderRadius: 12,
            fontSize: 12,
            fontWeight: 600,
            color: cfg.color,
            background: cfg.bg,
            border: `1px solid ${cfg.color}22`,
            whiteSpace: 'nowrap',
        }}>
            {cfg.label}
        </span>
    )
}

function formatPeriodo(ppa) {
    if (!ppa?.periodo_inicio && !ppa?.periodo_fim) {
        return '-'
    }

    return `${ppa.periodo_inicio || '-'} - ${ppa.periodo_fim || '-'}`
}

function extractList(payload) {
    if (Array.isArray(payload?.data)) {
        return payload.data
    }

    return []
}

export default function PpaPage() {
    const toast = useToast()
    const [loading, setLoading] = useState(true)
    const [error, setError] = useState('')
    const [search, setSearch] = useState('')
    const [estadoFilter, setEstadoFilter] = useState('')
    const [ppas, setPpas] = useState([])
    const [selectedPpa, setSelectedPpa] = useState(null)
    const [programasDoPpa, setProgramasDoPpa] = useState([])
    const [loadingProgramas, setLoadingProgramas] = useState(false)
    const [formOpen, setFormOpen] = useState(false)
    const [saving, setSaving] = useState(false)
    const [editingPpa, setEditingPpa] = useState(null)
    const [formData, setFormData] = useState(EMPTY_FORM)

    const totalProgramas = useMemo(() => (
        ppas.reduce((acc, item) => acc + Number(item?.total_programas || 0), 0)
    ), [ppas])

    useEffect(() => {
        loadPpas()
    }, [])

    useEffect(() => {
        if (!selectedPpa?.id) {
            setProgramasDoPpa([])
            return
        }

        loadProgramasDoPpa(selectedPpa.id)
    }, [selectedPpa?.id])

    async function loadPpas(nextFilters = null) {
        const activeFilters = nextFilters || {
            search,
            estado: estadoFilter,
        }

        try {
            setLoading(true)
            setError('')
            const response = await getPpas(activeFilters)
            const rows = extractList(response)
            setPpas(rows)

            if (rows.length === 0) {
                setSelectedPpa(null)
                return
            }

            if (!selectedPpa) {
                setSelectedPpa(rows[0])
                return
            }

            const refreshedSelected = rows.find((item) => Number(item.id) === Number(selectedPpa.id))
            setSelectedPpa(refreshedSelected || rows[0])
        } catch (err) {
            const message = err?.message || 'Falha ao carregar PPAs.'
            setError(message)
        } finally {
            setLoading(false)
        }
    }

    async function loadProgramasDoPpa(ppaId) {
        try {
            setLoadingProgramas(true)
            const response = await getProgramas({ ppa_id: ppaId })
            setProgramasDoPpa(extractList(response))
        } catch (_) {
            setProgramasDoPpa([])
        } finally {
            setLoadingProgramas(false)
        }
    }

    function openCreateModal() {
        setEditingPpa(null)
        setFormData(EMPTY_FORM)
        setFormOpen(true)
    }

    function openEditModal(ppa) {
        setEditingPpa(ppa)
        setFormData({
            nome: ppa.nome || '',
            periodo_inicio: ppa.periodo_inicio ? String(ppa.periodo_inicio) : '',
            periodo_fim: ppa.periodo_fim ? String(ppa.periodo_fim) : '',
            estado: ppa.estado || 'Rascunho',
            objetivo_geral: ppa.objetivo_geral || '',
        })
        setFormOpen(true)
    }

    function closeModal() {
        setFormOpen(false)
        setEditingPpa(null)
        setFormData(EMPTY_FORM)
    }

    function setField(field, value) {
        setFormData((prev) => ({
            ...prev,
            [field]: value,
        }))
    }

    function validateForm() {
        if (!formData.nome.trim()) {
            toast.error('Informe o nome do PPA.')
            return false
        }

        if (!formData.periodo_inicio || !formData.periodo_fim) {
            toast.error('Informe o periodo inicial e final.')
            return false
        }

        if (Number(formData.periodo_fim) < Number(formData.periodo_inicio)) {
            toast.error('O periodo final nao pode ser menor que o inicial.')
            return false
        }

        return true
    }

    async function handleSubmit(event) {
        event.preventDefault()
        if (!validateForm()) {
            return
        }

        const payload = {
            nome: formData.nome.trim(),
            periodo_inicio: Number(formData.periodo_inicio),
            periodo_fim: Number(formData.periodo_fim),
            estado: formData.estado,
            objetivo_geral: formData.objetivo_geral.trim() || null,
        }

        try {
            setSaving(true)
            if (editingPpa?.id) {
                await updatePpa(editingPpa.id, payload)
                toast.success('PPA atualizado com sucesso.')
            } else {
                await createPpa(payload)
                toast.success('PPA criado com sucesso.')
            }

            closeModal()
            await loadPpas()
        } catch (err) {
            toast.error(err?.message || 'Falha ao salvar o PPA.')
        } finally {
            setSaving(false)
        }
    }

    async function handleDelete(ppa) {
        if (!ppa?.id) {
            return
        }

        if (!window.confirm(`Deseja excluir o PPA "${ppa.nome}"?`)) {
            return
        }

        try {
            await deletePpa(ppa.id)
            toast.success('PPA removido com sucesso.')
            await loadPpas()
        } catch (err) {
            toast.error(err?.message || 'Falha ao remover o PPA.')
        }
    }

    return (
        <>
            <div className="page-content">
                <div className="card" style={{ marginBottom: 'var(--spacing-4)' }}>
                    <div className="card-body" style={{ display: 'flex', gap: 'var(--spacing-3)', flexWrap: 'wrap', alignItems: 'end' }}>
                        <div style={{ minWidth: 220, flex: 1 }}>
                            <label style={{ display: 'block', marginBottom: 'var(--spacing-1)', fontWeight: 600 }}>Busca</label>
                            <Input
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Nome do PPA"
                            />
                        </div>
                        <div style={{ minWidth: 180 }}>
                            <label style={{ display: 'block', marginBottom: 'var(--spacing-1)', fontWeight: 600 }}>Estado</label>
                            <select
                                value={estadoFilter}
                                onChange={(event) => setEstadoFilter(event.target.value)}
                                style={{
                                    width: '100%',
                                    height: 40,
                                    borderRadius: 'var(--radius-md)',
                                    border: '1px solid var(--color-gray-300)',
                                    padding: '0 var(--spacing-3)',
                                }}
                            >
                                <option value="">Todos</option>
                                {ESTADOS_PPA.map((estado) => (
                                    <option key={estado} value={estado}>{estado}</option>
                                ))}
                            </select>
                        </div>
                        <Button variant="secondary" onClick={() => loadPpas()}>
                            Filtrar
                        </Button>
                        <Button onClick={openCreateModal}>
                            Novo PPA
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
                    <div className="card-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                        <h2 className="card-title">Gestão do PPA</h2>
                        <span style={{ fontSize: '0.875rem', color: 'var(--color-gray-500)' }}>
                            {ppas.length} planos | {totalProgramas} programas
                        </span>
                    </div>
                    <div className="card-body" style={{ padding: 0 }}>
                        {loading ? (
                            <div style={{ padding: 'var(--spacing-6)' }}>Carregando PPAs...</div>
                        ) : ppas.length === 0 ? (
                            <div style={{ padding: 'var(--spacing-6)', color: 'var(--color-gray-500)' }}>
                                Nenhum PPA encontrado.
                            </div>
                        ) : (
                            <table className="table">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Periodo</th>
                                        <th>Estado</th>
                                        <th>Saúde</th>
                                        <th>Programas</th>
                                        <th>Execução</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {ppas.map((ppa) => (
                                        <tr
                                            key={ppa.id}
                                            style={{
                                                backgroundColor: Number(selectedPpa?.id) === Number(ppa.id) ? 'var(--color-primary-50)' : 'transparent',
                                                cursor: 'pointer',
                                            }}
                                            onClick={() => setSelectedPpa(ppa)}
                                        >
                                            <td>
                                                <strong>{ppa.nome}</strong>
                                            </td>
                                            <td>{formatPeriodo(ppa)}</td>
                                            <td>{ppa.estado || '-'}</td>
                                            <td><SaudeBadge status={ppa.status_saude || 'em_dia'} /></td>
                                            <td>{Number(ppa.total_programas || 0)}</td>
                                            <td>{Number(ppa.percent_execucao || 0).toFixed(2)}%</td>
                                            <td>
                                                <button
                                                    type="button"
                                                    className="btn btn-secondary"
                                                    style={{ padding: 'var(--spacing-1) var(--spacing-2)' }}
                                                    onClick={(event) => {
                                                        event.stopPropagation()
                                                        openEditModal(ppa)
                                                    }}
                                                >
                                                    Editar
                                                </button>
                                                <button
                                                    type="button"
                                                    className="btn btn-secondary"
                                                    style={{
                                                        padding: 'var(--spacing-1) var(--spacing-2)',
                                                        marginLeft: 'var(--spacing-2)',
                                                        color: 'var(--color-danger-600)',
                                                    }}
                                                    onClick={(event) => {
                                                        event.stopPropagation()
                                                        handleDelete(ppa)
                                                    }}
                                                >
                                                    Excluir
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                </div>

                <div className="card">
                    <div className="card-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                        <h2 className="card-title">
                            Programas do PPA {selectedPpa ? `"${selectedPpa.nome}"` : ''}
                        </h2>
                        <span style={{ fontSize: '0.875rem', color: 'var(--color-gray-500)' }}>
                            {programasDoPpa.length} programas
                        </span>
                    </div>
                    <div className="card-body" style={{ padding: 0 }}>
                        {loadingProgramas ? (
                            <div style={{ padding: 'var(--spacing-6)' }}>Carregando programas...</div>
                        ) : programasDoPpa.length === 0 ? (
                            <div style={{ padding: 'var(--spacing-6)', color: 'var(--color-gray-500)' }}>
                                Nenhum programa vinculado a este PPA.
                            </div>
                        ) : (
                            <table className="table">
                                <thead>
                                    <tr>
                                        <th>Codigo</th>
                                        <th>Nome</th>
                                        <th>Secretaria</th>
                                        <th>Estado</th>
                                        <th>Saúde</th>
                                        <th>Execução</th>
                                        <th>Projetos</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {programasDoPpa.map((programa) => (
                                        <tr key={programa.id}>
                                            <td>{programa.codigo || '-'}</td>
                                            <td>{programa.nome}</td>
                                            <td>{programa.unidade_nome || '-'}</td>
                                            <td>{programa.estado || '-'}</td>
                                            <td><SaudeBadge status={programa.status_saude || 'em_dia'} /></td>
                                            <td>{Number(programa.percent_execucao || 0).toFixed(2)}%</td>
                                            <td>{Number(programa.total_projetos || 0)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                </div>
            </div>

            <Modal
                isOpen={formOpen}
                onClose={closeModal}
                title={editingPpa ? 'Editar PPA' : 'Novo PPA'}
                footer={(
                    <>
                        <Button variant="secondary" onClick={closeModal}>Cancelar</Button>
                        <Button onClick={handleSubmit} loading={saving}>
                            {editingPpa ? 'Salvar' : 'Criar PPA'}
                        </Button>
                    </>
                )}
            >
                <form onSubmit={handleSubmit}>
                    <div style={{ display: 'grid', gap: 'var(--spacing-4)' }}>
                        <Input
                            label="Nome do PPA *"
                            value={formData.nome}
                            onChange={(event) => setField('nome', event.target.value)}
                            placeholder="Ex: PPA 2025-2028"
                        />
                        <div style={{ display: 'grid', gap: 'var(--spacing-4)', gridTemplateColumns: '1fr 1fr' }}>
                            <Input
                                type="number"
                                label="Periodo inicio *"
                                value={formData.periodo_inicio}
                                onChange={(event) => setField('periodo_inicio', event.target.value)}
                                min="2000"
                                max="2100"
                            />
                            <Input
                                type="number"
                                label="Periodo fim *"
                                value={formData.periodo_fim}
                                onChange={(event) => setField('periodo_fim', event.target.value)}
                                min="2000"
                                max="2100"
                            />
                        </div>
                        <div>
                            <label style={{ display: 'block', marginBottom: 'var(--spacing-1)', fontWeight: 600 }}>Estado</label>
                            <select
                                value={formData.estado}
                                onChange={(event) => setField('estado', event.target.value)}
                                style={{
                                    width: '100%',
                                    height: 40,
                                    borderRadius: 'var(--radius-md)',
                                    border: '1px solid var(--color-gray-300)',
                                    padding: '0 var(--spacing-3)',
                                }}
                            >
                                {ESTADOS_PPA.map((estado) => (
                                    <option key={estado} value={estado}>{estado}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label style={{ display: 'block', marginBottom: 'var(--spacing-1)', fontWeight: 600 }}>
                                Objetivo geral
                            </label>
                            <textarea
                                value={formData.objetivo_geral}
                                onChange={(event) => setField('objetivo_geral', event.target.value)}
                                rows={4}
                                style={{
                                    width: '100%',
                                    borderRadius: 'var(--radius-md)',
                                    border: '1px solid var(--color-gray-300)',
                                    padding: 'var(--spacing-2) var(--spacing-3)',
                                    fontFamily: 'inherit',
                                    fontSize: '0.875rem',
                                }}
                            />
                        </div>
                    </div>
                </form>
            </Modal>
        </>
    )
}
