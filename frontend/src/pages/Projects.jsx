/**
 * Projects page — decomposed version.
 *
 * Utilities → projects/projectStatusUtils.js
 * Table     → projects/ProjectTable.jsx
 * Form      → projects/ProjectForm.jsx
 */

import { useState, useEffect, useMemo } from 'react'
import { getProjects, createProject, updateProject, updateProjectStatus, deleteProject, getUnidades } from '../services/api'
import Modal from '../components/ui/Modal'
import Button from '../components/ui/Button'
import { useToast } from '../contexts/ToastContext'
import { useValidation } from '../hooks/useValidation'

import {
    getStatusLabelById,
    getAllowedProjectStatuses,
    canTransitionProjectStatus,
    getNumericProjectStatus,
    normalizeDateValue,
    buildGroupedUnidades,
    CREATE_ALLOWED_PROJECT_STATUSES
} from './projects/projectStatusUtils'

import ProjectTable from './projects/ProjectTable'
import ProjectForm from './projects/ProjectForm'

const EMPTY_PROJECT = { name: '', short_name: '', description: '', start_date: '', end_date: '', company_id: '', status: '1' }

function Projects() {
    const toast = useToast()
    const validation = useValidation()
    const [projects, setProjects] = useState([])
    const [meta, setMeta] = useState({})
    const [loading, setLoading] = useState(true)
    const [error, setError] = useState(null)
    const [search, setSearch] = useState('')
    const [unidades, setUnidades] = useState([])
    const [unidadesLoading, setUnidadesLoading] = useState(false)
    const groupedUnidades = useMemo(() => buildGroupedUnidades(unidades), [unidades])

    // Modal state
    const [isModalOpen, setIsModalOpen] = useState(false)
    const [isEditModalOpen, setIsEditModalOpen] = useState(false)
    const [editingProject, setEditingProject] = useState(null)
    const [formData, setFormData] = useState({ ...EMPTY_PROJECT })
    const [creating, setCreating] = useState(false)
    const [quickStatusSelectionByProjectId, setQuickStatusSelectionByProjectId] = useState({})
    const [quickStatusLoadingByProjectId, setQuickStatusLoadingByProjectId] = useState({})

    const createStatusOptions = useMemo(
        () => getAllowedProjectStatuses(null, { forCreate: true }),
        []
    )
    const editCurrentStatus = useMemo(() => {
        if (!editingProject) return null
        const numeric = Number(editingProject.status)
        return Number.isNaN(numeric) ? 0 : numeric
    }, [editingProject])
    const editStatusOptions = useMemo(
        () => getAllowedProjectStatuses(editCurrentStatus),
        [editCurrentStatus]
    )
    const editStatusHint = useMemo(() => {
        if (editCurrentStatus === null) return ''
        const transitions = getAllowedProjectStatuses(editCurrentStatus, { includeCurrent: false })
        if (transitions.length === 0) return 'Este status nao permite novas transicoes.'
        return `Transicoes permitidas: ${transitions.map(getStatusLabelById).join(', ')}.`
    }, [editCurrentStatus])

    useEffect(() => { loadProjects() }, [])

    // ── Data loading ──────────────────────────────────────────────

    async function loadProjects(params = {}) {
        try {
            setLoading(true)
            const data = await getProjects(params)
            setProjects(data.data || [])
            setMeta(data.meta || {})
        } catch (err) {
            setError(err.message)
        } finally {
            setLoading(false)
        }
    }

    async function loadUnidades() {
        try {
            setUnidadesLoading(true)
            const data = await getUnidades({ escopo: 1 })
            setUnidades(data.data || [])
        } catch (err) {
            console.error('Erro ao carregar unidades:', err)
        } finally {
            setUnidadesLoading(false)
        }
    }

    // ── Form helpers ──────────────────────────────────────────────

    function handleFormChange(field, value) {
        setFormData(prev => ({ ...prev, [field]: value }))
    }

    function getProjectUnitId(project) {
        const value = project?.unidade_id ?? project?.unidade?.id ?? project?.company_id ?? project?.company?.id ?? null
        return value !== null && value !== undefined ? String(value) : ''
    }

    function confirmProjectStatusTransition(currentStatus, nextStatus) {
        if (currentStatus === nextStatus) return true
        if (nextStatus === 5) return confirm('Marcar este projeto como Completo? O progresso sera ajustado para 100%.')
        if (nextStatus === 6) return confirm('Arquivar este projeto? Ele sera removido dos fluxos operacionais ativos.')
        return true
    }

    // ── CRUD handlers ─────────────────────────────────────────────

    async function handleCreateProject(e) {
        e.preventDefault()
        validation.clearErrors()

        const isValid = validation.validateFields({
            name: () => validation.validateRequired(formData.name, 'Nome do projeto'),
            short_name: () => validation.validateMaxLength(formData.short_name, 10, 'Nome curto'),
            description: () => validation.validateMaxLength(formData.description, 1000, 'Descrição'),
            dates: () => validation.validateDateRange(formData.start_date, formData.end_date),
            company_id: () => validation.validateRequired(formData.company_id, 'Unidade responsavel')
        })
        if (!isValid) return

        try {
            setCreating(true)
            const unidadeId = formData.company_id ? parseInt(formData.company_id, 10) : null
            const statusValue = formData.status ? parseInt(formData.status, 10) : 0
            if (!CREATE_ALLOWED_PROJECT_STATUSES.includes(statusValue)) {
                toast.error('Status inicial invalido para criacao de projeto.')
                return
            }
            await createProject({
                name: formData.name,
                short_name: formData.short_name.trim(),
                description: formData.description || null,
                start_date: formData.start_date || null,
                end_date: formData.end_date || null,
                unidade_id: unidadeId,
                company_id: unidadeId,
                status: statusValue
            })
            toast.success('Projeto criado com sucesso!')
            setIsModalOpen(false)
            setFormData({ ...EMPTY_PROJECT })
            validation.clearErrors()
            loadProjects()
        } catch (err) {
            toast.error('Erro ao criar projeto: ' + err.message)
        } finally {
            setCreating(false)
        }
    }

    async function handleUpdateProject(e) {
        e.preventDefault()
        if (!editingProject) return
        validation.clearErrors()

        const isValid = validation.validateFields({
            name: () => validation.validateRequired(formData.name, 'Nome do projeto'),
            short_name: () => validation.validateMaxLength(formData.short_name, 10, 'Nome curto'),
            description: () => validation.validateMaxLength(formData.description, 1000, 'Descrição'),
            dates: () => validation.validateDateRange(formData.start_date, formData.end_date),
            company_id: () => validation.validateRequired(formData.company_id, 'Unidade responsavel')
        })
        if (!isValid) return

        try {
            setCreating(true)
            const unidadeId = formData.company_id ? parseInt(formData.company_id, 10) : null
            const currentStatus = getNumericProjectStatus(editingProject)
            const nextStatus = formData.status ? parseInt(formData.status, 10) : currentStatus

            if (!canTransitionProjectStatus(currentStatus, nextStatus)) {
                toast.error(`Transicao de status invalida: ${getStatusLabelById(currentStatus)} -> ${getStatusLabelById(nextStatus)}.`)
                return
            }
            if (!confirmProjectStatusTransition(currentStatus, nextStatus)) return

            await updateProject(editingProject.id, {
                name: formData.name,
                short_name: formData.short_name.trim(),
                description: formData.description || null,
                start_date: formData.start_date || null,
                end_date: formData.end_date || null,
                unidade_id: unidadeId,
                company_id: unidadeId
            })
            if (currentStatus !== nextStatus) {
                await updateProjectStatus(editingProject.id, { status: nextStatus })
            }
            toast.success('Projeto atualizado com sucesso!')
            setIsEditModalOpen(false)
            setEditingProject(null)
            setFormData({ ...EMPTY_PROJECT })
            validation.clearErrors()
            loadProjects()
        } catch (err) {
            toast.error('Erro ao atualizar projeto: ' + err.message)
        } finally {
            setCreating(false)
        }
    }

    async function handleDeleteProject(project) {
        if (!project) return
        if (!confirm(`Deseja apagar o projeto "${project.name}"?`)) return
        try {
            await deleteProject(project.id)
            toast.success('Projeto apagado com sucesso!')
            loadProjects()
        } catch (err) {
            toast.error('Erro ao apagar projeto: ' + err.message)
        }
    }

    function handleEditStatusSelection(nextStatusValue) {
        const nextStatus = parseInt(nextStatusValue, 10)
        if (!editingProject) {
            setFormData(prev => ({ ...prev, status: String(nextStatus) }))
            return
        }
        const currentStatus = getNumericProjectStatus(editingProject)
        if (!canTransitionProjectStatus(currentStatus, nextStatus)) {
            toast.error(`Transicao nao permitida: ${getStatusLabelById(currentStatus)} -> ${getStatusLabelById(nextStatus)}.`)
            return
        }
        setFormData(prev => ({ ...prev, status: String(nextStatus) }))
    }

    // ── Quick Status ──────────────────────────────────────────────

    function handleQuickStatusSelect(projectId, value) {
        setQuickStatusSelectionByProjectId(prev => ({ ...prev, [String(projectId)]: value }))
    }

    async function handleApplyQuickStatus(project) {
        const projectId = Number(project?.id)
        if (!projectId) return
        const selection = quickStatusSelectionByProjectId[String(projectId)] ?? ''
        if (!selection) { toast.error('Selecione um status para aplicar.'); return }

        const currentStatus = getNumericProjectStatus(project)
        const nextStatus = parseInt(selection, 10)
        if (Number.isNaN(nextStatus)) { toast.error('Status selecionado invalido.'); return }
        if (!canTransitionProjectStatus(currentStatus, nextStatus)) {
            toast.error(`Transicao de status invalida: ${getStatusLabelById(currentStatus)} -> ${getStatusLabelById(nextStatus)}.`)
            return
        }
        if (!confirmProjectStatusTransition(currentStatus, nextStatus)) return

        const projectKey = String(projectId)
        setQuickStatusLoadingByProjectId(prev => ({ ...prev, [projectKey]: true }))
        try {
            const payload = await updateProjectStatus(projectId, { status: nextStatus })
            const updatedStatus = Number(payload?.status ?? nextStatus)
            const updatedPercent = Number(payload?.percent_complete ?? (updatedStatus === 5 ? 100 : project?.percent_complete ?? 0))
            setProjects(prevProjects => prevProjects.map(item => {
                if (Number(item.id) !== projectId) return item
                return { ...item, status: updatedStatus, percent_complete: Number.isNaN(updatedPercent) ? item.percent_complete : updatedPercent }
            }))
            setQuickStatusSelectionByProjectId(prev => ({ ...prev, [projectKey]: '' }))
            toast.success(`Status atualizado para "${getStatusLabelById(updatedStatus)}".`)
        } catch (err) {
            toast.error('Erro ao atualizar status: ' + err.message)
        } finally {
            setQuickStatusLoadingByProjectId(prev => ({ ...prev, [projectKey]: false }))
        }
    }

    // ── Edit modal opener ─────────────────────────────────────────

    async function openEditModal(project) {
        await loadUnidades()
        setEditingProject(project)
        setFormData({
            name: project.name || '',
            short_name: project.short_name || '',
            description: project.description || '',
            start_date: normalizeDateValue(project.start_date),
            end_date: normalizeDateValue(project.end_date),
            company_id: getProjectUnitId(project),
            status: project.status != null ? String(project.status) : '1'
        })
        validation.clearErrors()
        setIsEditModalOpen(true)
    }

    // ── Render ────────────────────────────────────────────────────

    return (
        <>
            <div className="topbar">
                <h1 className="page-title">Projetos</h1>
                <div style={{ display: 'flex', gap: 'var(--spacing-3)' }}>
                    <form onSubmit={(e) => { e.preventDefault(); loadProjects({ search }) }} style={{ display: 'flex', gap: 'var(--spacing-2)' }}>
                        <input
                            type="text"
                            placeholder="Buscar projetos..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            style={{
                                padding: 'var(--spacing-2) var(--spacing-3)',
                                border: '1px solid var(--color-gray-300)',
                                borderRadius: 'var(--radius-md)',
                                fontSize: '0.875rem'
                            }}
                        />
                        <button type="submit" className="btn btn-secondary">Buscar</button>
                    </form>
                    <button className="btn btn-primary" onClick={async () => { await loadUnidades(); setIsModalOpen(true) }}>+ Novo Projeto</button>
                </div>
            </div>

            <div className="page-content">
                {error && (
                    <div className="card" style={{ marginBottom: 'var(--spacing-4)' }}>
                        <div className="card-body">
                            <p style={{ color: 'var(--color-danger-500)' }}>Erro: {error}</p>
                        </div>
                    </div>
                )}

                <div className="card">
                    <div className="card-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                        <h2 className="card-title">Lista de Projetos</h2>
                        <span style={{ fontSize: '0.875rem', color: 'var(--color-gray-500)' }}>
                            {meta.total || 0} projetos
                        </span>
                    </div>
                    <div className="card-body" style={{ padding: 0 }}>
                        {loading ? (
                            <div style={{ padding: 'var(--spacing-6)', textAlign: 'center' }}>Carregando...</div>
                        ) : projects.length === 0 ? (
                            <div style={{ padding: 'var(--spacing-6)', textAlign: 'center', color: 'var(--color-gray-500)' }}>
                                Nenhum projeto encontrado
                            </div>
                        ) : (
                            <ProjectTable
                                projects={projects}
                                onEdit={openEditModal}
                                onDelete={handleDeleteProject}
                                quickStatusSelectionByProjectId={quickStatusSelectionByProjectId}
                                quickStatusLoadingByProjectId={quickStatusLoadingByProjectId}
                                onQuickStatusSelect={handleQuickStatusSelect}
                                onApplyQuickStatus={handleApplyQuickStatus}
                            />
                        )}
                    </div>
                </div>

                {/* Pagination */}
                {meta.total_pages > 1 && (
                    <div style={{ display: 'flex', justifyContent: 'center', gap: 'var(--spacing-2)', marginTop: 'var(--spacing-6)' }}>
                        {Array.from({ length: meta.total_pages }, (_, i) => i + 1).map(page => (
                            <button
                                key={page}
                                className={`btn ${page === meta.page ? 'btn-primary' : 'btn-secondary'}`}
                                onClick={() => loadProjects({ page })}
                                style={{ minWidth: 40 }}
                            >
                                {page}
                            </button>
                        ))}
                    </div>
                )}
            </div>

            {/* Create Project Modal */}
            <Modal
                isOpen={isModalOpen}
                onClose={() => setIsModalOpen(false)}
                title="Novo Projeto"
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setIsModalOpen(false)}>Cancelar</Button>
                        <Button variant="primary" onClick={handleCreateProject} disabled={creating || !formData.name.trim() || !formData.company_id}>
                            {creating ? 'Criando...' : 'Criar Projeto'}
                        </Button>
                    </>
                }
            >
                <ProjectForm
                    formData={formData}
                    onChange={handleFormChange}
                    validation={validation}
                    groupedUnidades={groupedUnidades}
                    unidadesLoading={unidadesLoading}
                    statusOptions={createStatusOptions}
                    onSubmit={handleCreateProject}
                />
            </Modal>

            {/* Edit Project Modal */}
            <Modal
                isOpen={isEditModalOpen}
                onClose={() => { setIsEditModalOpen(false); setEditingProject(null) }}
                title="Editar Projeto"
                footer={
                    <>
                        <Button variant="secondary" onClick={() => { setIsEditModalOpen(false); setEditingProject(null) }}>Cancelar</Button>
                        <Button variant="primary" onClick={handleUpdateProject} disabled={creating || !formData.name.trim() || !formData.company_id}>
                            {creating ? 'Salvando...' : 'Salvar Alterações'}
                        </Button>
                    </>
                }
            >
                <ProjectForm
                    formData={formData}
                    onChange={handleFormChange}
                    validation={validation}
                    groupedUnidades={groupedUnidades}
                    unidadesLoading={unidadesLoading}
                    statusOptions={editStatusOptions}
                    statusHint={editStatusHint}
                    onStatusChange={handleEditStatusSelection}
                    onSubmit={handleUpdateProject}
                />
            </Modal>
        </>
    )
}

export default Projects
