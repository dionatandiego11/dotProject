import { useId, useMemo, useState } from 'react'
import PropTypes from 'prop-types'

function normalize(value) {
    if (value === null || value === undefined) return ''
    return String(value).toLowerCase()
}

function compareValues(a, b) {
    if (a === b) return 0
    if (a === null || a === undefined) return -1
    if (b === null || b === undefined) return 1

    const aNumber = Number(a)
    const bNumber = Number(b)
    if (!Number.isNaN(aNumber) && !Number.isNaN(bNumber)) {
        return aNumber - bNumber
    }

    return String(a).localeCompare(String(b), 'pt-BR', { sensitivity: 'base', numeric: true })
}

function getColumnValue(column, row) {
    if (typeof column.accessor === 'function') return column.accessor(row)
    if (typeof column.accessor === 'string') return row?.[column.accessor]
    return row?.[column.key]
}

export default function DataTable({
    columns,
    rows,
    rowKey = 'id',
    searchable = true,
    searchPlaceholder = 'Buscar...',
    defaultPageSize = 10,
    pageSizeOptions = [10, 20, 50],
    emptyText = 'Nenhum registro encontrado',
    tableAriaLabel = 'Tabela de dados',
}) {
    const tableId = useId()
    const [query, setQuery] = useState('')
    const [page, setPage] = useState(1)
    const [pageSize, setPageSize] = useState(defaultPageSize)
    const [sortState, setSortState] = useState({ key: null, direction: 'asc' })

    const filteredRows = useMemo(() => {
        if (!query.trim()) return rows
        const term = query.trim().toLowerCase()

        return rows.filter((row) => (
            columns.some((column) => normalize(getColumnValue(column, row)).includes(term))
        ))
    }, [rows, columns, query])

    const sortedRows = useMemo(() => {
        if (!sortState.key) return filteredRows
        const column = columns.find((item) => item.key === sortState.key)
        if (!column) return filteredRows

        const ordered = [...filteredRows].sort((a, b) => {
            const left = getColumnValue(column, a)
            const right = getColumnValue(column, b)
            const result = compareValues(left, right)
            return sortState.direction === 'asc' ? result : -result
        })

        return ordered
    }, [filteredRows, columns, sortState])

    const totalPages = Math.max(1, Math.ceil(sortedRows.length / pageSize))
    const currentPage = Math.min(page, totalPages)
    const pagedRows = useMemo(() => {
        const start = (currentPage - 1) * pageSize
        return sortedRows.slice(start, start + pageSize)
    }, [sortedRows, currentPage, pageSize])

    function toggleSort(column) {
        if (column.sortable === false) return
        setPage(1)

        setSortState((prev) => {
            if (prev.key !== column.key) {
                return { key: column.key, direction: 'asc' }
            }
            return { key: column.key, direction: prev.direction === 'asc' ? 'desc' : 'asc' }
        })
    }

    function renderSortIndicator(column) {
        if (column.sortable === false) return null
        if (sortState.key !== column.key) return '↕'
        return sortState.direction === 'asc' ? '↑' : '↓'
    }

    function getAriaSort(column) {
        if (column.sortable === false || sortState.key !== column.key) return 'none'
        return sortState.direction === 'asc' ? 'ascending' : 'descending'
    }

    return (
        <div style={{ display: 'grid', gap: '0.75rem' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', gap: '0.75rem', flexWrap: 'wrap', alignItems: 'center' }}>
                {searchable ? (
                    <div style={{ flex: '1 1 260px', minWidth: 0 }}>
                        <label
                            htmlFor={`${tableId}-search`}
                            style={{
                                position: 'absolute',
                                width: 1,
                                height: 1,
                                padding: 0,
                                margin: -1,
                                overflow: 'hidden',
                                clip: 'rect(0, 0, 0, 0)',
                                whiteSpace: 'nowrap',
                                border: 0,
                            }}
                        >
                            Buscar na tabela
                        </label>
                        <input
                            id={`${tableId}-search`}
                            type="text"
                            className="kanban-filter"
                            placeholder={searchPlaceholder}
                            value={query}
                            onChange={(event) => {
                                setQuery(event.target.value)
                                setPage(1)
                            }}
                            style={{ width: '100%', minWidth: 0 }}
                        />
                    </div>
                ) : <span />}

                <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', fontSize: '0.8125rem' }}>
                    <span>{sortedRows.length} registros</span>
                    <label htmlFor={`${tableId}-page-size`}>Itens por pagina</label>
                    <select
                        id={`${tableId}-page-size`}
                        value={pageSize}
                        onChange={(event) => {
                            setPageSize(Number(event.target.value))
                            setPage(1)
                        }}
                        style={{
                            border: '1px solid var(--color-gray-300)',
                            borderRadius: 'var(--radius-md)',
                            padding: '0.25rem 0.5rem',
                            background: 'var(--color-bg)',
                        }}
                        aria-label="Itens por pagina"
                    >
                        {pageSizeOptions.map((size) => (
                            <option key={size} value={size}>{size}/pag</option>
                        ))}
                    </select>
                </div>
            </div>

            <div style={{ overflowX: 'auto' }}>
                <table className="table" aria-label={tableAriaLabel}>
                    <thead>
                        <tr>
                            {columns.map((column) => (
                                <th
                                    key={column.key}
                                    aria-sort={getAriaSort(column)}
                                    style={{
                                        width: column.width || 'auto',
                                    }}
                                >
                                    {column.sortable === false ? (
                                        column.label
                                    ) : (
                                        <button
                                            type="button"
                                            onClick={() => toggleSort(column)}
                                            aria-label={`Ordenar por ${column.label}`}
                                            style={{
                                                display: 'inline-flex',
                                                alignItems: 'center',
                                                gap: '0.25rem',
                                                background: 'transparent',
                                                border: 'none',
                                                color: 'inherit',
                                                padding: 0,
                                                font: 'inherit',
                                                cursor: 'pointer',
                                            }}
                                        >
                                            <span>{column.label}</span>
                                            <span aria-hidden="true">{renderSortIndicator(column)}</span>
                                        </button>
                                    )}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {pagedRows.length === 0 && (
                            <tr>
                                <td colSpan={columns.length} style={{ textAlign: 'center', color: 'var(--color-gray-500)' }}>
                                    {emptyText}
                                </td>
                            </tr>
                        )}
                        {pagedRows.map((row, index) => (
                            <tr key={typeof rowKey === 'function' ? rowKey(row) : (row?.[rowKey] ?? index)}>
                                {columns.map((column) => (
                                    <td key={`${column.key}-${typeof rowKey === 'function' ? rowKey(row) : (row?.[rowKey] ?? index)}`}>
                                        {column.render ? column.render(row) : String(getColumnValue(column, row) ?? '')}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <div style={{ display: 'flex', justifyContent: 'flex-end', alignItems: 'center', gap: '0.5rem', flexWrap: 'wrap' }} aria-label="Paginacao da tabela">
                <button
                    type="button"
                    className="btn btn-secondary"
                    onClick={() => setPage((prev) => Math.max(1, prev - 1))}
                    disabled={currentPage <= 1}
                    aria-label="Pagina anterior"
                >
                    Anterior
                </button>
                <span style={{ fontSize: '0.8125rem' }}>
                    Pagina {currentPage} de {totalPages}
                </span>
                <button
                    type="button"
                    className="btn btn-secondary"
                    onClick={() => setPage((prev) => Math.min(totalPages, prev + 1))}
                    disabled={currentPage >= totalPages}
                    aria-label="Proxima pagina"
                >
                    Proxima
                </button>
            </div>
        </div>
    )
}

DataTable.propTypes = {
    columns: PropTypes.arrayOf(PropTypes.shape({
        key: PropTypes.string.isRequired,
        label: PropTypes.string.isRequired,
        accessor: PropTypes.oneOfType([PropTypes.string, PropTypes.func]),
        render: PropTypes.func,
        sortable: PropTypes.bool,
        width: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
    })).isRequired,
    rows: PropTypes.arrayOf(PropTypes.object).isRequired,
    rowKey: PropTypes.oneOfType([PropTypes.string, PropTypes.func]),
    searchable: PropTypes.bool,
    searchPlaceholder: PropTypes.string,
    defaultPageSize: PropTypes.number,
    pageSizeOptions: PropTypes.arrayOf(PropTypes.number),
    emptyText: PropTypes.string,
    tableAriaLabel: PropTypes.string,
}
