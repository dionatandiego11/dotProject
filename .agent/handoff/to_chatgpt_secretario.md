# 📝 Handoff: Debug Dashboard Secretário

**Data**: 02/02/2026 09:15  
**De**: Kimi  
**Para**: ChatGPT ou Antigravity  
**Prioridade**: Alta

---

## Contexto

Corrigi o erro de tipo no PermissionService (cast para int) e agora 4 de 5 dashboards funcionam:
- ✅ Prefeito: 200
- ✅ Controlador: 200
- ✅ Coordenador: 200 (corrigido)
- ✅ Técnico: 200
- ❌ Secretário: 500

## Problema

O dashboard do Secretário retorna HTTP 500 com resposta vazia. O erro **não aparece** nos logs do PHP-FPM.

### Comportamento observado:
```bash
curl http://localhost:8088/api.php/v1/dashboard/secretario -H "Authorization: Bearer $TOKEN"
# Retorna: (vazio)
# HTTP Status: 500
```

### Logs do PHP-FPM:
- Não mostram erro relacionado ao `/dashboard/secretario`
- Apenas erros do NotificationService (não relacionado)

## Arquivos Relevantes

- `src/Api/Controller/DashboardController.php`
  - Método `secretario()` (linha ~424)
  - Método `getSecretarioDashboardModern()` (linha ~457)
  - Método `checkModernTables()` (linha ~75)

- `src/Service/PermissionService.php`
  - Método `getEscopoDados()` (linha ~111) - **Já corrigido**

## Código do Método Secretário

```php
public function secretario(): Response
{
    try {
        $userId = $this->getUserId();
        $escopo = $this->getPermissionService()->getEscopoDados($userId);

        if (!$escopo) {
            return $this->json(['error' => 'Escopo não encontrado'], 403);
        }

        $unidadeId = $escopo['unidade_id'];
        $unidadesEscopo = $escopo['unidades_escopo'];

        $cacheKey = $this->cacheKey('dashboard', 'secretario', $unidadeId);
        $cached = $this->cache->get($cacheKey);

        if ($cached) {
            return $this->json($cached);
        }

        if ($this->checkModernTables()) {
            $data = $this->getSecretarioDashboardModern($unidadeId, $unidadesEscopo);
        } else {
            $data = $this->getSecretarioDashboardLegacy($userId);
        }

        $this->cache->set($cacheKey, $data, 300);

        return $this->json(['data' => $data]);
    } catch (\Throwable $e) {
        return $this->json([
            'error' => 'Erro ao carregar dashboard: ' . $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ], 500);
    }
}
```

## Possíveis Causas

1. **Erro no checkModernTables()** - Query SQL pode estar falhando
2. **Erro nas queries SQL** do `getSecretarioDashboardModern()`
3. **Tabelas não existem** - `dotp_programas`, `dotp_projetos_prefeitura`
4. **Erro de permissão** no banco de dados
5. **Erro no middleware** antes do controller

## Critérios de Sucesso

- [ ] Identificar a causa raiz do erro 500
- [ ] Dashboard Secretário retornar HTTP 200
- [ ] Retornar dados válidos no JSON

## Observações

- O try-catch foi adicionado mas não está capturando o erro
- O erro pode estar ocorrendo antes do método ser chamado
- Verificar se há algum problema na rota ou middleware

## Comandos Úteis

```bash
# Testar o endpoint
TOKEN="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
curl -v http://localhost:8088/api.php/v1/dashboard/secretario \
  -H "Authorization: Bearer $TOKEN"

# Ver logs em tempo real
docker logs dotproject_phpfpm_1 -f

# Verificar tabelas no banco
docker exec dotproject_mariadb_1 mysql -u root -pdotproject \
  -e "SHOW TABLES LIKE 'dotp_%';" dotproject
```
