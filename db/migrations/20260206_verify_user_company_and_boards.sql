-- Verification for 20260206_backfill_user_company_and_boards.sql
-- Expected result:
-- - users_sem_company = 0 (or known intentional exceptions)
-- - users_company_sem_unidade = 0
-- - boards_sem_unidade = 0
-- - users_sem_company_com_fonte = 0

SELECT
    COUNT(*) AS users_sem_company
FROM dotp_users u
WHERE u.user_company IS NULL OR u.user_company = 0;

SELECT
    COUNT(*) AS users_company_sem_unidade
FROM dotp_users u
LEFT JOIN dotp_unidades_organizacionais un ON un.unidade_id = u.user_company
WHERE u.user_company IS NOT NULL
  AND u.user_company <> 0
  AND un.unidade_id IS NULL;

SELECT
    COUNT(*) AS boards_sem_unidade
FROM dotp_kanban_boards b
LEFT JOIN dotp_unidades_organizacionais un ON un.unidade_id = b.board_company
WHERE b.board_company = 0
   OR un.unidade_id IS NULL;

SELECT
    COUNT(*) AS users_sem_company_com_fonte
FROM dotp_users u
WHERE (u.user_company IS NULL OR u.user_company = 0)
  AND (
      EXISTS (
          SELECT 1
          FROM dotp_usuario_unidades v
          JOIN dotp_unidades_organizacionais un ON un.unidade_id = v.vinculo_unidade_id
          WHERE v.vinculo_user_id = u.user_id
            AND v.vinculo_status = 'ativo'
            AND un.unidade_status = 'ativo'
      )
      OR EXISTS (
          SELECT 1
          FROM dotp_unidades_organizacionais un
          WHERE un.unidade_responsavel_id = u.user_id
            AND un.unidade_status = 'ativo'
      )
  );
