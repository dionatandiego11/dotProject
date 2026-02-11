export function buildStatusMaps(columns) {
    const statusByColumn = new Map()
    const columnByStatus = new Map()
    const sorted = [...(columns || [])].sort((a, b) => (a.order || 0) - (b.order || 0))
    const firstActive = sorted.find((col) => !col.is_backlog && !col.is_done)

    sorted.forEach((col) => {
        const columnId = Number(col.id)
        let status = 2
        if (col.is_backlog) status = 0
        else if (col.is_done) status = 3
        else if (firstActive && col.id === firstActive.id) status = 1

        statusByColumn.set(columnId, status)
        if (!columnByStatus.has(status)) {
            columnByStatus.set(status, columnId)
        }
    })

    return { statusByColumn, columnByStatus }
}

export function normalizeProgressForStatus(status, currentPercent) {
    const numericStatus = Number(status)
    const rawPercent = Number(currentPercent)
    const percent = Number.isFinite(rawPercent) ? rawPercent : 0

    if (numericStatus === 0) return 0
    if (numericStatus === 3) return 100
    if (numericStatus === 1) return (percent < 0 || percent > 99) ? 0 : percent
    if (numericStatus === 2) return (percent <= 0 || percent >= 100) ? 50 : percent

    return Math.max(0, Math.min(100, percent))
}
