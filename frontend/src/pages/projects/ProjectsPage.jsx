import { useEffect, useMemo, useState } from 'react'
import {
    getProjects,
    createProject,
    updateProject,
    updateProjectStatus,
    deleteProject,
    getUnidades,
} from '../../services/api'
import Modal from '../../components/ui/Modal'
import Button from '../../components/ui/Button'
import { useToast } from '../../contexts/ToastContext'
import { useValidation } from '../../hooks/useValidation'
import {
    getStatusLabelById,
    getAllowedProjectStatuses,
    canTransitionProjectStatus,
    getNumericProjectStatus,
    buildGroupedUnidades,
    CREATE_ALLOWED_PROJECT_STATUSES,
} from './projectStatusUtils'
import ProjectForm from './ProjectForm'
import ProjectFilters from './ProjectFilters'
import ProjectStats from './ProjectStats'
import ProjectList from './ProjectList'
import useProjectFilters from './useProjectFilters'
import useProjectForm from './useProjectForm'

export default function ProjectsPage() {
    const toast = useToast()
    const validation = useValidation()
    const [projects, setProjects] = useState([])
    const [meta, setMeta] = useState({})
    const [loading, setLoading] = useState(true)
    const [error, setError] = useState(null)
    const [unidades, setUnidades] = useState([])
    const [unidadesLoading, setUnidadesLoading] = useState(false)
    const [quickStatusSelectionByProjectId, setQuickStatusSelectionByProjectId] = useState({})
    const [quickStatusLoadingByProjectId, setQuickStatusLoadingByProjectId] = useState({})

    const groupedUnidades = useMemo(() => buildGroupedUnidades(unidades), [unidades])

    const filters = useProjectFilters({ onSearch: loadProjects })
    const {
        search,
        setSearch,
        handleSearchSubmit,
    } = filters

    const projectForm = useProjectForm({ loadUnidades, validation })
    const {
        isCreateModalOpen,
        isEditModalOpen,
        editingProject,
        formData,
        creating,
        setCreating,
        setFormData,
        handleFormChange,
        resetForm,
        openCreateModal,
        openEditModal,
        closeCreateModal,
        closeEditModal,
    } = projectForm

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

    useEffect(() => {
        loadProjects()
    }, [])

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

    function confirmProjectStatusTransition(currentStatus, nextStatus) {
        if (currentStatus === nextStatus) return true
        if (nextStatus === 5) return window.confirm('Marcar este projeto como Completo? O progresso sera ajustado para 100%.')
        if (nextStatus === 6) return window.confirm('Arquivar este projeto? Ele sera removido dos fluxos operacionais ativos.')
        return true
    }

    async function handleCreateProject(event) {
        event.preventDefault()
        validation.clearErrors()

        const isValid = validation.validateFields({
            name: () => validation.validateRequired(formData.name, 'Nome do projeto'),
            short_name: () => validation.validateMaxLength(formData.short_name, 10, 'Nome curto'),
            description: () => validation.validateMaxLength(formData.description, 1000, 'Descricao'),
            dates: () => validation.validateDateRange(formData.start_date, formData.end_date),
            company_id: () => validation.validateRequired(formData.company_id, 'Unidade responsavel'),
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
                status: statusValue,
            })

            toast.success('Projeto criado com sucesso!')
            closeCreateModal()
            resetForm()
            loadProjects()
        } catch (err) {
            toast.error('Erro ao criar projeto: ' + err.message)
        } finally {
            setCreating(false)
        }
    }

    async function handleUpdateProject(event) {
        event.preventDefault()
        if (!editingProject) return
        validation.clearErrors()

        const isValid = validation.validateFields({
            name: () => validation.validateRequired(formData.name, 'Nome do projeto'),
            short_name: () => validation.validateMaxLength(formData.short_name, 10, 'Nome curto'),
            description: () => validation.validateMaxLength(formData.description, 1000, 'Descricao'),
            dates: () => validation.validateDateRange(formData.start_date, formData.end_date),
            company_id: () => validation.validateRequired(formData.company_id, 'Unidade responsavel'),
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
                company_id: unidadeId,
            })
            if (currentStatus !== nextStatus) {
                await updateProjectStatus(editingProject.id, { status: nextStatus })
            }

            toast.success('Projeto atualizado com sucesso!')
            closeEditModal()
            resetForm()
            loadProjects()
        } catch (err) {
            toast.error('Erro ao atualizar projeto: ' + err.message)
        } finally {
            setCreating(false)
        }
    }

    async function handleDeleteProject(project) {
        if (!project) return
        if (!window.confirm(`Deseja apagar o projeto "${project.name}"?`)) return

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
            setFormData((prev) => ({ ...prev, status: String(nextStatus) }))
            return
        }

        const currentStatus = getNumericProjectStatus(editingProject)
        if (!canTransitionProjectStatus(currentStatus, nextStatus)) {
            toast.error(`Transicao nao permitida: ${getStatusLabelById(currentStatus)} -> ${getStatusLabelById(nextStatus)}.`)
            return
        }

        setFormData((prev) => ({ ...prev, status: String(nextStatus) }))
    }

    function handleQuickStatusSelect(projectId, value) {
        setQuickStatusSelectionByProjectId((prev) => ({ ...prev, [String(projectId)]: value }))
    }

    async function handleApplyQuickStatus(project) {
        const projectId = Number(project?.id)
        if (!projectId) return

        const selection = quickStatusSelectionByProjectId[String(projectId)] ?? ''
        if (!selection) {
            toast.error('Selecione um status para aplicar.')
            return
        }

        const currentStatus = getNumericProjectStatus(project)
        const nextStatus = parseInt(selection, 10)
        if (Number.isNaN(nextStatus)) {
            toast.error('Status selecionado invalido.')
            return
        }
        if (!canTransitionProjectStatus(currentStatus, nextStatus)) {
            toast.error(`Transicao de status invalida: ${getStatusLabelById(currentStatus)} -> ${getStatusLabelById(nextStatus)}.`)
            return
        }
        if (!confirmProjectStatusTransition(currentStatus, nextStatus)) return

        const projectKey = String(projectId)
        setQuickStatusLoadingByProjectId((prev) => ({ ...prev, [projectKey]: true }))
        try {
            const payload = await updateProjectStatus(projectId, { status: nextStatus })
            const updatedStatus = Number(payload?.status ?? nextStatus)
            const updatedPercent = Number(payload?.percent_complete ?? (updatedStatus === 5 ? 100 : project?.percent_complete ?? 0))

            setProjects((prevProjects) => prevProjects.map((item) => {
                if (Number(item.id) !== projectId) return item
                return {
                    ...item,
                    status: updatedStatus,
                    percent_complete: Number.isNaN(updatedPercent) ? item.percent_complete : updatedPercent,
                }
            }))
            setQuickStatusSelectionByProjectId((prev) => ({ ...prev, [projectKey]: '' }))
            toast.success(`Status atualizado para "${getStatusLabelById(updatedStatus)}".`)
        } catch (err) {
            toast.error('Erro ao atualizar status: ' + err.message)
        } finally {
            setQuickStatusLoadingByProjectId((prev) => ({ ...prev, [projectKey]: false }))
        }
    }

    return (
        <>
            <ProjectFilters
                search={search}
                onSearchChange={setSearch}
                onSearchSubmit={handleSearchSubmit}
                onOpenCreate={openCreateModal}
            />

            <div className="page-content">
                {error && (
                    <div className="card" style={{ marginBottom: 'var(--spacing-4)' }}>
                        <div className="card-body">
                            <p style={{ color: 'var(--color-danger-500)' }}>Erro: {error}</p>
                        </div>
                    </div>
                )}

                <ProjectStats projects={projects} />

                <ProjectList
                    loading={loading}
                    projects={projects}
                    meta={meta}
                    onEdit={openEditModal}
                    onDelete={handleDeleteProject}
                    quickStatusSelectionByProjectId={quickStatusSelectionByProjectId}
                    quickStatusLoadingByProjectId={quickStatusLoadingByProjectId}
                    onQuickStatusSelect={handleQuickStatusSelect}
                    onApplyQuickStatus={handleApplyQuickStatus}
                    onPageChange={(page) => loadProjects({ page })}
                />
            </div>

            <Modal
                isOpen={isCreateModalOpen}
                onClose={closeCreateModal}
                title="Novo Projeto"
                footer={(
                    <>
                        <Button variant="secondary" onClick={closeCreateModal}>Cancelar</Button>
                        <Button
                            variant="primary"
                            onClick={handleCreateProject}
                            disabled={creating || !formData.name.trim() || !formData.company_id}
                        >
                            {creating ? 'Criando...' : 'Criar Projeto'}
                        </Button>
                    </>
                )}
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

            <Modal
                isOpen={isEditModalOpen}
                onClose={closeEditModal}
                title="Editar Projeto"
                footer={(
                    <>
                        <Button variant="secondary" onClick={closeEditModal}>Cancelar</Button>
                        <Button
                            variant="primary"
                            onClick={handleUpdateProject}
                            disabled={creating || !formData.name.trim() || !formData.company_id}
                        >
                            {creating ? 'Salvando...' : 'Salvar Alteracoes'}
                        </Button>
                    </>
                )}
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
