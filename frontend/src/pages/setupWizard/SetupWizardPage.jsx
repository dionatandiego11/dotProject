import { useNavigate } from 'react-router-dom'
import useWizardState from './useWizardState'
import WizardStep from './WizardStep'
import StepNiveis from './StepNiveis'
import StepUnidades from './StepUnidades'
import StepUsuarios from './StepUsuarios'
import '../SetupWizard.css'

function StepPrefeitura({ prefeitura, estadosBr, onChange }) {
    return (
        <div className="setup-wizard__step-content" key="step-0">
            <h3 className="setup-wizard__step-title">Dados da Prefeitura</h3>
            <p className="setup-wizard__step-subtitle">Informe os dados basicos do municipio</p>

            <div className="setup-wizard__field">
                <label className="setup-wizard__label">Nome da Prefeitura *</label>
                <input
                    className="setup-wizard__input"
                    placeholder="Ex: Prefeitura Municipal de Sao Paulo"
                    value={prefeitura.nome}
                    onChange={(event) => onChange('nome', event.target.value)}
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
                        onChange={(event) => onChange('cnpj', event.target.value)}
                    />
                </div>
                <div className="setup-wizard__field">
                    <label className="setup-wizard__label">Estado</label>
                    <select
                        className="setup-wizard__input"
                        value={prefeitura.estado}
                        onChange={(event) => onChange('estado', event.target.value)}
                    >
                        <option value="">Selecione...</option>
                        {estadosBr.map((uf) => (
                            <option key={uf} value={uf}>{uf}</option>
                        ))}
                    </select>
                </div>
            </div>

            <div className="setup-wizard__field">
                <label className="setup-wizard__label">Cidade</label>
                <input
                    className="setup-wizard__input"
                    placeholder="Nome da cidade"
                    value={prefeitura.cidade}
                    onChange={(event) => onChange('cidade', event.target.value)}
                />
            </div>
        </div>
    )
}

function renderStepContent(wizard) {
    switch (wizard.step) {
        case 0:
            return (
                <StepPrefeitura
                    prefeitura={wizard.prefeitura}
                    estadosBr={wizard.estadosBr}
                    onChange={wizard.setPrefeituraField}
                />
            )
        case 1:
            return (
                <StepNiveis
                    templates={wizard.templates}
                    selectedTemplate={wizard.selectedTemplate}
                    onSelectTemplate={wizard.setSelectedTemplate}
                    niveis={wizard.niveis}
                />
            )
        case 2:
            return (
                <StepUnidades
                    mode="secretarias"
                    secretarias={wizard.secretarias}
                    onAddSecretaria={wizard.addSecretaria}
                    onRemoveSecretaria={wizard.removeSecretaria}
                    onUpdateSecretaria={wizard.updateSecretaria}
                />
            )
        case 3:
            return (
                <StepUnidades
                    mode="departamentos"
                    secretarias={wizard.secretarias}
                    departamentos={wizard.departamentos}
                    expandedSecs={wizard.expandedSecs}
                    onAddDepartamento={wizard.addDepartamento}
                    onRemoveDepartamento={wizard.removeDepartamento}
                    onUpdateDepartamento={wizard.updateDepartamento}
                    onToggleSecretaria={wizard.toggleSecExpanded}
                />
            )
        case 4:
            return (
                <StepUsuarios
                    usuario={wizard.usuario}
                    conviteInput={wizard.conviteInput}
                    convites={wizard.convites}
                    onUsuarioChange={wizard.setUsuarioField}
                    onConviteInputChange={wizard.setConviteInput}
                    onConviteKeyDown={wizard.handleConviteKeyDown}
                    onAddConvite={wizard.addConvite}
                    onRemoveConvite={wizard.removeConvite}
                />
            )
        default:
            return null
    }
}

export default function SetupWizardPage() {
    const navigate = useNavigate()
    const wizard = useWizardState()

    if (wizard.success) {
        return (
            <div className="setup-wizard">
                <div className="setup-wizard__container">
                    <div className="setup-wizard__body">
                        <div className="setup-wizard__success">
                            <div className="setup-wizard__success-icon">{'\u2713'}</div>
                            <h2>Prefeitura configurada!</h2>
                            <p>
                                A estrutura organizacional de <strong>{wizard.prefeitura.nome}</strong> foi
                                criada com sucesso. Voce ja pode comecar a usar o sistema.
                            </p>
                            <button
                                className="setup-wizard__btn setup-wizard__btn--primary"
                                onClick={() => navigate('/admin/unidades')}
                            >
                                Ir para administracao {'->'}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        )
    }

    if (wizard.loadingReadiness) {
        return (
            <div className="setup-wizard">
                <div className="setup-wizard__container">
                    <div className="setup-wizard__body" style={{ display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                        <div style={{ textAlign: 'center' }}>
                            <div style={{ fontSize: 32, marginBottom: 8 }}>...</div>
                            <div style={{ color: '#4b5563' }}>Validando estado do ambiente...</div>
                        </div>
                    </div>
                </div>
            </div>
        )
    }

    if (wizard.requiresExplicitUnlock && !wizard.allowReconfigure) {
        return (
            <div className="setup-wizard">
                <div className="setup-wizard__container">
                    <div className="setup-wizard__header">
                        <h1>Setup administrativo bloqueado</h1>
                        <p>Este ambiente ja possui estrutura cadastrada.</p>
                    </div>

                    <div className="setup-wizard__body">
                        {wizard.error && (
                            <div className="setup-wizard__error">{wizard.error}</div>
                        )}

                        <div style={{
                            border: '1px solid #fca5a5',
                            backgroundColor: '#fef2f2',
                            borderRadius: 10,
                            padding: 16,
                            marginBottom: 16,
                            color: '#991b1b',
                        }}>
                            Reexecutar o setup pode duplicar niveis, unidades e vinculos. So continue se for intencional.
                        </div>

                        {wizard.readinessError ? (
                            <div className="setup-wizard__error">{wizard.readinessError}</div>
                        ) : (
                            <div style={{ display: 'grid', gap: 8, marginBottom: 16 }}>
                                <div style={{ fontSize: 14, color: '#374151' }}>
                                    Niveis ativos: <strong>{wizard.readinessSummary?.niveis_ativos || 0}</strong>
                                </div>
                                <div style={{ fontSize: 14, color: '#374151' }}>
                                    Unidades ativas: <strong>{wizard.readinessSummary?.unidades_ativas || 0}</strong>
                                </div>
                                <div style={{ fontSize: 14, color: '#374151' }}>
                                    Vinculos ativos: <strong>{wizard.readinessSummary?.vinculos_ativos || 0}</strong>
                                </div>
                            </div>
                        )}

                        <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 14, color: '#111827' }}>
                            <input
                                type="checkbox"
                                checked={wizard.confirmReconfigure}
                                onChange={(event) => {
                                    wizard.setConfirmReconfigure(event.target.checked)
                                    wizard.clearError()
                                }}
                            />
                            Entendo os riscos e desejo liberar a reconfiguracao.
                        </label>
                    </div>

                    <div className="setup-wizard__footer">
                        <button
                            className="setup-wizard__btn setup-wizard__btn--secondary"
                            onClick={() => navigate('/')}
                        >
                            {'<-'} Voltar ao dashboard
                        </button>
                        <button
                            className="setup-wizard__btn setup-wizard__btn--success"
                            onClick={wizard.unlockReconfigure}
                            disabled={!wizard.confirmReconfigure}
                        >
                            Liberar reconfiguracao
                        </button>
                    </div>
                </div>
            </div>
        )
    }

    return (
        <div className="setup-wizard">
            <div className="setup-wizard__container">
                <div className="setup-wizard__header">
                    <h1>Setup Administrativo</h1>
                    <p>Configure a estrutura da prefeitura em poucos passos</p>
                </div>

                <div className="setup-wizard__progress">
                    {wizard.stepLabels.map((label, index) => (
                        <WizardStep
                            key={label}
                            label={label}
                            index={index}
                            currentStep={wizard.step}
                        />
                    ))}
                </div>

                <div className="setup-wizard__body">
                    {wizard.error && (
                        <div className="setup-wizard__error">{wizard.error}</div>
                    )}
                    {renderStepContent(wizard)}
                </div>

                <div className="setup-wizard__footer">
                    <div>
                        {wizard.step > 0 && (
                            <button
                                className="setup-wizard__btn setup-wizard__btn--secondary"
                                onClick={wizard.prevStep}
                            >
                                {'<-'} Voltar
                            </button>
                        )}
                    </div>
                    <div>
                        {wizard.isLastStep ? (
                            <button
                                className="setup-wizard__btn setup-wizard__btn--success"
                                onClick={wizard.handleSubmit}
                                disabled={!wizard.canAdvance || wizard.loading}
                            >
                                {wizard.loading ? 'Configurando...' : 'Finalizar Configuracao'}
                            </button>
                        ) : (
                            <button
                                className="setup-wizard__btn setup-wizard__btn--primary"
                                onClick={wizard.nextStep}
                                disabled={!wizard.canAdvance}
                            >
                                Avancar {'->'}
                            </button>
                        )}
                    </div>
                </div>
            </div>
        </div>
    )
}
