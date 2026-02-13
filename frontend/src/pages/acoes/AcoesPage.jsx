import { useEffect, useMemo, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import {
    getAcoes,
    createAcao,
    updateAcao,
    deleteAcao,
    getPpas,
    getProgramas,
    getProjects,
} from '../../services/api'
import { useToast } from '../../contexts/ToastContext'
import Modal from '../../components/ui/Modal'
import Button from '../../components/ui/Button'
import Input from '../../components/ui/Input'

const EMPTY_FORM = {
    codigo: '',
    nome: '',
    programa_id: '',
    estado: 'Planejamento',
    objetivo: '',
    descricao: '',
    percent_execucao: '0',
}

const ESTADOS_ACAO = ['Planejamento', 'Em execucao', 'Concluido', 'Suspenso', 'Cancelado']

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

function getStatusLabel(status) {
    const map = {
        0: 'Nao definido',
        1: 'Proposto',
        2: 'Em planejamento',
        3: 'Em progresso',
        4: 'Em espera',
        5: 'Completo',
        6: 'Arquivado',
    }
    return map[Number(status)] || String(status ?? '-')
}

export default function AcoesPage() {
    const toast = useToast()
    const [searchParams] = useSearchParams()
    const [loading, setLoading] = useState(true)
    const [error, setError] = useState('')
    const [search, setSearch] = useState('')
    const [estadoFilter, setEstadoFilter] = useState('')
    const [ppaFilter, setPpaFilter] = useState('')
    const [programaFilter, setProgramaFilter] = useState(searchParams.get('programa_id') || '')
    const [acoes, setAcoes] = useState([])
    const [ppas, setPpas] = useState([])
    const [programas, setProgramas] = useState([])
    const [selectedAcao, setSelectedAcao] = useState(null)
    const [projects, setProjects] = useState([])
    const [loadingProjects, setLoadingProjects] = useState(false)
    const [formOpen, setFormOpen] = useState(false)
    const [saving, setSaving] = useState(false)
    const [editingAcao, setEditingAcao] = useState(null)
    const [formData, setFormData] = useState(EMPTY_FORM)

    const programasById = useMemo(() => {
        const map = new Map()
        for (const programa of programas) {
            map.set(Number(programa.id), programa)
        }
        return map
    }, [programas])

    const programasFiltradosPorPpa = useMemo(() => {
        if (!ppaFilter) {
            return programas
        }

        return programas.filter((programa) => Number(programa.ppa_id) === Number(ppaFilter))
    }, [programas, ppaFilter])

    const totalProjetos = useMemo(() => (
        acoes.reduce((acc, item) => acc + Number(item?.total_projetos || 0), 0)
    ), [acoes])

    useEffect(() => {
        loadLookups()
        loadAcoes()
    }, [])

    useEffect(() => {
        if (!selectedAcao?.id || !selectedAcao?.link_projetos_habilitado) {
            setProjects([])
            return
        }

        loadProjectsByAcao(selectedAcao.id)
    }, [selectedAcao?.id, selectedAcao?.link_projetos_habilitado])

    async function loadLookups() {
        try {
            const [ppasResponse, programasResponse] = await Promise.all([
                getPpas(),
                getProgramas(),
            ])

            setPpas(extractList(ppasResponse))
            setProgramas(extractList(programasResponse))
        } catch (_) {
            setPpas([])
            setProgramas([])
        }
    }

    async function loadAcoes(nextFilters = null) {
        const activeFilters = nextFilters || {
            search,
            estado: estadoFilter,
            ppa_id: ppaFilter,
            programa_id: programaFilter,
        }

        try {
            setLoading(true)
            setError('')
            const response = await getAcoes(activeFilters)
            const rows = extractList(response)
            setAcoes(rows)

            if (rows.length === 0) {
                setSelectedAcao(null)
                return
            }

            if (!selectedAcao) {
                setSelectedAcao(rows[0])
                return
            }

            const refreshed = rows.find((item) => Number(item.id) === Number(selectedAcao.id))
            setSelectedAcao(refreshed || rows[0])
        } catch (err) {
            setError(err?.message || 'Falha ao carregar acoes.')
        } finally {
            setLoading(false)
        }
    }

    async function loadProjectsByAcao(acaoId) {
        try {
            setLoadingProjects(true)
            const response = await getProjects({ acao_id: acaoId, per_page: 200 })
            setProjects(Array.isArray(response?.data) ? response.data : [])
        } catch (_) {
            setProjects([])
        } finally {
            setLoadingProjects(false)
        }
    }

    function openCreateModal() {
        setEditingAcao(null)
        setFormData({
            ...EMPTY_FORM,
            programa_id: programaFilter || '',
        })
        setFormOpen(true)
    }

    function openEditModal(acao) {
        setEditingAcao(acao)
        setFormData({
            codigo: acao.codigo || '',
            nome: acao.nome || '',
            programa_id: acao.programa_id ? String(acao.programa_id) : '',
            estado: acao.estado || 'Planejamento',
            objetivo: acao.objetivo || '',
            descricao: acao.descricao || '',
            percent_execucao: String(Number(acao.percent_execucao || 0)),
        })
        setFormOpen(true)
    }

    function closeModal() {
        setFormOpen(false)
        setEditingAcao(null)
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
            toast.error('Informe o nome da acao.')
            return false
        }

        if (!formData.programa_id) {
            toast.error('Selecione o programa da acao.')
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
            programa_id: Number(formData.programa_id),
            estado: formData.estado,
            objetivo: formData.objetivo.trim() || null,
            descricao: formData.descricao.trim() || null,
            percent_execucao: Number(formData.percent_execucao || 0),
        }

        try {
            setSaving(true)
            if (editingAcao?.id) {
                await updateAcao(editingAcao.id, payload)
                toast.success('Acao atualizada com sucesso.')
            } else {
                await createAcao(payload)
                toast.success('Acao criada com sucesso.')
            }

            closeModal()
            await loadAcoes()
        } catch (err) {
            toast.error(err?.message || 'Falha ao salvar acao.')
        } finally {
            setSaving(false)
        }
    }

    async function handleDelete(acao) {
        if (!acao?.id) {
            return
        }

        if (!window.confirm(`Deseja excluir a acao "${acao.nome}"?`)) {
            return
        }

        try {
            await deleteAcao(acao.id)
            toast.success('Acao removida com sucesso.')
            await loadAcoes()
        } catch (err) {
            toast.error(err?.message || 'Falha ao remover acao.')
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
                                placeholder="Nome ou codigo da acao"
                            />
                        </div>
                        <div style={{ minWidth: 180 }}>
                            <label style={{ display: 'block', marginBottom: 'var(--spacing-1)', fontWeight: 600 }}>PPA</label>
                            <select
                                value={ppaFilter}
                                onChange={(event) => {
                                    setPpaFilter(event.target.value)
                                    setProgramaFilter('')
                                }}
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
                        <div style={{ minWidth: 220 }}>
                            <label style={{ display: 'block', marginBottom: 'var(--spacing-1)', fontWeight: 600 }}>Programa</label>
                            <select
                                value={programaFilter}
                                onChange={(event) => setProgramaFilter(event.target.value)}
                                style={{
                                    width: '100%',
                                    height: 40,
                                    borderRadius: 'var(--radius-md)',
                                    border: '1px solid var(--color-gray-300)',
                                    padding: '0 var(--spacing-3)',
                                }}
                            >
                                <option value="">Todos</option>
                                {programasFiltradosPorPpa.map((programa) => (
                                    <option key={programa.id} value={String(programa.id)}>
                                        {programa.codigo ? `${programa.codigo} - ` : ''}{programa.nome}
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
                                {ESTADOS_ACAO.map((estado) => (
                                    <option key={estado} value={estado}>{estado}</option>
                                ))}
                            </select>
                        </div>
                        <Button variant="secondary" onClick={() => loadAcoes()}>
                            Filtrar
                        </Button>
                        <Button onClick={openCreateModal}>
                            Nova Acao
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
                        <h2 className="card-title">Gestao de Acoes</h2>
                        <span style={{ fontSize: '0.875rem', color: 'var(--color-gray-500)' }}>
                            {acoes.length} acoes | {totalProjetos} projetos vinculados
                        </span>
                    </div>
                    <div className="card-body" style={{ padding: 0 }}>
                        {loading ? (
                            <div style={{ padding: 'var(--spacing-6)' }}>Carregando acoes...</div>
                        ) : acoes.length === 0 ? (
                            <div style={{ padding: 'var(--spacing-6)', color: 'var(--color-gray-500)' }}>
                                Nenhuma acao encontrada.
                            </div>
                        ) : (
                            <table className="table">
                                <thead>
                                    <tr>
                                        <th>Codigo</th>
                                        <th>Acao</th>
                                        <th>Programa</th>
                                        <th>PPA</th>
                                        <th>Estado</th>
                                        <th>Execucao</th>
                                        <th>Projetos</th>
                                        <th>Acoes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {acoes.map((acao) => {
                                        const programa = programasById.get(Number(acao.programa_id))
                                        const ppaNome = acao.ppa_nome || ppas.find((item) => Number(item.id) === Number(acao.ppa_id))?.nome || '-'
                                        const isSelected = Number(selectedAcao?.id) === Number(acao.id)

                                        return (
                                            <tr
                                                key={acao.id}
                                                style={{
                                                    backgroundColor: isSelected ? 'var(--color-primary-50)' : 'transparent',
                                                    cursor: 'pointer',
                                                }}
                                                onClick={() => setSelectedAcao(acao)}
                                            >
                                                <td>{acao.codigo || '-'}</td>
                                                <td><strong>{acao.nome}</strong></td>
                                                <td>{acao.programa_nome || programa?.nome || '-'}</td>
                                                <td>{ppaNome}</td>
                                                <td>{acao.estado || '-'}</td>
                                                <td>{formatPercent(acao.percent_execucao)}</td>
                                                <td>{Number(acao.total_projetos || 0)}</td>
                                                <td>
                                                    <button
                                                        type="button"
                                                        className="btn btn-secondary"
                                                        style={{ padding: 'var(--spacing-1) var(--spacing-2)' }}
                                                        onClick={(event) => {
                                                            event.stopPropagation()
                                                            openEditModal(acao)
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
                                                            handleDelete(acao)
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
                            Projetos da Acao {selectedAcao ? `"${selectedAcao.nome}"` : ''}
                        </h2>
                        <span style={{ fontSize: '0.875rem', color: 'var(--color-gray-500)' }}>
                            {projects.length} projetos
                        </span>
                    </div>
                    <div className="card-body" style={{ padding: 0 }}>
                        {!selectedAcao ? (
                            <div style={{ padding: 'var(--spacing-6)', color: 'var(--color-gray-500)' }}>
                                Selecione uma acao para visualizar os projetos.
                            </div>
                        ) : !selectedAcao.link_projetos_habilitado ? (
                            <div style={{ padding: 'var(--spacing-6)', color: 'var(--color-gray-500)' }}>
                                Vinculo direto Acao - Projeto ainda nao habilitado neste banco (coluna project_acao_id ausente).
                            </div>
                        ) : loadingProjects ? (
                            <div style={{ padding: 'var(--spacing-6)' }}>Carregando projetos...</div>
                        ) : projects.length === 0 ? (
                            <div style={{ padding: 'var(--spacing-6)', color: 'var(--color-gray-500)' }}>
                                Nenhum projeto vinculado a esta acao.
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
                                            <td>{getStatusLabel(project.status)}</td>
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
                title={editingAcao ? 'Editar Acao' : 'Nova Acao'}
                size="lg"
                footer={(
                    <>
                        <Button variant="secondary" onClick={closeModal}>Cancelar</Button>
                        <Button onClick={handleSubmit} loading={saving}>
                            {editingAcao ? 'Salvar' : 'Criar acao'}
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
                                placeholder="ACA-2026-0001"
                            />
                            <Input
                                label="Nome da acao *"
                                value={formData.nome}
                                onChange={(event) => setField('nome', event.target.value)}
                                placeholder="Acao de urbanizacao de vias"
                            />
                        </div>

                        <div style={{ display: 'grid', gap: 'var(--spacing-4)', gridTemplateColumns: '1fr 180px' }}>
                            <div>
                                <label style={{ display: 'block', marginBottom: 'var(--spacing-1)', fontWeight: 600 }}>
                                    Programa vinculado *
                                </label>
                                <select
                                    value={formData.programa_id}
                                    onChange={(event) => setField('programa_id', event.target.value)}
                                    style={{
                                        width: '100%',
                                        height: 40,
                                        borderRadius: 'var(--radius-md)',
                                        border: '1px solid var(--color-gray-300)',
                                        padding: '0 var(--spacing-3)',
                                    }}
                                >
                                    <option value="">Selecione</option>
                                    {programas.map((programa) => (
                                        <option key={programa.id} value={String(programa.id)}>
                                            {programa.codigo ? `${programa.codigo} - ` : ''}{programa.nome}
                                        </option>
                                    ))}
                                </select>
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
                                    {ESTADOS_ACAO.map((estado) => (
                                        <option key={estado} value={estado}>{estado}</option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <Input
                            label="Execucao (%)"
                            type="number"
                            min="0"
                            max="100"
                            value={formData.percent_execucao}
                            onChange={(event) => setField('percent_execucao', event.target.value)}
                        />

                        <div>
                            <label style={{ display: 'block', marginBottom: 'var(--spacing-1)', fontWeight: 600 }}>
                                Objetivo
                            </label>
                            <textarea
                                value={formData.objetivo}
                                onChange={(event) => setField('objetivo', event.target.value)}
                                rows={3}
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

                        <div>
                            <label style={{ display: 'block', marginBottom: 'var(--spacing-1)', fontWeight: 600 }}>
                                Descricao
                            </label>
                            <textarea
                                value={formData.descricao}
                                onChange={(event) => setField('descricao', event.target.value)}
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
