-- Verification for 20260206_harden_users_company_fk.sql
-- Expected:
-- - users_company_zero = 0
-- - users_company_sem_company = 0
-- - fk_users_company_exists = 1
-- - idx_user_company_exists = 1

SELECT
    COUNT(*) AS users_company_zero
FROM dotp_users
WHERE user_company = 0;

SELECT
    COUNT(*) AS users_company_sem_company
FROM dotp_users u
LEFT JOIN dotp_companies c ON c.company_id = u.user_company
WHERE u.user_company IS NOT NULL
  AND c.company_id IS NULL;

SELECT
    COUNT(*) AS fk_users_company_exists
FROM information_schema.referential_constraints
WHERE constraint_schema = DATABASE()
  AND table_name = 'dotp_users'
  AND constraint_name = 'fk_users_company';

SELECT
    COUNT(*) AS idx_user_company_exists
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_users'
  AND index_name = 'idx_user_company';
