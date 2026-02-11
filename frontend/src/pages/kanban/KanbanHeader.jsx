import Button from '../../components/ui/Button'

export default function KanbanHeader({
    selectedProjectId,
    selectedProjectName,
    boardName,
    projects,
    onProjectChange,
    onCreateTask,
    filterText,
    onFilterTextChange,
    filterOwner,
    onFilterOwnerChange,
    filterPriority,
    onFilterPriorityChange,
    onlyMine,
    onOnlyMineChange,
    overdueOnly,
    onOverdueOnlyChange,
    hideCompleted,
    onHideCompletedChange,
    unitUsers,
    stats,
}) {
    const subtitle = selectedProjectId
        ? `${selectedProjectName || 'Projeto selecionado'}${boardName ? ` | ${boardName}` : ''}`
        : 'Selecione um projeto para ver o quadro'

    return (
        <>
            <div className="kanban-header">
                <div className="kanban-header-left">
                    <div className="kanban-title-wrap">
                        <h1 className="kanban-title">Tarefas</h1>
                        <span className="kanban-subtitle">{subtitle}</span>
                    </div>

                    <select
                        className="kanban-project-select"
                        value={selectedProjectId}
                        onChange={(event) => onProjectChange(event.target.value)}
                    >
                        <option value="">Selecione um projeto</option>
                        {projects.map((project) => (
                            <option key={project.id} value={project.id}>
                                {project.name}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="kanban-header-actions">
                    <Button
                        variant="primary"
                        onClick={onCreateTask}
                        disabled={!selectedProjectId}
                    >
                        + Nova Tarefa
                    </Button>
                </div>
            </div>

            <div className="kanban-toolbar">
                <div className="kanban-filters">
                    <input
                        className="kanban-filter"
                        type="text"
                        value={filterText}
                        onChange={(event) => onFilterTextChange(event.target.value)}
                        placeholder="Buscar tarefa..."
                    />
                    <select
                        className="kanban-filter"
                        value={filterOwner}
                        onChange={(event) => onFilterOwnerChange(event.target.value)}
                    >
                        <option value="all">Usuarios vinculados</option>
                        {unitUsers.length === 0 && (
                            <option value="" disabled>Nenhum usuario vinculado</option>
                        )}
                        {unitUsers.map((user) => (
                            <option key={user.id} value={user.id}>
                                {user.full_name || user.username}
                            </option>
                        ))}
                    </select>
                    <select
                        className="kanban-filter"
                        value={filterPriority}
                        onChange={(event) => onFilterPriorityChange(event.target.value)}
                    >
                        <option value="all">Todas prioridades</option>
                        <option value="0">Baixa</option>
                        <option value="1">Normal</option>
                        <option value="2">Alta</option>
                        <option value="3">Urgente</option>
                    </select>
                    <label className="kanban-toggle">
                        <input
                            type="checkbox"
                            checked={onlyMine}
                            onChange={(event) => onOnlyMineChange(event.target.checked)}
                        />
                        Somente minhas
                    </label>
                    <label className="kanban-toggle">
                        <input
                            type="checkbox"
                            checked={overdueOnly}
                            onChange={(event) => onOverdueOnlyChange(event.target.checked)}
                        />
                        Atrasadas
                    </label>
                    <label className="kanban-toggle">
                        <input
                            type="checkbox"
                            checked={hideCompleted}
                            onChange={(event) => onHideCompletedChange(event.target.checked)}
                        />
                        Ocultar concluidas
                    </label>
                </div>
                <div className="kanban-stats">
                    <span className="kanban-stat">Total: {stats.total}</span>
                    <span className="kanban-stat">Atrasadas: {stats.overdue}</span>
                </div>
            </div>
        </>
    )
}
