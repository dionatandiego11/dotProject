export default function StepUsuarios({
    usuario,
    conviteInput,
    convites,
    onUsuarioChange,
    onConviteInputChange,
    onConviteKeyDown,
    onAddConvite,
    onRemoveConvite,
}) {
    return (
        <div className="setup-wizard__step-content" key="step-4">
            <h3 className="setup-wizard__step-title">Primeiro Usuario (Prefeito)</h3>
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
                        onChange={(event) => onUsuarioChange('nome', event.target.value)}
                    />
                </div>
                <div className="setup-wizard__field">
                    <label className="setup-wizard__label">Sobrenome</label>
                    <input
                        className="setup-wizard__input"
                        placeholder="Sobrenome"
                        value={usuario.sobrenome}
                        onChange={(event) => onUsuarioChange('sobrenome', event.target.value)}
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
                    onChange={(event) => onUsuarioChange('email', event.target.value)}
                />
            </div>

            <div className="setup-wizard__row">
                <div className="setup-wizard__field">
                    <label className="setup-wizard__label">Usuario de acesso *</label>
                    <input
                        className="setup-wizard__input"
                        placeholder="prefeito"
                        value={usuario.username}
                        onChange={(event) => onUsuarioChange('username', event.target.value)}
                    />
                </div>
                <div className="setup-wizard__field">
                    <label className="setup-wizard__label">Senha *</label>
                    <input
                        className="setup-wizard__input"
                        type="password"
                        placeholder="Minimo 6 caracteres"
                        value={usuario.password}
                        onChange={(event) => onUsuarioChange('password', event.target.value)}
                    />
                </div>
            </div>

            <hr style={{ border: 'none', borderTop: '1px solid #e5e7eb', margin: '1.5rem 0' }} />

            <div className="setup-wizard__field">
                <label className="setup-wizard__label">Convidar outros usuarios (opcional)</label>
                <p style={{ fontSize: '0.75rem', color: '#9ca3af', margin: '0.25rem 0 0.5rem' }}>
                    Adicione emails para convidar posteriormente
                </p>
                <div style={{ display: 'flex', gap: '0.5rem' }}>
                    <input
                        className="setup-wizard__input"
                        placeholder="email@exemplo.com"
                        value={conviteInput}
                        onChange={(event) => onConviteInputChange(event.target.value)}
                        onKeyDown={onConviteKeyDown}
                        style={{ flex: 1 }}
                    />
                    <button
                        type="button"
                        className="setup-wizard__btn setup-wizard__btn--secondary"
                        onClick={onAddConvite}
                    >
                        Adicionar
                    </button>
                </div>
                {convites.length > 0 && (
                    <div className="setup-wizard__convites-list">
                        {convites.map((email) => (
                            <span key={email} className="setup-wizard__convite-tag">
                                {email}
                                <button type="button" onClick={() => onRemoveConvite(email)}>x</button>
                            </span>
                        ))}
                    </div>
                )}
            </div>
        </div>
    )
}
