export function isAdminUser(userData) {
    if (!userData) {
        return false
    }

    const role = typeof userData.role === 'string' ? userData.role.toLowerCase() : ''
    const profile = typeof userData.profile === 'string' ? userData.profile.toLowerCase() : ''

    return (
        role === 'admin' ||
        role === 'administrador' ||
        profile === 'admin' ||
        userData.is_admin === true ||
        userData.is_admin === 1 ||
        userData.user_id === 1 ||
        userData.id === 1 ||
        userData.nivel_acesso === 1 ||
        userData.user_type === 1
    )
}

export function detectProfile(userData) {
    if (!userData) return 'usuario'
    // Prioridade: profile > role > cargo > nivel_acesso
    if (userData.profile) return userData.profile.toLowerCase()
    if (userData.role) return userData.role.toLowerCase()
    if (userData.cargo) return userData.cargo.toLowerCase()
    if (userData.nivel_acesso) {
        const nivel = parseInt(userData.nivel_acesso, 10)
        switch (nivel) {
            case 1:
                return 'prefeito'
            case 2:
                return 'secretario'
            case 3:
                return 'coordenador'
            case 4:
                return 'tecnico'
            case 5:
                return 'controlador'
            default:
                return 'usuario'
        }
    }

    return 'usuario'
}
