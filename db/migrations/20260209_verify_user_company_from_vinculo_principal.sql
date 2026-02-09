-- Verification for 20260209_backfill_user_company_from_vinculo_principal.sql

SELECT COUNT(*) AS divergencias
FROM dotp_users u
WHERE COALESCE(u.user_company, 0) <> COALESCE((
    SELECT v.vinculo_unidade_id
    FROM dotp_usuario_unidades v
    WHERE v.vinculo_user_id = u.user_id
      AND v.vinculo_status = 'ativo'
    ORDER BY
        v.vinculo_is_principal DESC,
        COALESCE(v.vinculo_data_inicio, '1900-01-01') DESC,
        v.vinculo_id DESC
    LIMIT 1
), 0);

SELECT
    u.user_id,
    u.user_username,
    u.user_company,
    (
        SELECT v.vinculo_unidade_id
        FROM dotp_usuario_unidades v
        WHERE v.vinculo_user_id = u.user_id
          AND v.vinculo_status = 'ativo'
        ORDER BY
            v.vinculo_is_principal DESC,
            COALESCE(v.vinculo_data_inicio, '1900-01-01') DESC,
            v.vinculo_id DESC
        LIMIT 1
    ) AS unidade_principal_ativa
FROM dotp_users u
WHERE COALESCE(u.user_company, 0) <> COALESCE((
    SELECT v.vinculo_unidade_id
    FROM dotp_usuario_unidades v
    WHERE v.vinculo_user_id = u.user_id
      AND v.vinculo_status = 'ativo'
    ORDER BY
        v.vinculo_is_principal DESC,
        COALESCE(v.vinculo_data_inicio, '1900-01-01') DESC,
        v.vinculo_id DESC
    LIMIT 1
), 0)
ORDER BY u.user_id
LIMIT 20;
