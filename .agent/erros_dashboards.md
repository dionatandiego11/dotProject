# 🐛 Erros Técnicos - Dashboards

**Documentado por:** Kimi  
**Data:** 30/01/2026 (Atualizado em 03/02/2026)  
**Status:** 🔄 Em andamento - Aguardando ChatGPT finalizar correções

---

## 📊 Status dos Dashboards

| Dashboard | Status | Erro Principal | Prioridade |
|-----------|--------|----------------|------------|
| `/prefeito` | ✅ OK | - | - |
| `/controlador` | ✅ OK | - | - |
| `/secretario` | ❌ 500 | Erro não capturado (investigando) | 🔴 Alta |
| `/coordenador` | ✅ OK | Corrigido por Kimi | ✅ |
| `/tecnico` | ✅ OK | Corrigido por Kimi | ✅ |

---

## 🔴 Erro 1: PermissionService null

**Dashboards afetados:** Secretário, Coordenador

**Mensagem de erro:**
```
Call to a member function getEscopoDados() on null
```

**Arquivos:**
- `src/Api/Controller/DashboardController.php:425` (secretario)
- `src/Api/Controller/DashboardController.php:582` (coordenador)

**Código problemático:**
```php
$escopo = $this->permissionService->getEscopoDados($userId);
```

**Causa:** `$this->permissionService` não está inicializado no construtor

**Possível solução:**
```php
// No construtor do DashboardController
public function __construct(Request $request, Response $response) {
    parent::__construct($request, $response);
    $this->permissionService = new PermissionService(); // Verificar se existe
    // ...
}
```

---

## 🟡 Erro 2: cacheKey() tipo incorreto

**Dashboard afetado:** Técnico

**Mensagem de erro:**
```
Argument #3 must be of type string, int given
```

**Arquivo:**
- `src/Api/Controller/BaseController.php:154`
- `src/Api/Controller/DashboardController.php:750`

**Código problemático:**
```php
$cacheKey = $this->cacheKey('dashboard', 'tecnico', $userId);
// $userId é int, mas cacheKey() espera string
```

**Possível solução:**
```php
$cacheKey = $this->cacheKey('dashboard', 'tecnico', (string) $userId);
```

---

## 🟠 Erro 3: Logger::getInstance() não existe

**Dashboard afetado:** Todos (indiretamente via NotificationService)

**Mensagem de erro:**
```
Call to undefined method DotProject\Core\Logger::getInstance()
```

**Arquivo:**
- `src/Service/NotificationService.php:22`

**Causa:** Método não existe na classe Logger

**Possível solução:** Verificar se é `new Logger()` ou outro padrão

---

## 🟠 Erro 4: NotificationService::getUnreadCount()

**Endpoint afetado:** `/notifications/unread-count`

**Mensagem de erro:**
```
Call to undefined method DotProject\Service\NotificationService::getUnreadCount()
```

**Arquivo:**
- `src/Api/Controller/NotificationController.php:65`

---

## 🎯 Sugestão de Ordem de Correção

### Opção A: Por dependência
1. **Logger** (afeta todos)
2. **PermissionService** (afeta 2 dashboards)
3. **cacheKey** (afeta 1 dashboard)
4. **NotificationService** (feature separada)

### Opção B: Por impacto (iterativo)
1. **Secretário** (PermissionService) - libera 1 dashboard
2. **Coordenador** (PermissionService) - libera 2 dashboards
3. **Técnico** (cacheKey) - libera todos os dashboards
4. **Logger/Notification** - melhorias

---

## 📁 Arquivos Relacionados

```
src/
├── Api/
│   ├── Controller/
│   │   ├── DashboardController.php    (linhas 425, 582, 750)
│   │   ├── BaseController.php         (linha 154 - cacheKey)
│   │   └── NotificationController.php (linha 65)
│   └── Request.php                    (verificar getParam)
├── Service/
│   ├── PermissionService.php          (verificar inicialização)
│   └── NotificationService.php        (linha 22)
└── Core/
    └── Logger.php                     (verificar métodos disponíveis)
```

---

## 📝 Histórico de Correções

### 02/02/2026 - Kimi
- ✅ **PermissionService**: Adicionado cast `(int)` nas linhas 127-128
  ```php
  $unidadeId = (int) $vinculo['vinculo_unidade_id'];
  $nivel = (int) $vinculo['unidade_nivel'];
  ```
- ✅ **Dashboard Coordenador**: Funcionando após correção do PermissionService
- ✅ **Dashboard Técnico**: Funcionando após correção do PermissionService

### 03/02/2026 - Status Atual
- 🔄 **Dashboard Secretário**: Erro 500 persiste, não sendo capturado pelo try-catch
  - Possível causa: middleware ou inicialização do controller
  - @ChatGPT investigando
- 🔄 **NotificationService**: Erro 500 persiste
  - @ChatGPT corrigindo

---

## 💬 Notas para o ChatGPT

**ChatGPT**, para finalizar as correções:

1. **Dashboard Secretário:**
   - O erro 500 não está sendo capturado pelo try-catch no método `secretario()`
   - Sugestão: Adicionar logs no início do arquivo `api_routes_dashboard.php` e no construtor do `DashboardController`
   - Verificar se as tabelas existem no banco: `SHOW TABLES LIKE 'dotp_%'`

2. **NotificationService:**
   - Verificar se `Logger::getInstance()` existe em `src/Core/Logger.php`
   - Adicionar método `getUnreadCount()` se não existir

---

**Aguardando correções do ChatGPT!** 🚀
