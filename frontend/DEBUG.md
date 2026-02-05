# 🔧 Problema: Página em Branco

## Solução Rápida

### 1. Limpar Cache do Navegador

**Chrome/Edge:**
1. Pressione `Ctrl + Shift + Delete`
2. Selecione "Imagens e arquivos em cache"
3. Clique em "Limpar dados"
4. Recarregue a página: `Ctrl + F5`

**Firefox:**
1. Pressione `Ctrl + Shift + Delete`
2. Selecione "Cache"
3. Clique em "Limpar agora"
4. Recarregue a página: `Ctrl + F5`

### 2. Verificar Console do Navegador

1. Pressione `F12` (ou clique direito > Inspecionar)
2. Clique na aba "Console"
3. Veja se há erros em vermelho
4. Tire um print e envie

### 3. Testar em Modo Anônimo

Abra uma janela anônima (Ctrl + Shift + N) e acesse:
- (debug.html removido)

### 4. Verificar se o Servidor está Rodando

```bash
cd /mnt/c/Users/dionatan.resende/Downloads/dotProject/frontend
npm run dev
```

Deve aparecer:
```
VITE v5.4.21  ready in XXX ms
➜  Local:   http://localhost:5173/
```

### 5. URLs de Teste

- (debug.html/test.html removidos)
- http://localhost:5173/ - Aplicação principal
- http://localhost:5173/admin - Área administrativa

## Problemas Comuns

### "Cannot read property of undefined"
→ Limpar localStorage e fazer login novamente

### "Loading chunk failed"
→ Limpar cache do navegador

### "JSON parse error"
→ Verificar se o backend PHP está rodando

### Página fica carregando infinitamente
→ Verificar conexão com a API no console

## Comandos Úteis

```bash
# Reinstalar dependências
rm -rf node_modules package-lock.json
npm install

# Limpar build
rm -rf dist

# Rebuild
npm run build

# Iniciar servidor de desenvolvimento
npm run dev
```
