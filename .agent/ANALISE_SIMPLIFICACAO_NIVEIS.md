# Simplificação: Unificar Níveis e Unidades

## ❌ Problema

**Níveis Hierárquicos** e **Unidades** são conceitos separados mas relacionados:

```
Níveis Hierárquicos          Unidades
─────────────────           ─────────
Prefeitura         →        Prefeitura Municipal
Secretaria         →        Secretaria de Educação
                   →        Secretaria de Saúde
Coordenadoria      →        Coordenadoria de TI
                   →        Coordenadoria de Projetos
```

**Problemas:**
1. Duas telas para gerenciar algo relacionado
2. Usuário precisa criar nível ANTES de criar unidade
3. Complexidade desnecessária

## ✅ Solução Proposta

### Nova Estrutura

```
⚙️ Administração
   ├─ 🏢 Unidades Organizacionais  
   │     (com tipo/nível incluído)
   ├─ 👥 Usuários
   └─ 🔐 Permissões
```

### Campo "Tipo" na Unidade

Cada unidade terá um campo `tipo`:

| Tipo | Hierarquia | Exemplo |
|------|------------|---------|
| `prefeitura` | 1 | Prefeitura Municipal |
| `secretaria` | 2 | Secretaria de Educação |
| `coordenadoria` | 3 | Coordenadoria de TI |
| `unidade` | 4 | Posto de Saúde |

### Benefícios

1. **Uma tela** para gerenciar estrutura
2. **Mais intuitivo** - cria unidade e define tipo
3. **Menos código** - remove repository, controller, tabela
4. **Mais rápido** - setup inicial simplificado

## 🔧 Implementação

### Backend

```php
// Tabela: dotp_unidades_organizacionais
unidade_id (PK)
unidade_nome
unidade_tipo (enum: prefeitura, secretaria, coordenadoria, unidade)
unidade_ordem (1, 2, 3, 4...)
unidade_pai_id (FK para hierarquia)
...
```

### Frontend

```jsx
// Form de Unidade
<NomeInput />
<TipoSelect 
  options={[
    {value: 'prefeitura', label: 'Prefeitura (Nível 1)'},
    {value: 'secretaria', label: 'Secretaria (Nível 2)'},
    {value: 'coordenadoria', label: 'Coordenadoria (Nível 3)'},
    {value: 'unidade', label: 'Unidade Operacional (Nível 4)'},
  ]}
/>
<UnidadePaiSelect /> // Opcional
```

## 📋 Checklist

- [ ] Remover menu "Níveis Hierárquicos"
- [ ] Adicionar campo `tipo` na tabela de unidades
- [ ] Atualizar formulário de unidades
- [ ] Remover código de níveis (repository, controller)
- [ ] Migrar dados existentes (se houver)
- [ ] Testar criação de unidades

## 💡 Conclusão

Esta simplificação reduz:
- **1 menu** da navegação
- **1 tabela** do banco  
- **~500 linhas** de código
- **Complexidade** para o usuário

Mantém a **mesma funcionalidade** de forma mais simples!
