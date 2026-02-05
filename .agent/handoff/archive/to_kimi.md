# 📝 Handoff para Kimi

**Data**: 30/01/2026 09:35  
**De**: Claude/Antigravity  
**Prioridade**: Média

---

## Contexto

Implementei o `DashboardController.php` com fallback graceful. O controller agora:
- Detecta automaticamente se tabelas modernas existem
- Usa tabelas legadas (`dotp_projects`, `dotp_tasks`) como fallback
- Retorna campo `source` indicando qual schema está sendo usado

## Tarefa Solicitada

Testar o fluxo completo de autenticação e acesso aos dashboards:

### Subtarefas
- [ ] Fazer login e obter token JWT
- [ ] Acessar endpoint `/api/v1/dashboard/prefeito` com token
- [ ] Verificar se retorna dados (mesmo que mock/legado)
- [ ] Testar outros perfis (secretario, coordenador, tecnico, controlador)
- [ ] Verificar campo `source` na resposta

## Arquivos Relevantes

| Arquivo | Descrição |
|---------|-----------|
| `src/Api/Controller/DashboardController.php` | Controller atualizado |
| `api.php` | Entry point da API |
| `src/Api/Middleware/AuthMiddleware.php` | Middleware de autenticação |

## Comandos Úteis

```powershell
# Testar status do sistema (não requer auth)
Invoke-WebRequest -Uri "http://localhost:8088/api.php/v1/dashboard/status" -UseBasicParsing

# Fazer login (ajustar credenciais)
$login = Invoke-WebRequest -Uri "http://localhost:8088/api.php/v1/auth/login" -Method POST -Body '{"username":"admin","password":"passwd"}' -ContentType "application/json"

# Acessar dashboard com token
$token = ($login.Content | ConvertFrom-Json).token
Invoke-WebRequest -Uri "http://localhost:8088/api.php/v1/dashboard/prefeito" -Headers @{Authorization="Bearer $token"}
```

## Critérios de Sucesso

- [ ] Login retorna token JWT válido
- [ ] Dashboard retorna HTTP 200 com dados
- [ ] Campo `source` indica `modern_tables` ou `legacy_tables`
- [ ] Não há erros 500 ou exceptions

## Observações

- ⚠️ Se `source` = `legacy_tables`, significa que as migrations não foram executadas
- 📝 O endpoint `/api/v1/dashboard/status` pode ser usado para debug
- 🔗 Credenciais padrão: admin/passwd (ver `db/dotproject.sql`)

## Após Conclusão

1. Atualizar seção Kimi no `forum_agentes.md`
2. Marcar tarefas no `backlog.md`
3. Se houver problemas, criar diagnóstico em `meetings/`
