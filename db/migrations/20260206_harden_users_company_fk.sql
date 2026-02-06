-- Migration: harden users.user_company integrity
-- Date: 2026-02-06
-- Goal: prevent reintroduction of invalid user_company references.

-- 1) Normalize legacy sentinel to NULL.
UPDATE dotp_users
SET user_company = NULL
WHERE user_company = 0;

-- 2) Ensure every non-null user_company points to an existing company.
-- If a company row is missing but unidade exists, create compatible company row.
INSERT INTO dotp_companies (
    company_id,
    company_module,
    company_name,
    company_owner,
    company_type
)
SELECT DISTINCT
    u.user_company,
    0,
    COALESCE(NULLIF(TRIM(un.unidade_nome), ''), CONCAT('Company ', u.user_company)),
    0,
    0
FROM dotp_users u
LEFT JOIN dotp_companies c ON c.company_id = u.user_company
LEFT JOIN dotp_unidades_organizacionais un ON un.unidade_id = u.user_company
WHERE u.user_company IS NOT NULL
  AND u.user_company <> 0
  AND c.company_id IS NULL;

-- 3) Drop unrecoverable invalid references (no matching company).
UPDATE dotp_users u
LEFT JOIN dotp_companies c ON c.company_id = u.user_company
SET u.user_company = NULL
WHERE u.user_company IS NOT NULL
  AND u.user_company <> 0
  AND c.company_id IS NULL;

-- 4) Keep column nullable and remove legacy default 0.
ALTER TABLE dotp_users
    MODIFY COLUMN user_company INT(11) NULL DEFAULT NULL;

-- 5) Add index for joins/filtering.
SET @idx_user_company_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_users'
      AND index_name = 'idx_user_company'
);
SET @sql := IF(
    @idx_user_company_exists = 0,
    'ALTER TABLE dotp_users ADD INDEX idx_user_company (user_company)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6) Add FK users.user_company -> companies.company_id (safe delete behavior).
SET @fk_users_company_exists := (
    SELECT COUNT(*)
    FROM information_schema.referential_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'dotp_users'
      AND constraint_name = 'fk_users_company'
);
SET @sql := IF(
    @fk_users_company_exists = 0,
    'ALTER TABLE dotp_users ADD CONSTRAINT fk_users_company FOREIGN KEY (user_company) REFERENCES dotp_companies(company_id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
