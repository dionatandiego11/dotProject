import FormField from '../../components/ui/FormField'

export default function ProjectFilters({
    search,
    onSearchChange,
    onSearchSubmit,
    onOpenCreate,
}) {
    return (
        <div className="topbar">
            <h1 className="page-title">Projetos</h1>
            <div style={{ display: 'flex', gap: 'var(--spacing-3)' }}>
                <form onSubmit={onSearchSubmit} style={{ display: 'flex', gap: 'var(--spacing-2)', minWidth: 320 }}>
                    <FormField
                        as="input"
                        type="text"
                        placeholder="Buscar projetos..."
                        value={search}
                        onChange={(event) => onSearchChange(event.target.value)}
                        style={{ minWidth: 260 }}
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
