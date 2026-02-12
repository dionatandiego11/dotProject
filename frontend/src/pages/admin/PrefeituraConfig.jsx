import { useState, useEffect } from 'react'
import { useToast } from '../../contexts/ToastContext'

const ESTADOS_BR = [
    'AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MG', 'MS', 'MT',
    'PA', 'PB', 'PE', 'PI', 'PR', 'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO',
]

function PrefeituraConfig() {
    const { showToast } = useToast()
    const [form, setForm] = useState({
        nome: '',
        cnpj: '',
        estado: '',
        cidade: '',
    })
    const [loading, setLoading] = useState(true)
    const [saving, setSaving] = useState(false)
    const [dirty, setDirty] = useState(false)

    useEffect(() => {
        // Try loading current prefeitura config
        const token = localStorage.getItem('dp_token')
        fetch('/api/v1/admin/prefeitura', {
            headers: token ? { 'Authorization': `Bearer ${token}` } : {},
        })
            .then((res) => res.ok ? res.json() : null)
            .then((data) => {
                if (data) {
                    setForm({
                        nome: data.nome || data.tenant_name || '',
                        cnpj: data.cnpj || '',
                        estado: data.estado || '',
                        cidade: data.cidade || '',
                    })
                }
            })
            .catch(() => { })
            .finally(() => setLoading(false))
    }, [])

    function updateField(field, value) {
        setForm((prev) => ({ ...prev, [field]: value }))
        setDirty(true)
    }

    async function handleSave(event) {
        event.preventDefault()
        if (!form.nome.trim()) {
            showToast('Nome da prefeitura é obrigatório', 'error')
            return
        }
        setSaving(true)
        try {
            const token = localStorage.getItem('dp_token')
            const res = await fetch('/api/v1/admin/prefeitura', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
                },
                body: JSON.stringify(form),
            })
            if (!res.ok) throw new Error('Erro ao salvar')
            showToast('Dados da prefeitura atualizados!', 'success')
            setDirty(false)
        } catch (err) {
            showToast(err.message || 'Erro ao salvar dados', 'error')
        } finally {
            setSaving(false)
        }
    }

    if (loading) {
        return (
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', minHeight: 300 }}>
                <div style={{ color: '#64748b' }}>Carregando...</div>
            </div>
        )
    }

    return (
        <div>
            <div style={{ marginBottom: 28 }}>
                <h1 style={{ margin: 0, fontSize: 24, fontWeight: 700, color: '#0f172a' }}>
                    🏛️ Dados da Prefeitura
                </h1>
                <p style={{ margin: '4px 0 0', color: '#64748b', fontSize: 15 }}>
                    Informações básicas do município
                </p>
            </div>

            <form onSubmit={handleSave} style={{
                background: 'white',
                borderRadius: 12,
                border: '1px solid #e2e8f0',
                padding: '28px 32px',
                maxWidth: 640,
            }}>
                {/* Nome */}
                <div style={{ marginBottom: 20 }}>
                    <label style={labelStyle}>Nome da Prefeitura *</label>
                    <input
                        style={inputStyle}
                        placeholder="Ex: Prefeitura Municipal de São Paulo"
                        value={form.nome}
                        onChange={(e) => updateField('nome', e.target.value)}
                        autoFocus
                    />
                </div>

                {/* CNPJ + Estado */}
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16, marginBottom: 20 }}>
                    <div>
                        <label style={labelStyle}>CNPJ</label>
                        <input
                            style={inputStyle}
                            placeholder="00.000.000/0001-00"
                            value={form.cnpj}
                            onChange={(e) => updateField('cnpj', e.target.value)}
                        />
                    </div>
                    <div>
                        <label style={labelStyle}>Estado</label>
                        <select
                            style={inputStyle}
                            value={form.estado}
                            onChange={(e) => updateField('estado', e.target.value)}
                        >
                            <option value="">Selecione...</option>
                            {ESTADOS_BR.map((uf) => (
                                <option key={uf} value={uf}>{uf}</option>
                            ))}
                        </select>
                    </div>
                </div>

                {/* Cidade */}
                <div style={{ marginBottom: 28 }}>
                    <label style={labelStyle}>Cidade</label>
                    <input
                        style={inputStyle}
                        placeholder="Nome da cidade"
                        value={form.cidade}
                        onChange={(e) => updateField('cidade', e.target.value)}
                    />
                </div>

                {/* Actions */}
                <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                    <button
                        type="submit"
                        disabled={saving || !dirty}
                        style={{
                            background: dirty ? '#3b82f6' : '#94a3b8',
                            color: 'white',
                            border: 'none',
                            borderRadius: 8,
                            padding: '10px 28px',
                            fontWeight: 600,
                            cursor: dirty ? 'pointer' : 'not-allowed',
                            fontSize: 14,
                            transition: 'background 0.2s',
                        }}
                    >
                        {saving ? 'Salvando...' : 'Salvar Alterações'}
                    </button>
                    {dirty && (
                        <span style={{ fontSize: 13, color: '#f59e0b' }}>
                            Alterações não salvas
                        </span>
                    )}
                </div>
            </form>
        </div>
    )
}

const labelStyle = {
    display: 'block',
    fontSize: 13,
    fontWeight: 600,
    color: '#334155',
    marginBottom: 6,
}

const inputStyle = {
    width: '100%',
    padding: '10px 14px',
    borderRadius: 8,
    border: '1px solid #cbd5e1',
    fontSize: 14,
    color: '#1e293b',
    outline: 'none',
    boxSizing: 'border-box',
    transition: 'border-color 0.15s',
}

export default PrefeituraConfig
