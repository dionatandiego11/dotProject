# 🔍 Handoff: Diagnóstico Completo do Erro 500 - Dashboard Secretário

**De:** Kimi  
**Para:** ChatGPT  
**Data:** 2026-02-02  
**Status:** ✅ DIAGNÓSTICO CONCLUÍDO - Aguardando correções

---

## 📊 Resumo dos Testes

| Dashboard | Status | User ID | Unidade ID | Observação |
|-----------|--------|---------|------------|------------|
| Prefeito | ✅ 200 | 1 | - | Funcionando |
| Secretário | ❌ 500 | 1 | 2 | **Erro identificado** |
| Coordenador | ✅ 200 | - | - | Funcionando |
| Técnico | ✅ 200 | - | - | Funcionando |
| Controlador | ✅ 200 | - | - | Funcionando |

---

## 🔍 Diagnóstico Detalhado

### ✅ O que funciona:
1. Roteamento (`api_routes_dashboard.php`) - OK
2. Autenticação JWT - OK (user_id: 1)
3. PermissionService - OK
4. Vínculo do usuário - OK
   - Role: SECRETARIO
   - Unidade: Secretaria de Mobilidade Urbana (SEMOB)
   - unidade_id: 2
5. Método `checkModernTables()` - OK (retorna true)
6. Queries de Programas - OK (2 registros)
7. Queries de Projetos - OK (3 registros)
8. Queries de Projetos Atenção - OK (2 registros)

### ❌ Onde falha:

**Local:** `AlertaRepository::getEstatisticas()`  
**Arquivo:** `src/Repository/AlertaRepository.php`

**Problemas encontrados:**

#### 1. Tabela inexistente na query `findComDetalhes()` (Linhas 222-223)
```php
// ERRADO:
LEFT JOIN projetos p ON p.id = a.alerta_projeto_id
LEFT JOIN programas pr ON pr.id = a.alerta_programa_id

// CORRETO:
LEFT JOIN dotp_projetos_prefeitura p ON p.id = a.alerta_projeto_id
LEFT JOIN dotp_programas pr ON pr.id = a.alerta_programa_id
```

#### 2. Possível tabela inexistente em `getEstatisticas()`
```php
// Query usa: {$this->table} = 'dotp_alertas'
// Verificar se a tabela `dotp_alertas` existe no banco
```

---

## 🧪 Logs de Debug Relevantes

```
[DEBUG] getSecretarioDashboardModern() - Query 3: Projetos Atenção
[DEBUG] getSecretarioDashboardModern() - Atenção OK: 2
[DEBUG] getSecretarioDashboardModern() - Query 4: Alertas
<< ERRO OCORRE AQUI - Nenhum log após esta linha >>
```

---

## 🎯 Causa Raiz Confirmada

**Erro de SQL:** Tabela `projetos` não existe. A tabela correta é `dotp_projetos_prefeitura`.

```sql
-- Query problemática em findComDetalhes():
SELECT a.*, p.nome as projeto_nome, ...
FROM dotp_alertas a
LEFT JOIN projetos p ON p.id = a.alerta_projeto_id  -- ERRO: tabela não existe
LEFT JOIN programas pr ON pr.id = a.alerta_programa_id  -- ERRO: tabela não existe
LEFT JOIN dotp_unidades_organizacionais u ON u.unidade_id = a.alerta_unidade_id
```

---

## 📋 Checklist de Correções

### 🔴 CRÍTICO - Corrigir AlertaRepository.php

- [ ] **Linha 222:** `LEFT JOIN projetos p` → `LEFT JOIN dotp_projetos_prefeitura p`
- [ ] **Linha 223:** `LEFT JOIN programas pr` → `LEFT JOIN dotp_programas pr`

### 🟡 MÉDIO - Verificar Tabelas

- [ ] Verificar se `dotp_alertas` existe no banco
- [ ] Se não existir, criar migration/tabela

### 🟢 BAIXO - Verificar PermissionService.php

- [ ] **Linha 265:** `FROM projetos` → `FROM dotp_projetos_prefeitura`
- [ ] **Linha 268:** `FROM programas` → `FROM dotp_programas`
- [ ] **Linha 291:** `FROM projetos` → `FROM dotp_projetos_prefeitura`
- [ ] **Linha 296:** `FROM programas` → `FROM dotp_programas`
- [ ] **Linha 316-317:** `FROM projetos` → `FROM dotp_projetos_prefeitura`
- [ ] **Linha 320-321:** `FROM programas` → `FROM dotp_programas`

---

## 🗄️ Estrutura de Tabelas Confirmada

| Tabela Esperada | Status |
|-----------------|--------|
| dotp_programas | ✅ Existe |
| dotp_projetos_prefeitura | ✅ Existe |
| dotp_etapas | ✅ Existe |
| dotp_alertas | ❓ Verificar |
| dotp_usuario_unidades | ✅ Existe |
| dotp_unidades_organizacionais | ✅ Existe |

---

## 🔧 Comando para Verificar Tabelas

```bash
docker-compose exec mariadb mysql -u dotproject -pdotproject123 -e "USE dotproject; SHOW TABLES LIKE 'dotp_%';"
```

---

## 📝 Após Correções

Após fazer as correções:
1. Limpar cache Redis: `docker-compose exec redis redis-cli FLUSHALL`
2. Testar endpoint: `curl http://localhost:8088/api.php/v1/dashboard/secretario -H "Authorization: Bearer $TOKEN"`
3. Confirmar HTTP 200

---

**Aguardando correções para testar novamente!** 🚀
