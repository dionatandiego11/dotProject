export default function StepNiveis({ templates, selectedTemplate, onSelectTemplate, niveis }) {
    const templateList = templates ? Object.entries(templates) : []

    return (
        <div className="setup-wizard__step-content" key="step-1">
            <h3 className="setup-wizard__step-title">Niveis Hierarquicos</h3>
            <p className="setup-wizard__step-subtitle">
                Escolha um modelo de estrutura organizacional ou personalize
            </p>

            <div className="setup-wizard__templates">
                {templateList.map(([key, template]) => (
                    <div
                        key={key}
                        className={`setup-wizard__template-card ${selectedTemplate === key ? 'selected' : ''}`}
                        onClick={() => onSelectTemplate(key)}
                    >
                        <h4>{selectedTemplate === key ? 'Selecionado: ' : ''}{template.nome}</h4>
                        <p>{template.descricao}</p>
                        <div className="setup-wizard__niveis-preview">
                            {(template.niveis || []).map((nivel, index) => (
                                <span
                                    key={`${key}-nivel-${index}`}
                                    className="setup-wizard__nivel-tag"
                                    style={{ background: nivel.cor || '#2563eb' }}
                                >
                                    {nivel.ordem}. {nivel.nome}
                                </span>
                            ))}
                        </div>
                    </div>
                ))}
            </div>

            {niveis.length > 0 && (
                <div style={{ marginTop: '1rem' }}>
                    <label className="setup-wizard__label" style={{ marginBottom: '0.5rem' }}>
                        Niveis selecionados ({niveis.length}):
                    </label>
                    {niveis.map((nivel, index) => (
                        <div
                            key={`selected-nivel-${index}`}
                            style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.25rem' }}
                        >
                            <span
                                className="setup-wizard__nivel-tag"
                                style={{ background: nivel.cor || '#2563eb' }}
                            >
                                {nivel.ordem}
                            </span>
                            <span style={{ fontSize: '0.875rem', color: '#374151' }}>
                                {nivel.nome} - <em style={{ color: '#6b7280' }}>{nivel.titulo_responsavel}</em>
                            </span>
                        </div>
                    ))}
                </div>
            )}
        </div>
    )
}
