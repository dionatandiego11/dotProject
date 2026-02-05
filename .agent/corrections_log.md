# 📋 Log de Correções - dotProject
> Memória de correções realizadas para referência futura

---

## 🔧 Sessão: 30/01/2026

### Problema 1: Proxy do Vite (ECONNREFUSED)
**Sintoma**: Frontend não conseguia conectar com a API, erro `ECONNREFUSED`

**Causa**: O container Docker `frontend` usava `localhost:8088` como target do proxy, mas dentro do container Docker, `localhost` refere-se ao próprio container, não ao host.

**Solução**: Alterado `vite.config.js`:
```javascript
// Antes (ERRADO - dentro do Docker)
target: 'http://localhost:8088'

// Depois (CORRETO - usa nome do serviço Docker)
target: 'http://nginx:80'
```

**Arquivo**: `frontend/vite.config.js`

---

### Problema 2: Login retornando 401 (Invalid Credentials)
**Sintoma**: Login com `admin/passwd` retornava 401

**Causa**: Senha estava em formato bcrypt mas valor não correspondia

**Solução**: Resetada senha do admin para MD5:
```sql
UPDATE dotp_users SET user_password = MD5('admin') WHERE user_username = 'admin'
```

**Credenciais atuais**: `admin` / `admin`

---

### Problema 3: DashboardController erro 500
**Sintoma**: Endpoint `/dashboard/prefeito` retornava 500

**Causa Raiz**: O construtor instanciava repositórios que podiam falhar, e os métodos internos (`getPrefeitoDashboardModern()`) tentavam usar `$cacheKey` não definido e retornavam `Response` ao invés de `array`.

**Solução 1 - Lazy Loading**:
```php
// Antes
public function __construct(Request $request, Response $response) {
    $this->alertaRepo = new AlertaRepository(); // Executa no construtor
}

// Depois
private function getAlertaRepo(): AlertaRepository {
    if ($this->alertaRepo === null) {
        $this->alertaRepo = new AlertaRepository();
    }
    return $this->alertaRepo;
}
```

**Solução 2 - Retorno correto**:
```php
// Antes (ERRADO)
private function getPrefeitoDashboardModern(): array {
    // ...
    $this->cache->set($cacheKey, $data, 300); // $cacheKey não existe aqui!
    return $this->json(['data' => $data]);    // Retorna Response, não array!
}

// Depois (CORRETO)
private function getPrefeitoDashboardModern(): array {
    // ...
    return $data; // Retorna array, método pai faz cache e json
}
```

**Arquivo**: `src/Api/Controller/DashboardController.php`

---

### Problema 4: Método cacheKey com assinatura incorreta
**Sintoma**: Chamadas `cacheKey('a', 'b', 'c')` ignoravam argumentos extras

**Solução**: Alterado para variadic:
```php
// Antes
protected function cacheKey(string $suffix = ''): string

// Depois
protected function cacheKey(string ...$parts): string
```

**Arquivo**: `src/Api/Controller/BaseController.php`

---

## ⚠️ Erros Pendentes Identificados

### DashboardController - Métodos com Mesmo Padrão de Erro
Os seguintes métodos têm os mesmos problemas (usar `$cacheKey` não definido e retornar `Response` ao invés de `array`):

| Método | Linha Aprox. | Status |
|--------|--------------|--------|
| `getSecretarioDashboardModern()` | 520-522 | ❌ Pendente |
| `getCoordenadorDashboardModern()` | 682-684 | ❌ Pendente |
| `getTecnicoDashboard()` | 877-879 | ❌ Pendente |
| `getControladorDashboardLegacy()` | 1009-1011 | ❌ Pendente |

### Database.fetchAll sendo chamado com 2 argumentos
O método `fetchAll()` aceita apenas 1 argumento (SQL), mas está sendo chamado com 2 (SQL + params). Deveria usar `fetchAllParams()`.

**Linhas afetadas**: 471, 483, 502, 545, 560, 627, 648, 663, 704, 726, 785, 813, 828, 844, 858

### Request.getAttribute() não existe
O método correto é `$this->request->getParam()`.

**Linhas afetadas**: 98, 424, 506, 581, 752, 1020, 1044, 1069

---

## 📊 Impacto das Correções

| Métrica | Antes | Depois |
|---------|-------|--------|
| Login | ❌ 401 | ✅ 200 + JWT |
| Dashboard Prefeito | ❌ 500 | ✅ 200 + dados |
| Dashboard Status | N/A | ✅ 200 |
| Outros Dashboards | ❌ 500 | ⚠️ Pendente |

---

## 🔑 Lições Aprendidas

1. **Docker Networking**: Dentro de containers, usar nome do serviço, não `localhost`
2. **Separação de responsabilidades**: Métodos internos devem retornar dados, método público faz cache/response
3. **Lazy Loading**: Evitar instanciar dependências pesadas no construtor
4. **Verificar assinaturas**: Antes de chamar métodos, verificar se aceitam os argumentos
