-- Seed template for onboarding a new tenant (shared schema).
-- Date: 2026-02-11
--
-- Usage:
-- 1) Ajuste as variáveis abaixo.
-- 2) Execute após 20260211_add_tenant_foundation.sql.

SET @tenant_slug := 'demo';
SET @tenant_name := 'Prefeitura Demo';
SET @tenant_domain := 'demo.dotproject.app';
SET @admin_username := 'admin_demo';
SET @admin_password := 'Trocar123!';
SET @admin_first_name := 'Administrador';
SET @admin_last_name := 'Demo';
SET @admin_email := 'admin.demo@prefeitura.gov.br';

INSERT INTO dotp_tenants (tenant_slug, tenant_name, tenant_domain, tenant_active)
VALUES (@tenant_slug, @tenant_name, @tenant_domain, 1)
ON DUPLICATE KEY UPDATE
    tenant_name = VALUES(tenant_name),
    tenant_domain = VALUES(tenant_domain),
    tenant_active = VALUES(tenant_active);

SET @tenant_id := (
    SELECT tenant_id
    FROM dotp_tenants
    WHERE tenant_slug = @tenant_slug
    LIMIT 1
);

-- Contact for tenant admin.
INSERT INTO dotp_contacts (contact_first_name, contact_last_name, contact_email)
SELECT @admin_first_name, @admin_last_name, @admin_email
WHERE NOT EXISTS (
    SELECT 1
    FROM dotp_contacts
    WHERE contact_email = @admin_email
);

SET @admin_contact_id := (
    SELECT contact_id
    FROM dotp_contacts
    WHERE contact_email = @admin_email
    ORDER BY contact_id DESC
    LIMIT 1
);

-- Insert tenant admin user (supports schemas with/without user_status and tenant_id columns).
SET @users_has_tenant := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_users'
      AND column_name = 'tenant_id'
);
SET @users_has_status := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_users'
      AND column_name = 'user_status'
);

SET @sql := CONCAT(
    'INSERT INTO dotp_users (',
    'user_contact, user_username, user_password, user_parent, user_type, user_company, user_department, user_owner, user_signature',
    IF(@users_has_status = 1, ', user_status', ''),
    IF(@users_has_tenant = 1, ', tenant_id', ''),
    ') SELECT ',
    @admin_contact_id, ', ',
    QUOTE(@admin_username), ', MD5(', QUOTE(@admin_password), '), 0, 1, 0, 0, 0, ''''',
    IF(@users_has_status = 1, ', 0', ''),
    IF(@users_has_tenant = 1, CONCAT(', ', @tenant_id), ''),
    ' WHERE NOT EXISTS (',
    'SELECT 1 FROM dotp_users WHERE user_username = ', QUOTE(@admin_username),
    IF(@users_has_tenant = 1, CONCAT(' AND tenant_id = ', @tenant_id), ''),
    ')'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Optional default hierarchy levels per tenant when table supports tenant_id.
SET @levels_has_tenant := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'dotp_niveis_hierarquicos'
      AND column_name = 'tenant_id'
);

SET @sql := IF(
    @levels_has_tenant = 1,
    CONCAT(
        'INSERT INTO dotp_niveis_hierarquicos (tenant_id, nivel_ordem, nivel_nome, nivel_titulo_responsavel, nivel_descricao, nivel_cor, nivel_ativo) ',
        'SELECT ', @tenant_id, ', t.nivel_ordem, t.nivel_nome, t.nivel_titulo_responsavel, t.nivel_descricao, t.nivel_cor, 1 ',
        'FROM (',
        ' SELECT 1 AS nivel_ordem, ''Executivo'' AS nivel_nome, ''Prefeito'' AS nivel_titulo_responsavel, ''Nivel estrategico'' AS nivel_descricao, ''#1d4ed8'' AS nivel_cor',
        ' UNION ALL SELECT 2, ''Secretaria'', ''Secretario'', ''Nivel tatico'', ''#0ea5e9''',
        ' UNION ALL SELECT 3, ''Coordenacao'', ''Coordenador'', ''Nivel operacional'', ''#16a34a''',
        ' UNION ALL SELECT 4, ''Tecnico'', ''Tecnico'', ''Execucao de tarefas'', ''#f59e0b''',
        ') t ',
        'WHERE NOT EXISTS (',
        '  SELECT 1 FROM dotp_niveis_hierarquicos n ',
        '  WHERE n.tenant_id = ', @tenant_id, ' AND n.nivel_ordem = t.nivel_ordem',
        ')'
    ),
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
