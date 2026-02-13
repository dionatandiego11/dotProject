import { useState, useEffect, useCallback } from 'react'
import { getMetas, createMeta, updateMeta, deleteMeta, getAcoes } from '../../services/ppa'

const STATUS_SAUDE_BADGE = {
    em_dia: { label: 'Em dia', color: '#22c55e', bg: '#f0fdf4' },
    atencao: { label: 'Atenção', color: '#f59e0b', bg: '#fffbeb' },
    critico: { label: 'Crítico', color: '#ef4444', bg: '#fef2f2' },
    impedido: { label: 'Impedido', color: '#6b7280', bg: '#f3f4f6' },
}

function SaudeBadge({ status }) {
    const cfg = STATUS_SAUDE_BADGE[status] || STATUS_SAUDE_BADGE.em_dia
    return (
        <span style={{
            display: 'inline-block',
            padding: '2px 10px',
            borderRadius: 12,
            fontSize: 12,
            fontWeight: 600,
            color: cfg.color,
            background: cfg.bg,
            border: `1px solid ${cfg.color}22`,
        }}>
            {cfg.label}
        </span>
    )
}

function PercentBar({ value }) {
    const pct = Math.min(100, Math.max(0, value || 0))
    const color = pct >= 80 ? '#22c55e' : pct >= 50 ? '#f59e0b' : '#ef4444'
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
            <div style={{
                flex: 1,
                height: 8,
                borderRadius: 4,
                background: '#e5e7eb',
                overflow: 'hidden',
            }}>
                <div style={{
                    width: `${pct}%`,
                    height: '100%',
                    borderRadius: 4,
                    background: color,
                    transition: 'width 0.3s ease',
                }} />
            </div>
            <span style={{ fontSize: 13, fontWeight: 600, color, minWidth: 40, textAlign: 'right' }}>
                {pct.toFixed(1)}%
            </span>
        </div>
    )
}

const EMPTY_FORM = {
    acao_id: '',
    descricao: '',
    unidade_medida: 'unidades',
    valor_previsto: '',
    valor_realizado: '',
    ano_referencia: new Date().getFullYear(),
    observacao: '',
}

export default function MetasPage() {
    const [metas, setMetas] = useState([])
    const [acoes, setAcoes] = useState([])
    const [loading, setLoading] = useState(true)
    const [error, setError] = useState(null)

    // Filters
    const [filterAcao, setFilterAcao] = useState('')
    const [filterAno, setFilterAno] = useState('')
    const [filterSearch, setFilterSearch] = useState('')

    // Modal
    const [showModal, setShowModal] = useState(false)
    const [editingId, setEditingId] = useState(null)
    const [form, setForm] = useState({ ...EMPTY_FORM })
    const [saving, setSaving] = useState(false)

    const fetchData = useCallback(async () => {
        setLoading(true)
        setError(null)
        try {
            const params = {}
            if (filterAcao) params.acao_id = filterAcao
            if (filterAno) params.ano_referencia = filterAno
            if (filterSearch) params.search = filterSearch

            const [metasRes, acoesRes] = await Promise.all([
                getMetas(params),
                acoes.length === 0 ? getAcoes() : Promise.resolve({ data: acoes }),
            ])

            setMetas(metasRes?.data || [])
            if (acoesRes?.data?.length) setAcoes(acoesRes.data)
        } catch (err) {
            setError(err.message || 'Erro ao carregar metas')
        } finally {
            setLoading(false)
        }
    }, [filterAcao, filterAno, filterSearch])

    useEffect(() => { fetchData() }, [fetchData])

    const openCreateModal = () => {
        setEditingId(null)
        setForm({ ...EMPTY_FORM })
        setShowModal(true)
    }

    const openEditModal = (meta) => {
        setEditingId(meta.id)
        setForm({
            acao_id: meta.acao_id || '',
            descricao: meta.descricao || '',
            unidade_medida: meta.unidade_medida || 'unidades',
            valor_previsto: meta.valor_previsto || '',
            valor_realizado: meta.valor_realizado || '',
            ano_referencia: meta.ano_referencia || new Date().getFullYear(),
            observacao: meta.observacao || '',
        })
        setShowModal(true)
    }

    const handleSave = async () => {
        setSaving(true)
        try {
            if (editingId) {
                await updateMeta(editingId, form)
            } else {
                await createMeta(form)
            }
            setShowModal(false)
            fetchData()
        } catch (err) {
            alert(err.message || 'Erro ao salvar meta')
        } finally {
            setSaving(false)
        }
    }

    const handleDelete = async (id) => {
        if (!confirm('Excluir esta meta?')) return
        try {
            await deleteMeta(id)
            fetchData()
        } catch (err) {
            alert(err.message || 'Erro ao excluir')
        }
    }

    const anos = [...new Set(metas.map(m => m.ano_referencia))].sort((a, b) => b - a)

    // Styles
    const cardStyle = {
        background: '#fff',
        borderRadius: 12,
        boxShadow: '0 1px 3px rgba(0,0,0,0.08)',
        border: '1px solid #e5e7eb',
        overflow: 'hidden',
    }
    const thStyle = {
        padding: '12px 16px',
        textAlign: 'left',
        fontSize: 12,
        fontWeight: 600,
        color: '#6b7280',
        textTransform: 'uppercase',
        letterSpacing: '0.05em',
        borderBottom: '2px solid #f3f4f6',
    }
    const tdStyle = {
        padding: '12px 16px',
        fontSize: 14,
        borderBottom: '1px solid #f3f4f6',
    }

    return (
        <div style={{ padding: '24px 32px', maxWidth: 1200, margin: '0 auto' }}>
            {/* Header */}
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24 }}>
                <div>
                    <h1 style={{ fontSize: 24, fontWeight: 700, color: '#1e293b', margin: 0 }}>
                        📊 Metas & Indicadores
                    </h1>
                    <p style={{ color: '#64748b', fontSize: 14, marginTop: 4 }}>
                        Acompanhamento previsto × realizado para prestação de contas (TCE)
                    </p>
                </div>
                <button
                    onClick={openCreateModal}
                    style={{
                        background: 'linear-gradient(135deg, #6366f1, #8b5cf6)',
                        color: '#fff',
                        border: 'none',
                        borderRadius: 10,
                        padding: '10px 20px',
                        fontWeight: 600,
                        cursor: 'pointer',
                        fontSize: 14,
                    }}
                >
                    + Nova Meta
                </button>
            </div>

            {/* Filters */}
            <div style={{ display: 'flex', gap: 12, marginBottom: 20, flexWrap: 'wrap' }}>
                <select
                    value={filterAcao}
                    onChange={e => setFilterAcao(e.target.value)}
                    style={{ padding: '8px 12px', borderRadius: 8, border: '1px solid #d1d5db', fontSize: 14, minWidth: 200 }}
                >
                    <option value="">Todas as ações</option>
                    {acoes.map(a => (
                        <option key={a.id} value={a.id}>{a.codigo ? `${a.codigo} - ` : ''}{a.nome}</option>
                    ))}
                </select>

                <select
                    value={filterAno}
                    onChange={e => setFilterAno(e.target.value)}
                    style={{ padding: '8px 12px', borderRadius: 8, border: '1px solid #d1d5db', fontSize: 14 }}
                >
                    <option value="">Todos os anos</option>
                    {anos.map(a => (
                        <option key={a} value={a}>{a}</option>
                    ))}
                </select>

                <input
                    type="text"
                    placeholder="Buscar..."
                    value={filterSearch}
                    onChange={e => setFilterSearch(e.target.value)}
                    style={{ padding: '8px 12px', borderRadius: 8, border: '1px solid #d1d5db', fontSize: 14, flex: 1, minWidth: 180 }}
                />
            </div>

            {/* Error */}
            {error && (
                <div style={{ background: '#fef2f2', color: '#dc2626', padding: '12px 16px', borderRadius: 8, marginBottom: 16 }}>
                    {error}
                </div>
            )}

            {/* Loading */}
            {loading && <p style={{ color: '#9ca3af', textAlign: 'center', padding: 40 }}>Carregando metas...</p>}

            {/* Table */}
            {!loading && metas.length === 0 && (
                <div style={{ textAlign: 'center', padding: 60, color: '#9ca3af' }}>
                    <p style={{ fontSize: 16, marginBottom: 8 }}>Nenhuma meta encontrada</p>
                    <p style={{ fontSize: 13 }}>Clique em "+ Nova Meta" para adicionar</p>
                </div>
            )}

            {!loading && metas.length > 0 && (
                <div style={cardStyle}>
                    <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                        <thead>
                            <tr style={{ background: '#fafafa' }}>
                                <th style={thStyle}>Descrição</th>
                                <th style={thStyle}>Ação</th>
                                <th style={thStyle}>Ano</th>
                                <th style={thStyle}>Unidade</th>
                                <th style={thStyle}>Previsto</th>
                                <th style={thStyle}>Realizado</th>
                                <th style={{ ...thStyle, minWidth: 140 }}>% Realizado</th>
                                <th style={thStyle}>Status</th>
                                <th style={{ ...thStyle, textAlign: 'center' }}>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            {metas.map(meta => (
                                <tr key={meta.id} style={{ cursor: 'pointer' }}
                                    onMouseEnter={e => e.currentTarget.style.background = '#f0f9ff'}
                                    onMouseLeave={e => e.currentTarget.style.background = 'transparent'}
                                >
                                    <td style={{ ...tdStyle, fontWeight: 500, maxWidth: 250 }}>{meta.descricao}</td>
                                    <td style={{ ...tdStyle, fontSize: 13, color: '#6b7280' }}>
                                        {meta.acao_codigo ? `${meta.acao_codigo} - ` : ''}{meta.acao_nome || '—'}
                                    </td>
                                    <td style={tdStyle}>{meta.ano_referencia}</td>
                                    <td style={{ ...tdStyle, fontSize: 13 }}>{meta.unidade_medida}</td>
                                    <td style={{ ...tdStyle, fontWeight: 500 }}>
                                        {Number(meta.valor_previsto).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                                    </td>
                                    <td style={{ ...tdStyle, fontWeight: 500 }}>
                                        {Number(meta.valor_realizado).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                                    </td>
                                    <td style={tdStyle}>
                                        <PercentBar value={meta.percent_realizado} />
                                    </td>
                                    <td style={tdStyle}>
                                        {meta.atingida
                                            ? <SaudeBadge status="em_dia" />
                                            : meta.percent_realizado >= 50
                                                ? <SaudeBadge status="atencao" />
                                                : <SaudeBadge status="critico" />
                                        }
                                    </td>
                                    <td style={{ ...tdStyle, textAlign: 'center' }}>
                                        <button
                                            onClick={() => openEditModal(meta)}
                                            title="Editar"
                                            style={{ background: 'none', border: 'none', cursor: 'pointer', fontSize: 16, marginRight: 8 }}
                                        >✏️</button>
                                        <button
                                            onClick={() => handleDelete(meta.id)}
                                            title="Excluir"
                                            style={{ background: 'none', border: 'none', cursor: 'pointer', fontSize: 16 }}
                                        >🗑️</button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {/* Summary */}
            {!loading && metas.length > 0 && (
                <div style={{ display: 'flex', gap: 16, marginTop: 20, flexWrap: 'wrap' }}>
                    <div style={{ ...cardStyle, padding: '16px 20px', flex: 1, minWidth: 180 }}>
                        <div style={{ fontSize: 12, color: '#6b7280', textTransform: 'uppercase', marginBottom: 4 }}>Total Metas</div>
                        <div style={{ fontSize: 24, fontWeight: 700, color: '#1e293b' }}>{metas.length}</div>
                    </div>
                    <div style={{ ...cardStyle, padding: '16px 20px', flex: 1, minWidth: 180 }}>
                        <div style={{ fontSize: 12, color: '#6b7280', textTransform: 'uppercase', marginBottom: 4 }}>Atingidas</div>
                        <div style={{ fontSize: 24, fontWeight: 700, color: '#22c55e' }}>
                            {metas.filter(m => m.atingida).length}
                        </div>
                    </div>
                    <div style={{ ...cardStyle, padding: '16px 20px', flex: 1, minWidth: 180 }}>
                        <div style={{ fontSize: 12, color: '#6b7280', textTransform: 'uppercase', marginBottom: 4 }}>% Médio</div>
                        <div style={{ fontSize: 24, fontWeight: 700, color: '#6366f1' }}>
                            {(metas.reduce((s, m) => s + (Number(m.percent_realizado) || 0), 0) / metas.length).toFixed(1)}%
                        </div>
                    </div>
                </div>
            )}

            {/* Modal */}
            {showModal && (
                <div style={{
                    position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.4)',
                    display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 1000,
                }}>
                    <div style={{
                        background: '#fff', borderRadius: 16, padding: 32,
                        width: '100%', maxWidth: 520, maxHeight: '90vh', overflowY: 'auto',
                        boxShadow: '0 20px 60px rgba(0,0,0,0.2)',
                    }}>
                        <h2 style={{ fontSize: 20, fontWeight: 700, color: '#1e293b', marginBottom: 20 }}>
                            {editingId ? 'Editar Meta' : 'Nova Meta'}
                        </h2>

                        <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                            <label style={{ fontSize: 13, fontWeight: 600, color: '#374151' }}>
                                Ação vinculada *
                                <select
                                    value={form.acao_id}
                                    onChange={e => setForm({ ...form, acao_id: e.target.value })}
                                    style={{ display: 'block', width: '100%', padding: '8px 12px', borderRadius: 8, border: '1px solid #d1d5db', marginTop: 4, fontSize: 14 }}
                                >
                                    <option value="">Selecione...</option>
                                    {acoes.map(a => (
                                        <option key={a.id} value={a.id}>{a.codigo ? `${a.codigo} - ` : ''}{a.nome}</option>
                                    ))}
                                </select>
                            </label>

                            <label style={{ fontSize: 13, fontWeight: 600, color: '#374151' }}>
                                Descrição da meta *
                                <input
                                    type="text"
                                    value={form.descricao}
                                    onChange={e => setForm({ ...form, descricao: e.target.value })}
                                    placeholder="Ex: Pavimentar 5 km de vias urbanas"
                                    style={{ display: 'block', width: '100%', padding: '8px 12px', borderRadius: 8, border: '1px solid #d1d5db', marginTop: 4, fontSize: 14 }}
                                />
                            </label>

                            <div style={{ display: 'flex', gap: 12 }}>
                                <label style={{ fontSize: 13, fontWeight: 600, color: '#374151', flex: 1 }}>
                                    Unidade de medida
                                    <input
                                        type="text"
                                        value={form.unidade_medida}
                                        onChange={e => setForm({ ...form, unidade_medida: e.target.value })}
                                        placeholder="Ex: km, unidades, %"
                                        style={{ display: 'block', width: '100%', padding: '8px 12px', borderRadius: 8, border: '1px solid #d1d5db', marginTop: 4, fontSize: 14 }}
                                    />
                                </label>
                                <label style={{ fontSize: 13, fontWeight: 600, color: '#374151', width: 110 }}>
                                    Ano *
                                    <input
                                        type="number"
                                        value={form.ano_referencia}
                                        onChange={e => setForm({ ...form, ano_referencia: e.target.value })}
                                        style={{ display: 'block', width: '100%', padding: '8px 12px', borderRadius: 8, border: '1px solid #d1d5db', marginTop: 4, fontSize: 14 }}
                                    />
                                </label>
                            </div>

                            <div style={{ display: 'flex', gap: 12 }}>
                                <label style={{ fontSize: 13, fontWeight: 600, color: '#374151', flex: 1 }}>
                                    Valor Previsto
                                    <input
                                        type="number"
                                        step="0.01"
                                        value={form.valor_previsto}
                                        onChange={e => setForm({ ...form, valor_previsto: e.target.value })}
                                        placeholder="0.00"
                                        style={{ display: 'block', width: '100%', padding: '8px 12px', borderRadius: 8, border: '1px solid #d1d5db', marginTop: 4, fontSize: 14 }}
                                    />
                                </label>
                                <label style={{ fontSize: 13, fontWeight: 600, color: '#374151', flex: 1 }}>
                                    Valor Realizado
                                    <input
                                        type="number"
                                        step="0.01"
                                        value={form.valor_realizado}
                                        onChange={e => setForm({ ...form, valor_realizado: e.target.value })}
                                        placeholder="0.00"
                                        style={{ display: 'block', width: '100%', padding: '8px 12px', borderRadius: 8, border: '1px solid #d1d5db', marginTop: 4, fontSize: 14 }}
                                    />
                                </label>
                            </div>

                            <label style={{ fontSize: 13, fontWeight: 600, color: '#374151' }}>
                                Observação
                                <textarea
                                    value={form.observacao}
                                    onChange={e => setForm({ ...form, observacao: e.target.value })}
                                    rows={3}
                                    style={{ display: 'block', width: '100%', padding: '8px 12px', borderRadius: 8, border: '1px solid #d1d5db', marginTop: 4, fontSize: 14, resize: 'vertical' }}
                                />
                            </label>
                        </div>

                        <div style={{ display: 'flex', gap: 12, justifyContent: 'flex-end', marginTop: 24 }}>
                            <button
                                onClick={() => setShowModal(false)}
                                style={{ padding: '10px 20px', borderRadius: 8, border: '1px solid #d1d5db', background: '#fff', cursor: 'pointer', fontWeight: 500 }}
                            >
                                Cancelar
                            </button>
                            <button
                                onClick={handleSave}
                                disabled={saving || !form.descricao || !form.acao_id}
                                style={{
                                    padding: '10px 24px', borderRadius: 8, border: 'none',
                                    background: saving ? '#d1d5db' : 'linear-gradient(135deg, #6366f1, #8b5cf6)',
                                    color: '#fff', cursor: saving ? 'not-allowed' : 'pointer', fontWeight: 600,
                                }}
                            >
                                {saving ? 'Salvando...' : editingId ? 'Atualizar' : 'Criar'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    )
}
