-- Verification for 20260209_harden_notifications_integrity.sql
-- Expected result:
-- - notifications_read_null = 0
-- - notifications_sent_null = 0
-- - notifications_channel_null_or_blank = 0
-- - notifications_created_null = 0
-- - has_idx_notif_user_read_created = 1
-- - has_idx_notif_channel_sent_created = 1
-- - has_idx_notif_user_type_created = 1

SELECT
    COUNT(*) AS notifications_read_null
FROM dotp_notifications
WHERE notification_is_read IS NULL;

SELECT
    COUNT(*) AS notifications_sent_null
FROM dotp_notifications
WHERE notification_is_sent IS NULL;

SELECT
    COUNT(*) AS notifications_channel_null_or_blank
FROM dotp_notifications
WHERE notification_channel IS NULL
   OR TRIM(notification_channel) = '';

SELECT
    COUNT(*) AS notifications_created_null
FROM dotp_notifications
WHERE notification_created_at IS NULL;

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_idx_notif_user_read_created
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_notifications'
  AND index_name = 'idx_notif_user_read_created';

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_idx_notif_channel_sent_created
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_notifications'
  AND index_name = 'idx_notif_channel_sent_created';

SELECT
    CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS has_idx_notif_user_type_created
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'dotp_notifications'
  AND index_name = 'idx_notif_user_type_created';
