-- Verificacao da migration 20260206_sync_companies_unidades.sql
-- Resultado esperado:
-- - unidades_sem_company = 0
-- - placeholders_em_companies_de_unidades = 0
-- - project_company_sem_unidade = 0

SELECT
    COUNT(*) AS unidades_sem_company
FROM dotp_unidades_organizacionais u
LEFT JOIN dotp_companies c ON c.company_id = u.unidade_id
WHERE c.company_id IS NULL;

SELECT
    COUNT(*) AS placeholders_em_companies_de_unidades
FROM dotp_companies c
JOIN dotp_unidades_organizacionais u ON u.unidade_id = c.company_id
WHERE c.company_name IS NULL
   OR TRIM(c.company_name) = ''
   OR c.company_name REGEXP '^Empresa [0-9]+$';

SELECT
    COUNT(*) AS project_company_sem_unidade
FROM dotp_projects p
LEFT JOIN dotp_unidades_organizacionais u ON u.unidade_id = p.project_company
WHERE p.project_company <> 0
  AND u.unidade_id IS NULL;
