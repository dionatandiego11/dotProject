import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { getSetupTemplates, setupPrefeitura, login } from '../services/api'
import './SetupWizard.css'

const STEP_LABELS = [
    'Prefeitura',
    'Níveis',
    'Secretarias',
    'Departamentos',
    'Usuário',
]

const ESTADOS_BR = [
    'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG',
    'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'
]

function SetupWizard() {
    const navigate = useNavigate()
    const [step, setStep] = useState(0)
    const [loading, setLoading] = useState(false)
    const [error, setError] = useState(null)
    const [success, setSuccess] = useState(false)
    const [templates, setTemplates] = useState(null)

    // Data for all steps
    const [prefeitura, setPrefeitura] = useState({ nome: '', cnpj: '', estado: '', cidade: '' })
    const [selectedTemplate, setSelectedTemplate] = useState('padrao')
    const [niveis, setNiveis] = useState([])
    const [secretarias, setSecretarias] = useState([{ nome: '', sigla: '' }])
    const [departamentos, setDepartamentos] = useState({})
    const [expandedSecs, setExpandedSecs] = useState({})
    const [usuario, setUsuario] = useState({ nome: '', sobrenome: '', email: '', username: '', password: '' })
    const [conviteInput, setConviteInput] = useState('')
    const [convites, setConvites] = useState([])

    // Load templates on mount
    useEffect(() => {
        getSetupTemplates()
            .then(res => {
                const data = res?.data || res
                setTemplates(data)
                if (data?.padrao?.niveis) {
                    setNiveis(data.padrao.niveis)
                }
            })
            .catch(() => {
                // Fallback templates
                const fallback = [
                    { ordem: 1, nome: 'Prefeitura', titulo_responsavel: 'Prefeito(a)', cor: '#1e3a8a' },
                    { ordem: 2, nome: 'Secretaria', titulo_responsavel: 'Secretário(a)', cor: '#2563eb' },
                    { ordem: 3, nome: 'Coordenação', titulo_responsavel: 'Coordenador(a)', cor: '#3b82f6' },
                    { ordem: 4, nome: 'Departamento', titulo_responsavel: 'Chefe de Depto.', cor: '#60a5fa' },
                    { ordem: 5, nome: 'Setor', titulo_responsavel: 'Responsável', cor: '#93c5fd' },
                ]
                setNiveis(fallback)
            })
    }, [])

    // When template changes, update niveis
    useEffect(() => {
        if (templates && templates[selectedTemplate]) {
            setNiveis(templates[selectedTemplate].niveis || [])
        }
    }, [selectedTemplate, templates])

    // --- Step navigation ---
    function canAdvance() {
        switch (step) {
            case 0: return prefeitura.nome.trim() !== ''
            case 1: return niveis.length >= 2
            case 2: return true // secretarias optional
            case 3: return true // departamentos optional
            case 4: return usuario.nome.trim() !== '' && usuario.username.trim() !== '' && usuario.password.trim() !== '' && usuario.email.trim() !== ''
            default: return false
        }
    }

    function nextStep() {
        if (step < STEP_LABELS.length - 1) {
            setError(null)
            setStep(step + 1)
        }
    }

    function prevStep() {
        if (step > 0) {
            setError(null)
            setStep(step - 1)
        }
    }

    // --- Secretarias management ---
    function addSecretaria() {
        setSecretarias([...secretarias, { nome: '', sigla: '' }])
    }

    function removeSecretaria(index) {
        const name = secretarias[index].nome
        const updated = secretarias.filter((_, i) => i !== index)
        setSecretarias(updated)
        // Remove associated departamentos
        if (name) {
            const newDepts = { ...departamentos }
            delete newDepts[name]
            setDepartamentos(newDepts)
        }
    }

    function updateSecretaria(index, field, value) {
        const oldName = secretarias[index].nome
        const updated = [...secretarias]
        updated[index] = { ...updated[index], [field]: value }
        setSecretarias(updated)
        // Rename departamentos key if name changed
        if (field === 'nome' && oldName && oldName !== value && departamentos[oldName]) {
            const newDepts = { ...departamentos }
            newDepts[value] = newDepts[oldName]
            delete newDepts[oldName]
            setDepartamentos(newDepts)
        }
    }

    // --- Departamentos management ---
    function addDepartamento(secNome) {
        setDepartamentos(prev => ({
            ...prev,
            [secNome]: [...(prev[secNome] || []), { nome: '', sigla: '' }]
        }))
    }

    function removeDepartamento(secNome, index) {
        setDepartamentos(prev => ({
            ...prev,
            [secNome]: prev[secNome].filter((_, i) => i !== index)
        }))
    }

    function updateDepartamento(secNome, index, field, value) {
        setDepartamentos(prev => ({
            ...prev,
            [secNome]: prev[secNome].map((d, i) => i === index ? { ...d, [field]: value } : d)
        }))
    }

    function toggleSecExpanded(secNome) {
        setExpandedSecs(prev => ({ ...prev, [secNome]: !prev[secNome] }))
    }

    // --- Convites ---
    function addConvite() {
        const email = conviteInput.trim()
        if (email && email.includes('@') && !convites.includes(email)) {
            setConvites([...convites, email])
            setConviteInput('')
        }
    }

    function removeConvite(email) {
        setConvites(convites.filter(c => c !== email))
    }

    function handleConviteKeyDown(e) {
        if (e.key === 'Enter') {
            e.preventDefault()
            addConvite()
        }
    }

    // --- Submit ---
    async function handleSubmit() {
        setError(null)
        setLoading(true)

        const filteredSecretarias = secretarias.filter(s => s.nome.trim())
        const filteredDepartamentos = {}
        for (const [secNome, depts] of Object.entries(departamentos)) {
            const filtered = depts.filter(d => d.nome.trim())
            if (filtered.length > 0) {
                filteredDepartamentos[secNome] = filtered
            }
        }

        const payload = {
            prefeitura: {
                ...prefeitura,
                sigla: prefeitura.nome.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 5),
            },
            niveis,
            secretarias: filteredSecretarias,
            departamentos: filteredDepartamentos,
            usuario,
            convites,
        }

        try {
            await setupPrefeitura(payload)
            // Auto-login with the created user
            try {
                await login(usuario.username, usuario.password)
            } catch (_) { /* ignore login error, user can login manually */ }
            setSuccess(true)
        } catch (err) {
            setError(err.message || 'Erro ao configurar a prefeitura')
        } finally {
            setLoading(false)
        }
    }

    // --- Success screen ---
    if (success) {
        return (
            <div className="setup-wizard">
                <div className="setup-wizard__container">
                    <div className="setup-wizard__body">
                        <div className="setup-wizard__success">
                            <div className="setup-wizard__success-icon">🎉</div>
                            <h2>Prefeitura configurada!</h2>
                            <p>
                                A estrutura organizacional de <strong>{prefeitura.nome}</strong> foi
                                criada com sucesso. Você já pode começar a usar o sistema.
                            </p>
                            <button
                                className="setup-wizard__btn setup-wizard__btn--primary"
                                onClick={() => navigate('/')}
                            >
                                Acessar o Sistema →
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        )
    }

    // --- Render steps ---
    function renderStep() {
        switch (step) {
            case 0: return renderStepPrefeitura()
            case 1: return renderStepNiveis()
            case 2: return renderStepSecretarias()
            case 3: return renderStepDepartamentos()
            case 4: return renderStepUsuario()
            default: return null
        }
    }

    function renderStepPrefeitura() {
        return (
            <div className="setup-wizard__step-content" key="step-0">
                <h3 className="setup-wizard__step-title">📍 Dados da Prefeitura</h3>
                <p className="setup-wizard__step-subtitle">Informe os dados básicos do seu município</p>

                <div className="setup-wizard__field">
                    <label className="setup-wizard__label">Nome da Prefeitura *</label>
                    <input
                        className="setup-wizard__input"
                        placeholder="Ex: Prefeitura Municipal de São Paulo"
                        value={prefeitura.nome}
                        onChange={e => setPrefeitura({ ...prefeitura, nome: e.target.value })}
                        autoFocus
                    />
                </div>

                <div className="setup-wizard__row">
                    <div className="setup-wizard__field">
                        <label className="setup-wizard__label">CNPJ</label>
                        <input
                            className="setup-wizard__input"
                            placeholder="00.000.000/0001-00"
                            value={prefeitura.cnpj}
                            onChange={e => setPrefeitura({ ...prefeitura, cnpj: e.target.value })}
                        />
                    </div>
                    <div className="setup-wizard__field">
                        <label className="setup-wizard__label">Estado</label>
                        <select
                            className="setup-wizard__input"
                            value={prefeitura.estado}
                            onChange={e => setPrefeitura({ ...prefeitura, estado: e.target.value })}
                        >
                            <option value="">Selecione...</option>
                            {ESTADOS_BR.map(uf => <option key={uf} value={uf}>{uf}</option>)}
                        </select>
                    </div>
                </div>

                <div className="setup-wizard__field">
                    <label className="setup-wizard__label">Cidade</label>
                    <input
                        className="setup-wizard__input"
                        placeholder="Nome da cidade"
                        value={prefeitura.cidade}
                        onChange={e => setPrefeitura({ ...prefeitura, cidade: e.target.value })}
                    />
                </div>
            </div>
        )
    }

    function renderStepNiveis() {
        const templateList = templates ? Object.entries(templates) : []

        return (
            <div className="setup-wizard__step-content" key="step-1">
                <h3 className="setup-wizard__step-title">🏛️ Níveis Hierárquicos</h3>
                <p className="setup-wizard__step-subtitle">
                    Escolha um modelo de estrutura organizacional ou personalize
                </p>

                <div className="setup-wizard__templates">
                    {templateList.map(([key, tpl]) => (
                        <div
                            key={key}
                            className={`setup-wizard__template-card ${selectedTemplate === key ? 'selected' : ''}`}
                            onClick={() => setSelectedTemplate(key)}
                        >
                            <h4>{selectedTemplate === key ? '✅ ' : ''}{tpl.nome}</h4>
                            <p>{tpl.descricao}</p>
                            <div className="setup-wizard__niveis-preview">
                                {(tpl.niveis || []).map((n, i) => (
                                    <span
                                        key={i}
                                        className="setup-wizard__nivel-tag"
                                        style={{ background: n.cor || '#2563eb' }}
                                    >
                                        {n.ordem}. {n.nome}
                                    </span>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>

                {niveis.length > 0 && (
                    <div style={{ marginTop: '1rem' }}>
                        <label className="setup-wizard__label" style={{ marginBottom: '0.5rem' }}>
                            Níveis selecionados ({niveis.length}):
                        </label>
                        {niveis.map((n, i) => (
                            <div key={i} style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.25rem' }}>
                                <span
                                    className="setup-wizard__nivel-tag"
                                    style={{ background: n.cor || '#2563eb' }}
                                >
                                    {n.ordem}
                                </span>
                                <span style={{ fontSize: '0.875rem', color: '#374151' }}>
                                    {n.nome} — <em style={{ color: '#6b7280' }}>{n.titulo_responsavel}</em>
                                </span>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        )
    }

    function renderStepSecretarias() {
        return (
            <div className="setup-wizard__step-content" key="step-2">
                <h3 className="setup-wizard__step-title">🏢 Secretarias</h3>
                <p className="setup-wizard__step-subtitle">
                    Cadastre as secretarias da prefeitura (você pode adicionar mais depois)
                </p>

                <div className="setup-wizard__list">
                    {secretarias.map((sec, i) => (
                        <div className="setup-wizard__list-item" key={i}>
                            <input
                                className="setup-wizard__input"
                                placeholder="Nome da secretaria"
                                value={sec.nome}
                                onChange={e => updateSecretaria(i, 'nome', e.target.value)}
                                style={{ flex: 2 }}
                            />
                            <input
                                className="setup-wizard__input"
                                placeholder="Sigla"
                                value={sec.sigla}
                                onChange={e => updateSecretaria(i, 'sigla', e.target.value)}
                                style={{ flex: 0, width: 80 }}
                            />
                            {secretarias.length > 1 && (
                                <button
                                    className="setup-wizard__btn-icon"
                                    onClick={() => removeSecretaria(i)}
                                    title="Remover"
                                >
                                    ✕
                                </button>
                            )}
                        </div>
                    ))}
                </div>

                <button className="setup-wizard__btn-add" onClick={addSecretaria} style={{ marginTop: '0.75rem' }}>
                    + Adicionar Secretaria
                </button>
            </div>
        )
    }

    function renderStepDepartamentos() {
        const validSecretarias = secretarias.filter(s => s.nome.trim())

        if (validSecretarias.length === 0) {
            return (
                <div className="setup-wizard__step-content" key="step-3">
                    <h3 className="setup-wizard__step-title">📂 Coordenações / Departamentos</h3>
                    <p className="setup-wizard__step-subtitle">
                        Nenhuma secretaria cadastrada. Você pode pular este passo ou voltar e adicionar secretarias.
                    </p>
                </div>
            )
        }

        return (
            <div className="setup-wizard__step-content" key="step-3">
                <h3 className="setup-wizard__step-title">📂 Coordenações / Departamentos</h3>
                <p className="setup-wizard__step-subtitle">
                    Adicione subdivisões dentro de cada secretaria (opcional)
                </p>

                {validSecretarias.map((sec) => {
                    const secName = sec.nome
                    const depts = departamentos[secName] || []
                    const isExpanded = expandedSecs[secName] !== false // default expanded

                    return (
                        <div className="setup-wizard__tree-section" key={secName}>
                            <div
                                className="setup-wizard__tree-header"
                                onClick={() => toggleSecExpanded(secName)}
                            >
                                <span>{isExpanded ? '▾' : '▸'}</span>
                                <span>🏢 {secName}</span>
                                {sec.sigla && <span style={{ color: '#9ca3af', fontWeight: 400 }}>({sec.sigla})</span>}
                                <span style={{ marginLeft: 'auto', color: '#9ca3af', fontSize: '0.75rem' }}>
                                    {depts.length} departamento{depts.length !== 1 ? 's' : ''}
                                </span>
                            </div>

                            {isExpanded && (
                                <div className="setup-wizard__tree-body">
                                    <div className="setup-wizard__list">
                                        {depts.map((dept, i) => (
                                            <div className="setup-wizard__list-item" key={i}>
                                                <input
                                                    className="setup-wizard__input"
                                                    placeholder="Nome do departamento"
                                                    value={dept.nome}
                                                    onChange={e => updateDepartamento(secName, i, 'nome', e.target.value)}
                                                    style={{ flex: 2 }}
                                                />
                                                <input
                                                    className="setup-wizard__input"
                                                    placeholder="Sigla"
                                                    value={dept.sigla}
                                                    onChange={e => updateDepartamento(secName, i, 'sigla', e.target.value)}
                                                    style={{ flex: 0, width: 80 }}
                                                />
                                                <button
                                                    className="setup-wizard__btn-icon"
                                                    onClick={() => removeDepartamento(secName, i)}
                                                    title="Remover"
                                                >
                                                    ✕
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                    <button
                                        className="setup-wizard__btn-add"
                                        onClick={() => addDepartamento(secName)}
                                        style={{ marginTop: '0.5rem' }}
                                    >
                                        + Adicionar Departamento
                                    </button>
                                </div>
                            )}
                        </div>
                    )
                })}
            </div>
        )
    }

    function renderStepUsuario() {
        return (
            <div className="setup-wizard__step-content" key="step-4">
                <h3 className="setup-wizard__step-title">👤 Primeiro Usuário (Prefeito)</h3>
                <p className="setup-wizard__step-subtitle">
                    Crie a conta de administrador principal do sistema
                </p>

                <div className="setup-wizard__row">
                    <div className="setup-wizard__field">
                        <label className="setup-wizard__label">Nome *</label>
                        <input
                            className="setup-wizard__input"
                            placeholder="Nome"
                            value={usuario.nome}
                            onChange={e => setUsuario({ ...usuario, nome: e.target.value })}
                        />
                    </div>
                    <div className="setup-wizard__field">
                        <label className="setup-wizard__label">Sobrenome</label>
                        <input
                            className="setup-wizard__input"
                            placeholder="Sobrenome"
                            value={usuario.sobrenome}
                            onChange={e => setUsuario({ ...usuario, sobrenome: e.target.value })}
                        />
                    </div>
                </div>

                <div className="setup-wizard__field">
                    <label className="setup-wizard__label">E-mail *</label>
                    <input
                        className="setup-wizard__input"
                        type="email"
                        placeholder="prefeito@prefeitura.gov.br"
                        value={usuario.email}
                        onChange={e => setUsuario({ ...usuario, email: e.target.value })}
                    />
                </div>

                <div className="setup-wizard__row">
                    <div className="setup-wizard__field">
                        <label className="setup-wizard__label">Usuário de acesso *</label>
                        <input
                            className="setup-wizard__input"
                            placeholder="prefeito"
                            value={usuario.username}
                            onChange={e => setUsuario({ ...usuario, username: e.target.value })}
                        />
                    </div>
                    <div className="setup-wizard__field">
                        <label className="setup-wizard__label">Senha *</label>
                        <input
                            className="setup-wizard__input"
                            type="password"
                            placeholder="Mínimo 6 caracteres"
                            value={usuario.password}
                            onChange={e => setUsuario({ ...usuario, password: e.target.value })}
                        />
                    </div>
                </div>

                <hr style={{ border: 'none', borderTop: '1px solid #e5e7eb', margin: '1.5rem 0' }} />

                <div className="setup-wizard__field">
                    <label className="setup-wizard__label">📧 Convidar outros usuários (opcional)</label>
                    <p style={{ fontSize: '0.75rem', color: '#9ca3af', margin: '0.25rem 0 0.5rem' }}>
                        Adicione emails para convidar posteriormente
                    </p>
                    <div style={{ display: 'flex', gap: '0.5rem' }}>
                        <input
                            className="setup-wizard__input"
                            placeholder="email@exemplo.com"
                            value={conviteInput}
                            onChange={e => setConviteInput(e.target.value)}
                            onKeyDown={handleConviteKeyDown}
                            style={{ flex: 1 }}
                        />
                        <button
                            className="setup-wizard__btn setup-wizard__btn--secondary"
                            onClick={addConvite}
                            type="button"
                        >
                            Adicionar
                        </button>
                    </div>
                    {convites.length > 0 && (
                        <div className="setup-wizard__convites-list">
                            {convites.map(email => (
                                <span key={email} className="setup-wizard__convite-tag">
                                    {email}
                                    <button onClick={() => removeConvite(email)}>×</button>
                                </span>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        )
    }

    // --- Main render ---
    const isLastStep = step === STEP_LABELS.length - 1

    return (
        <div className="setup-wizard">
            <div className="setup-wizard__container">
                {/* Header */}
                <div className="setup-wizard__header">
                    <h1>⚙️ Configuração Inicial</h1>
                    <p>Configure sua prefeitura em poucos passos</p>
                </div>

                {/* Progress Bar */}
                <div className="setup-wizard__progress">
                    {STEP_LABELS.map((label, i) => {
                        let state = 'pending'
                        if (i < step) state = 'completed'
                        if (i === step) state = 'active'

                        return (
                            <div key={i} className={`setup-wizard__step-indicator ${state}`}>
                                <div className={`setup-wizard__step-dot ${state}`}>
                                    {state === 'completed' ? '✓' : i + 1}
                                </div>
                                <span className={`setup-wizard__step-label ${state}`}>{label}</span>
                            </div>
                        )
                    })}
                </div>

                {/* Body */}
                <div className="setup-wizard__body">
                    {error && (
                        <div className="setup-wizard__error">⚠️ {error}</div>
                    )}
                    {renderStep()}
                </div>

                {/* Footer */}
                <div className="setup-wizard__footer">
                    <div>
                        {step > 0 && (
                            <button
                                className="setup-wizard__btn setup-wizard__btn--secondary"
                                onClick={prevStep}
                            >
                                ← Voltar
                            </button>
                        )}
                    </div>
                    <div>
                        {isLastStep ? (
                            <button
                                className="setup-wizard__btn setup-wizard__btn--success"
                                onClick={handleSubmit}
                                disabled={!canAdvance() || loading}
                            >
                                {loading ? '⏳ Configurando...' : '🚀 Finalizar Configuração'}
                            </button>
                        ) : (
                            <button
                                className="setup-wizard__btn setup-wizard__btn--primary"
                                onClick={nextStep}
                                disabled={!canAdvance()}
                            >
                                Avançar →
                            </button>
                        )}
                    </div>
                </div>
            </div>
        </div>
    )
}

export default SetupWizard
