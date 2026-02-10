import { useState, useEffect } from 'react'
import {
    getUnidades,
    getNiveis,
    getOrganograma,
    getUsuarios,
    deleteUnidade,
    getAdminOnboardingReadiness
} from '../../services/api'
import UnidadeForm from './UnidadeForm'

const styles = {
    container: { padding: 24 },
    header: {
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: 32
    },
    title: { margin: 0, fontSize: 28, fontWeight: 700, color: '#111827' },
    subtitle: { margin: '8px 0 0 0', color: '#6b7280', fontSize: 16 },
    onboardingCard: {
        marginBottom: 20,
        background: '#f8fafc',
        border: '1px solid #e2e8f0',
        borderRadius: 12,
        padding: 16
    },
    onboardingHeader: {
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        gap: 12,
        flexWrap: 'wrap'
    },
    onboardingTitle: { margin: 0, fontSize: 16, fontWeight: 700, color: '#0f172a' },
    onboardingMeta: { margin: '6px 0 0 0', fontSize: 13, color: '#475569' },
    onboardingBadge: (ready) => ({
        padding: '6px 10px',
        borderRadius: 999,
        fontSize: 12,
        fontWeight: 700,
        color: ready ? '#065f46' : '#92400e',
        background: ready ? '#d1fae5' : '#fef3c7'
    }),
    onboardingList: {
        margin: '12px 0 0 0',
        padding: 0,
        listStyle: 'none',
        display: 'grid',
        gap: 8
    },
    onboardingItem: {
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        gap: 10,
        fontSize: 13
    },
    onboardingDone: { color: '#065f46', fontWeight: 600 },
    onboardingPending: { color: '#92400e', fontWeight: 600 },
    treeWrapper: {
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        padding: 40,
        background: 'white',
        borderRadius: 16,
        boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1)',
        minHeight: 600,
        overflowX: 'auto'
    },
    nodeContainer: {
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        position: 'relative',
    },
    nodeCard: (levelColor) => ({
        width: 280,
        background: 'white',
        borderRadius: 8,
        border: `1px solid #e5e7eb`,
        borderTop: `4px solid ${levelColor}`,
        boxShadow: '0 2px 4px rgba(0,0,0,0.05)',
        padding: 16,
        textAlign: 'center',
        position: 'relative',
        zIndex: 2,
        transition: 'transform 0.2s, box-shadow 0.2s',
        marginBottom: 20,
        cursor: 'default'
    }),
    nodeTitle: {
        margin: '0 0 4px 0',
        fontSize: 16,
        fontWeight: 600,
        color: '#1f2937'
    },
    nodeRole: {
        fontSize: 12,
        fontWeight: 600,
        textTransform: 'uppercase',
        color: '#9ca3af',
        letterSpacing: 0.5,
        marginBottom: 8
    },
    managerSection: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        gap: 8,
        background: '#f9fafb',
        padding: '8px 12px',
        borderRadius: 20,
        marginTop: 8
    },
    managerAvatar: {
        width: 24,
        height: 24,
        borderRadius: '50%',
        background: '#e5e7eb',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        fontSize: 12,
        color: '#6b7280'
    },
    managerName: {
        fontSize: 13,
        fontWeight: 500,
        color: '#374151'
    },
    btnIcon: {
        background: 'transparent',
        border: 'none',
        cursor: 'pointer',
        fontSize: 16,
        padding: 4,
        opacity: 0.6,
        transition: 'opacity 0.2s'
    },
    btnNew: {
        padding: '10px 20px',
        background: '#2563eb',
        color: 'white',
        border: 'none',
        borderRadius: 8,
        fontWeight: 600,
        cursor: 'pointer'
    },
}

const LEVEL_COLORS = {
    1: '#ef4444',
    2: '#3b82f6',
    3: '#f59e0b',
    4: '#8b5cf6',
    5: '#10b981'
}

function UnidadesTree({ mode = 'gestao' }) {
    const [tree, setTree] = useState([])
    const [unidadesFlat, setUnidadesFlat] = useState([])
    const [niveis, setNiveis] = useState([])
    const [usuarios, setUsuarios] = useState([])
    const [loading, setLoading] = useState(true)
    const [error, setError] = useState(null)
    const [showForm, setShowForm] = useState(false)
    const [editingUnidade, setEditingUnidade] = useState(null)
    const [expanded, setExpanded] = useState({})
    const [search, setSearch] = useState('')
    const [onboarding, setOnboarding] = useState(null)

    useEffect(() => {
        loadData()
    }, [])

    async function loadData() {
        try {
            setLoading(true)
            setError(null)
            const requests = [
                getUnidades(),
                getNiveis(),
                getUsuarios()
            ]
            if (mode !== 'gestao') {
                requests.unshift(getOrganograma())
            }

            const responses = await Promise.all(requests)
            const offset = mode !== 'gestao' ? 1 : 0
            const organogramaData = mode !== 'gestao' ? responses[0] : null
            const unidadesData = responses[0 + offset]
            const niveisData = responses[1 + offset]
            const usuariosData = responses[2 + offset]

            if (organogramaData) {
                const treeData = organogramaData?.data ?? []
                const treeArray = Array.isArray(treeData) ? treeData : Object.values(treeData || {})
                setTree(treeArray)
            } else {
                setTree([])
            }
            setUnidadesFlat(unidadesData.data || [])
            setNiveis(niveisData.data || [])
            setUsuarios(usuariosData.data || [])

            if (mode === 'gestao') {
                try {
                    const readiness = await getAdminOnboardingReadiness()
                    setOnboarding(readiness?.data || null)
                } catch (readinessError) {
                    console.warn('Falha ao carregar onboarding:', readinessError)
                    setOnboarding(null)
                }
            } else {
                setOnboarding(null)
            }
        } catch (err) {
            console.error('Erro:', err)
            setError(err?.message || 'Falha ao carregar organograma')
        } finally {
            setLoading(false)
        }
    }

    async function refreshUsuarios() {
        try {
            const data = await getUsuarios()
            setUsuarios(data.data || [])
        } catch (err) {
            console.error('Erro ao atualizar usuarios:', err)
        }
    }

    function TreeNode({ node }) {
        const levelColor = LEVEL_COLORS[node.nivel] || '#9ca3af'
        const nivelNome = node.nivel_label || niveis.find(n => n.id === node.nivel)?.nome || 'Unidade'
        const responsavelNome = node.responsavel?.nome || 'Sem Gestor'
        const responsavelEmail = node.responsavel?.email
        const responsavelTelefone = node.responsavel?.telefone
        const hasChildren = node.filhas && node.filhas.length > 0
        const isExpanded = !!expanded[node.id]

        return (
            <div style={styles.nodeContainer}>
                <div style={styles.nodeCard(levelColor)}>
                    <div style={styles.nodeRole}>{nivelNome}</div>
                    <div style={styles.nodeTitle}>
                        {node.sigla && <span style={{ color: levelColor, marginRight: 6 }}>{node.sigla}</span>}
                        {node.nome}
                    </div>

                    <div style={styles.managerSection}>
                        <div style={styles.managerAvatar}>
                            {responsavelNome.charAt(0).toUpperCase()}
                        </div>
                        <div style={styles.managerName}>{responsavelNome}</div>
                        {mode === 'gestao' && (
                            <button
                                style={{ ...styles.btnIcon, marginLeft: 'auto' }}
                                title="Editar unidade"
                                onClick={() => handleEdit(node)}
                            >
                                ??
                            </button>
                        )}
                        {mode !== 'gestao' && hasChildren && (
                            <button
                                style={{ ...styles.btnIcon, marginLeft: 'auto' }}
                                title={isExpanded ? 'Recolher' : 'Expandir'}
                                onClick={() => setExpanded(prev => ({ ...prev, [node.id]: !isExpanded }))}
                            >
                                {isExpanded ? '-' : '+'}
                            </button>
                        )}
                    </div>

                    {(responsavelEmail || responsavelTelefone) && (
                        <div style={{ marginTop: 8, fontSize: 12, color: '#6b7280' }}>
                            {responsavelEmail && <div>{responsavelEmail}</div>}
                            {responsavelTelefone && <div>{responsavelTelefone}</div>}
                        </div>
                    )}

                    {mode === 'gestao' && (
                        <div style={{ marginTop: 8, borderTop: '1px solid #f3f4f6', paddingTop: 8, display: 'flex', gap: 12, justifyContent: 'center' }}>
                            <button
                                style={{ background: 'none', border: 'none', color: '#ef4444', fontSize: 12, cursor: 'pointer' }}
                                onClick={() => handleDelete(node)}
                            >
                                Excluir
                            </button>
                        </div>
                    )}
                </div>

                {hasChildren && (mode === 'gestao' || isExpanded) && (
                    <>
                        <div style={{ width: 2, height: 20, background: '#e5e7eb' }} />
                        <div style={{
                            display: 'flex',
                            gap: 20,
                            position: 'relative',
                            paddingTop: 20,
                            borderTop: node.filhas.length > 1 ? '2px solid #e5e7eb' : 'none'
                        }}>
                            {node.filhas.map(child => (
                                <TreeNode key={child.id} node={child} />
                            ))}
                        </div>
                    </>
                )}
            </div>
        )
    }

    function handleEdit(unidade) {
        refreshUsuarios()
        setEditingUnidade(unidade)
        setShowForm(true)
    }

    async function handleDelete(unidade) {
        if (!confirm(`Tem certeza que deseja dissolver a unidade "${unidade.nome}"?`)) return
        try {
            await deleteUnidade(unidade.id)
            loadData()
        } catch (e) {
            const message = e.message || ''
            if (message.toLowerCase().includes('nao encontrada')) {
                loadData()
                alert('Unidade já foi removida.')
                return
            }
            alert('Erro: ' + message)
        }
    }

    if (loading) return <div style={{ padding: 40, textAlign: 'center' }}>Carregando organograma...</div>
    if (error) return <div style={{ padding: 40, textAlign: 'center', color: '#dc2626' }}>{error}</div>

    const normalizedSearch = search.trim().toLowerCase()
    const unidadesById = new Map(unidadesFlat.map(u => [u.id, u]))
    const childrenByParent = unidadesFlat.reduce((acc, unidade) => {
        const key = unidade.pai_id ?? 'root'
        if (!acc[key]) acc[key] = []
        acc[key].push(unidade)
        return acc
    }, {})

    const resolveSecretaria = (unidade) => {
        let current = unidade
        while (current && current.pai_id) {
            const parent = unidadesById.get(current.pai_id)
            if (!parent) break
            if (parent.nivel === 2) return parent
            current = parent
        }
        return unidade.nivel === 2 ? unidade : null
    }

    const matchesSearch = (unidade) => {
        if (!normalizedSearch) return true
        const parent = unidade.pai_id ? unidadesById.get(unidade.pai_id) : null
        const values = [
            unidade.nome,
            unidade.sigla,
            unidade.nivel_label,
            parent?.nome,
        ].filter(Boolean).join(' ').toLowerCase()
        return values.includes(normalizedSearch)
    }

    const visibleIds = new Set()
    if (normalizedSearch) {
        unidadesFlat.forEach((unidade) => {
            if (!matchesSearch(unidade)) return
            let current = unidade
            while (current) {
                visibleIds.add(current.id)
                if (!current.pai_id) break
                current = unidadesById.get(current.pai_id)
            }
        })
    }

    const unidadesFiltradas = normalizedSearch
        ? unidadesFlat.filter((u) => visibleIds.has(u.id))
        : unidadesFlat

    const grouped = unidadesFiltradas.reduce((acc, unidade) => {
        const secretaria = resolveSecretaria(unidade)
        const key = secretaria ? secretaria.id : 'sem-secretaria'
        if (!acc[key]) {
            acc[key] = {
                secretaria: secretaria || { id: 'sem-secretaria', nome: 'Sem Secretaria' },
                itens: [],
            }
        }
        acc[key].itens.push(unidade)
        return acc
    }, {})

    const groupedList = Object.values(grouped).sort((a, b) => {
        const aIsSem = a.secretaria.nome === 'Sem Secretaria'
        const bIsSem = b.secretaria.nome === 'Sem Secretaria'
        if (aIsSem && !bIsSem) return -1
        if (!aIsSem && bIsSem) return 1
        return a.secretaria.nome.localeCompare(b.secretaria.nome)
    })

    const renderBranch = (parentId, level, allowedIds) => {
        const children = (childrenByParent[parentId ?? 'root'] || [])
            .filter((u) => allowedIds.has(u.id))
            .sort((a, b) => a.nome.localeCompare(b.nome))

        if (children.length === 0) return null

        return children.map((unidade) => {
            const nivelNome = unidade.nivel_label || niveis.find(n => n.id === unidade.nivel)?.nome || 'Unidade'
            const pai = unidadesById.get(unidade.pai_id)
            return (
                <div key={unidade.id}>
                    <div style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 12,
                        padding: '10px 12px',
                        borderTop: '1px solid #f3f4f6',
                        paddingLeft: 12 + (level * 18),
                        background: level > 0 ? '#fcfcfd' : 'transparent'
                    }}>
                        <div style={{ minWidth: 120, fontSize: 12, color: '#6b7280' }}>{nivelNome}</div>
                        <div style={{ flex: 1, fontWeight: 600 }}>{unidade.nome}</div>
                        <div style={{ flex: 1, fontSize: 12, color: '#6b7280' }}>{pai ? pai.nome : 'Raiz'}</div>
                        <button
                            style={{ background: 'none', border: 'none', color: '#2563eb', fontSize: 12, cursor: 'pointer' }}
                            onClick={() => handleEdit(unidade)}
                        >
                            Editar
                        </button>
                        <button
                            style={{ background: 'none', border: 'none', color: '#ef4444', fontSize: 12, cursor: 'pointer' }}
                            onClick={() => handleDelete(unidade)}
                        >
                            Excluir
                        </button>
                    </div>
                    {renderBranch(unidade.id, level + 1, allowedIds)}
                </div>
            )
        })
    }

    const onboardingChecklist = Array.isArray(onboarding?.checklist) ? onboarding.checklist : []
    const onboardingPending = onboardingChecklist.filter((item) => item?.status !== 'done')
    const onboardingProgress = onboarding?.progress || { completed: 0, total: 0, percentage: 0 }
    const onboardingReady = onboardingProgress.total > 0 && onboardingProgress.completed === onboardingProgress.total

    return (
        <div style={styles.container}>
            <div style={styles.header}>
                <div>
                    <h1 style={styles.title}>{mode === 'gestao' ? 'Unidades Organizacionais' : 'Organograma da Prefeitura'}</h1>
                    <p style={styles.subtitle}>
                        {mode === 'gestao'
                            ? 'Gerencie unidades e responsaveis da estrutura'
                            : 'Estrutura hierarquica com responsaveis e contatos'}
                    </p>
                </div>
                {mode === 'gestao' && (
                    <div style={{ display: 'flex', gap: 12, alignItems: 'center' }}>
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Filtrar por nome, sigla ou pai..."
                            style={{ padding: '8px 10px', borderRadius: 8, border: '1px solid #d1d5db', minWidth: 260 }}
                        />
                        <button
                            style={styles.btnNew}
                            onClick={() => {
                                refreshUsuarios()
                                setEditingUnidade(null)
                                setShowForm(true)
                            }}
                        >
                            + Nova Unidade
                        </button>
                    </div>
                )}
            </div>

            {mode === 'gestao' && onboarding && (
                <div style={styles.onboardingCard}>
                    <div style={styles.onboardingHeader}>
                        <div>
                            <h2 style={styles.onboardingTitle}>Prontidao de implantacao</h2>
                            <p style={styles.onboardingMeta}>
                                {onboardingProgress.completed}/{onboardingProgress.total} etapas concluidas
                            </p>
                        </div>
                        <div style={styles.onboardingBadge(onboardingReady)}>
                            {onboardingProgress.percentage}% concluido
                        </div>
                    </div>

                    <ul style={styles.onboardingList}>
                        {onboardingChecklist.map((item) => {
                            const statusDone = item?.status === 'done'
                            return (
                                <li key={item.key} style={styles.onboardingItem}>
                                    <span>{item.label}</span>
                                    <span style={statusDone ? styles.onboardingDone : styles.onboardingPending}>
                                        {statusDone ? 'Concluido' : 'Pendente'}
                                    </span>
                                </li>
                            )
                        })}
                    </ul>

                    {onboardingPending.length > 0 && (
                        <p style={{ margin: '10px 0 0 0', fontSize: 12, color: '#92400e' }}>
                            Proximo foco: {onboardingPending[0]?.hint || 'Concluir itens pendentes do checklist.'}
                        </p>
                    )}
                </div>
            )}

            {mode === 'gestao' ? (
                <div style={styles.treeWrapper}>
                    {unidadesFiltradas.length > 0 ? (
                        <div style={{ width: '100%', maxWidth: 1000 }}>
                            {groupedList.map(group => {
                                const groupedIds = new Set(group.itens.map((u) => u.id))
                                const isSemSecretaria = group.secretaria.id === 'sem-secretaria'
                                const headerUnidade = !isSemSecretaria
                                    ? unidadesById.get(group.secretaria.id)
                                    : null
                                return (
                                    <div key={group.secretaria.id} style={{ marginBottom: 20, border: '1px solid #e5e7eb', borderRadius: 12, overflow: 'hidden' }}>
                                        <div style={{ background: '#f9fafb', padding: '10px 14px', fontWeight: 700, color: '#111827' }}>
                                            {group.secretaria.nome}
                                            <span style={{ marginLeft: 8, color: '#6b7280', fontWeight: 500, fontSize: 12 }}>
                                                {group.itens.length} unidades
                                            </span>
                                        </div>
                                        {headerUnidade && (
                                            <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '10px 12px', borderTop: '1px solid #f3f4f6' }}>
                                                <div style={{ minWidth: 120, fontSize: 12, color: '#6b7280' }}>{headerUnidade.nivel_label || 'Secretaria'}</div>
                                                <div style={{ flex: 1, fontWeight: 700 }}>{headerUnidade.nome}</div>
                                                <div style={{ flex: 1, fontSize: 12, color: '#6b7280' }}>{headerUnidade.pai_id ? (unidadesById.get(headerUnidade.pai_id)?.nome || 'Raiz') : 'Raiz'}</div>
                                                <button
                                                    style={{ background: 'none', border: 'none', color: '#2563eb', fontSize: 12, cursor: 'pointer' }}
                                                    onClick={() => handleEdit(headerUnidade)}
                                                >
                                                    Editar
                                                </button>
                                                <button
                                                    style={{ background: 'none', border: 'none', color: '#ef4444', fontSize: 12, cursor: 'pointer' }}
                                                    onClick={() => handleDelete(headerUnidade)}
                                                >
                                                    Excluir
                                                </button>
                                            </div>
                                        )}
                                        {renderBranch(isSemSecretaria ? null : group.secretaria.id, 0, groupedIds)}
                                    </div>
                                )
                            })}
                        </div>
                    ) : (
                        <div style={{ textAlign: 'center', marginTop: 100, color: '#9ca3af' }}>
                            <h3 style={{ fontSize: 20 }}>Nenhuma estrutura definida</h3>
                            <p>Comece criando a unidade "Prefeitura".</p>
                        </div>
                    )}
                </div>
            ) : (
                <div style={styles.treeWrapper}>
                    {tree.length > 0 ? (
                        tree.map(rootNode => (
                            <TreeNode key={rootNode.id} node={rootNode} />
                        ))
                    ) : (
                        <div style={{ textAlign: 'center', marginTop: 100, color: '#9ca3af' }}>
                            <h3 style={{ fontSize: 20 }}>Nenhuma estrutura definida</h3>
                            <p>Comece criando a unidade "Prefeitura".</p>
                        </div>
                    )}
                </div>
            )}

            {showForm && (
                <UnidadeForm
                    unidade={editingUnidade}
                    unidades={unidadesFlat}
                    niveis={niveis}
                    usuarios={usuarios}
                    onSave={() => { setShowForm(false); loadData(); }}
                    onCancel={() => setShowForm(false)}
                />
            )}
        </div>
    )
}

export default UnidadesTree
