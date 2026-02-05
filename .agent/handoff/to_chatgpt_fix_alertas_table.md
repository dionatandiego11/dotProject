# 🔧 Handoff: Corrigir Estrutura da Tabela dotp_alertas

**De:** Kimi  
**Para:** ChatGPT  
**Data:** 2026-02-02  
**Status:** 🚨 NOVO PROBLEMA IDENTIFICADO

---

## 🎯 Problema Encontrado

Após suas correções no `AlertaRepository.php`, descobri que o problema é mais profundo:

**Inconsistência entre código e banco de dados!**

---

## 📊 Comparação: Código vs Banco

### Código Espera (migration 004):
```sql
alerta_id
alerta_tipo
alerta_titulo
alerta_descricao
alerta_projeto_id
alerta_etapa_id
alerta_programa_id
alerta_destinatario_id
alerta_unidade_id
alerta_prioridade
alerta_lido
alerta_data_leitura
alerta_acao_requerida
alerta_link_acao
alerta_data_criacao
```

### Banco de Dados Tem:
```sql
id
tipo
titulo (mas mensagem no lugar de descricao)
projeto_id
programa_id
etapa_id
destinatario_id
prioridade
lido
data_leitura
created_at
```

---

## 🎯 Soluções Possíveis

### Opção A: Recriar a Tabela (Recomendada)

Rodar a migration correta:

```sql
-- Dropar tabela incorreta
DROP TABLE IF EXISTS dotp_alertas;

-- Criar com estrutura correta
CREATE TABLE IF NOT EXISTS dotp_alertas (
    alerta_id INT AUTO_INCREMENT PRIMARY KEY,
    alerta_tipo VARCHAR(50) NOT NULL,
    alerta_titulo VARCHAR(255) NOT NULL,
    alerta_descricao TEXT,
    alerta_projeto_id INT NULL,
    alerta_etapa_id INT NULL,
    alerta_programa_id INT NULL,
    alerta_destinatario_id INT NOT NULL,
    alerta_unidade_id INT NULL,
    alerta_prioridade ENUM('baixa', 'media', 'alta', 'critica') DEFAULT 'media',
    alerta_lido BOOLEAN DEFAULT FALSE,
    alerta_data_leitura TIMESTAMP NULL,
    alerta_acao_requerida VARCHAR(255),
    alerta_link_acao VARCHAR(500),
    alerta_data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_destinatario (alerta_destinatario_id),
    INDEX idx_destinatario_lido (alerta_destinatario_id, alerta_lido),
    INDEX idx_tipo (alerta_tipo),
    INDEX idx_prioridade (alerta_prioridade),
    INDEX idx_projeto (alerta_projeto_id),
    INDEX idx_data_criacao (alerta_data_criacao),
    INDEX idx_nao_lidos (alerta_destinatario_id, alerta_prioridade, alerta_lido)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Opção B: Adaptar o Código

Alterar o `AlertaRepository.php` para usar os nomes de colunas sem prefixo.

---

## 📋 Recomendação

**Siga a Opção A** (recriar a tabela) porque:
1. O código já foi escrito para usar os prefixos `alerta_`
2. A migration 004 já define a estrutura correta
3. Manter consistência com o padrão do projeto (outras tabelas usam prefixos)

---

## 🔧 Comando para Executar

```bash
docker exec -i dotproject_mariadb_1 mysql -u dotproject -pdotproject123 dotproject << 'EOF'
DROP TABLE IF EXISTS dotp_alertas;

CREATE TABLE IF NOT EXISTS dotp_alertas (
    alerta_id INT AUTO_INCREMENT PRIMARY KEY,
    alerta_tipo VARCHAR(50) NOT NULL,
    alerta_titulo VARCHAR(255) NOT NULL,
    alerta_descricao TEXT,
    alerta_projeto_id INT NULL,
    alerta_etapa_id INT NULL,
    alerta_programa_id INT NULL,
    alerta_destinatario_id INT NOT NULL,
    alerta_unidade_id INT NULL,
    alerta_prioridade ENUM('baixa', 'media', 'alta', 'critica') DEFAULT 'media',
    alerta_lido BOOLEAN DEFAULT FALSE,
    alerta_data_leitura TIMESTAMP NULL,
    alerta_acao_requerida VARCHAR(255),
    alerta_link_acao VARCHAR(500),
    alerta_data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_destinatario (alerta_destinatario_id),
    INDEX idx_destinatario_lido (alerta_destinatario_id, alerta_lido),
    INDEX idx_tipo (alerta_tipo),
    INDEX idx_prioridade (alerta_prioridade),
    INDEX idx_projeto (alerta_projeto_id),
    INDEX idx_data_criacao (alerta_data_criacao),
    INDEX idx_nao_lidos (alerta_destinatario_id, alerta_prioridade, alerta_lido)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOF
```

---

## ✅ Após Correção

Testar:
```bash
curl http://localhost:8088/api.php/v1/dashboard/secretario \
  -H "Authorization: Bearer $TOKEN"
```

Deve retornar HTTP 200! 🎉

---

**Aguardando correção da estrutura da tabela!** 
