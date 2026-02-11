import { useEffect, useState, useMemo } from 'react'
import { getUsuarios, createUsuario, updateUsuario, deleteUsuario, getUnidades, getVinculos, createVinculo } from '../../services/api'
import Modal from '../../components/ui/Modal'
import Button from '../../components/ui/Button'
import Input from '../../components/ui/Input'
import Badge from '../../components/ui/Badge'
import DataTable from '../../components/ui/DataTable'
import FormField from '../../components/ui/FormField'
import Skeleton from '../../components/ui/Skeleton'
import { useToast } from '../../contexts/ToastContext'
import { useValidation } from '../../hooks/useValidation'

const ALLOWED_VINCULO_ROLES = ['coordenador', 'gestor', 'analista', 'tecnico']

function normalizeVinculoRole(role) {
    const value = String(role || '').trim().toLowerCase()
    return ALLOWED_VINCULO_ROLES.includes(value) ? value : ''
}

function isPrincipalVinculo(vinculo) {
    return vinculo?.vinculo_is_principal === true
        || vinculo?.vinculo_is_principal === 1
        || vinculo?.vinculo_is_principal === '1'
}

function UsuariosAdmin() {
    const toast = useToast()
    const validation = useValidation()
    const [usuarios, setUsuarios] = useState([])
    const [loading, setLoading] = useState(true)
    const [error, setError] = useState(null)
    const [search, setSearch] = useState('')
    const [includeInactive, setIncludeInactive] = useState(false)
    const [isModalOpen, setIsModalOpen] = useState(false)
    const [isEditModalOpen, setIsEditModalOpen] = useState(false)
    const [editingUser, setEditingUser] = useState(null)
    const [saving, setSaving] = useState(false)
    const [unidades, setUnidades] = useState([])
    const [loadingUnidades, setLoadingUnidades] = useState(false)
    const [loadingVinculo, setLoadingVinculo] = useState(false)
    const [formData, setFormData] = useState({
        user_username: '',
        user_password: '',
        contact_first_name: '',
        contact_last_name: '',
        contact_email: '',
        contact_phone: '',
        user_status: '0',
        unidade_id: '',
        vinculo_role: ''
    })

    useEffect(() => {
        loadUsuarios()
    }, [includeInactive])

    useEffect(() => {
        loadUnidades()
    }, [])

    
    async function loadUnidades() {
        try {
            setLoadingUnidades(true)
            const data = await getUnidades()
            setUnidades(data.data || [])
        } catch (err) {
            console.error('Erro ao carregar unidades:', err)
        } finally {
            setLoadingUnidades(false)
        }
    }

    async function loadVinculoForUser(userId) {
        try {
            setLoadingVinculo(true)
            const data = await getVinculos({ user_id: userId })
            const vinculos = data.data || []
            if (vinculos.length > 0) {
                const principal = vinculos.find(isPrincipalVinculo) || vinculos[0]
                setFormData(prev => ({
                    ...prev,
                    unidade_id: principal.vinculo_unidade_id ? String(principal.vinculo_unidade_id) : '',
                    vinculo_role: normalizeVinculoRole(principal.vinculo_role)
                }))
            } else {
                setFormData(prev => ({
                    ...prev,
                    unidade_id: '',
                    vinculo_role: ''
                }))
            }
        } catch (err) {
            console.error('Erro ao carregar vinculo:', err)
        } finally {
            setLoadingVinculo(false)
        }
    }

    async function loadUsuarios(params = {}) {
        try {
            setLoading(true)
            setError(null)
            const data = await getUsuarios({
                ...params,
                include_inactive: includeInactive ? 1 : 0
            })
            setUsuarios(data.data || [])
        } catch (err) {
            setError(err.message || 'Erro ao carregar usuários')
        } finally {
            setLoading(false)
        }
    }

    function handleSearch(e) {
        e.preventDefault()
        loadUsuarios({ q: search })
    }

    function openCreate() {
        setEditingUser(null)
        setFormData({
            user_username: '',
            user_password: '',
            contact_first_name: '',
            contact_last_name: '',
            contact_email: '',
            contact_phone: '',
            user_status: '0',
            unidade_id: '',
            vinculo_role: ''
        })
        validation.clearErrors()
        setIsModalOpen(true)
    }

    function openEdit(user) {
        setEditingUser(user)
        setFormData({
            user_username: user.username || '',
            user_password: '',
            contact_first_name: user.first_name || '',
            contact_last_name: user.last_name || '',
            contact_email: user.email || '',
            contact_phone: user.phone || '',
            user_status: user.status != null ? String(user.status) : '0',
            unidade_id: '',
            vinculo_role: ''
        })
        validation.clearErrors()
        setIsEditModalOpen(true)
        loadVinculoForUser(user.id)
    }

    async function handleCreate(e) {
        e.preventDefault()
        validation.clearErrors()
        const normalizedRole = normalizeVinculoRole(formData.vinculo_role)
        const hasSelectedUnidade = !!formData.unidade_id
        const hasRoleWithoutUnit = !!normalizedRole && !hasSelectedUnidade
        const hasUnitWithoutRole = hasSelectedUnidade && !normalizedRole

        const isValid = validation.validateFields({
            user_username: () => validation.validateRequired(formData.user_username, 'Login'),
            user_password: () => validation.validateRequired(formData.user_password, 'Senha'),
            contact_email: () => validation.validateEmail(formData.contact_email, 'Email'),
            unidade_id: () => hasRoleWithoutUnit
                ? 'Selecione a unidade para definir papel'
                : null,
            vinculo_role: () => hasUnitWithoutRole
                ? 'Papel na unidade e obrigatorio'
                : null
        })

        if (!isValid) return

        try {
            setSaving(true)
            const created = await createUsuario({
                user_username: formData.user_username,
                user_password: formData.user_password,
                contact_first_name: formData.contact_first_name || null,
                contact_last_name: formData.contact_last_name || null,
                contact_email: formData.contact_email || null,
                contact_phone: formData.contact_phone || null,
                user_status: parseInt(formData.user_status, 10)
            })
            const createdUserId = created?.data?.id
            if (createdUserId && hasSelectedUnidade && normalizedRole) {
                await createVinculo({
                    user_id: createdUserId,
                    unidade_id: parseInt(formData.unidade_id, 10),
                    role: normalizedRole,
                    is_principal: 1
                })
            }
            toast.success('Usuário criado com sucesso!')
            setIsModalOpen(false)
            loadUsuarios()
        } catch (err) {
            toast.error('Erro ao criar usuário: ' + err.message)
        } finally {
            setSaving(false)
        }
    }

    async function handleUpdate(e) {
        e.preventDefault()
        if (!editingUser) return
        validation.clearErrors()
        const normalizedRole = normalizeVinculoRole(formData.vinculo_role)
        const hasSelectedUnidade = !!formData.unidade_id
        const hasRoleWithoutUnit = !!normalizedRole && !hasSelectedUnidade
        const hasUnitWithoutRole = hasSelectedUnidade && !normalizedRole

        const isValid = validation.validateFields({
            user_username: () => validation.validateRequired(formData.user_username, 'Login'),
            contact_email: () => validation.validateEmail(formData.contact_email, 'Email'),
            unidade_id: () => hasRoleWithoutUnit
                ? 'Selecione a unidade para definir papel'
                : null,
            vinculo_role: () => hasUnitWithoutRole
                ? 'Papel na unidade e obrigatorio'
                : null
        })

        if (!isValid) return

        try {
            setSaving(true)
            await updateUsuario(editingUser.id, {
                user_username: formData.user_username,
                user_password: formData.user_password || null,
                contact_first_name: formData.contact_first_name || null,
                contact_last_name: formData.contact_last_name || null,
                contact_email: formData.contact_email || null,
                contact_phone: formData.contact_phone || null,
                user_status: parseInt(formData.user_status, 10)
            })
            if (hasSelectedUnidade && normalizedRole) {
                await createVinculo({
                    user_id: editingUser.id,
                    unidade_id: parseInt(formData.unidade_id, 10),
                    role: normalizedRole,
                    is_principal: 1
                })
            }
            toast.success('Usuário atualizado com sucesso!')
            setIsEditModalOpen(false)
            setEditingUser(null)
            loadUsuarios()
        } catch (err) {
            toast.error('Erro ao atualizar usuário: ' + err.message)
        } finally {
            setSaving(false)
        }
    }

    async function handleDelete(user) {
        if (!confirm(`Deseja desativar o usuário "${user.username}"?`)) return
        try {
            await deleteUsuario(user.id)
            toast.success('Usuário desativado.')
            loadUsuarios()
        } catch (err) {
            toast.error('Erro ao desativar usuário: ' + err.message)
        }
    }

    const userColumns = [
        {
            key: 'name',
            label: 'Nome',
            accessor: (user) => `${user.first_name || ''} ${user.last_name || ''}`.trim() || '-'
        },
        {
            key: 'username',
            label: 'Login',
            accessor: 'username'
        },
        {
            key: 'email',
            label: 'Email',
            accessor: (user) => user.email || '-'
        },
        {
            key: 'status',
            label: 'Status',
            accessor: (user) => user.status,
            render: (user) => (
                <Badge variant={user.status === 0 ? 'success' : 'warning'} withDot>
                    {user.status === 0 ? 'Ativo' : 'Inativo'}
                </Badge>
            )
        },
        {
            key: 'actions',
            label: 'Acoes',
            sortable: false,
            render: (user) => (
                <>
                    <button
                        className="btn btn-secondary"
                        style={{ padding: 'var(--spacing-1) var(--spacing-2)' }}
                        onClick={() => openEdit(user)}
                    >
                        Editar
                    </button>
                    <button
                        className="btn btn-secondary"
                        style={{ padding: 'var(--spacing-1) var(--spacing-2)', marginLeft: 8 }}
                        onClick={() => handleDelete(user)}
                    >
                        Excluir
                    </button>
                </>
            )
        }
    ]

    return (
        <div style={{ padding: 24 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20 }}>
                <div>
                    <h1 style={{ margin: 0, fontSize: 24 }}>Usuários</h1>
                    <p style={{ margin: '4px 0 0', color: '#6b7280' }}>Cadastro completo para login e responsáveis</p>
                </div>
                <Button variant="primary" onClick={openCreate}>+ Novo Usuário</Button>
            </div>

            <div style={{ display: 'flex', gap: 12, marginBottom: 16 }}>
                <form onSubmit={handleSearch} style={{ display: 'flex', gap: 8, flex: 1 }}>
                    <FormField
                        as="input"
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Buscar por nome, email ou login..."
                        style={{ flex: 1 }}
                    />
                    <Button variant="secondary" type="submit">Buscar</Button>
                </form>
                <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 13, color: '#374151' }}>
                    <input
                        type="checkbox"
                        checked={includeInactive}
                        onChange={(e) => setIncludeInactive(e.target.checked)}
                    />
                    Mostrar inativos
                </label>
            </div>

            {error && (
                <div style={{ padding: 12, background: '#fee2e2', color: '#dc2626', borderRadius: 8, marginBottom: 12 }}>
                    {error}
                </div>
            )}

            <div className="card">
                <div className="card-body" style={{ padding: 0 }}>
                    {loading ? (
                        <div style={{ padding: 24 }}>
                            <Skeleton lines={6} />
                        </div>
                    ) : usuarios.length === 0 ? (
                        <div style={{ padding: 24, textAlign: 'center', color: '#6b7280' }}>Nenhum usuário encontrado</div>
                    ) : (
                        <div style={{ padding: 16 }}>
                            <DataTable
                                columns={userColumns}
                                rows={usuarios}
                                rowKey="id"
                                searchable={false}
                                defaultPageSize={10}
                                pageSizeOptions={[10, 20, 50]}
                                emptyText="Nenhum usuário encontrado"
                            />
                        </div>
                    )}
                </div>
            </div>

            <Modal
                isOpen={isModalOpen}
                onClose={() => setIsModalOpen(false)}
                title="Novo Usuário"
                closeOnOverlay={false}
                closeOnEscape={false}
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setIsModalOpen(false)}>Cancelar</Button>
                        <Button variant="primary" onClick={handleCreate} disabled={saving}>
                            {saving ? 'Salvando...' : 'Criar Usuário'}
                        </Button>
                    </>
                }
            >
                <UserForm
                    formData={formData}
                    setFormData={setFormData}
                    validation={validation}
                    requirePassword
                    onSubmit={handleCreate}
                    unidades={unidades}
                    loadingUnidades={loadingUnidades}
                    loadingVinculo={loadingVinculo}
                />
            </Modal>

            <Modal
                isOpen={isEditModalOpen}
                onClose={() => setIsEditModalOpen(false)}
                title="Editar Usuário"
                closeOnOverlay={false}
                closeOnEscape={false}
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setIsEditModalOpen(false)}>Cancelar</Button>
                        <Button variant="primary" onClick={handleUpdate} disabled={saving}>
                            {saving ? 'Salvando...' : 'Salvar Alterações'}
                        </Button>
                    </>
                }
            >
                <UserForm
                    formData={formData}
                    setFormData={setFormData}
                    validation={validation}
                    requirePassword={false}
                    onSubmit={handleUpdate}
                    unidades={unidades}
                    loadingUnidades={loadingUnidades}
                    loadingVinculo={loadingVinculo}
                />
            </Modal>
        </div>
    )
}

function UserForm({ formData, setFormData, validation, requirePassword, onSubmit, unidades, loadingUnidades, loadingVinculo }) {
    const unidadesAgrupadas = useMemo(() => {
        if (!unidades || unidades.length === 0) return []

        const byId = new Map()
        unidades.forEach((u) => {
            byId.set(u.id, u)
        })

        const getSecretariaId = (unidade) => {
            if (!unidade) return null
            if (unidade.nivel_label === 'Secretaria' || unidade.nivel === 2) return unidade.id
            let current = unidade
            let safety = 0
            while (current && current.pai_id && safety < 10) {
                const parent = byId.get(current.pai_id)
                if (!parent) break
                if (parent.nivel_label === 'Secretaria' || parent.nivel === 2) return parent.id
                current = parent
                safety += 1
            }
            return null
        }

        const grupos = new Map()
        unidades.forEach((u) => {
            const secretariaId = getSecretariaId(u)
            const key = secretariaId ? `sec-${secretariaId}` : 'sem-secretaria'
            if (!grupos.has(key)) {
                grupos.set(key, {
                    id: secretariaId,
                    nome: secretariaId ? (byId.get(secretariaId)?.nome || 'Secretaria') : 'Sem Secretaria',
                    itens: []
                })
            }
            grupos.get(key).itens.push(u)
        })

        const ordered = Array.from(grupos.values()).sort((a, b) => {
            if (a.id === null && b.id !== null) return 1
            if (a.id !== null && b.id === null) return -1
            return a.nome.localeCompare(b.nome)
        })

        ordered.forEach((g) => {
            g.itens.sort((a, b) => a.nome.localeCompare(b.nome))
        })

        return ordered
    }, [unidades])

    return (
        <form onSubmit={onSubmit}>
            <div style={{ marginBottom: 16 }}>
                <label style={{ display: 'block', marginBottom: 6, fontWeight: 500 }}>Login *</label>
                <Input
                    value={formData.user_username}
                    onChange={(e) => {
                        setFormData({ ...formData, user_username: e.target.value })
                        validation.clearFieldError('user_username')
                    }}
                    placeholder="usuario.login"
                    error={validation.errors.user_username}
                    fullWidth
                />
                {validation.errors.user_username && (
                    <span style={{ color: 'var(--color-danger-500)', fontSize: '0.75rem' }}>
                        {validation.errors.user_username}
                    </span>
                )}
            </div>

            <div style={{ marginBottom: 16 }}>
                <label style={{ display: 'block', marginBottom: 6, fontWeight: 500 }}>
                    {requirePassword ? 'Senha inicial *' : 'Nova senha (opcional)'}
                </label>
                <Input
                    type="password"
                    value={formData.user_password}
                    onChange={(e) => {
                        setFormData({ ...formData, user_password: e.target.value })
                        validation.clearFieldError('user_password')
                    }}
                    placeholder={requirePassword ? 'Defina uma senha' : 'Deixe em branco para manter'}
                    required={requirePassword}
                    error={validation.errors.user_password}
                    fullWidth
                />
                {validation.errors.user_password && (
                    <span style={{ color: 'var(--color-danger-500)', fontSize: '0.75rem' }}>
                        {validation.errors.user_password}
                    </span>
                )}
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
                <div style={{ marginBottom: 16 }}>
                    <label style={{ display: 'block', marginBottom: 6, fontWeight: 500 }}>Nome</label>
                    <Input
                        value={formData.contact_first_name}
                        onChange={(e) => setFormData({ ...formData, contact_first_name: e.target.value })}
                        placeholder="Nome"
                    />
                </div>
                <div style={{ marginBottom: 16 }}>
                    <label style={{ display: 'block', marginBottom: 6, fontWeight: 500 }}>Sobrenome</label>
                    <Input
                        value={formData.contact_last_name}
                        onChange={(e) => setFormData({ ...formData, contact_last_name: e.target.value })}
                        placeholder="Sobrenome"
                    />
                </div>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
                <div style={{ marginBottom: 16 }}>
                    <label style={{ display: 'block', marginBottom: 6, fontWeight: 500 }}>Email</label>
                    <Input
                        type="email"
                        value={formData.contact_email}
                        onChange={(e) => {
                            setFormData({ ...formData, contact_email: e.target.value })
                            validation.clearFieldError('contact_email')
                        }}
                        placeholder="nome@prefeitura.gov.br"
                    />
                    {validation.errors.contact_email && (
                        <span style={{ color: 'var(--color-danger-500)', fontSize: '0.75rem' }}>
                            {validation.errors.contact_email}
                        </span>
                    )}
                </div>
                <div style={{ marginBottom: 16 }}>
                    <label style={{ display: 'block', marginBottom: 6, fontWeight: 500 }}>Telefone</label>
                    <Input
                        value={formData.contact_phone}
                        onChange={(e) => setFormData({ ...formData, contact_phone: e.target.value })}
                        placeholder="(00) 00000-0000"
                    />
                </div>
            </div>

            <div style={{ marginBottom: 8 }}>
                <FormField
                    as="select"
                    label="Status"
                    value={formData.user_status}
                    onChange={(e) => setFormData({ ...formData, user_status: e.target.value })}
                    options={[
                        { value: '0', label: 'Ativo' },
                        { value: '1', label: 'Inativo' }
                    ]}
                />
            </div>

            <div style={{ marginTop: 12 }}>
                <label style={{ display: 'block', marginBottom: 6, fontWeight: 500 }}>
                    Departamento / Unidade
                </label>
                <select
                    value={formData.unidade_id}
                    onChange={(e) => {
                        const unidadeId = e.target.value
                        setFormData({
                            ...formData,
                            unidade_id: unidadeId,
                            vinculo_role: unidadeId ? formData.vinculo_role : ''
                        })
                        validation.clearFieldError('unidade_id')
                    }}
                    style={{
                        width: '100%',
                        padding: '10px 12px',
                        border: '1px solid #d1d5db',
                        borderRadius: 8
                    }}
                    disabled={loadingUnidades || loadingVinculo}
                >
                    <option value="">{loadingUnidades ? 'Carregando unidades...' : 'Selecione a unidade'}</option>
                    {unidadesAgrupadas.map((grupo) => (
                        <optgroup key={grupo.id ?? 'sem-secretaria'} label={grupo.nome}>
                            {grupo.itens.map((unidade) => (
                                <option key={unidade.id} value={unidade.id}>
                                    {unidade.nome}
                                </option>
                            ))}
                        </optgroup>
                    ))}
                </select>
                {validation.errors.unidade_id && (
                    <span style={{ color: 'var(--color-danger-500)', fontSize: '0.75rem' }}>
                        {validation.errors.unidade_id}
                    </span>
                )}
            </div>

            <div style={{ marginTop: 12 }}>
                <FormField
                    as="select"
                    label="Papel na unidade"
                    value={formData.vinculo_role}
                    onChange={(e) => {
                        setFormData({ ...formData, vinculo_role: normalizeVinculoRole(e.target.value) })
                        validation.clearFieldError('vinculo_role')
                    }}
                    disabled={!formData.unidade_id || loadingUnidades || loadingVinculo}
                    options={[
                        { value: '', label: 'Selecione o papel' },
                        { value: 'coordenador', label: 'Coordenador' },
                        { value: 'gestor', label: 'Gestor' },
                        { value: 'analista', label: 'Analista' },
                        { value: 'tecnico', label: 'Tecnico' }
                    ]}
                    error={validation.errors.vinculo_role}
                />
            </div>
        </form>
    )
}

export default UsuariosAdmin
