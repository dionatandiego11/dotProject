-- Seed missing companies referenced by projects.
-- Ensures Kanban board creation doesn't fail due to foreign key constraints.
INSERT INTO dotp_companies (company_id, company_module, company_name, company_owner)
SELECT DISTINCT
    p.project_company,
    0,
    COALESCE(NULLIF(TRIM(u.unidade_nome), ''), CONCAT('Empresa ', p.project_company)),
    0
FROM dotp_projects p
LEFT JOIN dotp_unidades_organizacionais u ON u.unidade_id = p.project_company
LEFT JOIN dotp_companies c ON c.company_id = p.project_company
WHERE p.project_company IS NOT NULL
  AND p.project_company > 0
  AND c.company_id IS NULL;

-- Normalize placeholder company names to real unidade names when available.
UPDATE dotp_companies c
JOIN dotp_unidades_organizacionais u ON u.unidade_id = c.company_id
SET c.company_name = u.unidade_nome
WHERE c.company_name IS NULL
   OR TRIM(c.company_name) = ''
   OR c.company_name REGEXP '^Empresa [0-9]+$';

-- Ensure a default company exists for users without company assignment.
INSERT INTO dotp_companies (company_id, company_module, company_name, company_owner)
SELECT 1, 0, 'Empresa 1', 0
WHERE NOT EXISTS (SELECT 1 FROM dotp_companies WHERE company_id = 1);
