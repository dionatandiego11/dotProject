/**
 * ProjectForm - shared form for creating and editing projects.
 */

import Input from '../../components/ui/Input'
import { getStatusLabelById } from './projectStatusUtils'

export default function ProjectForm({
    formData,
    onChange,
    validation,
    groupedUnidades,
    unidadesLoading,
    programas = [],
    programasLoading = false,
    acoes = [],
    acoesLoading = false,
    statusOptions,
    statusHint,
    onStatusChange,
    onSubmit,
}) {
    const handleChange = (field) => (event) => {
        onChange(field, event.target.value)
        validation.clearFieldError(field)
    }

    const handleStatusChange = (event) => {
        if (onStatusChange) {
            onStatusChange(event.target.value)
            return
        }

        onChange('status', event.target.value)
    }

    const programaSelecionado = formData.programa_id ? Number(formData.programa_id) : null
    const acoesFiltradas = programaSelecionado === null
        ? acoes
        : acoes.filter((acao) => Number(acao.programa_id) === programaSelecionado)
    const acaoIdsSelecionadas = Array.isArray(formData.acao_ids)
        ? formData.acao_ids.map((id) => String(id))
        : []

    return (
        <form onSubmit={onSubmit}>
            <div style={{ marginBottom: 'var(--spacing-4)' }}>
                <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                    Nome do Projeto *
                </label>
                <Input
                    value={formData.name}
                    onChange={handleChange('name')}
                    placeholder="Digite o nome do projeto"
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
                    Nome Curto
                    {formData.short_name && (
                        <span style={{
                            fontSize: '0.75rem',
                            color: formData.short_name.length > 8 ? 'var(--color-warning-500)' : 'var(--color-gray-400)',
                            marginLeft: 'var(--spacing-2)',
                            fontWeight: 'normal',
                        }}>
                            ({formData.short_name.length}/10)
                        </span>
                    )}
                </label>
                <Input
                    value={formData.short_name}
                    onChange={handleChange('short_name')}
                    placeholder="Ex: PROJ-2026"
                    style={validation.errors.short_name ? { borderColor: 'var(--color-danger-500)' } : {}}
                />
                {validation.errors.short_name && (
                    <span style={{ color: 'var(--color-danger-500)', fontSize: '0.75rem', marginTop: 'var(--spacing-1)', display: 'block' }}>
                        {validation.errors.short_name}
                    </span>
                )}
            </div>

            <div style={{ marginBottom: 'var(--spacing-4)' }}>
                <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                    Descricao
                    {formData.description && (
                        <span style={{
                            fontSize: '0.75rem',
                            color: formData.description.length > 900 ? 'var(--color-warning-500)' : 'var(--color-gray-400)',
                            marginLeft: 'var(--spacing-2)',
                            fontWeight: 'normal',
                        }}>
                            ({formData.description.length}/1000)
                        </span>
                    )}
                </label>
                <textarea
                    value={formData.description}
                    onChange={handleChange('description')}
                    placeholder="Descricao do projeto"
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

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 'var(--spacing-4)' }}>
                <div>
                    <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                        Data de Inicio
                    </label>
                    <Input
                        type="date"
                        value={formData.start_date}
                        onChange={(event) => {
                            onChange('start_date', event.target.value)
                            validation.clearFieldError('dates')
                        }}
                        style={{ width: '100%' }}
                    />
                </div>

                <div>
                    <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                        Data de Termino
                    </label>
                    <Input
                        type="date"
                        value={formData.end_date}
                        onChange={(event) => {
                            onChange('end_date', event.target.value)
                            validation.clearFieldError('dates')
                        }}
                        min={formData.start_date || undefined}
                        style={{
                            width: '100%',
                            ...(validation.errors.dates ? { borderColor: 'var(--color-danger-500)' } : {}),
                        }}
                    />
                </div>
            </div>
            {validation.errors.dates && (
                <span style={{ color: 'var(--color-danger-500)', fontSize: '0.75rem', marginTop: 'var(--spacing-2)', display: 'block' }}>
                    {validation.errors.dates}
                </span>
            )}

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: 'var(--spacing-4)', marginTop: 'var(--spacing-4)' }}>
                <div>
                    <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                        Unidade Responsavel {groupedUnidades.length > 0 ? '*' : ''}
                    </label>
                    <select
                        value={formData.company_id}
                        onChange={(event) => {
                            onChange('company_id', event.target.value)
                            validation.clearFieldError('company_id')
                        }}
                        disabled={unidadesLoading}
                        style={{
                            width: '100%',
                            padding: 'var(--spacing-2) var(--spacing-3)',
                            border: validation.errors.company_id ? '1px solid var(--color-danger-500)' : '1px solid var(--color-gray-300)',
                            borderRadius: 'var(--radius-md)',
                            fontSize: '0.875rem',
                            background: 'white',
                        }}
                    >
                        <option value="">
                            {unidadesLoading ? 'Carregando unidades...' : 'Selecione uma unidade'}
                        </option>
                        {groupedUnidades.map((grupo, index) => (
                            <optgroup key={`${grupo.label}-${index}`} label={grupo.label}>
                                {grupo.options.map((unidade) => (
                                    <option key={unidade.id} value={unidade.id}>
                                        {unidade.label}
                                    </option>
                                ))}
                            </optgroup>
                        ))}
                    </select>
                    {validation.errors.company_id && (
                        <span style={{ color: 'var(--color-danger-500)', fontSize: '0.75rem', marginTop: 'var(--spacing-1)', display: 'block' }}>
                            {validation.errors.company_id}
                        </span>
                    )}
                </div>

                <div>
                    <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                        Programa
                    </label>
                    <select
                        value={formData.programa_id}
                        onChange={(event) => {
                            const nextProgramaId = event.target.value
                            onChange('programa_id', nextProgramaId)
                            if (acaoIdsSelecionadas.length > 0) {
                                const idsPermitidas = new Set(
                                    acoes
                                        .filter((acao) => !nextProgramaId || String(acao.programa_id) === nextProgramaId)
                                        .map((acao) => String(acao.id))
                                )
                                const idsFiltradas = acaoIdsSelecionadas.filter((id) => idsPermitidas.has(id))
                                if (idsFiltradas.length !== acaoIdsSelecionadas.length) {
                                    onChange('acao_ids', idsFiltradas)
                                }
                            }
                        }}
                        disabled={programasLoading}
                        style={{
                            width: '100%',
                            padding: 'var(--spacing-2) var(--spacing-3)',
                            border: '1px solid var(--color-gray-300)',
                            borderRadius: 'var(--radius-md)',
                            fontSize: '0.875rem',
                            background: 'white',
                        }}
                    >
                        <option value="">
                            {programasLoading ? 'Carregando programas...' : 'Sem vinculo'}
                        </option>
                        {programas.map((programa) => (
                            <option key={programa.id} value={String(programa.id)}>
                                {programa.codigo ? `${programa.codigo} - ` : ''}{programa.nome}
                            </option>
                        ))}
                    </select>
                </div>

                <div>
                    <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                        Ações
                        {acaoIdsSelecionadas.length > 0 && (
                            <span style={{ marginLeft: 'var(--spacing-2)', fontSize: '0.75rem', color: 'var(--color-gray-500)', fontWeight: 400 }}>
                                ({acaoIdsSelecionadas.length} selecionada(s))
                            </span>
                        )}
                    </label>
                    <div
                        style={{
                            border: '1px solid var(--color-gray-300)',
                            borderRadius: 'var(--radius-md)',
                            background: 'white',
                            padding: 'var(--spacing-2)',
                            maxHeight: 160,
                            overflowY: 'auto',
                        }}
                    >
                        {acoesLoading ? (
                            <div style={{ fontSize: '0.875rem', color: 'var(--color-gray-500)' }}>
                                Carregando ações...
                            </div>
                        ) : acoesFiltradas.length === 0 ? (
                            <div style={{ fontSize: '0.875rem', color: 'var(--color-gray-500)' }}>
                                Nenhuma ação disponível para o programa selecionado.
                            </div>
                        ) : (
                            acoesFiltradas.map((acao) => {
                                const value = String(acao.id)
                                const checked = acaoIdsSelecionadas.includes(value)
                                return (
                                    <label
                                        key={acao.id}
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 'var(--spacing-2)',
                                            padding: 'var(--spacing-1) 0',
                                            fontSize: '0.875rem',
                                            cursor: 'pointer',
                                        }}
                                    >
                                        <input
                                            type="checkbox"
                                            checked={checked}
                                            onChange={(event) => {
                                                const nextValues = event.target.checked
                                                    ? [...acaoIdsSelecionadas, value]
                                                    : acaoIdsSelecionadas.filter((id) => id !== value)
                                                onChange('acao_ids', nextValues)
                                            }}
                                        />
                                        <span>{acao.codigo ? `${acao.codigo} - ` : ''}{acao.nome}</span>
                                    </label>
                                )
                            })
                        )}
                    </div>
                </div>

                <div>
                    <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                        {onStatusChange ? 'Status do Projeto' : 'Status Inicial'}
                    </label>
                    <select
                        value={formData.status}
                        onChange={handleStatusChange}
                        style={{
                            width: '100%',
                            padding: 'var(--spacing-2) var(--spacing-3)',
                            border: '1px solid var(--color-gray-300)',
                            borderRadius: 'var(--radius-md)',
                            fontSize: '0.875rem',
                            background: 'white',
                        }}
                    >
                        {statusOptions.map((status) => (
                            <option key={status} value={String(status)}>
                                {getStatusLabelById(status)}
                            </option>
                        ))}
                    </select>
                    {statusHint && (
                        <span style={{ color: 'var(--color-gray-500)', fontSize: '0.75rem', marginTop: 'var(--spacing-1)', display: 'block' }}>
                            {statusHint}
                        </span>
                    )}
                </div>
            </div>
        </form>
    )
}
