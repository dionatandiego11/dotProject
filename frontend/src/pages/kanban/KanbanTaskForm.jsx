import Input from '../../components/ui/Input'

function selectStyle() {
    return {
        width: '100%',
        padding: 'var(--spacing-2) var(--spacing-3)',
        border: '1px solid var(--color-gray-300)',
        borderRadius: 'var(--radius-md)',
        fontSize: '0.875rem',
        background: 'white',
    }
}

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
                <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                    Nome da Tarefa *
                </label>
                <Input
                    value={task.name}
                    onChange={(event) => {
                        updateField('name', event.target.value)
                        validation.clearFieldError('name')
                    }}
                    placeholder="Digite o nome da tarefa"
                    style={validation.errors.name ? { borderColor: 'var(--color-danger-500)' } : {}}
                />
                {validation.errors.name && (
                    <span style={{ color: 'var(--color-danger-500)', fontSize: '0.75rem', marginTop: 'var(--spacing-1)', display: 'block' }}>
                        {validation.errors.name}
                    </span>
                )}
            </div>

            <div style={{ marginBottom: 'var(--spacing-4)' }}>
                <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                    Descricao
                </label>
                <textarea
                    value={task.description}
                    onChange={(event) => {
                        updateField('description', event.target.value)
                        if (!isEdit) validation.clearFieldError('description')
                    }}
                    placeholder="Descricao da tarefa"
                    rows={3}
                    style={{
                        width: '100%',
                        padding: 'var(--spacing-2) var(--spacing-3)',
                        border: validation.errors.description ? '1px solid var(--color-danger-500)' : '1px solid var(--color-gray-300)',
                        borderRadius: 'var(--radius-md)',
                        fontSize: '0.875rem',
                        fontFamily: 'inherit',
                    }}
                />
                {validation.errors.description && (
                    <span style={{ color: 'var(--color-danger-500)', fontSize: '0.75rem', marginTop: 'var(--spacing-1)', display: 'block' }}>
                        {validation.errors.description}
                    </span>
                )}
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 'var(--spacing-4)' }}>
                <div>
                    <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                        {isEdit ? 'Status' : 'Prioridade'}
                    </label>
                    {isEdit ? (
                        <select
                            value={task.status}
                            onChange={(event) => updateField('status', event.target.value)}
                            style={selectStyle()}
                        >
                            <option value="0">Backlog</option>
                            <option value="1">A Fazer</option>
                            <option value="2">Em Andamento</option>
                            <option value="3">Concluido</option>
                        </select>
                    ) : (
                        <select
                            value={task.priority}
                            onChange={(event) => updateField('priority', event.target.value)}
                            style={selectStyle()}
                        >
                            <option value="0">Baixa</option>
                            <option value="1">Normal</option>
                            <option value="2">Alta</option>
                            <option value="3">Urgente</option>
                        </select>
                    )}
                </div>

                <div>
                    <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                        {isEdit ? 'Prioridade' : 'Status inicial'}
                    </label>
                    {isEdit ? (
                        <select
                            value={task.priority}
                            onChange={(event) => updateField('priority', event.target.value)}
                            style={selectStyle()}
                        >
                            <option value="0">Baixa</option>
                            <option value="1">Normal</option>
                            <option value="2">Alta</option>
                            <option value="3">Urgente</option>
                        </select>
                    ) : (
                        <select
                            value={task.status}
                            onChange={(event) => updateField('status', event.target.value)}
                            style={selectStyle()}
                        >
                            <option value="0">Backlog</option>
                            <option value="1">A Fazer</option>
                            <option value="2">Em Andamento</option>
                            <option value="3">Concluido</option>
                        </select>
                    )}
                </div>

                <div>
                    <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                        {isEdit ? 'Progresso (%)' : 'Prazo'}
                    </label>
                    {isEdit ? (
                        <Input
                            type="number"
                            min="0"
                            max="100"
                            value={task.percent_complete}
                            onChange={(event) => updateField('percent_complete', event.target.value)}
                        />
                    ) : (
                        <>
                            <Input
                                type="date"
                                value={task.end_date}
                                onChange={(event) => {
                                    updateField('end_date', event.target.value)
                                    validation.clearFieldError('end_date')
                                }}
                                style={validation.errors.end_date ? { borderColor: 'var(--color-danger-500)' } : {}}
                            />
                            {validation.errors.end_date && (
                                <span style={{ color: 'var(--color-danger-500)', fontSize: '0.75rem', marginTop: 'var(--spacing-1)', display: 'block' }}>
                                    {validation.errors.end_date}
                                </span>
                            )}
                        </>
                    )}
                </div>
            </div>

            {isEdit && (
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 'var(--spacing-4)', marginTop: 'var(--spacing-4)' }}>
                    <div>
                        <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                            Inicio
                        </label>
                        <Input
                            type="date"
                            value={task.start_date}
                            onChange={(event) => updateField('start_date', event.target.value)}
                        />
                    </div>
                    <div>
                        <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                            Prazo
                        </label>
                        <Input
                            type="date"
                            value={task.end_date}
                            onChange={(event) => {
                                updateField('end_date', event.target.value)
                                validation.clearFieldError('end_date')
                            }}
                            style={validation.errors.end_date ? { borderColor: 'var(--color-danger-500)' } : {}}
                        />
                        {validation.errors.end_date && (
                            <span style={{ color: 'var(--color-danger-500)', fontSize: '0.75rem', marginTop: 'var(--spacing-1)', display: 'block' }}>
                                {validation.errors.end_date}
                            </span>
                        )}
                    </div>
                    <div>
                        <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                            Duracao (dias)
                        </label>
                        <Input
                            type="number"
                            min="0"
                            value={task.duration}
                            onChange={(event) => updateField('duration', event.target.value)}
                        />
                    </div>
                </div>
            )}

            <div style={{ marginTop: 'var(--spacing-4)' }}>
                <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                    Responsavel
                </label>
                <select
                    value={task.owner_id}
                    onChange={(event) => updateField('owner_id', event.target.value)}
                    style={selectStyle()}
                >
                    <option value="">Sem responsavel</option>
                    {unitUsers.length === 0 && (
                        <option value="" disabled>Nenhum usuario vinculado</option>
                    )}
                    {unitUsers.map((user) => (
                        <option key={user.id} value={user.id}>
                            {user.full_name || user.username}
                        </option>
                    ))}
                </select>
            </div>
        </form>
    )
}
