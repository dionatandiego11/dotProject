import { useState, useEffect } from 'react'
import { createNivel, updateNivel } from '../../services/api'

const styles = {
    overlay: {
        position: 'fixed',
        top: 0,
        left: 0,
        right: 0,
        bottom: 0,
        backgroundColor: 'rgba(0, 0, 0, 0.5)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        zIndex: 1000
    },
    modal: {
        background: 'white',
        borderRadius: 12,
        padding: 24,
        width: '100%',
        maxWidth: 500,
        boxShadow: '0 20px 50px rgba(0,0,0,0.3)'
    },
    header: {
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: 20
    },
    title: {
        margin: 0,
        fontSize: 20,
        fontWeight: 600
    },
    closeBtn: {
        background: 'none',
        border: 'none',
        fontSize: 24,
        cursor: 'pointer',
        color: '#6b7280'
    },
    formGroup: {
        marginBottom: 16
    },
    label: {
        display: 'block',
        marginBottom: 6,
        fontWeight: 500,
        color: '#374151'
    },
    input: {
        width: '100%',
        padding: '10px 12px',
        border: '1px solid #d1d5db',
        borderRadius: 6,
        fontSize: 14,
        boxSizing: 'border-box'
    },
    textarea: {
        width: '100%',
        padding: '10px 12px',
        border: '1px solid #d1d5db',
        borderRadius: 6,
        fontSize: 14,
        minHeight: 80,
        resize: 'vertical',
        boxSizing: 'border-box'
    },
    actions: {
        display: 'flex',
        gap: 12,
        marginTop: 24
    },
    btnPrimary: {
        flex: 1,
        padding: '12px 20px',
        background: 'linear-gradient(135deg, #3b82f6 0%, #2563eb 100%)',
        color: 'white',
        border: 'none',
        borderRadius: 8,
        fontSize: 14,
        fontWeight: 600,
        cursor: 'pointer'
    },
    btnSecondary: {
        flex: 1,
        padding: '12px 20px',
        background: '#f3f4f6',
        color: '#374151',
        border: '1px solid #d1d5db',
        borderRadius: 8,
        fontSize: 14,
        fontWeight: 500,
        cursor: 'pointer'
    },
    error: {
        color: '#ef4444',
        fontSize: 12,
        marginTop: 4
    }
}

function NivelForm({ nivel, onSave, onCancel }) {
    const [form, setForm] = useState({
        nome: '',
        ordem: 1,
        descricao: '',
        titulo_responsavel: ''
    })
    const [errors, setErrors] = useState({})
    const [saving, setSaving] = useState(false)

    useEffect(() => {
        if (nivel) {
            setForm({
                nome: nivel.nome || '',
                ordem: nivel.ordem || 1,
                descricao: nivel.descricao || '',
                titulo_responsavel: nivel.titulo_responsavel || ''
            })
        }
    }, [nivel])

    function validate() {
        const newErrors = {}

        if (!form.nome.trim()) {
            newErrors.nome = 'Nome é obrigatório'
        }

        if (!form.ordem || form.ordem < 1) {
            newErrors.ordem = 'Ordem deve ser maior que 0'
        }

        setErrors(newErrors)
        return Object.keys(newErrors).length === 0
    }

    async function handleSubmit(e) {
        e.preventDefault()

        if (!validate()) return

        setSaving(true)

        try {
            let result;
            if (nivel?.id) {
                result = await updateNivel(nivel.id, form)
            } else {
                result = await createNivel(form)
            }

            // API returns { data: ... } or just data depending on endpoint
            // api.js usually returns the JSON response
            onSave(result.data || form)
        } catch (err) {
            console.error(err)
            setErrors({ submit: err.message || 'Erro ao salvar' })
        } finally {
            setSaving(false)
        }
    }

    function handleChange(field, value) {
        setForm(prev => ({ ...prev, [field]: value }))
        if (errors[field]) {
            setErrors(prev => ({ ...prev, [field]: null }))
        }
    }

    return (
        <div style={styles.overlay} onClick={onCancel}>
            <div style={styles.modal} onClick={e => e.stopPropagation()}>
                <div style={styles.header}>
                    <h2 style={styles.title}>
                        {nivel?.id ? '✏️ Editar Nível' : '➕ Novo Nível'}
                    </h2>
                    <button style={styles.closeBtn} onClick={onCancel}>×</button>
                </div>

                <form onSubmit={handleSubmit}>
                    <div style={styles.formGroup}>
                        <label style={styles.label}>Nome *</label>
                        <input
                            style={styles.input}
                            type="text"
                            value={form.nome}
                            onChange={e => handleChange('nome', e.target.value)}
                            placeholder="Ex: Secretaria"
                        />
                        {errors.nome && <div style={styles.error}>{errors.nome}</div>}
                    </div>

                    <div style={styles.formGroup}>
                        <label style={styles.label}>Ordem Hierárquica *</label>
                        <input
                            style={styles.input}
                            type="number"
                            min="1"
                            value={form.ordem}
                            onChange={e => handleChange('ordem', parseInt(e.target.value) || 1)}
                        />
                        <small style={{ color: '#6b7280' }}>1 = mais alto (Prefeitura), maior = mais baixo</small>
                        {errors.ordem && <div style={styles.error}>{errors.ordem}</div>}
                    </div>

                    <div style={styles.formGroup}>
                        <label style={styles.label}>Título do Responsável</label>
                        <input
                            style={styles.input}
                            type="text"
                            value={form.titulo_responsavel}
                            onChange={e => handleChange('titulo_responsavel', e.target.value)}
                            placeholder="Ex: Secretário"
                        />
                    </div>

                    <div style={styles.formGroup}>
                        <label style={styles.label}>Descrição</label>
                        <textarea
                            style={styles.textarea}
                            value={form.descricao}
                            onChange={e => handleChange('descricao', e.target.value)}
                            placeholder="Descrição opcional do nível..."
                        />
                    </div>

                    {errors.submit && (
                        <div style={{ ...styles.error, marginBottom: 12 }}>
                            {errors.submit}
                        </div>
                    )}

                    <div style={styles.actions}>
                        <button
                            type="button"
                            style={styles.btnSecondary}
                            onClick={onCancel}
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            style={styles.btnPrimary}
                            disabled={saving}
                        >
                            {saving ? 'Salvando...' : (nivel?.id ? 'Salvar' : 'Criar Nível')}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    )
}

export default NivelForm
