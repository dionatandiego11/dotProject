import FormField from '../../components/ui/FormField'

export default function KanbanTaskForm({
    mode,
    task,
    setTask,
    unitUsers,
    validation,
    onSubmit,
}) {
    const isEdit = mode === 'edit'

    function updateField(field, value) {
        setTask((prev) => ({ ...prev, [field]: value }))
    }

    return (
        <form onSubmit={onSubmit}>
            <div style={{ marginBottom: 'var(--spacing-4)' }}>
                <FormField
                    as="input"
                    label="Nome da Tarefa"
                    required
                    value={task.name}
                    onChange={(event) => {
                        updateField('name', event.target.value)
                        validation.clearFieldError('name')
                    }}
                    placeholder="Digite o nome da tarefa"
                    error={validation.errors.name}
                />
            </div>

            <div style={{ marginBottom: 'var(--spacing-4)' }}>
                <FormField
                    as="textarea"
                    label="Descricao"
                    value={task.description}
                    onChange={(event) => {
                        updateField('description', event.target.value)
                        if (!isEdit) validation.clearFieldError('description')
                    }}
                    placeholder="Descricao da tarefa"
                    rows={3}
                    error={validation.errors.description}
                />
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 'var(--spacing-4)' }}>
                <FormField
                    as="select"
                    label={isEdit ? 'Status' : 'Prioridade'}
                    value={isEdit ? task.status : task.priority}
                    onChange={(event) => updateField(isEdit ? 'status' : 'priority', event.target.value)}
                    options={[
                        { value: '0', label: isEdit ? 'Backlog' : 'Baixa' },
                        { value: '1', label: isEdit ? 'A Fazer' : 'Normal' },
                        { value: '2', label: isEdit ? 'Em Andamento' : 'Alta' },
                        { value: '3', label: isEdit ? 'Concluido' : 'Urgente' },
                    ]}
                />

                <FormField
                    as="select"
                    label={isEdit ? 'Prioridade' : 'Status inicial'}
                    value={isEdit ? task.priority : task.status}
                    onChange={(event) => updateField(isEdit ? 'priority' : 'status', event.target.value)}
                    options={[
                        { value: '0', label: isEdit ? 'Baixa' : 'Backlog' },
                        { value: '1', label: isEdit ? 'Normal' : 'A Fazer' },
                        { value: '2', label: isEdit ? 'Alta' : 'Em Andamento' },
                        { value: '3', label: isEdit ? 'Urgente' : 'Concluido' },
                    ]}
                />

                {isEdit ? (
                    <FormField
                        as="input"
                        type="number"
                        min="0"
                        max="100"
                        label="Progresso (%)"
                        value={task.percent_complete}
                        onChange={(event) => updateField('percent_complete', event.target.value)}
                    />
                ) : (
                    <FormField
                        as="input"
                        type="date"
                        label="Prazo"
                        value={task.end_date}
                        onChange={(event) => {
                            updateField('end_date', event.target.value)
                            validation.clearFieldError('end_date')
                        }}
                        error={validation.errors.end_date}
                    />
                )}
            </div>

            {isEdit && (
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 'var(--spacing-4)', marginTop: 'var(--spacing-4)' }}>
                    <FormField
                        as="input"
                        type="date"
                        label="Inicio"
                        value={task.start_date}
                        onChange={(event) => updateField('start_date', event.target.value)}
                    />
                    <FormField
                        as="input"
                        type="date"
                        label="Prazo"
                        value={task.end_date}
                        onChange={(event) => {
                            updateField('end_date', event.target.value)
                            validation.clearFieldError('end_date')
                        }}
                        error={validation.errors.end_date}
                    />
                    <FormField
                        as="input"
                        type="number"
                        min="0"
                        label="Duracao (dias)"
                        value={task.duration}
                        onChange={(event) => updateField('duration', event.target.value)}
                    />
                </div>
            )}

            <div style={{ marginTop: 'var(--spacing-4)' }}>
                <FormField
                    as="select"
                    label="Responsavel"
                    value={task.owner_id}
                    onChange={(event) => updateField('owner_id', event.target.value)}
                    options={[
                        { value: '', label: 'Sem responsavel' },
                        ...(unitUsers.length === 0 ? [{ value: '', label: 'Nenhum usuario vinculado', disabled: true }] : []),
                        ...unitUsers.map((user) => ({
                            value: String(user.id),
                            label: user.full_name || user.username,
                        })),
                    ]}
                />
            </div>
        </form>
    )
}
