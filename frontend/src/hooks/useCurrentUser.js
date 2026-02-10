import { useCallback, useEffect, useState } from 'react'
import {
    clearCurrentUser,
    getCurrentUserSnapshot,
    loadCurrentUser,
    subscribeCurrentUser,
} from '../services/state/currentUserStore'

export default function useCurrentUser({ autoLoad = true } = {}) {
    const [snapshot, setSnapshot] = useState(() => getCurrentUserSnapshot())

    useEffect(() => subscribeCurrentUser(setSnapshot), [])

    useEffect(() => {
        if (!autoLoad) {
            return
        }

        loadCurrentUser().catch(() => {
            // Error is already stored in snapshot.
        })
    }, [autoLoad])

    const reload = useCallback(() => loadCurrentUser({ force: true }), [])
    const clear = useCallback(() => clearCurrentUser(), [])

    return {
        ...snapshot,
        reload,
        clear,
    }
}
