/**
 * File Upload Component
 * 
 * Componente para upload e gestão de arquivos em tarefas
 */

import { useState, useRef } from 'react'
import PropTypes from 'prop-types'
import { useToast } from '../contexts/ToastContext'

function FileUpload({ taskId, files = [], onUpload, onDelete }) {
    const toast = useToast()
    const fileInputRef = useRef(null)
    const [uploading, setUploading] = useState(false)
    const [dragOver, setDragOver] = useState(false)

    const maxSize = 10 * 1024 * 1024 // 10MB
    const allowedTypes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'text/plain', 'text/csv',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip'
    ]

    function validateFile(file) {
        if (file.size > maxSize) {
            toast.error(`Arquivo muito grande. Máximo 10MB`)
            return false
        }
        
        if (!allowedTypes.includes(file.type)) {
            toast.error(`Tipo de arquivo não permitido: ${file.type || 'desconhecido'}`)
            return false
        }
        
        return true
    }

    async function handleFileSelect(event) {
        const file = event.target.files?.[0]
        if (!file) return
        
        if (!validateFile(file)) {
            event.target.value = ''
            return
        }
        
        await uploadFile(file)
        event.target.value = ''
    }

    async function uploadFile(file) {
        try {
            setUploading(true)
            await onUpload(file)
            toast.success(`Arquivo "${file.name}" enviado com sucesso!`)
        } catch (err) {
            toast.error('Erro ao enviar arquivo: ' + err.message)
        } finally {
            setUploading(false)
        }
    }

    async function handleDelete(fileId, fileName) {
        if (!confirm(`Deseja remover o arquivo "${fileName}"?`)) {
            return
        }
        
        try {
            await onDelete(fileId)
            toast.success('Arquivo removido')
        } catch (err) {
            toast.error('Erro ao remover arquivo: ' + err.message)
        }
    }

    function handleDragOver(e) {
        e.preventDefault()
        setDragOver(true)
    }

    function handleDragLeave(e) {
        e.preventDefault()
        setDragOver(false)
    }

    function handleDrop(e) {
        e.preventDefault()
        setDragOver(false)
        
        const file = e.dataTransfer.files?.[0]
        if (!file) return
        
        if (validateFile(file)) {
            uploadFile(file)
        }
    }

    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B'
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
    }

    function getFileIcon(mimeType) {
        if (mimeType?.startsWith('image/')) return '🖼️'
        if (mimeType === 'application/pdf') return '📄'
        if (mimeType?.includes('word')) return '📝'
        if (mimeType?.includes('excel') || mimeType?.includes('sheet')) return '📊'
        if (mimeType === 'application/zip') return '📦'
        return '📎'
    }

    return (
        <div>
            {/* Upload Area */}
            <div
                onClick={() => fileInputRef.current?.click()}
                onDragOver={handleDragOver}
                onDragLeave={handleDragLeave}
                onDrop={handleDrop}
                style={{
                    border: `2px dashed ${dragOver ? 'var(--color-primary-500)' : 'var(--color-gray-300)'}`,
                    borderRadius: 'var(--radius-lg)',
                    padding: 'var(--spacing-6)',
                    textAlign: 'center',
                    cursor: uploading ? 'not-allowed' : 'pointer',
                    background: dragOver ? 'var(--color-primary-50)' : 'var(--color-gray-50)',
                    transition: 'all 0.2s ease',
                    opacity: uploading ? 0.6 : 1
                }}
            >
                <input
                    ref={fileInputRef}
                    type="file"
                    onChange={handleFileSelect}
                    style={{ display: 'none' }}
                    accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.zip,.txt,.csv"
                    disabled={uploading}
                />
                
                {uploading ? (
                    <div>
                        <div style={{ fontSize: '2rem', marginBottom: 'var(--spacing-2)' }}>
                            ⬆️
                        </div>
                        <p style={{ color: 'var(--color-gray-600)', fontWeight: 500 }}>
                            Enviando...
                        </p>
                    </div>
                ) : (
                    <div>
                        <div style={{ fontSize: '2rem', marginBottom: 'var(--spacing-2)' }}>
                            📎
                        </div>
                        <p style={{ color: 'var(--color-gray-600)', fontWeight: 500, marginBottom: 'var(--spacing-1)' }}>
                            Clique ou arraste um arquivo aqui
                        </p>
                        <p style={{ color: 'var(--color-gray-400)', fontSize: '0.75rem' }}>
                            Máximo 10MB • Imagens, PDF, Office, ZIP
                        </p>
                    </div>
                )}
            </div>

            {/* Files List */}
            {files.length > 0 && (
                <div style={{ marginTop: 'var(--spacing-4)' }}>
                    <h4 style={{ 
                        fontSize: '0.875rem', 
                        fontWeight: 600, 
                        color: 'var(--color-gray-700)',
                        marginBottom: 'var(--spacing-2)'
                    }}>
                        Arquivos anexados ({files.length})
                    </h4>
                    
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--spacing-2)' }}>
                        {files.map(file => (
                            <div
                                key={file.file_id}
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 'var(--spacing-3)',
                                    padding: 'var(--spacing-3)',
                                    background: 'white',
                                    border: '1px solid var(--color-gray-200)',
                                    borderRadius: 'var(--radius-md)',
                                    transition: 'all 0.15s ease'
                                }}
                            >
                                <span style={{ fontSize: '1.5rem' }}>
                                    {getFileIcon(file.file_mime_type)}
                                </span>
                                
                                <div style={{ flex: 1, minWidth: 0 }}>
                                    <a
                                        href={`/api/v1/files/${file.file_id}/download`}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        style={{
                                            color: 'var(--color-primary-600)',
                                            fontWeight: 500,
                                            fontSize: '0.875rem',
                                            textDecoration: 'none',
                                            display: 'block',
                                            overflow: 'hidden',
                                            textOverflow: 'ellipsis',
                                            whiteSpace: 'nowrap'
                                        }}
                                        onMouseEnter={(e) => e.target.style.textDecoration = 'underline'}
                                        onMouseLeave={(e) => e.target.style.textDecoration = 'none'}
                                    >
                                        {file.file_name}
                                    </a>
                                    <span style={{ 
                                        fontSize: '0.75rem', 
                                        color: 'var(--color-gray-500)'
                                    }}>
                                        {file.file_size_formatted || formatFileSize(file.file_size)} • 
                                        {' '}{file.uploaded_by_name || 'Desconhecido'} • 
                                        {' '}{new Date(file.file_created_at).toLocaleDateString('pt-BR')}
                                    </span>
                                </div>
                                
                                <button
                                    onClick={() => handleDelete(file.file_id, file.file_name)}
                                    style={{
                                        background: 'none',
                                        border: 'none',
                                        color: 'var(--color-gray-400)',
                                        cursor: 'pointer',
                                        padding: 'var(--spacing-1)',
                                        borderRadius: 'var(--radius-md)',
                                        transition: 'all 0.15s ease'
                                    }}
                                    onMouseEnter={(e) => {
                                        e.target.style.color = 'var(--color-danger-500)'
                                        e.target.style.backgroundColor = 'var(--color-danger-50)'
                                    }}
                                    onMouseLeave={(e) => {
                                        e.target.style.color = 'var(--color-gray-400)'
                                        e.target.style.backgroundColor = 'transparent'
                                    }}
                                    title="Remover arquivo"
                                >
                                    🗑️
                                </button>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    )
}

FileUpload.propTypes = {
    taskId: PropTypes.number.isRequired,
    files: PropTypes.array,
    onUpload: PropTypes.func.isRequired,
    onDelete: PropTypes.func.isRequired
}

export default FileUpload
