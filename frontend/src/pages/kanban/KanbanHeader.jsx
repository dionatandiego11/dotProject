import Button from '../../components/ui/Button'
import FormField from '../../components/ui/FormField'

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

                    <div style={{ flex: '1 1 280px', minWidth: 'min(260px, 100%)', maxWidth: 420 }}>
                        <FormField
                            as="select"
                            label="Projeto"
                            value={selectedProjectId}
                            onChange={(event) => onProjectChange(event.target.value)}
                            options={[
                                { value: '', label: 'Selecione um projeto' },
                                ...projects.map((project) => ({ value: String(project.id), label: project.name })),
                            ]}
                        />
                    </div>
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
                    <FormField
                        as="input"
                        type="text"
                        label="Buscar"
                        value={filterText}
                        onChange={(event) => onFilterTextChange(event.target.value)}
                        placeholder="Buscar tarefa..."
                        style={{ flex: '1 1 220px', minWidth: 0 }}
                    />
                    <FormField
                        as="select"
                        label="Responsavel"
                        value={filterOwner}
                        onChange={(event) => onFilterOwnerChange(event.target.value)}
                        options={[
                            { value: 'all', label: 'Usuarios vinculados' },
                            ...(unitUsers.length === 0 ? [{ value: '', label: 'Nenhum usuario vinculado', disabled: true }] : []),
                            ...unitUsers.map((user) => ({
                                value: String(user.id),
                                label: user.full_name || user.username,
                            })),
                        ]}
                        style={{ flex: '1 1 220px', minWidth: 0 }}
                    />
                    <FormField
                        as="select"
                        label="Prioridade"
                        value={filterPriority}
                        onChange={(event) => onFilterPriorityChange(event.target.value)}
                        options={[
                            { value: 'all', label: 'Todas prioridades' },
                            { value: '0', label: 'Baixa' },
                            { value: '1', label: 'Normal' },
                            { value: '2', label: 'Alta' },
                            { value: '3', label: 'Urgente' },
                        ]}
                        style={{ flex: '1 1 180px', minWidth: 0 }}
                    />
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
