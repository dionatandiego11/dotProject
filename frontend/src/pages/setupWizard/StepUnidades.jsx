export default function StepUnidades({
    mode,
    secretarias,
    departamentos,
    expandedSecs,
    onAddSecretaria,
    onRemoveSecretaria,
    onUpdateSecretaria,
    onAddDepartamento,
    onRemoveDepartamento,
    onUpdateDepartamento,
    onToggleSecretaria,
}) {
    if (mode === 'secretarias') {
        return (
            <div className="setup-wizard__step-content" key="step-2">
                <h3 className="setup-wizard__step-title">Secretarias</h3>
                <p className="setup-wizard__step-subtitle">
                    Cadastre as secretarias da prefeitura (voce pode adicionar mais depois)
                </p>

                <div className="setup-wizard__list">
                    {secretarias.map((secretaria, index) => (
                        <div className="setup-wizard__list-item" key={`secretaria-${index}`}>
                            <input
                                className="setup-wizard__input"
                                placeholder="Nome da secretaria"
                                value={secretaria.nome}
                                onChange={(event) => onUpdateSecretaria(index, 'nome', event.target.value)}
                                style={{ flex: 2 }}
                            />
                            <input
                                className="setup-wizard__input"
                                placeholder="Sigla"
                                value={secretaria.sigla}
                                onChange={(event) => onUpdateSecretaria(index, 'sigla', event.target.value)}
                                style={{ flex: 0, width: 80 }}
                            />
                            {secretarias.length > 1 && (
                                <button
                                    type="button"
                                    className="setup-wizard__btn-icon"
                                    onClick={() => onRemoveSecretaria(index)}
                                    title="Remover"
                                >
                                    x
                                </button>
                            )}
                        </div>
                    ))}
                </div>

                <button
                    type="button"
                    className="setup-wizard__btn-add"
                    onClick={onAddSecretaria}
                    style={{ marginTop: '0.75rem' }}
                >
                    + Adicionar Secretaria
                </button>
            </div>
        )
    }

    const validSecretarias = secretarias.filter((secretaria) => secretaria.nome.trim())

    if (validSecretarias.length === 0) {
        return (
            <div className="setup-wizard__step-content" key="step-3">
                <h3 className="setup-wizard__step-title">Coordenacoes / Departamentos</h3>
                <p className="setup-wizard__step-subtitle">
                    Nenhuma secretaria cadastrada. Voce pode pular este passo ou voltar e adicionar secretarias.
                </p>
            </div>
        )
    }

    return (
        <div className="setup-wizard__step-content" key="step-3">
            <h3 className="setup-wizard__step-title">Coordenacoes / Departamentos</h3>
            <p className="setup-wizard__step-subtitle">
                Adicione subdivisoes dentro de cada secretaria (opcional)
            </p>

            {validSecretarias.map((secretaria) => {
                const secName = secretaria.nome
                const deptos = departamentos[secName] || []
                const isExpanded = expandedSecs[secName] !== false

                return (
                    <div className="setup-wizard__tree-section" key={secName}>
                        <div
                            className="setup-wizard__tree-header"
                            onClick={() => onToggleSecretaria(secName)}
                        >
                            <span>{isExpanded ? 'v' : '>'}</span>
                            <span>Secretaria {secName}</span>
                            {secretaria.sigla && (
                                <span style={{ color: '#9ca3af', fontWeight: 400 }}>
                                    ({secretaria.sigla})
                                </span>
                            )}
                            <span style={{ marginLeft: 'auto', color: '#9ca3af', fontSize: '0.75rem' }}>
                                {deptos.length} departamento{deptos.length !== 1 ? 's' : ''}
                            </span>
                        </div>

                        {isExpanded && (
                            <div className="setup-wizard__tree-body">
                                <div className="setup-wizard__list">
                                    {deptos.map((depto, index) => (
                                        <div className="setup-wizard__list-item" key={`${secName}-depto-${index}`}>
                                            <input
                                                className="setup-wizard__input"
                                                placeholder="Nome do departamento"
                                                value={depto.nome}
                                                onChange={(event) => onUpdateDepartamento(secName, index, 'nome', event.target.value)}
                                                style={{ flex: 2 }}
                                            />
                                            <input
                                                className="setup-wizard__input"
                                                placeholder="Sigla"
                                                value={depto.sigla}
                                                onChange={(event) => onUpdateDepartamento(secName, index, 'sigla', event.target.value)}
                                                style={{ flex: 0, width: 80 }}
                                            />
                                            <button
                                                type="button"
                                                className="setup-wizard__btn-icon"
                                                onClick={() => onRemoveDepartamento(secName, index)}
                                                title="Remover"
                                            >
                                                x
                                            </button>
                                        </div>
                                    ))}
                                </div>
                                <button
                                    type="button"
                                    className="setup-wizard__btn-add"
                                    onClick={() => onAddDepartamento(secName)}
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
