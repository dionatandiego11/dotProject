import { useState, useEffect } from 'react'
import { getNiveis, deleteNivel } from '../../services/api'
import NivelForm from './NivelForm'

const styles = {
    container: { padding: 20 },
    header: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20 },
    title: { margin: 0, fontSize: 24 },
    btnNew: {
        padding: '10px 20px',
        background: 'linear-gradient(135deg, #3b82f6 0%, #2563eb 100%)',
        color: 'white',
        border: 'none',
        borderRadius: 8,
        cursor: 'pointer',
        fontWeight: 600
    },
    table: { width: '100%', borderCollapse: 'collapse', background: 'white', borderRadius: 8, overflow: 'hidden' },
    th: { padding: 14, textAlign: 'left', background: '#f3f4f6', fontWeight: 600 },
    td: { padding: 14, borderBottom: '1px solid #e5e7eb' },
    badge: { display: 'inline-block', padding: '4px 12px', borderRadius: 20, fontSize: 12, fontWeight: 600 },
    actions: { display: 'flex', gap: 8 },
    btnEdit: { padding: '6px 12px', background: '#f59e0b', color: 'white', border: 'none', borderRadius: 4, cursor: 'pointer' },
    btnDelete: { padding: '6px 12px', background: '#ef4444', color: 'white', border: 'none', borderRadius: 4, cursor: 'pointer' }
}

function NiveisList() {
    const [niveis, setNiveis] = useState([])
    const [loading, setLoading] = useState(true)
    const [showForm, setShowForm] = useState(false)
    const [editingNivel, setEditingNivel] = useState(null)

    useEffect(() => {
        loadNiveis()
    }, [])

    async function loadNiveis() {
        try {
            const data = await getNiveis()
            // apiRequest usually returns the parsed JSON response
            // If API returns { data: [...] }, extract it
            setNiveis(data.data || [])
        } catch (err) {
            console.error('Erro:', err)
        } finally {
            setLoading(false)
        }
    }

    function handleNew() {
        setEditingNivel(null)
        setShowForm(true)
    }

    function handleEdit(nivel) {
        setEditingNivel(nivel)
        setShowForm(true)
    }

    function handleSave() {
        setShowForm(false)
        setEditingNivel(null)
        loadNiveis()
    }

    async function handleDelete(nivel) {
        if (!confirm(`Deseja excluir o nível "${nivel.nome}"?`)) return

        try {
            await deleteNivel(nivel.id)
            loadNiveis()
        } catch (err) {
            console.error('Erro:', err)
            alert('Erro ao excluir nível: ' + err.message)
        }
    }

    if (loading) {
        return <div style={{ padding: 20 }}>Carregando...</div>
    }

    return (
        <div style={styles.container}>
            <div style={styles.header}>
                <h1 style={styles.title}>🏛️ Níveis Hierárquicos</h1>
                <button style={styles.btnNew} onClick={handleNew}>
                    + Novo Nível
                </button>
            </div>

            {niveis.length === 0 ? (
                <div style={{ textAlign: 'center', padding: 40, background: '#f9fafb', borderRadius: 8 }}>
                    <p style={{ color: '#6b7280' }}>Nenhum nível cadastrado</p>
                    <button style={styles.btnNew} onClick={handleNew}>
                        Criar primeiro nível
                    </button>
                </div>
            ) : (
                <table style={styles.table}>
                    <thead>
                        <tr>
                            <th style={styles.th}>Ordem</th>
                            <th style={styles.th}>Nome</th>
                            <th style={styles.th}>Responsável</th>
                            <th style={styles.th}>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        {niveis.sort((a, b) => a.ordem - b.ordem).map((nivel) => (
                            <tr key={nivel.id}>
                                <td style={styles.td}>
                                    <span style={{ ...styles.badge, background: '#dbeafe', color: '#1d4ed8' }}>
                                        {nivel.ordem}
                                    </span>
                                </td>
                                <td style={styles.td}><strong>{nivel.nome}</strong></td>
                                <td style={styles.td}>{nivel.titulo_responsavel || '-'}</td>
                                <td style={styles.td}>
                                    <div style={styles.actions}>
                                        <button style={styles.btnEdit} onClick={() => handleEdit(nivel)}>
                                            ✏️ Editar
                                        </button>
                                        <button style={styles.btnDelete} onClick={() => handleDelete(nivel)}>
                                            🗑️
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}

            {showForm && (
                <NivelForm
                    nivel={editingNivel}
                    onSave={handleSave}
                    onCancel={() => setShowForm(false)}
                />
            )}
        </div>
    )
}

export default NiveisList
