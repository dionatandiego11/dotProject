-- ===========================================
-- Queries Otimizadas
-- ===========================================
-- Coleção de queries otimizadas para operações frequentes

-- ===========================================
-- 1. DASHBOARD - Resumo do usuário
-- ===========================================
-- Query original potencialmente lenta (N+1 problem)
-- Query otimizada com JOINs e agregações

SELECT 
    u.user_id,
    u.user_username,
    COUNT(DISTINCT p.project_id) as total_projects,
    COUNT(DISTINCT CASE WHEN p.project_status = 0 THEN p.project_id END) as active_projects,
    COUNT(DISTINCT t.task_id) as total_tasks,
    COUNT(DISTINCT CASE WHEN t.task_status = 0 THEN t.task_id END) as pending_tasks,
    COUNT(DISTINCT CASE WHEN t.task_end_date < CURDATE() AND t.task_status != 1 THEN t.task_id END) as overdue_tasks,
    COALESCE(AVG(t.task_percent_complete), 0) as avg_progress
FROM dotp_users u
LEFT JOIN dotp_projects p ON p.project_owner = u.user_id
LEFT JOIN dotp_tasks t ON t.task_project = p.project_id
WHERE u.user_id = ?
GROUP BY u.user_id, u.user_username;

-- ===========================================
-- 2. LISTAGEM DE PROJETOS COM PROGRESSO
-- ===========================================

SELECT 
    p.project_id,
    p.project_name,
    p.project_short_name,
    p.project_start_date,
    p.project_end_date,
    p.project_status,
    p.project_color_identifier,
    c.company_name as owner_company,
    COUNT(t.task_id) as total_tasks,
    COUNT(CASE WHEN t.task_status = 1 THEN 1 END) as completed_tasks,
    COALESCE(AVG(t.task_percent_complete), 0) as progress_percent
FROM dotp_projects p
LEFT JOIN dotp_companies c ON c.company_id = p.project_company
LEFT JOIN dotp_tasks t ON t.task_project = p.project_id
WHERE p.project_status = 0  -- Ativos
GROUP BY p.project_id, p.project_name, p.project_short_name, 
         p.project_start_date, p.project_end_date, p.project_status,
         p.project_color_identifier, c.company_name
ORDER BY p.project_name
LIMIT 20 OFFSET 0;

-- ===========================================
-- 3. TAREFAS DO USUÁRIO COM DETALHES
-- ===========================================

SELECT 
    t.task_id,
    t.task_name,
    t.task_start_date,
    t.task_end_date,
    t.task_status,
    t.task_priority,
    t.task_percent_complete,
    p.project_id,
    p.project_name,
    p.project_color_identifier,
    c.contact_first_name,
    c.contact_last_name,
    DATEDIFF(t.task_end_date, CURDATE()) as days_remaining,
    CASE 
        WHEN t.task_end_date < CURDATE() AND t.task_status != 1 THEN 'overdue'
        WHEN DATEDIFF(t.task_end_date, CURDATE()) <= 3 THEN 'urgent'
        ELSE 'normal'
    END as urgency
FROM dotp_tasks t
INNER JOIN dotp_user_tasks ut ON ut.task_id = t.task_id
INNER JOIN dotp_projects p ON p.project_id = t.task_project
LEFT JOIN dotp_contacts c ON c.contact_id = t.task_assigned_to
WHERE ut.user_id = ?
  AND t.task_status = 0  -- Ativas
ORDER BY 
    CASE t.task_priority 
        WHEN 1 THEN 1 
        WHEN 2 THEN 2 
        WHEN 3 THEN 3 
        ELSE 4 
    END,
    t.task_end_date ASC
LIMIT 50;

-- ===========================================
-- 4. RELATÓRIO DE PRODUTIVIDADE POR USUÁRIO
-- ===========================================

SELECT 
    u.user_id,
    u.user_username,
    c.contact_first_name,
    c.contact_last_name,
    COUNT(DISTINCT t.task_id) as tasks_assigned,
    COUNT(DISTINCT CASE WHEN t.task_status = 1 THEN t.task_id END) as tasks_completed,
    COUNT(DISTINCT CASE WHEN t.task_end_date < CURDATE() AND t.task_status != 1 THEN t.task_id END) as tasks_overdue,
    ROUND(
        COUNT(CASE WHEN t.task_status = 1 THEN 1 END) * 100.0 / NULLIF(COUNT(t.task_id), 0), 
        2
    ) as completion_rate,
    COALESCE(SUM(t.task_hours), 0) as total_hours,
    AVG(t.task_percent_complete) as avg_progress
FROM dotp_users u
LEFT JOIN dotp_user_tasks ut ON ut.user_id = u.user_id
LEFT JOIN dotp_tasks t ON t.task_id = ut.task_id
LEFT JOIN dotp_contacts c ON c.contact_id = u.user_contact
WHERE u.user_status = 0  -- Usuários ativos
GROUP BY u.user_id, u.user_username, c.contact_first_name, c.contact_last_name
ORDER BY completion_rate DESC, tasks_completed DESC;

-- ===========================================
-- 5. PROJETOS ATRASADOS
-- ===========================================

SELECT 
    p.project_id,
    p.project_name,
    p.project_end_date,
    DATEDIFF(CURDATE(), p.project_end_date) as days_overdue,
    c.company_name,
    u.user_username as owner,
    COUNT(t.task_id) as total_tasks,
    COUNT(CASE WHEN t.task_status = 1 THEN 1 END) as completed_tasks,
    COUNT(CASE WHEN t.task_status != 1 THEN 1 END) as pending_tasks
FROM dotp_projects p
LEFT JOIN dotp_companies c ON c.company_id = p.project_company
LEFT JOIN dotp_users u ON u.user_id = p.project_owner
LEFT JOIN dotp_tasks t ON t.task_project = p.project_id
WHERE p.project_end_date < CURDATE()
  AND p.project_status = 0  -- Ativo
GROUP BY p.project_id, p.project_name, p.project_end_date, 
         c.company_name, u.user_username
HAVING pending_tasks > 0
ORDER BY days_overdue DESC;

-- ===========================================
-- 6. ATIVIDADE RECENTE (Timeline)
-- ===========================================

SELECT 
    'task' as type,
    t.task_id as id,
    t.task_name as title,
    t.task_updated as updated_date,
    u.user_username as updated_by,
    p.project_name
FROM dotp_tasks t
JOIN dotp_users u ON u.user_id = t.task_updator
JOIN dotp_projects p ON p.project_id = t.task_project
WHERE t.task_updated >= DATE_SUB(NOW(), INTERVAL 7 DAY)

UNION ALL

SELECT 
    'project' as type,
    p.project_id as id,
    p.project_name as title,
    p.project_updated as updated_date,
    u.user_username as updated_by,
    NULL as project_name
FROM dotp_projects p
JOIN dotp_users u ON u.user_id = p.project_updator
WHERE p.project_updated >= DATE_SUB(NOW(), INTERVAL 7 DAY)

ORDER BY updated_date DESC
LIMIT 50;

-- ===========================================
-- 7. BURNDOWN CHART DATA
-- ===========================================

SELECT 
    DATE(t.task_end_date) as date,
    COUNT(*) as tasks_due,
    COUNT(CASE WHEN t.task_status = 1 THEN 1 END) as tasks_completed,
    SUM(t.task_hours) as hours_total,
    SUM(CASE WHEN t.task_status = 1 THEN t.task_hours ELSE 0 END) as hours_completed
FROM dotp_tasks t
WHERE t.task_project = ?
  AND t.task_end_date BETWEEN ? AND ?
GROUP BY DATE(t.task_end_date)
ORDER BY date;

-- ===========================================
-- 8. CAPACIDADE DO TIME (Velocity)
-- ===========================================

SELECT 
    DATE_FORMAT(t.task_end_date, '%Y-%m') as month,
    COUNT(*) as tasks_completed,
    SUM(t.task_hours) as hours_completed,
    AVG(t.task_hours) as avg_hours_per_task,
    COUNT(DISTINCT t.task_assigned_to) as contributors
FROM dotp_tasks t
WHERE t.task_status = 1  -- Completadas
  AND t.task_end_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
GROUP BY DATE_FORMAT(t.task_end_date, '%Y-%m')
ORDER BY month DESC;

-- ===========================================
-- 9. BUSCA FULL-TEXT
-- ===========================================

-- Busca em projetos
SELECT 
    p.project_id,
    p.project_name,
    p.project_description,
    MATCH(p.project_name, p.project_description) AGAINST(?) as relevance
FROM dotp_projects p
WHERE MATCH(p.project_name, p.project_description) AGAINST(?)
ORDER BY relevance DESC
LIMIT 20;

-- ===========================================
-- 10. ESTATÍSTICAS DO SISTEMA
-- ===========================================

SELECT 
    (SELECT COUNT(*) FROM dotp_projects WHERE project_status = 0) as active_projects,
    (SELECT COUNT(*) FROM dotp_tasks WHERE task_status = 0) as pending_tasks,
    (SELECT COUNT(*) FROM dotp_tasks WHERE task_status = 1) as completed_tasks,
    (SELECT COUNT(*) FROM dotp_users WHERE user_status = 0) as active_users,
    (SELECT COUNT(*) FROM dotp_tasks WHERE task_end_date < CURDATE() AND task_status != 1) as overdue_tasks,
    (SELECT COALESCE(AVG(task_percent_complete), 0) FROM dotp_tasks WHERE task_status = 0) as avg_progress;
