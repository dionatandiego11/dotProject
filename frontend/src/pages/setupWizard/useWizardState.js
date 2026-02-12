import { useEffect, useMemo, useState } from 'react'
import { getSetupTemplates, getAdminOnboardingReadiness, setupPrefeitura } from '../../services/api'

const STEP_LABELS = [
    'Prefeitura',
    'Niveis',
    'Secretarias',
    'Departamentos',
    'Usuario',
]

const ESTADOS_BR = [
    'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG',
    'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'
]

const FALLBACK_NIVEIS = [
    { ordem: 1, nome: 'Prefeitura', titulo_responsavel: 'Prefeito(a)', cor: '#1e3a8a' },
    { ordem: 2, nome: 'Secretaria', titulo_responsavel: 'Secretario(a)', cor: '#2563eb' },
    { ordem: 3, nome: 'Coordenacao', titulo_responsavel: 'Coordenador(a)', cor: '#3b82f6' },
    { ordem: 4, nome: 'Departamento', titulo_responsavel: 'Chefe de Depto.', cor: '#60a5fa' },
    { ordem: 5, nome: 'Setor', titulo_responsavel: 'Responsavel', cor: '#93c5fd' },
]

function buildPrefeituraSigla(nome) {
    return nome
        .trim()
        .split(/\s+/)
        .map((word) => word[0] || '')
        .join('')
        .toUpperCase()
        .slice(0, 5)
}

export default function useWizardState() {
    const [step, setStep] = useState(0)
    const [loading, setLoading] = useState(false)
    const [loadingReadiness, setLoadingReadiness] = useState(true)
    const [error, setError] = useState(null)
    const [success, setSuccess] = useState(false)
    const [templates, setTemplates] = useState(null)
    const [readinessSummary, setReadinessSummary] = useState(null)
    const [readinessError, setReadinessError] = useState('')
    const [allowReconfigure, setAllowReconfigure] = useState(false)
    const [confirmReconfigure, setConfirmReconfigure] = useState(false)

    const [prefeitura, setPrefeitura] = useState({ nome: '', cnpj: '', estado: '', cidade: '' })
    const [selectedTemplate, setSelectedTemplate] = useState('padrao')
    const [niveis, setNiveis] = useState([])
    const [secretarias, setSecretarias] = useState([{ nome: '', sigla: '' }])
    const [departamentos, setDepartamentos] = useState({})
    const [expandedSecs, setExpandedSecs] = useState({})
    const [usuario, setUsuario] = useState({ nome: '', sobrenome: '', email: '', username: '', password: '' })
    const [conviteInput, setConviteInput] = useState('')
    const [convites, setConvites] = useState([])

    useEffect(() => {
        let isActive = true

        async function loadReadiness() {
            try {
                const response = await getAdminOnboardingReadiness()
                if (!isActive) return
                setReadinessSummary(response?.data?.summary || null)
                setReadinessError('')
            } catch (_) {
                if (!isActive) return
                setReadinessError('Nao foi possivel validar o estado atual da estrutura.')
            } finally {
                if (isActive) {
                    setLoadingReadiness(false)
                }
            }
        }

        loadReadiness()

        getSetupTemplates()
            .then((response) => {
                if (!isActive) return
                const data = response?.data || response
                setTemplates(data)
                if (data?.padrao?.niveis) {
                    setNiveis(data.padrao.niveis)
                }
            })
            .catch(() => {
                if (!isActive) return
                setNiveis(FALLBACK_NIVEIS)
            })

        return () => {
            isActive = false
        }
    }, [])

    useEffect(() => {
        if (!templates || !templates[selectedTemplate]) return
        setNiveis(templates[selectedTemplate].niveis || [])
    }, [selectedTemplate, templates])

    const hasExistingStructure = useMemo(() => (
        Number(readinessSummary?.niveis_ativos || 0) > 0 ||
        Number(readinessSummary?.unidades_ativas || 0) > 0 ||
        Number(readinessSummary?.vinculos_ativos || 0) > 0
    ), [readinessSummary])

    const requiresExplicitUnlock = Boolean(readinessError) || hasExistingStructure
    const isLastStep = step === STEP_LABELS.length - 1
    const currentStepNumber = step + 1
    const totalSteps = STEP_LABELS.length
    const currentStepLabel = STEP_LABELS[step] || ''
    const nextStepLabel = STEP_LABELS[step + 1] || ''

    const setupSummary = useMemo(() => {
        const secretariasAtivas = secretarias.filter((secretaria) => secretaria.nome.trim())
        const departamentosAtivos = Object.values(departamentos).reduce((total, deptos) => {
            if (!Array.isArray(deptos)) {
                return total
            }

            return total + deptos.filter((depto) => (depto.nome || '').trim()).length
        }, 0)

        return {
            niveis: niveis.length,
            secretarias: secretariasAtivas.length,
            departamentos: departamentosAtivos,
            convites: convites.length,
            usuarioPrincipal: usuario.username.trim(),
        }
    }, [niveis, secretarias, departamentos, convites, usuario.username])

    const canAdvance = useMemo(() => {
        switch (step) {
            case 0:
                return prefeitura.nome.trim() !== ''
            case 1:
                return niveis.length >= 2
            case 2:
                return true
            case 3:
                return true
            case 4:
                return usuario.nome.trim() !== '' &&
                    usuario.username.trim() !== '' &&
                    usuario.password.trim() !== '' &&
                    usuario.email.trim() !== ''
            default:
                return false
        }
    }, [step, prefeitura.nome, niveis.length, usuario.nome, usuario.username, usuario.password, usuario.email])

    function clearError() {
        setError(null)
    }

    function setPrefeituraField(field, value) {
        setPrefeitura((prev) => ({ ...prev, [field]: value }))
    }

    function setUsuarioField(field, value) {
        setUsuario((prev) => ({ ...prev, [field]: value }))
    }

    function nextStep() {
        if (step < STEP_LABELS.length - 1) {
            clearError()
            setStep(step + 1)
        }
    }

    function prevStep() {
        if (step > 0) {
            clearError()
            setStep(step - 1)
        }
    }

    function addSecretaria() {
        setSecretarias([...secretarias, { nome: '', sigla: '' }])
    }

    function removeSecretaria(index) {
        const name = secretarias[index]?.nome
        const updated = secretarias.filter((_, currentIndex) => currentIndex !== index)
        setSecretarias(updated)

        if (name) {
            const newDepts = { ...departamentos }
            delete newDepts[name]
            setDepartamentos(newDepts)
        }
    }

    function updateSecretaria(index, field, value) {
        const oldName = secretarias[index]?.nome
        const updated = [...secretarias]
        updated[index] = { ...updated[index], [field]: value }
        setSecretarias(updated)

        if (field === 'nome' && oldName && oldName !== value && departamentos[oldName]) {
            const newDepts = { ...departamentos }
            newDepts[value] = newDepts[oldName]
            delete newDepts[oldName]
            setDepartamentos(newDepts)
        }
    }

    function addDepartamento(secNome) {
        setDepartamentos((prev) => ({
            ...prev,
            [secNome]: [...(prev[secNome] || []), { nome: '', sigla: '' }],
        }))
    }

    function removeDepartamento(secNome, index) {
        setDepartamentos((prev) => ({
            ...prev,
            [secNome]: prev[secNome].filter((_, currentIndex) => currentIndex !== index),
        }))
    }

    function updateDepartamento(secNome, index, field, value) {
        setDepartamentos((prev) => ({
            ...prev,
            [secNome]: prev[secNome].map((dept, currentIndex) => (
                currentIndex === index ? { ...dept, [field]: value } : dept
            )),
        }))
    }

    function toggleSecExpanded(secNome) {
        setExpandedSecs((prev) => ({ ...prev, [secNome]: !prev[secNome] }))
    }

    function addConvite() {
        const email = conviteInput.trim()
        if (email && email.includes('@') && !convites.includes(email)) {
            setConvites([...convites, email])
            setConviteInput('')
        }
    }

    function removeConvite(email) {
        setConvites(convites.filter((item) => item !== email))
    }

    function handleConviteKeyDown(event) {
        if (event.key === 'Enter') {
            event.preventDefault()
            addConvite()
        }
    }

    function unlockReconfigure() {
        if (!confirmReconfigure) {
            setError('Confirme o aceite para liberar a reconfiguracao.')
            return
        }
        setAllowReconfigure(true)
        clearError()
    }

    async function handleSubmit() {
        if (requiresExplicitUnlock && !allowReconfigure) {
            setError('Confirme a reconfiguracao antes de finalizar o setup.')
            return
        }

        clearError()
        setLoading(true)

        const filteredSecretarias = secretarias.filter((sec) => sec.nome.trim())
        const filteredDepartamentos = {}

        for (const [secNome, deptos] of Object.entries(departamentos)) {
            const filtered = deptos.filter((dept) => dept.nome.trim())
            if (filtered.length > 0) {
                filteredDepartamentos[secNome] = filtered
            }
        }

        const payload = {
            prefeitura: {
                ...prefeitura,
                sigla: buildPrefeituraSigla(prefeitura.nome),
            },
            niveis,
            secretarias: filteredSecretarias,
            departamentos: filteredDepartamentos,
            usuario,
            convites,
            force_reconfigure: allowReconfigure,
        }

        try {
            await setupPrefeitura(payload)
            setSuccess(true)
        } catch (err) {
            setError(err?.message || 'Erro ao configurar a prefeitura')
        } finally {
            setLoading(false)
        }
    }

    return {
        step,
        stepLabels: STEP_LABELS,
        estadosBr: ESTADOS_BR,
        loading,
        loadingReadiness,
        error,
        success,
        templates,
        selectedTemplate,
        niveis,
        prefeitura,
        secretarias,
        departamentos,
        expandedSecs,
        usuario,
        conviteInput,
        convites,
        readinessSummary,
        readinessError,
        allowReconfigure,
        confirmReconfigure,
        hasExistingStructure,
        requiresExplicitUnlock,
        isLastStep,
        currentStepNumber,
        totalSteps,
        currentStepLabel,
        nextStepLabel,
        setupSummary,
        canAdvance,
        clearError,
        setPrefeituraField,
        setUsuarioField,
        setSelectedTemplate,
        setConfirmReconfigure,
        setConviteInput,
        nextStep,
        prevStep,
        addSecretaria,
        removeSecretaria,
        updateSecretaria,
        addDepartamento,
        removeDepartamento,
        updateDepartamento,
        toggleSecExpanded,
        addConvite,
        removeConvite,
        handleConviteKeyDown,
        unlockReconfigure,
        handleSubmit,
    }
}
