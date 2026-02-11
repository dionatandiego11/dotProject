import { useEffect, useMemo, useRef, useState } from 'react'
import {
    getProjects,
    getUsuarios,
    getUsuario,
    getCurrentUser,
    getVinculos,
    getUnidade,
    getArvoreUnidades,
    getKanbanBoards,
    getKanbanBoard,
    createKanbanBoard,
} from '../../services/api'

function normalizeUser(user) {
    if (!user) return null
    const id = user.id ?? user.user_id
    if (!id) return null

    const first = user.first_name ?? user.user_first_name ?? user.contact_first_name ?? ''
    const last = user.last_name ?? user.user_last_name ?? user.contact_last_name ?? ''
    const full = user.full_name ?? `${first} ${last}`.trim()

    return {
        ...user,
        id: Number(id),
        full_name: full || user.username || user.user_username || '',
    }
}

export default function useKanbanBoard({ searchParams, setSearchParams }) {
    const [projects, setProjects] = useState([])
    const [selectedProjectId, setSelectedProjectId] = useState(searchParams.get('project') || '')
    const [board, setBoard] = useState(null)
    const [columns, setColumns] = useState([])
    const [users, setUsers] = useState([])
    const [currentUser, setCurrentUser] = useState(null)
    const [unitUsers, setUnitUsers] = useState([])
    const [unitUserIds, setUnitUserIds] = useState(new Set())
    const [loading, setLoading] = useState(true)
    const [error, setError] = useState(null)
    const vinculosCache = useRef(new Map())

    const [filterText, setFilterText] = useState('')
    const [filterOwner, setFilterOwner] = useState('all')
    const [filterPriority, setFilterPriority] = useState('all')
    const [onlyMine, setOnlyMine] = useState(false)
    const [overdueOnly, setOverdueOnly] = useState(false)
    const [hideCompleted, setHideCompleted] = useState(false)

    useEffect(() => {
        loadProjects()
        loadUsers()
        loadCurrentUser()
    }, [])

    useEffect(() => {
        const projectParam = searchParams.get('project')
        if (projectParam !== null && projectParam !== selectedProjectId) {
            setSelectedProjectId(projectParam)
        }
    }, [searchParams])

    useEffect(() => {
        if (!selectedProjectId) {
            setBoard(null)
            setColumns([])
            setLoading(false)
            return
        }
        loadBoard(selectedProjectId)
    }, [selectedProjectId, projects])

    useEffect(() => {
        if (!selectedProjectId) {
            setUnitUsers([])
            setUnitUserIds(new Set())
            return
        }

        const project = projects.find((p) => String(p.id) === String(selectedProjectId))
        const unitId = project?.unidade?.id || project?.unidade_id || project?.company?.id || project?.company_id
        if (!unitId) {
            setUnitUsers([])
            setUnitUserIds(new Set())
            return
        }

        loadUnitUsers(unitId)
    }, [selectedProjectId, projects, users])

    useEffect(() => {
        if (filterOwner !== 'all' && !unitUserIds.has(parseInt(filterOwner, 10))) {
            setFilterOwner('all')
        }
    }, [unitUserIds, filterOwner])

    async function loadProjects() {
        try {
            const data = await getProjects({ per_page: 200 })
            setProjects(data.data || [])
        } catch (err) {
            console.error('Erro ao carregar projetos:', err)
        }
    }

    async function loadUsers() {
        try {
            const data = await getUsuarios()
            const list = (data.data || []).map(normalizeUser).filter(Boolean)
            setUsers(list)
        } catch (err) {
            console.error('Erro ao carregar usuarios:', err)
        }
    }

    async function loadCurrentUser() {
        try {
            const data = await getCurrentUser()
            setCurrentUser(data || null)
        } catch (err) {
            console.error('Erro ao carregar usuario atual:', err)
        }
    }

    async function loadBoard(projectId) {
        try {
            setLoading(true)
            setError(null)

            const boardsResult = await getKanbanBoards(projectId)
            if (boardsResult?.success === false) {
                throw new Error(boardsResult?.message || 'Falha ao carregar boards')
            }

            const boards = boardsResult?.data?.boards || boardsResult?.boards || []
            const projectBoard = boards.find((item) => String(item?.project_id) === String(projectId))

            let resolvedBoardId = projectBoard?.id || null
            if (!resolvedBoardId) {
                const project = projects.find((item) => String(item.id) === String(projectId))
                const projectUnitIdRaw = project?.unidade?.id || project?.unidade_id || project?.company?.id || project?.company_id
                const projectUnitId = projectUnitIdRaw ? parseInt(projectUnitIdRaw, 10) : null

                const createResult = await createKanbanBoard({
                    name: project?.name ? `Kanban - ${project.name}` : 'Kanban',
                    project_id: parseInt(projectId, 10),
                    ...(projectUnitId ? { unidade_id: projectUnitId } : {}),
                })

                if (!createResult?.success) {
                    throw new Error(createResult?.message || 'Falha ao criar board')
                }

                resolvedBoardId = createResult?.data?.id || createResult?.data?.board_id || null
            }

            if (!resolvedBoardId) {
                setBoard(null)
                setColumns([])
                return null
            }

            const boardResult = await getKanbanBoard(resolvedBoardId)
            if (!boardResult?.success) {
                throw new Error(boardResult?.message || 'Falha ao carregar board')
            }

            const boardData = boardResult?.data?.board || boardResult?.data || null
            const columnsData = boardResult?.data?.columns || boardData?.columns || []
            const normalizedColumns = (columnsData || [])
                .map((col) => ({
                    ...col,
                    tasks: [...(col.tasks || [])].sort((a, b) => (a.order || 0) - (b.order || 0)),
                }))
                .sort((a, b) => (a.order || 0) - (b.order || 0))

            setBoard(boardData)
            setColumns(normalizedColumns)
            return { board: boardData, columns: normalizedColumns }
        } catch (err) {
            const message = err instanceof Error && err.message ? err.message : 'Erro de conexao'
            setError(message)
            setBoard(null)
            setColumns([])
            return null
        } finally {
            setLoading(false)
        }
    }

    async function loadUnitUsers(unitId) {
        try {
            const ids = new Set()
            const responsavelIds = new Set()
            let treeLoaded = false
            let unitIds = [unitId]

            try {
                const tree = await getArvoreUnidades(unitId)
                const nodes = Array.isArray(tree?.data) ? tree.data : []
                const collect = (list) => {
                    list.forEach((node) => {
                        if (node?.id) unitIds.push(node.id)
                        const respId = node?.responsavel_id ?? node?.responsavel?.id
                        if (respId) responsavelIds.add(Number(respId))
                        if (node?.filhas?.length) collect(node.filhas)
                    })
                }

                if (nodes.length > 0) {
                    treeLoaded = true
                    collect(nodes)
                }
            } catch (_) {
                // fallback para unidade base
            }

            const uniqueIds = Array.from(new Set(unitIds))
            const vinculosList = await Promise.all(uniqueIds.map(async (id) => {
                if (vinculosCache.current.has(id)) {
                    return vinculosCache.current.get(id)
                }
                try {
                    const data = await getVinculos({ unidade_id: id })
                    const list = data.data || []
                    vinculosCache.current.set(id, list)
                    return list
                } catch {
                    return []
                }
            }))

            vinculosList.flat().forEach((item) => {
                if (item?.vinculo_user_id) ids.add(Number(item.vinculo_user_id))
            })

            if (!treeLoaded) {
                try {
                    const unidade = await getUnidade(unitId)
                    const fallbackResp = (
                        unidade?.data?.responsavel_id ||
                        unidade?.data?.responsavel?.id ||
                        unidade?.responsavel_id ||
                        unidade?.responsavel?.id ||
                        null
                    )
                    if (fallbackResp) responsavelIds.add(Number(fallbackResp))
                } catch {
                    // ignore
                }
            }

            responsavelIds.forEach((id) => ids.add(id))

            setUnitUserIds(ids)
            let filtered = users.filter((user) => ids.has(Number(user.id)))

            if (filtered.length < ids.size) {
                const missing = Array.from(ids).filter((id) => !filtered.some((u) => Number(u.id) === Number(id)))
                if (missing.length > 0) {
                    const fetched = await Promise.all(missing.map(async (id) => {
                        try {
                            const data = await getUsuario(id)
                            return normalizeUser(data?.data || null)
                        } catch {
                            return null
                        }
                    }))
                    filtered = [...filtered, ...fetched.filter(Boolean)]
                }
            }

            setUnitUsers(filtered)
        } catch (err) {
            console.error('Erro ao carregar usuarios vinculados:', err)
            setUnitUserIds(new Set())
            setUnitUsers([])
        }
    }

    function handleProjectChange(projectId) {
        setSelectedProjectId(projectId)
        const params = new URLSearchParams(searchParams)
        if (projectId) params.set('project', projectId)
        else params.delete('project')
        setSearchParams(params)
    }

    const filteredColumns = useMemo(() => {
        const text = filterText.trim().toLowerCase()
        const ownerId = filterOwner !== 'all' ? parseInt(filterOwner, 10) : null
        const priority = filterPriority !== 'all' ? parseInt(filterPriority, 10) : null
        const onlyMineId = onlyMine && currentUser?.id ? Number(currentUser.id) : null

        return (columns || []).map((column) => {
            const tasks = (column.tasks || []).filter((item) => {
                const taskData = item?.task || {}
                if (text) {
                    const values = [taskData.name, taskData.description, taskData.assigned_to_name]
                        .filter(Boolean)
                        .join(' ')
                        .toLowerCase()
                    if (!values.includes(text)) return false
                }

                if (ownerId !== null && Number(taskData.assigned_to) !== ownerId) return false
                if (priority !== null && Number(taskData.priority) !== priority + 1) return false
                if (onlyMineId !== null && Number(taskData.assigned_to) !== onlyMineId) return false
                if (overdueOnly && !taskData.is_overdue) return false
                if (hideCompleted && (column.is_done || Number(taskData.percent_complete || 0) >= 100)) return false

                return true
            })

            const taskCount = tasks.length
            const averageProgress = taskCount > 0
                ? Math.round((tasks.reduce((acc, item) => acc + (item.task?.percent_complete || 0), 0) / taskCount) * 10) / 10
                : 0

            return {
                ...column,
                tasks,
                task_count: taskCount,
                average_progress: averageProgress,
            }
        })
    }, [columns, filterText, filterOwner, filterPriority, onlyMine, overdueOnly, hideCompleted, currentUser])

    const filteredStats = useMemo(() => {
        return filteredColumns.reduce((acc, column) => {
            acc.total += column.task_count || 0
            if (column.tasks?.length) {
                acc.overdue += column.tasks.filter((item) => item?.task?.is_overdue).length
            }
            return acc
        }, { total: 0, overdue: 0 })
    }, [filteredColumns])

    return {
        projects,
        selectedProjectId,
        board,
        columns,
        setColumns,
        unitUsers,
        currentUser,
        loading,
        error,
        loadBoard,
        handleProjectChange,
        filteredColumns,
        filteredStats,
        filterText,
        setFilterText,
        filterOwner,
        setFilterOwner,
        filterPriority,
        setFilterPriority,
        onlyMine,
        setOnlyMine,
        overdueOnly,
        setOverdueOnly,
        hideCompleted,
        setHideCompleted,
    }
}
