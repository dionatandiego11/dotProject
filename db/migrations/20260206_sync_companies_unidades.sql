-- Migration: sincroniza companies legadas com unidades organizacionais
-- Data: 2026-02-06
-- Objetivo: reduzir inconsistência entre project_company (legado) e unidades (canônico)

-- 1) Garante uma company para cada unidade organizacional.
INSERT INTO dotp_companies (
    company_id,
    company_module,
    company_name,
    company_owner,
    company_type
)
SELECT
    u.unidade_id,
    0,
    u.unidade_nome,
    0,
    0
FROM dotp_unidades_organizacionais u
LEFT JOIN dotp_companies c ON c.company_id = u.unidade_id
WHERE c.company_id IS NULL;

-- 2) Corrige nomes placeholder/blank nas companies que representam unidades.
UPDATE dotp_companies c
JOIN dotp_unidades_organizacionais u ON u.unidade_id = c.company_id
SET c.company_name = u.unidade_nome
WHERE c.company_name IS NULL
   OR TRIM(c.company_name) = ''
   OR c.company_name REGEXP '^Empresa [0-9]+$';
