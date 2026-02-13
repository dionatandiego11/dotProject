import { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import {
    getProgramas,
    createPrograma,
    updatePrograma,
    deletePrograma,
    getPpas,
    getUnidades,
    getProjects,
} from '../../services/api'
import { useToast } from '../../contexts/ToastContext'
import Modal from '../../components/ui/Modal'
import Button from '../../components/ui/Button'
import Input from '../../components/ui/Input'
import { buildGroupedUnidades } from '../projects/projectStatusUtils'

const EMPTY_FORM = {
    codigo: '',
    nome: '',
    ppa_id: '',
    unidade_id: '',
    objetivo: '',
    estado: 'Planejamento',
    percent_execucao: '0',
}

const ESTADOS_PROGRAMA = ['Planejamento', 'Em execucao', 'Concluido', 'Suspenso', 'Cancelado']

function extractList(payload) {
    if (Array.isArray(payload?.data)) {
        return payload.data
    }

    return []
}

function formatPercent(value) {
    return `${Number(value || 0).toFixed(2)}%`
}

function getProjectUnit(project) {
    return project?.unidade?.nome || project?.company?.name || '-'
}

export default function ProgramasPage() {
    const navigate = useNavigate()
    const toast = useToast()
    const [loading, setLoading] = useState(true)
    const [error, setError] = useState('')
    const [search, setSearch] = useState('')
    const [estadoFilter, setEstadoFilter] = useState('')
    const [ppaFilter, setPpaFilter] = useState('')
    const [programas, setProgramas] = useState([])
    const [ppas, setPpas] = useState([])
    const [unidades, setUnidades] = useState([])
    const [selectedPrograma, setSelectedPrograma] = useState(null)
    const [projects, setProjects] = useState([])
    const [loadingProjects, setLoadingProjects] = useState(false)
    const [formOpen, setFormOpen] = useState(false)
    const [saving, setSaving] = useState(false)
    const [editingPrograma, setEditingPrograma] = useState(null)
    const [formData, setFormData] = useState(EMPTY_FORM)

    const groupedUnidades = useMemo(() => buildGroupedUnidades(unidades), [unidades])
    const totalProjetos = useMemo(() => (
        programas.reduce((acc, item) => acc + Number(item?.total_projetos || 0), 0)
    ), [programas])
    const totalAcoes = useMemo(() => (
        programas.reduce((acc, item) => acc + Number(item?.total_acoes || 0), 0)
    ), [programas])

    useEffect(() => {
        loadLookups()
        loadProgramas()
    }, [])

    useEffect(() => {
        if (!selectedPrograma?.id) {
            setProjects([])
            return
        }

        loadProjectsByPrograma(selectedPrograma.id)
    }, [selectedPrograma?.id])

    async function loadLookups() {
        try {
            const [ppasResponse, unidadesResponse] = await Promise.all([
                getPpas(),
                getUnidades({ escopo: 1 }),
            ])

            setPpas(extractList(ppasResponse))
            setUnidades(extractList(unidadesResponse))
        } catch (_) {
            setPpas([])
            setUnidades([])
        }
    }

    async function loadProgramas(nextFilters = null) {
        const activeFilters = nextFilters || {
            search,
            estado: estadoFilter,
            ppa_id: ppaFilter,
        }

        try {
            setLoading(true)
            setError('')
            const response = await getProgramas(activeFilters)
            const rows = extractList(response)
            setProgramas(rows)

            if (rows.length === 0) {
                setSelectedPrograma(null)
                return
            }

            if (!selectedPrograma) {
                setSelectedPrograma(rows[0])
                return
            }

            const refreshed = rows.find((item) => Number(item.id) === Number(selectedPrograma.id))
            setSelectedPrograma(refreshed || rows[0])
        } catch (err) {
            setError(err?.message || 'Falha ao carregar programas.')
        } finally {
            setLoading(false)
        }
    }

    async function loadProjectsByPrograma(programaId) {
        try {
            setLoadingProjects(true)
            const response = await getProjects({ programa_id: programaId, per_page: 200 })
            setProjects(Array.isArray(response?.data) ? response.data : [])
        } catch (_) {
            setProjects([])
        } finally {
            setLoadingProjects(false)
        }
    }

    function openCreateModal() {
        setEditingPrograma(null)
        setFormData(EMPTY_FORM)
        setFormOpen(true)
    }

    function openEditModal(programa) {
        setEditingPrograma(programa)
        setFormData({
            codigo: programa.codigo || '',
            nome: programa.nome || '',
            ppa_id: programa.ppa_id ? String(programa.ppa_id) : '',
            unidade_id: programa.unidade_id ? String(programa.unidade_id) : '',
            objetivo: programa.objetivo || '',
            estado: programa.estado || 'Planejamento',
            percent_execucao: String(Number(programa.percent_execucao || 0)),
        })
        setFormOpen(true)
    }

    function closeModal() {
        setFormOpen(false)
        setEditingPrograma(null)
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
            toast.error('Informe o nome do programa.')
            return false
        }

        if (!formData.unidade_id) {
            toast.error('Selecione a secretaria responsavel.')
            return false
        }

        const percent = Number(formData.percent_execucao || 0)
        if (Number.isNaN(percent) || percent < 0 || percent > 100) {
            toast.error('Percentual de execucao deve ficar entre 0 e 100.')
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
            codigo: formData.codigo.trim() || null,
            nome: formData.nome.trim(),
            ppa_id: formData.ppa_id ? Number(formData.ppa_id) : null,
            unidade_id: Number(formData.unidade_id),
            objetivo: formData.objetivo.trim() || null,
            estado: formData.estado,
            percent_execucao: Number(formData.percent_execucao || 0),
        }

        try {
            setSaving(true)
            if (editingPrograma?.id) {
                await updatePrograma(editingPrograma.id, payload)
                toast.success('Programa atualizado com sucesso.')
            } else {
                await createPrograma(payload)
                toast.success('Programa criado com sucesso.')
            }

            closeModal()
            await loadProgramas()
        } catch (err) {
            toast.error(err?.message || 'Falha ao salvar programa.')
        } finally {
            setSaving(false)
        }
    }

    async function handleDelete(programa) {
        if (!programa?.id) {
            return
        }

        if (!window.confirm(`Deseja excluir o programa "${programa.nome}"?`)) {
            return
        }

        try {
            await deletePrograma(programa.id)
            toast.success('Programa removido com sucesso.')
            await loadProgramas()
        } catch (err) {
            toast.error(err?.message || 'Falha ao remover programa.')
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
                                placeholder="Nome ou codigo"
                            />
                        </div>
                        <div style={{ minWidth: 180 }}>
                            <label style={{ display: 'block', marginBottom: 'var(--spacing-1)', fontWeight: 600 }}>PPA</label>
                            <select
                                value={ppaFilter}
                                onChange={(event) => setPpaFilter(event.target.value)}
                                style={{
                                    width: '100%',
                                    height: 40,
                                    borderRadius: 'var(--radius-md)',
                                    border: '1px solid var(--color-gray-300)',
                                    padding: '0 var(--spacing-3)',
                                }}
                            >
                                <option value="">Todos</option>
                                {ppas.map((ppa) => (
                                    <option key={ppa.id} value={String(ppa.id)}>
                                        {ppa.nome}
                                    </option>
                                ))}
                            </select>
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
                                {ESTADOS_PROGRAMA.map((estado) => (
                                    <option key={estado} value={estado}>{estado}</option>
                                ))}
                            </select>
                        </div>
                        <Button variant="secondary" onClick={() => loadProgramas()}>
                            Filtrar
                        </Button>
                        <Button onClick={openCreateModal}>
                            Novo Programa
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
                        <h2 className="card-title">Gestao de Programas</h2>
                        <span style={{ fontSize: '0.875rem', color: 'var(--color-gray-500)' }}>
                            {programas.length} programas | {totalAcoes} acoes | {totalProjetos} projetos vinculados
                        </span>
                    </div>
                    <div className="card-body" style={{ padding: 0 }}>
                        {loading ? (
                            <div style={{ padding: 'var(--spacing-6)' }}>Carregando programas...</div>
                        ) : programas.length === 0 ? (
                            <div style={{ padding: 'var(--spacing-6)', color: 'var(--color-gray-500)' }}>
                                Nenhum programa encontrado.
                            </div>
                        ) : (
                            <table className="table">
                                <thead>
                                    <tr>
                                        <th>Codigo</th>
                                        <th>Programa</th>
                                        <th>PPA</th>
                                        <th>Secretaria</th>
                                        <th>Estado</th>
                                        <th>Execucao</th>
                                        <th>Acoes</th>
                                        <th>Projetos</th>
                                        <th>Gestao</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {programas.map((programa) => {
                                        const ppa = ppas.find((item) => Number(item.id) === Number(programa.ppa_id))
                                        const isSelected = Number(selectedPrograma?.id) === Number(programa.id)

                                        return (
                                            <tr
                                                key={programa.id}
                                                style={{
                                                    backgroundColor: isSelected ? 'var(--color-primary-50)' : 'transparent',
                                                    cursor: 'pointer',
                                                }}
                                                onClick={() => setSelectedPrograma(programa)}
                                            >
                                                <td>{programa.codigo || '-'}</td>
                                                <td><strong>{programa.nome}</strong></td>
                                                <td>{ppa?.nome || '-'}</td>
                                                <td>{programa.unidade_nome || '-'}</td>
                                                <td>{programa.estado || '-'}</td>
                                                <td>{formatPercent(programa.percent_execucao)}</td>
                                                <td>{Number(programa.total_acoes || 0)}</td>
                                                <td>{Number(programa.total_projetos || 0)}</td>
                                                <td>
                                                    <button
                                                        type="button"
                                                        className="btn btn-secondary"
                                                        style={{ padding: 'var(--spacing-1) var(--spacing-2)' }}
                                                        onClick={(event) => {
                                                            event.stopPropagation()
                                                            navigate(`/acoes?programa_id=${programa.id}`)
                                                        }}
                                                    >
                                                        Ver Acoes
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="btn btn-secondary"
                                                        style={{ padding: 'var(--spacing-1) var(--spacing-2)', marginLeft: 'var(--spacing-2)' }}
                                                        onClick={(event) => {
                                                            event.stopPropagation()
                                                            openEditModal(programa)
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
                                                            handleDelete(programa)
                                                        }}
                                                    >
                                                        Excluir
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

                <div className="card">
                    <div className="card-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                        <h2 className="card-title">
                            Projetos do Programa {selectedPrograma ? `"${selectedPrograma.nome}"` : ''}
                        </h2>
                        <span style={{ fontSize: '0.875rem', color: 'var(--color-gray-500)' }}>
                            {projects.length} projetos
                        </span>
                    </div>
                    <div className="card-body" style={{ padding: 0 }}>
                        {loadingProjects ? (
                            <div style={{ padding: 'var(--spacing-6)' }}>Carregando projetos...</div>
                        ) : projects.length === 0 ? (
                            <div style={{ padding: 'var(--spacing-6)', color: 'var(--color-gray-500)' }}>
                                Nenhum projeto vinculado a este programa.
                            </div>
                        ) : (
                            <table className="table">
                                <thead>
                                    <tr>
                                        <th>Projeto</th>
                                        <th>Unidade</th>
                                        <th>Status</th>
                                        <th>Progresso</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {projects.map((project) => (
                                        <tr key={project.id}>
                                            <td>{project.name}</td>
                                            <td>{getProjectUnit(project)}</td>
                                            <td>{project.status}</td>
                                            <td>{Number(project.percent_complete || 0)}%</td>
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
                title={editingPrograma ? 'Editar Programa' : 'Novo Programa'}
                size="lg"
                footer={(
                    <>
                        <Button variant="secondary" onClick={closeModal}>Cancelar</Button>
                        <Button onClick={handleSubmit} loading={saving}>
                            {editingPrograma ? 'Salvar' : 'Criar programa'}
                        </Button>
                    </>
                )}
            >
                <form onSubmit={handleSubmit}>
                    <div style={{ display: 'grid', gap: 'var(--spacing-4)' }}>
                        <div style={{ display: 'grid', gap: 'var(--spacing-4)', gridTemplateColumns: '180px 1fr' }}>
                            <Input
                                label="Codigo"
                                value={formData.codigo}
                                onChange={(event) => setField('codigo', event.target.value)}
                                placeholder="PRG-2026-0001"
                            />
                            <Input
                                label="Nome do programa *"
                                value={formData.nome}
                                onChange={(event) => setField('nome', event.target.value)}
                                placeholder="Programa de infraestrutura urbana"
                            />
                        </div>

                        <div style={{ display: 'grid', gap: 'var(--spacing-4)', gridTemplateColumns: '1fr 1fr' }}>
                            <div>
                                <label style={{ display: 'block', marginBottom: 'var(--spacing-1)', fontWeight: 600 }}>PPA</label>
                                <select
                                    value={formData.ppa_id}
                                    onChange={(event) => setField('ppa_id', event.target.value)}
                                    style={{
                                        width: '100%',
                                        height: 40,
                                        borderRadius: 'var(--radius-md)',
                                        border: '1px solid var(--color-gray-300)',
                                        padding: '0 var(--spacing-3)',
                                    }}
                                >
                                    <option value="">Sem vinculo</option>
                                    {ppas.map((ppa) => (
                                        <option key={ppa.id} value={String(ppa.id)}>
                                            {ppa.nome}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label style={{ display: 'block', marginBottom: 'var(--spacing-1)', fontWeight: 600 }}>
                                    Secretaria responsavel *
                                </label>
                                <select
                                    value={formData.unidade_id}
                                    onChange={(event) => setField('unidade_id', event.target.value)}
                                    style={{
                                        width: '100%',
                                        height: 40,
                                        borderRadius: 'var(--radius-md)',
                                        border: '1px solid var(--color-gray-300)',
                                        padding: '0 var(--spacing-3)',
                                    }}
                                >
                                    <option value="">Selecione</option>
                                    {groupedUnidades.map((grupo) => (
                                        <optgroup key={grupo.label} label={grupo.label}>
                                            {grupo.options.map((option) => (
                                                <option key={option.id} value={String(option.id)}>
                                                    {option.label}
                                                </option>
                                            ))}
                                        </optgroup>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div style={{ display: 'grid', gap: 'var(--spacing-4)', gridTemplateColumns: '1fr 180px' }}>
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
                                    {ESTADOS_PROGRAMA.map((estado) => (
                                        <option key={estado} value={estado}>{estado}</option>
                                    ))}
                                </select>
                            </div>
                            <Input
                                label="Execucao (%)"
                                type="number"
                                min="0"
                                max="100"
                                value={formData.percent_execucao}
                                onChange={(event) => setField('percent_execucao', event.target.value)}
                            />
                        </div>

                        <div>
                            <label style={{ display: 'block', marginBottom: 'var(--spacing-1)', fontWeight: 600 }}>
                                Objetivo
                            </label>
                            <textarea
                                value={formData.objetivo}
                                onChange={(event) => setField('objetivo', event.target.value)}
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
