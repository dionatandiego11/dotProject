import FormField from '../../components/ui/FormField'

export default function ProjectFilters({
    search,
    onSearchChange,
    onSearchSubmit,
    onOpenCreate,
}) {
    return (
        <div className="topbar" style={{ flexWrap: 'wrap', rowGap: 'var(--spacing-3)' }}>
            <h1 className="page-title">Projetos</h1>
            <div style={{ display: 'flex', gap: 'var(--spacing-3)', flexWrap: 'wrap', flex: '1 1 480px', justifyContent: 'flex-end' }}>
                <form onSubmit={onSearchSubmit} style={{ display: 'flex', gap: 'var(--spacing-2)', flex: '1 1 320px', minWidth: 0, flexWrap: 'wrap' }}>
                    <FormField
                        as="input"
                        type="text"
                        aria-label="Buscar projetos"
                        placeholder="Buscar projetos..."
                        value={search}
                        onChange={(event) => onSearchChange(event.target.value)}
                        style={{ flex: '1 1 220px', minWidth: 0 }}
                    />
                    <button type="submit" className="btn btn-secondary">Buscar</button>
                </form>
                <button className="btn btn-primary" onClick={onOpenCreate}>
                    + Novo Projeto
                </button>
            </div>
        </div>
    )
}
