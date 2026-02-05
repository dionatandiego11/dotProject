import { useState, useEffect } from 'react'
import { createUnidade, updateUnidade, getVinculos, createVinculo, deleteVinculo } from '../../services/api'
import Modal from '../../components/ui/Modal'

const styles = {
    overlay: {
        position: 'fixed',
        top: 0,
        left: 0,
        right: 0,
        bottom: 0,
        background: 'rgba(0,0,0,0.5)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        zIndex: 1000
    },
    modal: {
        background: 'white',
        borderRadius: 12,
        width: '90%',
        maxWidth: 600,
        maxHeight: '90vh',
        overflow: 'auto',
        boxShadow: '0 20px 25px -5px rgba(0,0,0,0.1)'
    },
    header: {
        padding: 20,
        borderBottom: '1px solid #e5e7eb',
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center'
    },
    title: { margin: 0, fontSize: 18, fontWeight: 600 },
    closeBtn: {
        background: 'none',
        border: 'none',
        fontSize: 24,
        cursor: 'pointer',
        color: '#6b7280'
    },
    body: { padding: 20 },
    formGroup: { marginBottom: 16 },
    label: {
        display: 'block',
        marginBottom: 6,
        fontWeight: 500,
        color: '#374151',
        fontSize: 14
    },
    required: { color: '#ef4444' },
    input: {
        width: '100%',
        padding: '10px 12px',
        border: '1px solid #d1d5db',
        borderRadius: 8,
        fontSize: 14,
        boxSizing: 'border-box'
    },
    select: {
        width: '100%',
        padding: '10px 12px',
        border: '1px solid #d1d5db',
        borderRadius: 8,
        fontSize: 14,
        background: 'white'
    },
    textarea: {
        width: '100%',
        padding: '10px 12px',
        border: '1px solid #d1d5db',
        borderRadius: 8,
        fontSize: 14,
        minHeight: 80,
        resize: 'vertical',
        boxSizing: 'border-box'
    },
    hint: {
        fontSize: 12,
        color: '#6b7280',
        marginTop: 4
    },
    footer: {
        padding: 20,
        borderTop: '1px solid #e5e7eb',
        display: 'flex',
        justifyContent: 'flex-end',
        gap: 12
    },
    btnCancel: {
        padding: '10px 20px',
        background: '#f3f4f6',
        color: '#374151',
        border: 'none',
        borderRadius: 8,
        cursor: 'pointer',
        fontWeight: 500
    },
    btnSave: {
        padding: '10px 20px',
        background: 'linear-gradient(135deg, #3b82f6 0%, #2563eb 100%)',
        color: 'white',
        border: 'none',
        borderRadius: 8,
        cursor: 'pointer',
        fontWeight: 600
    },
    nivelCard: {
        padding: 12,
        borderRadius: 8,
        border: '2px solid #e5e7eb',
        marginBottom: 8,
        cursor: 'pointer',
        transition: 'all 0.2s'
    },
    nivelCardSelected: {
        borderColor: '#3b82f6',
        background: '#eff6ff'
    },
    nivelIndicator: (nivel) => ({
        display: 'inline-block',
        width: 12,
        height: 12,
        borderRadius: '50%',
        background: {
            1: '#dc2626',
            2: '#2563eb',
            3: '#0891b2',
            4: '#7c3aed',
            5: '#059669'
        }[nivel] || '#6b7280',
        marginRight: 8
    })
}

const NIVEL_INFO = {
    1: { nome: 'Prefeitura', desc: 'Nível raiz - representa a prefeitura municipal', icon: '🏛️' },
    2: { nome: 'Secretaria', desc: 'Órgãos executivos diretamente subordinados à prefeitura', icon: '🏢' },
    3: { nome: 'Coordenação', desc: 'Unidades de coordenação dentro das secretarias', icon: '📋' },
    4: { nome: 'Departamento', desc: 'Divisões especializadas dentro das coordenações', icon: '📂' },
    5: { nome: 'Equipe Técnica', desc: 'Núcleos operacionais especializados', icon: '👥' }
}

function UnidadeForm({ unidade, unidades, niveis, usuarios = [], onSave, onCancel }) {
    const [formData, setFormData] = useState({
        nome: '',
        sigla: '',
        descricao: '',
        nivel: 2, // Default: Secretaria
        pai_id: null,
        responsavel_id: null,
        email: '',
        telefone: '',
        endereco: ''
    })
    const [loading, setLoading] = useState(false)
    const [error, setError] = useState(null)
    const [vinculos, setVinculos] = useState([])
    const [loadingVinculos, setLoadingVinculos] = useState(false)
    const [novoVinculo, setNovoVinculo] = useState({
        user_id: '',
        role: 'analista',
        cargo: ''
    })

    useEffect(() => {
        if (unidade) {
            setFormData({
                nome: unidade.nome || '',
                sigla: unidade.sigla || '',
                descricao: unidade.descricao || '',
                nivel: unidade.nivel || 2,
                pai_id: unidade.pai_id || null,
                responsavel_id: unidade.responsavel_id || unidade.responsavel?.id || null,
                email: unidade.email || '',
                telefone: unidade.telefone || '',
                endereco: unidade.endereco || ''
            })
        }
    }, [unidade])

    useEffect(() => {
        if (!unidade?.id) {
            setVinculos([])
            return
        }
        loadVinculos(unidade.id)
    }, [unidade?.id])

    async function loadVinculos(unidadeId) {
        try {
            setLoadingVinculos(true)
            const data = await getVinculos({ unidade_id: unidadeId })
            setVinculos(data.data || [])
        } catch (err) {
            console.error('Erro ao carregar vinculos:', err)
            setVinculos([])
        } finally {
            setLoadingVinculos(false)
        }
    }

    // Filtrar unidades possíveis como pai baseado no nível selecionado
    const getUnidadesPaiOptions = () => {
        const nivelSelecionado = parseInt(formData.nivel)
        
        // Prefeitura (nível 1) não tem pai
        if (nivelSelecionado === 1) return []
        
        // Permite vincular a qualquer nível superior
        return unidades
            .filter(u => u.nivel < nivelSelecionado)
            .sort((a, b) => {
                if (a.nivel !== b.nivel) return a.nivel - b.nivel
                return a.nome.localeCompare(b.nome)
            })
    }

    const handleChange = (e) => {
        const { name, value } = e.target
        setFormData(prev => ({
            ...prev,
            [name]: name === 'nivel' || name === 'pai_id' || name === 'responsavel_id'
                ? (value === '' ? null : parseInt(value)) 
                : value
        }))
    }

    const handleSubmit = async (e) => {
        e.preventDefault()
        setLoading(true)
        setError(null)

        try {
            if (unidade) {
                await updateUnidade(unidade.id, formData)
            } else {
                await createUnidade(formData)
            }
            onSave()
        } catch (err) {
            console.error('Erro ao salvar:', err)
            setError(err.message || 'Erro ao salvar unidade')
        } finally {
            setLoading(false)
        }
    }

    async function handleAddVinculo(e) {
        e.preventDefault()
        if (!unidade?.id) {
            setError('Salve a unidade antes de vincular usuarios.')
            return
        }
        if (!novoVinculo.user_id) {
            setError('Selecione um usuario para vincular.')
            return
        }
        try {
            setLoadingVinculos(true)
            await createVinculo({
                user_id: parseInt(novoVinculo.user_id, 10),
                unidade_id: unidade.id,
                role: novoVinculo.role,
                cargo: novoVinculo.cargo || null,
                is_principal: 0
            })
            setNovoVinculo({ user_id: '', role: 'analista', cargo: '' })
            await loadVinculos(unidade.id)
        } catch (err) {
            setError(err.message || 'Erro ao vincular usuario')
        } finally {
            setLoadingVinculos(false)
        }
    }

    async function handleRemoveVinculo(vinculoId) {
        if (!confirm('Remover vinculo deste usuario?')) return
        try {
            setLoadingVinculos(true)
            await deleteVinculo(vinculoId)
            await loadVinculos(unidade.id)
        } catch (err) {
            setError(err.message || 'Erro ao remover vinculo')
        } finally {
            setLoadingVinculos(false)
        }
    }

    const paiOptions = getUnidadesPaiOptions()
    const nivelAtual = NIVEL_INFO[formData.nivel]

    return (
        <div
            style={styles.overlay}
            onMouseDown={(e) => {
                if (e.target === e.currentTarget) {
                    onCancel()
                }
            }}
        >
            <div style={styles.modal} onClick={e => e.stopPropagation()}>
                <div style={styles.header}>
                    <h2 style={styles.title}>
                        {unidade ? '✏️ Editar' : '➕ Nova'} Unidade
                    </h2>
                    <button style={styles.closeBtn} onClick={onCancel}>×</button>
                </div>

                <form onSubmit={handleSubmit}>
                    <div style={styles.body}>
                        {error && (
                            <div style={{ 
                                padding: 12, 
                                background: '#fee2e2', 
                                color: '#dc2626',
                                borderRadius: 8,
                                marginBottom: 16
                            }}>
                                {error}
                            </div>
                        )}

                        {/* Seleção de Nível */}
                        <div style={styles.formGroup}>
                            <label style={styles.label}>
                                Nível Hierárquico <span style={styles.required}>*</span>
                            </label>
                            <div>
                                {[1, 2, 3, 4, 5].map(nivel => {
                                    const info = NIVEL_INFO[nivel]
                                    const isSelected = formData.nivel === nivel
                                    return (
                                        <div
                                            key={nivel}
                                            style={{
                                                ...styles.nivelCard,
                                                ...(isSelected ? styles.nivelCardSelected : {})
                                            }}
                                            onClick={() => setFormData(prev => ({ 
                                                ...prev, 
                                                nivel: nivel,
                                                pai_id: null // Reset pai ao mudar nível
                                            }))}
                                        >
                                            <div style={{ display: 'flex', alignItems: 'center', marginBottom: 4 }}>
                                                <span style={styles.nivelIndicator(nivel)}></span>
                                                <strong>{info.icon} {info.nome}</strong>
                                                {isSelected && <span style={{ marginLeft: 'auto', color: '#3b82f6' }}>✓</span>}
                                            </div>
                                            <div style={{ fontSize: 13, color: '#6b7280', paddingLeft: 20 }}>
                                                {info.desc}
                                            </div>
                                        </div>
                                    )
                                })}
                            </div>
                        </div>

                        {/* Seleção de Unidade Pai */}
                        {formData.nivel > 1 && (
                            <div style={styles.formGroup}>
                                <label style={styles.label}>
                                    Unidade Superior <span style={styles.required}>*</span>
                                </label>
                                <select
                                    name="pai_id"
                                    style={styles.select}
                                    value={formData.pai_id || ''}
                                    onChange={handleChange}
                                    required
                                >
                                    <option value="">Selecione a unidade superior...</option>
                                    {paiOptions.map(pai => (
                                        <option key={pai.id} value={pai.id}>
                                            {pai.sigla} - {pai.nome}
                                        </option>
                                    ))}
                                </select>
                                {paiOptions.length === 0 && (
                                    <p style={{ ...styles.hint, color: '#ef4444' }}>
                                        ⚠️ Não há unidades superiores cadastradas.
                                        <br />
                                        Crie uma {NIVEL_INFO[formData.nivel - 1]?.nome} ou outra unidade acima primeiro.
                                    </p>
                                )}
                                <p style={styles.hint}>
                                    A {nivelAtual?.nome} será vinculada à unidade selecionada acima
                                </p>
                            </div>
                        )}

                        {/* Nome e Sigla */}
                        <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: 16 }}>
                            <div style={styles.formGroup}>
                                <label style={styles.label}>
                                    Nome da Unidade <span style={styles.required}>*</span>
                                </label>
                                <input
                                    type="text"
                                    name="nome"
                                    style={styles.input}
                                    value={formData.nome}
                                    onChange={handleChange}
                                    placeholder="Ex: Secretaria de Educação"
                                    required
                                />
                            </div>
                            <div style={styles.formGroup}>
                                <label style={styles.label}>
                                    Sigla <span style={styles.required}>*</span>
                                </label>
                                <input
                                    type="text"
                                    name="sigla"
                                    style={styles.input}
                                    value={formData.sigla}
                                    onChange={handleChange}
                                    placeholder="Ex: SEMED"
                                    required
                                />
                            </div>
                        </div>

                        {/* Descrição */}
                        <div style={styles.formGroup}>
                            <label style={styles.label}>Descrição</label>
                            <textarea
                                name="descricao"
                                style={styles.textarea}
                                value={formData.descricao}
                                onChange={handleChange}
                                placeholder="Descrição das atribuições da unidade..."
                            />
                        </div>

                        {/* Responsavel */}
                        <div style={styles.formGroup}>
                            <label style={styles.label}>Responsavel</label>
                            <select
                                name="responsavel_id"
                                style={styles.select}
                                value={formData.responsavel_id || ''}
                                onChange={handleChange}
                            >
                                <option value="">Sem responsavel definido</option>
                                {usuarios.map(user => (
                                    <option key={user.id} value={user.id}>
                                        {user.full_name || user.username}
                                    </option>
                                ))}
                            </select>
                        </div>

                        {/* Contato */}

                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
                            <div style={styles.formGroup}>
                                <label style={styles.label}>E-mail</label>
                                <input
                                    type="email"
                                    name="email"
                                    style={styles.input}
                                    value={formData.email}
                                    onChange={handleChange}
                                    placeholder="email@prefeitura.gov.br"
                                />
                            </div>
                            <div style={styles.formGroup}>
                                <label style={styles.label}>Telefone</label>
                                <input
                                    type="text"
                                    name="telefone"
                                    style={styles.input}
                                    value={formData.telefone}
                                    onChange={handleChange}
                                    placeholder="(00) 0000-0000"
                                />
                            </div>
                        </div>

                        {/* Endereço */}
                        <div style={styles.formGroup}>
                            <label style={styles.label}>Endereço</label>
                            <input
                                type="text"
                                name="endereco"
                                style={styles.input}
                                value={formData.endereco}
                                onChange={handleChange}
                                placeholder="Endereço físico da unidade"
                            />
                        </div>
                    </div>

                    <div style={styles.footer}>
                        <button type="button" style={styles.btnCancel} onClick={onCancel}>
                            Cancelar
                        </button>
                        <button 
                            type="submit" 
                            style={styles.btnSave}
                            disabled={loading || (formData.nivel > 1 && !formData.pai_id)}
                        >
                            {loading ? 'Salvando...' : '💾 Salvar Unidade'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    )
}

export default UnidadeForm




