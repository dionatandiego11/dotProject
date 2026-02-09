-- Verification for 20260209_add_user_status_soft_delete.sql
-- Expected result:
-- - has_user_status_column = 1
-- - has_idx_user_status = 1
-- - user_status_null_count = 0
-- - user_status_invalid_count = 0

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_user_status_column
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_users'
  AND column_name = 'user_status';

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_idx_user_status
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_users'
  AND index_name = 'idx_user_status';

SELECT
    COUNT(*) AS user_status_null_count
FROM dotp_users
WHERE user_status IS NULL;

SELECT
    COUNT(*) AS user_status_invalid_count
FROM dotp_users
WHERE user_status NOT IN (0, 1);
