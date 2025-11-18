# Git Workflow - Estrutura com Fork Público + Repositório Privado

Este projeto usa uma estratégia de versionamento com **3 remotes** para separar código público e desenvolvimento privado.

## Estrutura de Remotes

```
upstream  → https://github.com/laravel/react-starter-kit (Laravel Original - Somente Leitura)
origin    → https://github.com/wmolinjr/react-starter-kit.git (Fork Público - Apenas main)
private   → https://github.com/wmolinjr/multi-tenant-site.git (Privado - develop e features)
```

## Estrutura de Branches

```
Laravel (upstream/main)                    [PÚBLICO - Laravel Original]
    ↓ sincronização
Fork Público (origin/main)                 [PÚBLICO - Seu Fork]
    ↓ push também para
Repo Privado (private/main)                [PRIVADO - Backup]
    ↓ merge
Repo Privado (private/develop)             [PRIVADO - Desenvolvimento]
    ↓ features
Repo Privado (private/feature/*)           [PRIVADO - Features]
```

### Branches Locais

```bash
main      [origin/main]     # Sincronizada com Laravel, pública
develop   [private/develop] # Desenvolvimento principal, privada
feature/* [local/private]   # Features em desenvolvimento, privadas
```

## Aliases Configurados

### 1. sync-upstream
Sincroniza com Laravel e atualiza ambos os repositórios (público e privado)

```bash
git sync-upstream
```

**O que faz:**
1. Busca atualizações do Laravel (`upstream`)
2. Faz checkout em `main`
3. Merge com `upstream/main`
4. Push para fork público (`origin/main`)
5. Push para repositório privado (`private/main`)

### 2. update-develop
Atualiza `develop` com mudanças do `main`

```bash
git update-develop
```

**O que faz:**
1. Checkout em `develop`
2. Merge com `main`
3. Push para repositório privado (`private/develop`)

### 3. new-feature
Cria nova branch de feature a partir de `develop`

```bash
git new-feature nome-da-feature
```

**O que faz:**
1. Checkout em `develop`
2. Cria branch `feature/nome-da-feature`
3. Faz checkout na nova branch

### 4. finish-feature
Finaliza feature e merge em `develop`

```bash
git finish-feature
```

**O que faz:**
1. Identifica branch atual
2. Checkout em `develop`
3. Merge da feature em `develop` (com --no-ff)
4. Push para `private/develop`
5. Deleta branch de feature local

### 5. status-all
Ver status e comparação com upstream

```bash
git status-all
```

**O que faz:**
1. Busca atualizações de todos os remotes
2. Mostra status atual
3. Lista commits que upstream tem e você não

## Workflows Comuns

### Workflow 1: Sincronização com Laravel (Semanal/Mensal)

```bash
# 1. Sincronizar main com Laravel
git sync-upstream

# 2. Atualizar develop com mudanças do main
git update-develop

# 3. Se houver conflitos em develop:
git checkout develop
git status
# Resolver conflitos manualmente
git add .
git commit -m "merge: resolve conflicts with upstream"
git push private develop
```

### Workflow 2: Desenvolver Nova Feature

```bash
# 1. Criar branch de feature
git new-feature autenticacao-social

# 2. Desenvolver
# ... fazer mudanças ...
git add .
git commit -m "feat: adiciona login com Google"
git commit -m "feat: adiciona login com GitHub"

# 3. Push para repositório privado (opcional, para backup)
git push private feature/autenticacao-social

# 4. Quando terminar, finalizar feature
git finish-feature

# A branch feature/autenticacao-social é deletada automaticamente
```

### Workflow 3: Correção de Bug Urgente (Hotfix)

```bash
# 1. Criar hotfix a partir de main
git checkout main
git checkout -b hotfix/corrigir-login

# 2. Corrigir
# ... fazer correção ...
git add .
git commit -m "fix: corrige bug no login"

# 3. Merge direto no main
git checkout main
git merge hotfix/corrigir-login

# 4. Push para ambos os repositórios
git push origin main
git push private main

# 5. Atualizar develop
git update-develop

# 6. Limpar
git branch -d hotfix/corrigir-login
```

### Workflow 4: Atualizar Feature com Mudanças do Develop

```bash
# Quando develop é atualizado e você quer trazer mudanças para sua feature
git checkout feature/minha-feature
git merge develop

# Se houver conflitos, resolver:
# ... resolver conflitos ...
git add .
git commit -m "merge: atualiza feature com develop"

# Push atualizado (opcional)
git push private feature/minha-feature
```

## Comandos de Verificação

### Ver Remotes Configurados
```bash
git remote -v
```

### Ver Branches e Tracking
```bash
git branch -vv
```

### Ver Histórico Gráfico
```bash
git log --oneline --graph --all --decorate -20
```

### Ver Diferenças com Upstream
```bash
git fetch upstream
git log --oneline main..upstream/main  # Commits que Laravel tem
git diff main upstream/main            # Diferenças de código
git diff --name-only main upstream/main  # Arquivos modificados
```

### Ver Status de Sincronização
```bash
git status-all
```

## Segurança e Privacidade

### ✅ Público (Visível para Todos)
- `origin/main` - Fork público sincronizado com Laravel
- Nenhum código de desenvolvimento

### 🔒 Privado (Apenas Você)
- `private/main` - Backup do main
- `private/develop` - Todo desenvolvimento
- `private/feature/*` - Todas as features
- Todas as customizações e mudanças específicas do projeto

### ⚠️ Importante
- **NUNCA** fazer push de `develop` ou `feature/*` para `origin`
- **SEMPRE** fazer push de desenvolvimento para `private`
- Apenas `main` vai para o fork público (`origin`)

## Fluxo de Dados

```
┌─────────────────────────────────────────────────────┐
│ Laravel (upstream)                                   │
│ https://github.com/laravel/react-starter-kit        │
└────────────────┬────────────────────────────────────┘
                 │ git sync-upstream
                 ↓
┌─────────────────────────────────────────────────────┐
│ Fork Público (origin/main)                          │
│ https://github.com/wmolinjr/react-starter-kit.git  │
│ [APENAS BRANCH MAIN - PÚBLICO]                      │
└────────────────┬────────────────────────────────────┘
                 │ git push origin main
                 │ git push private main
                 ↓
┌─────────────────────────────────────────────────────┐
│ Repositório Privado (private)                       │
│ https://github.com/wmolinjr/multi-tenant-site.git  │
│ [MAIN + DEVELOP + FEATURES - PRIVADO]              │
├─────────────────────────────────────────────────────┤
│ main                                                 │
│   ↓ git update-develop                             │
│ develop                                              │
│   ↓ git new-feature                                │
│ feature/auth, feature/api, etc.                     │
└─────────────────────────────────────────────────────┘
```

## Boas Práticas

### ✅ Sempre Fazer
- ✅ Trabalhar em branches de feature
- ✅ Manter `main` sincronizada com `upstream`
- ✅ Usar `develop` como base para features
- ✅ Fazer commits descritivos
- ✅ Sincronizar com upstream regularmente
- ✅ Push de desenvolvimento sempre para `private`

### ❌ Nunca Fazer
- ❌ Commitar diretamente em `main`
- ❌ Push de `develop` ou `feature/*` para `origin` (público)
- ❌ `push --force` em branches compartilhadas
- ❌ Ignorar atualizações do upstream
- ❌ Deletar `main` ou `develop`

## Troubleshooting

### Acidentalmente fiz push de develop para origin
```bash
# Deletar do repositório público
git push origin --delete develop
```

### Quero criar outra feature enquanto uma está em andamento
```bash
# Features são independentes
git new-feature segunda-feature
# Desenvolver normalmente
# Quando terminar:
git finish-feature

# Voltar para primeira feature
git checkout feature/primeira-feature
```

### Esqueci de fazer pull antes de começar feature
```bash
# Atualizar develop primeiro
git checkout develop
git pull private develop

# Rebasear feature sobre develop atualizado
git checkout feature/minha-feature
git rebase develop
```

### Ver onde cada branch está fazendo push
```bash
git branch -vv
```

## Links Úteis

- **Fork Público**: https://github.com/wmolinjr/react-starter-kit
- **Repositório Privado**: https://github.com/wmolinjr/multi-tenant-site
- **Laravel Original**: https://github.com/laravel/react-starter-kit
- **Documentação Git**: https://git-scm.com/doc
- **GitHub Flow**: https://guides.github.com/introduction/flow/

---

**Última atualização**: 2025-11-18
**Configurado por**: Claude Code
