-- Seed missing companies referenced by projects.
-- Ensures Kanban board creation doesn't fail due to foreign key constraints.
INSERT INTO dotp_companies (company_id, company_module, company_name, company_owner)
SELECT DISTINCT
    p.project_company,
    0,
    CONCAT('Empresa ', p.project_company),
    0
FROM dotp_projects p
LEFT JOIN dotp_companies c ON c.company_id = p.project_company
WHERE p.project_company IS NOT NULL
  AND p.project_company > 0
  AND c.company_id IS NULL;

-- Ensure a default company exists for users without company assignment.
INSERT INTO dotp_companies (company_id, company_module, company_name, company_owner)
SELECT 1, 0, 'Empresa 1', 0
WHERE NOT EXISTS (SELECT 1 FROM dotp_companies WHERE company_id = 1);
