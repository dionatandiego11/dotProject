# 📝 Handoff para ChatGPT - Correções Backend

**Data**: 02/02/2026 09:20  
**De**: Kimi  
**Para**: ChatGPT  
**Prioridade**: Alta

---

## 🎯 Tarefas Atribuídas

### 1. 🔴 CORREÇÃO CRÍTICA - Dashboard Secretário

**Status**: Erro 500 com resposta vazia
**Arquivo**: `src/Api/Controller/DashboardController.php`

**Problema**:
- Dashboard Secretário retorna HTTP 500
- Resposta vem vazia (sem mensagem de erro)
- Erro NÃO aparece nos logs do PHP-FPM
- Try-catch foi adicionado mas não captura o erro

**Possíveis causas**:
1. Erro no método `checkModernTables()` - Query SQL falhando
2. Erro nas queries SQL do `getSecretarioDashboardModern()`
3. Tabelas `dotp_programas` não existem no banco
4. Erro no middleware antes do controller

**Como investigar**:
```bash
# Testar endpoint
TOKEN="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
curl -v http://localhost:8088/api.php/v1/dashboard/secretario \
  -H "Authorization: Bearer $TOKEN"

# Verificar se tabelas existem
docker exec dotproject_mariadb_1 mysql -u root -pdotproject \
  -e "SHOW TABLES LIKE 'dotp_%';" dotproject
```

**Critério de sucesso**: Dashboard Secretário retornar HTTP 200 com dados

---

### 2. 🟡 CORREÇÃO - NotificationService

**Status**: Dois erros identificados
**Arquivo**: `src/Service/NotificationService.php`

**Erro 1 - Logger::getInstance()**:
```
Call to undefined method DotProject\Core\Logger::getInstance()
File: src/Service/NotificationService.php, Line: 22
```

**Solução**: Verificar se a classe Logger em `src/Core/Logger.php` tem o método `getInstance()`. Se não tiver, adicionar:
```php
public static function getInstance(): self
{
    return new self();
}
```

**Erro 2 - getUnreadCount()**:
```
Call to undefined method DotProject\Service\NotificationService::getUnreadCount()
File: src/Api/Controller/NotificationController.php, Line: 65
```

**Solução**: Adicionar método na classe NotificationService:
```php
public function getUnreadCount(int $userId): int
{
    // Implementar contagem de notificações não lidas
    $sql = "SELECT COUNT(*) FROM notificacoes 
            WHERE usuario_id = ? AND lida = 0";
    return (int) $this->db->fetchValue($sql, [$userId]);
}
```

---

### 3. 🟢 DOCUMENTAÇÃO

**Tarefa**: Documentar as correções realizadas

**O que documentar**:
1. Correção do PermissionService (cast int)
2. Correção dos dashboards (Secretário, Coordenador, Técnico)
3. Correção do NotificationService

**Arquivo**: Criar ou atualizar `.agent/docs/CORRECOES.md`

---

## 📋 Checklist

- [ ] Investigar erro 500 no Secretário
- [ ] Adicionar método getInstance() ao Logger ou corrigir NotificationService
- [ ] Adicionar método getUnreadCount() ao NotificationService
- [ ] Testar todos os dashboards (5)
- [ ] Documentar correções
- [ ] Atualizar fórum com status

---

## 🔗 Arquivos Relevantes

| Arquivo | Descrição |
|---------|-----------|
| `src/Api/Controller/DashboardController.php` | Controller dos dashboards |
| `src/Service/PermissionService.php` | Já corrigido por Kimi |
| `src/Service/NotificationService.php` | Precisa de correções |
| `src/Core/Logger.php` | Verificar método getInstance() |
| `src/Api/Controller/NotificationController.php` | Usa NotificationService |

---

## 💬 Dúvidas?

Qualquer dúvida, mencione no `.agent/chat_agent.md` com tag `[AJUDA]`.

Boa sorte! 🚀
