/**
 * Kanban Page
 * 
 * Página de visualização do quadro Kanban.
 */

import { useState, useEffect } from 'react'
import { useSearchParams, useNavigate } from 'react-router-dom'
import { KanbanBoard } from '../components/kanban'
import Loading from '../components/Loading'
import { getKanbanBoards, createKanbanBoard } from '../services/api'

function Kanban() {
    const [searchParams, setSearchParams] = useSearchParams()
    const navigate = useNavigate()
    const [boards, setBoards] = useState([])
    const [selectedBoard, setSelectedBoard] = useState(null)
    const [loading, setLoading] = useState(true)
    const [error, setError] = useState(null)
    const [showNewBoardModal, setShowNewBoardModal] = useState(false)
    const [newBoardName, setNewBoardName] = useState('')

    const boardId = searchParams.get('board')

    // Carrega lista de boards
    useEffect(() => {
        loadBoards()
    }, [])

    // Seleciona board da URL ou primeiro disponível
    useEffect(() => {
        if (boards.length > 0 && !selectedBoard) {
            const board = boardId 
                ? boards.find(b => b.id === parseInt(boardId))
                : boards[0]
            
            if (board) {
                setSelectedBoard(board)
                if (!boardId) {
                    setSearchParams({ board: board.id.toString() })
                }
            }
        }
    }, [boards, boardId])

    const loadBoards = async () => {
        try {
            setLoading(true)
            const result = await getKanbanBoards()

            if (result.success) {
                setBoards(result.data.boards || [])
            } else {
                setError(result.message || 'Erro ao carregar boards')
            }
        } catch (err) {
            setError('Erro de conexão')
        } finally {
            setLoading(false)
        }
    }

    const handleBoardChange = (boardId) => {
        const board = boards.find(b => b.id === parseInt(boardId))
        if (board) {
            setSelectedBoard(board)
            setSearchParams({ board: boardId.toString() })
        }
    }

    const handleCreateBoard = async (e) => {
        e.preventDefault()
        if (!newBoardName.trim()) return

        try {
            const result = await createKanbanBoard({
                name: newBoardName,
                company_id: 1 // TODO: Pegar do contexto
            })

            if (result.success) {
                setNewBoardName('')
                setShowNewBoardModal(false)
                await loadBoards()
                setSearchParams({ board: result.data.id.toString() })
            }
        } catch (err) {
            console.error('Erro ao criar board:', err)
        }
    }

    const handleTaskClick = (kanbanTask) => {
        if (kanbanTask.task_id) {
            navigate(`/tasks/${kanbanTask.task_id}`)
        }
    }

    if (loading) {
        return <Loading fullScreen />
    }

    if (error) {
        return (
            <div style={{ 
                display: 'flex', 
                flexDirection: 'column',
                alignItems: 'center', 
                justifyContent: 'center',
                height: '100%',
                gap: '1rem'
            }}>
                <div style={{ color: '#dc2626', fontSize: '1.125rem' }}>
                    {error}
                </div>
                <button 
                    onClick={loadBoards}
                    style={{
                        padding: '0.5rem 1rem',
                        backgroundColor: '#3b82f6',
                        color: 'white',
                        border: 'none',
                        borderRadius: '0.375rem',
                        cursor: 'pointer'
                    }}
                >
                    Tentar novamente
                </button>
            </div>
        )
    }

    return (
        <div style={{ height: '100%', display: 'flex', flexDirection: 'column' }}>
            {/* Header do Kanban */}
            <div style={{
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'center',
                marginBottom: '1rem',
                padding: '0 0.5rem'
            }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
                    <h1 style={{ 
                        fontSize: '1.5rem', 
                        fontWeight: 600,
                        margin: 0 
                    }}>
                        Kanban Board
                    </h1>

                    {/* Selector de Board */}
                    {boards.length > 0 && (
                        <select
                            value={selectedBoard?.id || ''}
                            onChange={(e) => handleBoardChange(e.target.value)}
                            style={{
                                padding: '0.5rem 1rem',
                                border: '1px solid #d1d5db',
                                borderRadius: '0.375rem',
                                fontSize: '0.875rem',
                                backgroundColor: 'white',
                                cursor: 'pointer'
                            }}
                        >
                            {boards.map(board => (
                                <option key={board.id} value={board.id}>
                                    {board.name}
                                </option>
                            ))}
                        </select>
                    )}
                </div>

                <button
                    onClick={() => setShowNewBoardModal(true)}
                    style={{
                        padding: '0.5rem 1rem',
                        backgroundColor: '#3b82f6',
                        color: 'white',
                        border: 'none',
                        borderRadius: '0.375rem',
                        fontSize: '0.875rem',
                        fontWeight: 500,
                        cursor: 'pointer',
                        display: 'flex',
                        alignItems: 'center',
                        gap: '0.5rem'
                    }}
                >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <line x1="12" y1="5" x2="12" y2="19" />
                        <line x1="5" y1="12" x2="19" y2="12" />
                    </svg>
                    Novo Board
                </button>
            </div>

            {/* Kanban Board */}
            {selectedBoard ? (
                <div style={{ flex: 1, overflow: 'hidden' }}>
                    <KanbanBoard 
                        boardId={selectedBoard.id}
                        onTaskClick={handleTaskClick}
                    />
                </div>
            ) : (
                <div style={{
                    display: 'flex',
                    flexDirection: 'column',
                    alignItems: 'center',
                    justifyContent: 'center',
                    height: '100%',
                    gap: '1rem',
                    color: '#6b7280'
                }}>
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                        <rect x="3" y="3" width="6" height="18" rx="2" />
                        <rect x="10" y="3" width="6" height="18" rx="2" />
                        <rect x="17" y="3" width="4" height="18" rx="2" />
                    </svg>
                    <p>Nenhum board encontrado</p>
                    <button
                        onClick={() => setShowNewBoardModal(true)}
                        style={{
                            padding: '0.5rem 1rem',
                            backgroundColor: '#3b82f6',
                            color: 'white',
                            border: 'none',
                            borderRadius: '0.375rem',
                            cursor: 'pointer'
                        }}
                    >
                        Criar primeiro board
                    </button>
                </div>
            )}

            {/* Modal de Novo Board */}
            {showNewBoardModal && (
                <div style={{
                    position: 'fixed',
                    top: 0,
                    left: 0,
                    right: 0,
                    bottom: 0,
                    backgroundColor: 'rgba(0, 0, 0, 0.5)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    zIndex: 1000
                }}>
                    <div style={{
                        backgroundColor: 'white',
                        padding: '1.5rem',
                        borderRadius: '0.5rem',
                        width: '100%',
                        maxWidth: '400px'
                    }}>
                        <h2 style={{ marginTop: 0, marginBottom: '1rem' }}>
                            Novo Board
                        </h2>
                        <form onSubmit={handleCreateBoard}>
                            <div style={{ marginBottom: '1rem' }}>
                                <label style={{
                                    display: 'block',
                                    fontSize: '0.875rem',
                                    fontWeight: 500,
                                    marginBottom: '0.5rem',
                                    color: '#374151'
                                }}>
                                    Nome do Board
                                </label>
                                <input
                                    type="text"
                                    value={newBoardName}
                                    onChange={(e) => setNewBoardName(e.target.value)}
                                    placeholder="Ex: Sprint 23"
                                    autoFocus
                                    style={{
                                        width: '100%',
                                        padding: '0.5rem 0.75rem',
                                        border: '1px solid #d1d5db',
                                        borderRadius: '0.375rem',
                                        fontSize: '0.875rem'
                                    }}
                                />
                            </div>
                            <div style={{
                                display: 'flex',
                                gap: '0.75rem',
                                justifyContent: 'flex-end'
                            }}>
                                <button
                                    type="button"
                                    onClick={() => setShowNewBoardModal(false)}
                                    style={{
                                        padding: '0.5rem 1rem',
                                        backgroundColor: 'white',
                                        border: '1px solid #d1d5db',
                                        borderRadius: '0.375rem',
                                        fontSize: '0.875rem',
                                        cursor: 'pointer'
                                    }}
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    disabled={!newBoardName.trim()}
                                    style={{
                                        padding: '0.5rem 1rem',
                                        backgroundColor: '#3b82f6',
                                        color: 'white',
                                        border: 'none',
                                        borderRadius: '0.375rem',
                                        fontSize: '0.875rem',
                                        cursor: newBoardName.trim() ? 'pointer' : 'not-allowed',
                                        opacity: newBoardName.trim() ? 1 : 0.5
                                    }}
                                >
                                    Criar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    )
}

export default Kanban
