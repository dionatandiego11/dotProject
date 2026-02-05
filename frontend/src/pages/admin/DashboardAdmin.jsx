function DashboardAdmin() {
    return (
        <div style={{ padding: 20 }}>
            <h1>Dashboard Administrativo</h1>
            <p>Esta é a área de administração do sistema.</p>
            
            <div style={{ 
                display: 'grid', 
                gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
                gap: 20,
                marginTop: 20
            }}>
                <div style={{ padding: 20, background: '#e0f2fe', borderRadius: 8 }}>
                    <h3>🏛️ Níveis</h3>
                    <p>Configurar níveis hierárquicos</p>
                </div>
                <div style={{ padding: 20, background: '#dcfce7', borderRadius: 8 }}>
                    <h3>🏢 Unidades</h3>
                    <p>Cadastrar unidades organizacionais</p>
                </div>
                <div style={{ padding: 20, background: '#f3e8ff', borderRadius: 8 }}>
                    <h3>👥 Usuários</h3>
                    <p>Gerenciar vínculos de usuários</p>
                </div>
                <div style={{ padding: 20, background: '#fef9c3', borderRadius: 8 }}>
                    <h3>🔐 Permissões</h3>
                    <p>Configurar matriz de permissões</p>
                </div>
            </div>
        </div>
    )
}

export default DashboardAdmin
