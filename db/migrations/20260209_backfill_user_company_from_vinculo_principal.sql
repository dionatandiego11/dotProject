-- 2026-02-09
-- Backfill user_company from active principal vinculo.
-- This keeps legacy fields aligned with organizational vínculo data.

UPDATE dotp_users u
SET u.user_company = (
    SELECT v.vinculo_unidade_id
    FROM dotp_usuario_unidades v
    WHERE v.vinculo_user_id = u.user_id
      AND v.vinculo_status = 'ativo'
    ORDER BY
        v.vinculo_is_principal DESC,
        COALESCE(v.vinculo_data_inicio, '1900-01-01') DESC,
        v.vinculo_id DESC
    LIMIT 1
);
