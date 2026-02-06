-- Migration: backfill user_company and normalize kanban board_company
-- Date: 2026-02-06
-- Goal: reduce legacy drift between users/kanban and canonical unidades.

-- 1) Backfill user_company when missing/zero.
-- Priority:
--   a) active principal vinculo
--   b) active vinculo (principal first, then oldest vinculo_id)
--   c) active unidade where user is responsavel
UPDATE dotp_users u
SET u.user_company = COALESCE(
    (
        SELECT v.vinculo_unidade_id
        FROM dotp_usuario_unidades v
        JOIN dotp_unidades_organizacionais un ON un.unidade_id = v.vinculo_unidade_id
        WHERE v.vinculo_user_id = u.user_id
          AND v.vinculo_status = 'ativo'
          AND v.vinculo_is_principal = 1
          AND un.unidade_status = 'ativo'
        ORDER BY v.vinculo_id ASC
        LIMIT 1
    ),
    (
        SELECT v.vinculo_unidade_id
        FROM dotp_usuario_unidades v
        JOIN dotp_unidades_organizacionais un ON un.unidade_id = v.vinculo_unidade_id
        WHERE v.vinculo_user_id = u.user_id
          AND v.vinculo_status = 'ativo'
          AND un.unidade_status = 'ativo'
        ORDER BY v.vinculo_is_principal DESC, v.vinculo_id ASC
        LIMIT 1
    ),
    (
        SELECT un.unidade_id
        FROM dotp_unidades_organizacionais un
        WHERE un.unidade_responsavel_id = u.user_id
          AND un.unidade_status = 'ativo'
        ORDER BY un.unidade_nivel_id ASC, un.unidade_id ASC
        LIMIT 1
    ),
    u.user_company
)
WHERE u.user_company IS NULL OR u.user_company = 0;

-- 2) Fix invalid board_company from linked project when possible.
UPDATE dotp_kanban_boards b
JOIN dotp_projects p ON p.project_id = b.board_project
JOIN dotp_unidades_organizacionais un_proj ON un_proj.unidade_id = p.project_company
LEFT JOIN dotp_unidades_organizacionais un_board ON un_board.unidade_id = b.board_company
SET b.board_company = p.project_company
WHERE (b.board_company = 0 OR un_board.unidade_id IS NULL)
  AND p.project_company <> 0;

-- 3) Fix remaining invalid board_company from board creator user_company.
UPDATE dotp_kanban_boards b
JOIN dotp_users u ON u.user_id = b.board_created_by
JOIN dotp_unidades_organizacionais un_user ON un_user.unidade_id = u.user_company
LEFT JOIN dotp_unidades_organizacionais un_board ON un_board.unidade_id = b.board_company
SET b.board_company = u.user_company
WHERE (b.board_company = 0 OR un_board.unidade_id IS NULL)
  AND u.user_company <> 0;
