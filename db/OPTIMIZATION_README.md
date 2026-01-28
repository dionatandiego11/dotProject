# 🚀 Guia de Otimização do Banco de Dados

> Instruções para aplicar índices e otimizar queries do dotProject

---

## 📋 Índices de Performance

### Aplicar Índices

```bash
# Entrar no container do banco
docker-compose exec mariadb mysql -u root -p dotproject

# Ou aplicar diretamente
docker-compose exec -T mariadb mysql -u root -p dotproject < db/optimization_indexes.sql
```

### Verificar Índices Criados

```sql
SHOW INDEX FROM dotp_projects;
SHOW INDEX FROM dotp_tasks;
```

---

## 🔍 Queries Otimizadas

O arquivo `optimization_queries.sql` contém queries otimizadas para:

1. **Dashboard** - Resumo do usuário (única query)
2. **Listagem de Projetos** - Com progresso agregado
3. **Tarefas do Usuário** - Com detalhes e urgência
4. **Relatório de Produtividade** - Por usuário
5. **Projetos Atrasados** - Alertas
6. **Atividade Recente** - Timeline unificada
7. **Burndown Chart** - Dados para gráficos
8. **Velocity do Time** - Métricas ágeis
9. **Busca Full-Text** - Pesquisa em projetos
10. **Estatísticas** - Overview do sistema

---

## ⚡ Explicação das Otimizações

### Índices Criados

| Tabela | Índice | Propósito |
|--------|--------|-----------|
| dotp_projects | idx_projects_status_owner | Listagem por status/dono |
| dotp_projects | ft_projects_search | Busca full-text |
| dotp_tasks | idx_tasks_project_status | Filtro projeto+status |
| dotp_tasks | idx_tasks_assignee | Tarefas do usuário |
| dotp_tasks | idx_tasks_overdue | Alertas de atraso |
| dotp_users | idx_users_username | Login rápido |

### Queries Otimizadas

**Antes (N+1 problem):**
```php
foreach ($projects as $p) {
    $tasks = $db->fetchAll("SELECT * FROM tasks WHERE project_id = ?", [$p['id']]);
}
// N+1 queries!
```

**Depois (JOIN):**
```sql
SELECT p.*, COUNT(t.task_id) as task_count
FROM projects p
LEFT JOIN tasks t ON t.project_id = p.id
GROUP BY p.id;
// 1 query apenas!
```

---

## 📊 Monitoramento

### Verificar Uso de Índices

```sql
-- Índices não usados
SELECT 
    table_name,
    index_name,
    rows_selected
FROM performance_schema.table_io_waits_summary_by_index_usage
WHERE index_name IS NOT NULL
ORDER BY rows_selected DESC;
```

### Queries Lentas

```sql
-- Habilitar log de queries lentas
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 2;

-- Ver queries lentas
SELECT * FROM mysql.slow_log 
ORDER BY start_time DESC 
LIMIT 10;
```

### Estatísticas de Tabelas

```sql
-- Tamanho das tabelas
SELECT 
    table_name,
    ROUND(data_length / 1024 / 1024, 2) AS data_size_mb,
    ROUND(index_length / 1024 / 1024, 2) AS index_size_mb,
    table_rows
FROM information_schema.tables
WHERE table_schema = 'dotproject'
ORDER BY data_length DESC;
```

---

## 🎯 Checklist de Otimização

- [ ] Aplicar índices do `optimization_indexes.sql`
- [ ] Atualizar queries no código (usar queries otimizadas)
- [ ] Configurar Redis para cache
- [ ] Monitorar performance antes/depois
- [ ] Ajustar parâmetros do MySQL se necessário

---

## 🚀 Resultados Esperados

| Métrica | Antes | Depois |
|---------|-------|--------|
| Tempo dashboard | ~500ms | <100ms |
| Listagem projetos | ~300ms | <50ms |
| Queries N+1 | Sim | Não |
| Uso de CPU | Alto | Médio |

---

## 📝 Notas

- Índices aumentam espaço em disco (~10-20%)
- Índices podem lentidão em INSERT/UPDATE (aceitável)
- Monitorar hit ratio do cache
- Reconstruir índices periodicamente: `OPTIMIZE TABLE`
