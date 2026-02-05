/**
 * Task Edit Modal
 * 
 * Modal para editar tarefa com upload de arquivos
 */

import { useState, useEffect } from 'react'
import PropTypes from 'prop-types'
import Modal from './ui/Modal'
import Button from './ui/Button'
import Input from './ui/Input'
import FileUpload from './FileUpload'
import { getTaskFiles, uploadTaskFile, deleteTaskFile } from '../services/api'
import { useToast } from '../contexts/ToastContext'

function TaskEditModal({ task, isOpen, onClose, onSave, projects = [] }) {
    const toast = useToast()
    const [editedTask, setEditedTask] = useState({
        name: '',
        description: '',
        project_id: '',
        priority: '1',
        end_date: '',
        percent_complete: 0
    })
    const [files, setFiles] = useState([])
    const [loading, setLoading] = useState(false)
    const [saving, setSaving] = useState(false)

    useEffect(() => {
        if (task) {
            setEditedTask({
                name: task.name || '',
                description: task.description || '',
                project_id: task.project_id || '',
                priority: String(task.priority || 1),
                end_date: task.end_date ? task.end_date.split('T')[0] : '',
                percent_complete: task.percent_complete || 0
            })
            loadFiles()
        }
    }, [task, isOpen])

    async function loadFiles() {
        if (!task?.id) return
        
        try {
            setLoading(true)
            const response = await getTaskFiles(task.id)
            setFiles(response.data?.files || [])
        } catch (err) {
            console.error('Failed to load files:', err)
        } finally {
            setLoading(false)
        }
    }

    async function handleFileUpload(file) {
        if (!task?.id) return
        await uploadTaskFile(task.id, file)
        await loadFiles()
    }

    async function handleFileDelete(fileId) {
        await deleteTaskFile(fileId)
        await loadFiles()
    }

    async function handleSave() {
        if (!editedTask.name.trim()) {
            toast.warning('Nome da tarefa é obrigatório')
            return
        }

        try {
            setSaving(true)
            await onSave({
                ...editedTask,
                priority: parseInt(editedTask.priority),
                percent_complete: parseInt(editedTask.percent_complete)
            })
            toast.success('Tarefa atualizada com sucesso!')
            onClose()
        } catch (err) {
            toast.error('Erro ao salvar: ' + err.message)
        } finally {
            setSaving(false)
        }
    }

    if (!task) return null

    return (
        <Modal
            isOpen={isOpen}
            onClose={onClose}
            title="Editar Tarefa"
            size="lg"
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button 
                        variant="primary" 
                        onClick={handleSave}
                        disabled={saving || !editedTask.name.trim()}
                    >
                        {saving ? 'Salvando...' : 'Salvar Alterações'}
                    </Button>
                </>
            }
        >
            <div style={{ display: 'grid', gap: 'var(--spacing-6)' }}>
                {/* Dados da Tarefa */}
                <div>
                    <h4 style={{ 
                        fontSize: '0.875rem', 
                        fontWeight: 600, 
                        color: 'var(--color-gray-700)',
                        marginBottom: 'var(--spacing-3)',
                        paddingBottom: 'var(--spacing-2)',
                        borderBottom: '1px solid var(--color-gray-200)'
                    }}>
                        Informações
                    </h4>
                    
                    <div style={{ display: 'grid', gap: 'var(--spacing-4)' }}>
                        <div>
                            <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                                Nome da Tarefa *
                            </label>
                            <Input
                                value={editedTask.name}
                                onChange={(e) => setEditedTask({ ...editedTask, name: e.target.value })}
                                placeholder="Digite o nome da tarefa"
                            />
                        </div>

                        <div>
                            <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                                Descrição
                            </label>
                            <textarea
                                value={editedTask.description}
                                onChange={(e) => setEditedTask({ ...editedTask, description: e.target.value })}
                                placeholder="Descrição da tarefa"
                                rows={3}
                                style={{
                                    width: '100%',
                                    padding: 'var(--spacing-2) var(--spacing-3)',
                                    border: '1px solid var(--color-gray-300)',
                                    borderRadius: 'var(--radius-md)',
                                    fontSize: '0.875rem',
                                    fontFamily: 'inherit'
                                }}
                            />
                        </div>

                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 'var(--spacing-4)' }}>
                            <div>
                                <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                                    Projeto
                                </label>
                                <select
                                    value={editedTask.project_id}
                                    onChange={(e) => setEditedTask({ ...editedTask, project_id: e.target.value })}
                                    style={{
                                        width: '100%',
                                        padding: 'var(--spacing-2) var(--spacing-3)',
                                        border: '1px solid var(--color-gray-300)',
                                        borderRadius: 'var(--radius-md)',
                                        fontSize: '0.875rem',
                                        background: 'white'
                                    }}
                                >
                                    <option value="">Sem projeto</option>
                                    {projects.map(project => (
                                        <option key={project.id} value={project.id}>
                                            {project.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                                    Prioridade
                                </label>
                                <select
                                    value={editedTask.priority}
                                    onChange={(e) => setEditedTask({ ...editedTask, priority: e.target.value })}
                                    style={{
                                        width: '100%',
                                        padding: 'var(--spacing-2) var(--spacing-3)',
                                        border: '1px solid var(--color-gray-300)',
                                        borderRadius: 'var(--radius-md)',
                                        fontSize: '0.875rem',
                                        background: 'white'
                                    }}
                                >
                                    <option value="0">Baixa</option>
                                    <option value="1">Normal</option>
                                    <option value="2">Alta</option>
                                    <option value="3">Urgente</option>
                                </select>
                            </div>
                        </div>

                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 'var(--spacing-4)' }}>
                            <div>
                                <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                                    Prazo
                                </label>
                                <Input
                                    type="date"
                                    value={editedTask.end_date}
                                    onChange={(e) => setEditedTask({ ...editedTask, end_date: e.target.value })}
                                />
                            </div>

                            <div>
                                <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                                    Progresso: {editedTask.percent_complete}%
                                </label>
                                <input
                                    type="range"
                                    min="0"
                                    max="100"
                                    step="10"
                                    value={editedTask.percent_complete}
                                    onChange={(e) => setEditedTask({ ...editedTask, percent_complete: parseInt(e.target.value) })}
                                    style={{ width: '100%' }}
                                />
                            </div>
                        </div>
                    </div>
                </div>

                {/* Upload de Arquivos */}
                <div>
                    <h4 style={{ 
                        fontSize: '0.875rem', 
                        fontWeight: 600, 
                        color: 'var(--color-gray-700)',
                        marginBottom: 'var(--spacing-3)',
                        paddingBottom: 'var(--spacing-2)',
                        borderBottom: '1px solid var(--color-gray-200)'
                    }}>
                        Anexos
                    </h4>
                    
                    <FileUpload
                        taskId={task.id}
                        files={files}
                        onUpload={handleFileUpload}
                        onDelete={handleFileDelete}
                    />
                </div>
            </div>
        </Modal>
    )
}

TaskEditModal.propTypes = {
    task: PropTypes.object,
    isOpen: PropTypes.bool.isRequired,
    onClose: PropTypes.func.isRequired,
    onSave: PropTypes.func.isRequired,
    projects: PropTypes.array
}

export default TaskEditModal
