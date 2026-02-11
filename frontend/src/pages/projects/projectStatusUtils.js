/**
 * Project Status Utilities
 *
 * Constants and helpers for project status management.
 */

export const PROJECT_STATUS_LABELS = {
    0: 'Nao definido',
    1: 'Proposto',
    2: 'Em planejamento',
    3: 'Em progresso',
    4: 'Em espera',
    5: 'Completo',
    6: 'Arquivado'
}

export const PROJECT_STATUS_BADGES = {
    0: 'info',
    1: 'info',
    2: 'warning',
    3: 'success',
    4: 'warning',
    5: 'success',
    6: 'info'
}

export const CREATE_ALLOWED_PROJECT_STATUSES = [0, 1, 2, 3, 4]

export const PROJECT_STATUS_TRANSITIONS = {
    0: [1, 2, 3, 4],
    1: [0, 2, 3, 4],
    2: [0, 1, 3, 4],
    3: [4, 5],
    4: [3, 5],
    5: [3, 6],
    6: []
}

export function getStatusLabelById(status) {
    return PROJECT_STATUS_LABELS[Number(status)] || 'Desconhecido'
}

export function getStatusBadgeById(status) {
    return PROJECT_STATUS_BADGES[Number(status)] || 'info'
}

export function getAllowedProjectStatuses(currentStatus, options = {}) {
    const includeCurrent = options.includeCurrent !== false
    const forCreate = options.forCreate === true

    if (forCreate) {
        return [...CREATE_ALLOWED_PROJECT_STATUSES]
    }

    const numericCurrent = Number(currentStatus)
    if (Number.isNaN(numericCurrent)) {
        return [...CREATE_ALLOWED_PROJECT_STATUSES]
    }

    const transitions = PROJECT_STATUS_TRANSITIONS[numericCurrent] || []
    const statuses = includeCurrent ? [numericCurrent, ...transitions] : [...transitions]

    return Array.from(new Set(statuses)).sort((a, b) => a - b)
}

export function canTransitionProjectStatus(currentStatus, targetStatus) {
    const numericCurrent = Number(currentStatus)
    const numericTarget = Number(targetStatus)

    if (Number.isNaN(numericCurrent) || Number.isNaN(numericTarget)) {
        return false
    }

    if (numericCurrent === numericTarget) {
        return true
    }

    const transitions = PROJECT_STATUS_TRANSITIONS[numericCurrent] || []
    return transitions.includes(numericTarget)
}

export function getNumericProjectStatus(project) {
    const numeric = Number(project?.status)
    return Number.isNaN(numeric) ? 0 : numeric
}

export function normalizeDateValue(value) {
    if (!value) return ''
    if (typeof value === 'string') {
        return value.substring(0, 10)
    }
    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return ''
    return date.toISOString().substring(0, 10)
}

export function buildUnidadeHierarchy(unidade, unidadeById) {
    const hierarchy = []
    const visited = new Set()
    let current = unidade

    while (current && !visited.has(current.id)) {
        hierarchy.unshift(current)
        visited.add(current.id)
        const parentId = current.pai_id
        current = parentId ? unidadeById.get(parentId) || null : null
    }

    return hierarchy
}

export function buildGroupedUnidades(unidades) {
    const unidadeById = new Map()
    const collator = new Intl.Collator('pt-BR', { sensitivity: 'base', numeric: true })

    for (const rawUnidade of unidades) {
        if (!rawUnidade || rawUnidade.id === null || rawUnidade.id === undefined) continue
        const id = Number(rawUnidade.id)
        const paiId = rawUnidade.pai_id === null || rawUnidade.pai_id === undefined
            ? null
            : Number(rawUnidade.pai_id)

        unidadeById.set(id, {
            ...rawUnidade,
            id,
            pai_id: paiId
        })
    }

    const grouped = new Map()

    for (const unidade of unidadeById.values()) {
        const hierarchy = buildUnidadeHierarchy(unidade, unidadeById)
        const secretariaNode = hierarchy.find((node) => node.nivel === 2)
        const groupLabel = secretariaNode?.nome || 'Demais unidades'

        let relativeHierarchy = hierarchy
        if (secretariaNode) {
            const index = hierarchy.findIndex((node) => node.id === secretariaNode.id)
            relativeHierarchy = hierarchy.slice(index)
        }

        const path = hierarchy.map((node) => node.nome).join(' > ')
        const pathLabel = relativeHierarchy.map((node) => node.nome).join(' > ') || unidade.nome
        const levelLabel = unidade.nivel_label ? ` (${unidade.nivel_label})` : ''
        const option = {
            id: unidade.id,
            label: `${pathLabel}${levelLabel}`,
            path,
            nivel: unidade.nivel ?? Number.MAX_SAFE_INTEGER
        }

        if (!grouped.has(groupLabel)) {
            grouped.set(groupLabel, [])
        }

        grouped.get(groupLabel).push(option)
    }

    return Array.from(grouped.entries())
        .map(([label, options]) => ({
            label,
            options: options.sort((a, b) => {
                if (a.nivel !== b.nivel) {
                    return a.nivel - b.nivel
                }

                return collator.compare(a.path, b.path)
            })
        }))
        .sort((a, b) => collator.compare(a.label, b.label))
}
