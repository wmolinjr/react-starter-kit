# Como Usar o Agente Multi-Tenant SaaS Builder

## 🤖 Sobre o Agente

O **Multi-Tenant SaaS Builder Agent** é um agente especializado que implementa o sistema Multi-Tenant SaaS seguindo rigorosamente a documentação em `docs/multi-tenant/`.

### Características:

- ✅ **Rigoroso:** Segue documentação à risca
- ✅ **Consultivo:** Pergunta quando tem dúvidas
- ✅ **Metódico:** Testa tudo antes de concluir
- ✅ **Documentado:** Registra cada etapa
- ✅ **Seguro:** Não pula etapas ou ignora erros

---

## 🚀 Como Invocar o Agente

### Método 1: Via Task Tool (Recomendado)

```typescript
// No Claude Code, use:
Task → laravel-inertia-fullstack

Prompt:
"Você é o Multi-Tenant SaaS Builder Agent.

INSTRUÇÕES:
- Leia AGENT-GUIDE.md para entender seu workflow
- Leia a documentação em docs/multi-tenant/
- Comece pela Etapa 1 (01-SETUP.md)
- Siga o workflow: ANÁLISE → IMPLEMENTAÇÃO → VERIFICAÇÃO → DOCUMENTAÇÃO → CONCLUSÃO
- Use TodoWrite para rastrear progresso
- Consulte Context7 antes de implementar features
- Verifique Telescope após mudanças no backend
- Teste com Playwright após mudanças no frontend
- Pergunte SEMPRE que tiver dúvidas
- Documente em IMPLEMENTATION-LOG.md

OBJETIVO:
Implementar a Etapa 1 - Setup Inicial (01-SETUP.md)

Comece analisando a documentação e me pergunte se tiver dúvidas."
```

### Método 2: Invocar Diretamente

Se você está conversando diretamente com Claude Code:

```
"Vou seguir o AGENT-GUIDE.md para implementar o Multi-Tenant SaaS.

Iniciando Etapa 1 - Setup Inicial.

[Agente lê 01-SETUP.md e começa o workflow]"
```

---

## 📋 Workflow Resumido

### 1️⃣ ANÁLISE
- Ler documentação da etapa
- Identificar arquivos a criar/modificar
- Listar dúvidas
- Criar TodoList

### 2️⃣ IMPLEMENTAÇÃO
- Implementar código
- Seguir best practices (Context7)
- Adicionar validações e segurança

### 3️⃣ VERIFICAÇÃO
- **Backend:** Telescope (requests, queries, exceptions)
- **Frontend:** Playwright (console, renderização, funcionalidade)
- **Testes:** `php artisan test`, `npm run types`

### 4️⃣ DOCUMENTAÇÃO
- Atualizar IMPLEMENTATION-LOG.md
- Marcar checklist da etapa

### 5️⃣ CONCLUSÃO
- Resumir o que foi feito
- Perguntar se pode prosseguir

---

## 💬 Exemplos de Interação

### Exemplo 1: Primeira Invocação

**Você:**
```
Quero implementar o Multi-Tenant SaaS seguindo a documentação.
Use o AGENT-GUIDE.md e comece pela Etapa 1.
```

**Agente:**
```
🚀 Iniciando implementação do Multi-Tenant SaaS

📖 Li o AGENT-GUIDE.md e entendi o workflow.

📋 Etapa 1 - Setup Inicial (01-SETUP.md)

ANÁLISE:
Vou instalar os seguintes pacotes:
- archtechx/tenancy (multi-tenancy core)
- laravel/cashier (billing com Stripe)
- spatie/laravel-medialibrary (file management)
- laravel/sanctum (API tokens)

DÚVIDAS antes de começar:

1. Qual banco de dados você prefere?
   - PostgreSQL (recomendado para produção)
   - SQLite (setup mais rápido para dev)

2. Qual ambiente de desenvolvimento?
   - Laravel Herd (macOS)
   - Laravel Valet (macOS)
   - Laravel Sail (Docker)
   - Local (PHP nativo)

Por favor, escolha para que eu possa prosseguir.
```

### Exemplo 2: Durante Implementação

**Agente:**
```
⚙️ [in_progress] Instalando archtechx/tenancy...

$ composer require archtechx/tenancy

✅ [completed] Pacote instalado com sucesso!

⚙️ [in_progress] Publicando configurações...

$ php artisan vendor:publish --provider="Stancl\Tenancy\TenancyServiceProvider"

✅ [completed] Arquivos publicados:
- config/tenancy.php
- app/Providers/TenancyServiceProvider.php
- routes/tenant.php

➡️ Próximo: Configurar config/tenancy.php
```

### Exemplo 3: Verificação com MCPs

**Agente:**
```
🧪 VERIFICAÇÃO (Obrigatória)

Backend - Telescope MCP:
✅ Requests: 0 erros
✅ Queries: Todas usando indexes
✅ Exceptions: Nenhuma

Frontend - Playwright MCP:
✅ Navegação para http://tenant.myapp.test/dashboard
✅ Console: Sem erros JavaScript
✅ Snapshot: Página renderizando corretamente

Testes Automatizados:
$ php artisan test

✅ 15 tests passed (0.5s)

ETAPA CONCLUÍDA! ✅

Posso prosseguir para Etapa 2 (Database Schema)?
```

### Exemplo 4: Quando Encontra Problema

**Agente:**
```
⚠️ PROBLEMA ENCONTRADO

Etapa: 6 - Team Management
Tarefa: Enviar email de convite

ERRO:
Connection refused ao tentar enviar email via SMTP

ANÁLISE:
- .env tem MAIL_HOST=mailpit
- Mailpit não está rodando

CONSULTANDO USUÁRIO:
Como você quer lidar com emails no desenvolvimento?

Opções:
1. Instalar Mailpit (recomendado)
   $ brew install mailpit && mailpit

2. Usar Laravel Log driver (emails salvos em log)
   MAIL_MAILER=log

3. Usar Mailtrap (serviço online)
   Preciso das credenciais

Qual opção você prefere?
```

---

## 🎯 Dicas para Melhor Interação

### ✅ Faça

1. **Seja Claro nas Decisões**
   ```
   ✅ "Use PostgreSQL"
   ✅ "Pule a etapa de billing por enquanto"
   ✅ "Vamos com Herd para desenvolvimento"
   ```

2. **Forneça Credenciais Quando Solicitadas**
   ```
   ✅ "Stripe Test Key: pk_test_..."
   ✅ "AWS Bucket: meu-bucket-saas"
   ```

3. **Confirme Antes de Prosseguir**
   ```
   ✅ "Sim, pode ir para próxima etapa"
   ✅ "Aguarde, quero revisar o código primeiro"
   ```

### ❌ Evite

1. **Instruções Vagas**
   ```
   ❌ "Faz do jeito que você achar melhor"
   → Prefira: "Use PostgreSQL conforme a doc recomenda"
   ```

2. **Pular Verificações**
   ```
   ❌ "Não precisa testar, confia"
   → Agente SEMPRE vai testar (é obrigatório)
   ```

3. **Adicionar Features Fora do Escopo**
   ```
   ❌ "Adiciona integração com WhatsApp também"
   → Isso não está na documentação, agente vai perguntar
   ```

---

## 📊 Acompanhando o Progresso

### Via IMPLEMENTATION-LOG.md

O agente atualiza automaticamente:

```markdown
## [Etapa 02] - Database Schema - 2025-11-19 10:30

### ✅ Tarefas Completadas
- [x] Migration create_tenants_table
- [x] Migration create_domains_table
- [x] Migration create_tenant_user_table
- [x] Seeder TenantSeeder

### 📁 Arquivos Criados
- database/migrations/2025_11_19_create_tenants_table.php
- database/migrations/2025_11_19_create_domains_table.php
...

### 🧪 Testes Executados
- ✅ Telescope: Sem N+1 queries
- ✅ PHPUnit: 20 passed
```

### Via TodoWrite

Durante a implementação, você verá:

```
☐ Etapa 1 - Setup Inicial
☐ Etapa 2 - Database Schema
  ☑ Migration create_tenants_table
  ⚙ Migration create_domains_table (in progress)
  ☐ Migration create_tenant_user_table
  ☐ Seeder TenantSeeder
```

---

## 🆘 Quando Parar o Agente

### Situações que Requerem Intervenção

```
⚠️ PARAR SE:

1. Muitos erros consecutivos (>3)
   → Pode haver problema de ambiente

2. Agente está confuso ou fazendo loops
   → Reiniciar com instruções mais claras

3. Você quer mudar de direção
   → Dizer claramente: "Pare, vamos mudar X"

4. Encontrou bug na documentação
   → Reportar e corrigir antes de prosseguir
```

### Como Parar

```
Você: "PARAR. Vamos revisar antes de continuar."

Agente: "⏸️ Pausado.

Status atual:
- Etapa 3 em progresso
- 2/5 tarefas concluídas
- Último arquivo: app/Models/Tenant.php

O que você gostaria de fazer?
1. Revisar código atual
2. Mudar decisão anterior
3. Pular esta etapa
4. Continuar de onde parou"
```

---

## 🔄 Retomar Após Pausa

### Se Você Parou no Meio de uma Etapa

```
Você: "Vamos continuar a Etapa 3 de onde paramos."

Agente: "📂 Verificando status...

IMPLEMENTATION-LOG.md mostra:
- Etapa 3 (Models) - 40% concluída
- Última tarefa: Model Tenant criado
- Próxima tarefa: Model Domain

Vou continuar de onde paramos. Correto?"
```

### Se Quer Pular Etapa

```
Você: "Vamos pular a Etapa 7 (Billing) por enquanto."

Agente: "⚠️ ATENÇÃO

A Etapa 7 (Billing) tem dependências:
- Etapa 2 (Database) - subscription tables
- Etapa 3 (Models) - Tenant Billable trait

Se pularmos, essas partes não funcionarão.

Opções:
1. Implementar versão mínima (sem Stripe real)
2. Remover billing completamente (limpar migrations)
3. Marcar como 'TODO' e continuar

Qual você prefere?"
```

---

## 🎓 Entendendo as Ferramentas MCP

### Context7 (Documentação)

**O Agente Usa Para:**
- Verificar API correta do Inertia
- Exemplos de código do Laravel
- Best practices do React

**Você Verá:**
```
📚 Context7: Consultando docs do Inertia.js...

✅ Encontrado: Form component com useForm hook
Implementando seguindo padrão oficial.
```

### Telescope (Debugging Backend)

**O Agente Usa Para:**
- Verificar queries (N+1 problems)
- Ver exceptions não tratadas
- Validar requests

**Você Verá:**
```
🔭 Telescope: Verificando última request POST /projects...

✅ Status: 200 OK
✅ Queries: 3 (sem N+1)
✅ Tempo: 145ms
⚠️ Warning: Query sem index em user_id
  → Adicionando index...
```

### Playwright (Testing Frontend)

**O Agente Usa Para:**
- Verificar renderização
- Testar formulários
- Ver erros de console

**Você Verá:**
```
🎭 Playwright: Testando /team...

✅ Página carregou
✅ Console: 0 erros
✅ Formulário de convite renderizado
⚙️ Testando envio de formulário...
✅ Convite enviado com sucesso
```

---

## 📞 Suporte

### Se o Agente Não Está Funcionando Bem

1. **Verifique se leu o AGENT-GUIDE.md**
   ```
   Você: "Você leu o AGENT-GUIDE.md?"
   ```

2. **Reinicie com Contexto**
   ```
   Você: "Reinicie como Multi-Tenant SaaS Builder Agent.
   Leia AGENT-GUIDE.md e comece pela Etapa X."
   ```

3. **Seja Mais Específico**
   ```
   ❌ "Faz aí"
   ✅ "Implemente a Etapa 2 seguindo 02-DATABASE.md"
   ```

### Reportar Problemas na Documentação

Se encontrar erro na documentação:

1. Anote qual etapa e seção
2. Descreva o problema
3. Sugira correção (se possível)

---

## 🎉 Conclusão

O agente está pronto para construir seu Multi-Tenant SaaS de forma:
- **Rigorosa** (testa tudo)
- **Documentada** (registra tudo)
- **Consultiva** (pergunta quando tem dúvida)
- **Segura** (não pula etapas)

**Comece agora:**
```
"Implemente o Multi-Tenant SaaS seguindo AGENT-GUIDE.md.
Comece pela Etapa 1 (Setup)."
```

Boa sorte! 🚀

---

**Versão:** 1.0
**Última Atualização:** 2025-11-19
