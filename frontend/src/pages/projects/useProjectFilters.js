import { useState } from 'react'

export default function useProjectFilters({ onSearch }) {
    const [search, setSearch] = useState('')

    function handleSearchSubmit(event) {
        event.preventDefault()
        onSearch({ search })
    }

    return {
        search,
        setSearch,
        handleSearchSubmit,
    }
}
