import { useState } from 'react'
import { normalizeDateValue } from './projectStatusUtils'

const EMPTY_PROJECT = {
    name: '',
    short_name: '',
    description: '',
    start_date: '',
    end_date: '',
    company_id: '',
    programa_id: '',
    acao_id: '',
    status: '1',
}

function getProjectUnitId(project) {
    const value = project?.unidade_id ?? project?.unidade?.id ?? project?.company_id ?? project?.company?.id ?? null
    return value !== null && value !== undefined ? String(value) : ''
}

function getProjectProgramaId(project) {
    const value = project?.programa_id ?? project?.programa?.id ?? null
    return value !== null && value !== undefined ? String(value) : ''
}

function getProjectAcaoId(project) {
    const value = project?.acao_id ?? project?.acao?.id ?? null
    return value !== null && value !== undefined ? String(value) : ''
}

export default function useProjectForm({ loadUnidades, validation }) {
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false)
    const [isEditModalOpen, setIsEditModalOpen] = useState(false)
    const [editingProject, setEditingProject] = useState(null)
    const [formData, setFormData] = useState({ ...EMPTY_PROJECT })
    const [creating, setCreating] = useState(false)

    function handleFormChange(field, value) {
        setFormData((prev) => ({ ...prev, [field]: value }))
    }

    function resetForm() {
        setFormData({ ...EMPTY_PROJECT })
        setEditingProject(null)
        validation.clearErrors()
    }

    async function openCreateModal() {
        await loadUnidades()
        resetForm()
        setIsCreateModalOpen(true)
    }

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
            programa_id: getProjectProgramaId(project),
            acao_id: getProjectAcaoId(project),
            status: project.status != null ? String(project.status) : '1',
        })
        validation.clearErrors()
        setIsEditModalOpen(true)
    }

    function closeCreateModal() {
        setIsCreateModalOpen(false)
    }

    function closeEditModal() {
        setIsEditModalOpen(false)
        setEditingProject(null)
    }

    return {
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
    }
}
