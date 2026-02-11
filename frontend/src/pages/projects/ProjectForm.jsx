/**
 * ProjectForm — shared form for creating and editing projects.
 */

import Input from '../../components/ui/Input'
import { getStatusLabelById } from './projectStatusUtils'

export default function ProjectForm({
    formData,
    onChange,
    validation,
    groupedUnidades,
    unidadesLoading,
    statusOptions,
    statusHint,
    onStatusChange,
    onSubmit
}) {
    const handleChange = (field) => (e) => {
        onChange(field, e.target.value)
        validation.clearFieldError(field)
    }

    const handleStatusChange = (e) => {
        if (onStatusChange) {
            onStatusChange(e.target.value)
        } else {
            onChange('status', e.target.value)
        }
    }

    return (
        <form onSubmit={onSubmit}>
            {/* Nome do Projeto */}
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

            {/* Nome Curto */}
            <div style={{ marginBottom: 'var(--spacing-4)' }}>
                <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                    Nome Curto
                    {formData.short_name && (
                        <span style={{
                            fontSize: '0.75rem',
                            color: formData.short_name.length > 8 ? 'var(--color-warning-500)' : 'var(--color-gray-400)',
                            marginLeft: 'var(--spacing-2)',
                            fontWeight: 'normal'
                        }}>
                            ({formData.short_name.length}/10)
                        </span>
                    )}
                </label>
                <Input
                    value={formData.short_name}
                    onChange={handleChange('short_name')}
                    placeholder="Ex: PROJ-2024"
                    style={validation.errors.short_name ? { borderColor: 'var(--color-danger-500)' } : {}}
                />
                {validation.errors.short_name && (
                    <span style={{ color: 'var(--color-danger-500)', fontSize: '0.75rem', marginTop: 'var(--spacing-1)', display: 'block' }}>
                        {validation.errors.short_name}
                    </span>
                )}
            </div>

            {/* Descrição */}
            <div style={{ marginBottom: 'var(--spacing-4)' }}>
                <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                    Descrição
                    {formData.description && (
                        <span style={{
                            fontSize: '0.75rem',
                            color: formData.description.length > 900 ? 'var(--color-warning-500)' : 'var(--color-gray-400)',
                            marginLeft: 'var(--spacing-2)',
                            fontWeight: 'normal'
                        }}>
                            ({formData.description.length}/1000)
                        </span>
                    )}
                </label>
                <textarea
                    value={formData.description}
                    onChange={handleChange('description')}
                    placeholder="Descrição do projeto"
                    rows={3}
                    style={{
                        width: '100%',
                        padding: 'var(--spacing-2) var(--spacing-3)',
                        border: validation.errors.description ? '1px solid var(--color-danger-500)' : '1px solid var(--color-gray-300)',
                        borderRadius: 'var(--radius-md)',
                        fontSize: '0.875rem',
                        fontFamily: 'inherit'
                    }}
                />
                {validation.errors.description && (
                    <span style={{ color: 'var(--color-danger-500)', fontSize: '0.75rem', marginTop: 'var(--spacing-1)', display: 'block' }}>
                        {validation.errors.description}
                    </span>
                )}
            </div>

            {/* Datas */}
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 'var(--spacing-4)' }}>
                <div>
                    <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                        Data de Início
                    </label>
                    <Input
                        type="date"
                        value={formData.start_date}
                        onChange={(e) => {
                            onChange('start_date', e.target.value)
                            validation.clearFieldError('dates')
                        }}
                        style={{ width: '100%' }}
                    />
                </div>

                <div>
                    <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                        Data de Término
                    </label>
                    <Input
                        type="date"
                        value={formData.end_date}
                        onChange={(e) => {
                            onChange('end_date', e.target.value)
                            validation.clearFieldError('dates')
                        }}
                        min={formData.start_date || undefined}
                        style={{
                            width: '100%',
                            ...(validation.errors.dates ? { borderColor: 'var(--color-danger-500)' } : {})
                        }}
                    />
                </div>
            </div>
            {validation.errors.dates && (
                <span style={{ color: 'var(--color-danger-500)', fontSize: '0.75rem', marginTop: 'var(--spacing-2)', display: 'block' }}>
                    {validation.errors.dates}
                </span>
            )}

            {/* Unidade + Status */}
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 'var(--spacing-4)', marginTop: 'var(--spacing-4)' }}>
                <div>
                    <label style={{ display: 'block', marginBottom: 'var(--spacing-2)', fontWeight: 500 }}>
                        Unidade Responsável {groupedUnidades.length > 0 ? '*' : ''}
                    </label>
                    <select
                        value={formData.company_id}
                        onChange={(e) => {
                            onChange('company_id', e.target.value)
                            validation.clearFieldError('company_id')
                        }}
                        disabled={unidadesLoading}
                        style={{
                            width: '100%',
                            padding: 'var(--spacing-2) var(--spacing-3)',
                            border: validation.errors.company_id ? '1px solid var(--color-danger-500)' : '1px solid var(--color-gray-300)',
                            borderRadius: 'var(--radius-md)',
                            fontSize: '0.875rem',
                            background: 'white'
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
                            background: 'white'
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
