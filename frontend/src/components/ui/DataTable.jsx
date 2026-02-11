import { useMemo, useState } from 'react'
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
}) {
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
        if (sortState.key !== column.key) return ' <->'
        return sortState.direction === 'asc' ? ' ^' : ' v'
    }

    return (
        <div style={{ display: 'grid', gap: '0.75rem' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', gap: '0.75rem', flexWrap: 'wrap' }}>
                {searchable ? (
                    <input
                        type="text"
                        className="kanban-filter"
                        placeholder={searchPlaceholder}
                        value={query}
                        onChange={(event) => {
                            setQuery(event.target.value)
                            setPage(1)
                        }}
                        style={{ minWidth: 240 }}
                    />
                ) : <span />}

                <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', fontSize: '0.8125rem' }}>
                    <span>{sortedRows.length} registros</span>
                    <select
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
                    >
                        {pageSizeOptions.map((size) => (
                            <option key={size} value={size}>{size}/pag</option>
                        ))}
                    </select>
                </div>
            </div>

            <div style={{ overflowX: 'auto' }}>
                <table className="table">
                    <thead>
                        <tr>
                            {columns.map((column) => (
                                <th
                                    key={column.key}
                                    onClick={() => toggleSort(column)}
                                    style={{
                                        cursor: column.sortable === false ? 'default' : 'pointer',
                                        width: column.width || 'auto',
                                    }}
                                >
                                    {column.label}
                                    {renderSortIndicator(column)}
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

            <div style={{ display: 'flex', justifyContent: 'flex-end', alignItems: 'center', gap: '0.5rem' }}>
                <button
                    type="button"
                    className="btn btn-secondary"
                    onClick={() => setPage((prev) => Math.max(1, prev - 1))}
                    disabled={currentPage <= 1}
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
}
