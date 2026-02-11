import ProjectTable from './ProjectTable'

export default function ProjectList({
    loading,
    projects,
    meta,
    onEdit,
    onDelete,
    quickStatusSelectionByProjectId,
    quickStatusLoadingByProjectId,
    onQuickStatusSelect,
    onApplyQuickStatus,
    onPageChange,
}) {
    return (
        <>
            <div className="card">
                <div className="card-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <h2 className="card-title">Lista de Projetos</h2>
                    <span style={{ fontSize: '0.875rem', color: 'var(--color-gray-500)' }}>
                        {meta.total || 0} projetos
                    </span>
                </div>
                <div className="card-body" style={{ padding: 0 }}>
                    {loading ? (
                        <div style={{ padding: 'var(--spacing-6)', textAlign: 'center' }}>Carregando...</div>
                    ) : projects.length === 0 ? (
                        <div style={{ padding: 'var(--spacing-6)', textAlign: 'center', color: 'var(--color-gray-500)' }}>
                            Nenhum projeto encontrado
                        </div>
                    ) : (
                        <ProjectTable
                            projects={projects}
                            onEdit={onEdit}
                            onDelete={onDelete}
                            quickStatusSelectionByProjectId={quickStatusSelectionByProjectId}
                            quickStatusLoadingByProjectId={quickStatusLoadingByProjectId}
                            onQuickStatusSelect={onQuickStatusSelect}
                            onApplyQuickStatus={onApplyQuickStatus}
                        />
                    )}
                </div>
            </div>

            {meta.total_pages > 1 && (
                <div style={{ display: 'flex', justifyContent: 'center', gap: 'var(--spacing-2)', marginTop: 'var(--spacing-6)' }}>
                    {Array.from({ length: meta.total_pages }, (_, index) => index + 1).map((page) => (
                        <button
                            key={page}
                            className={`btn ${page === meta.page ? 'btn-primary' : 'btn-secondary'}`}
                            onClick={() => onPageChange(page)}
                            style={{ minWidth: 40 }}
                        >
                            {page}
                        </button>
                    ))}
                </div>
            )}
        </>
    )
}
