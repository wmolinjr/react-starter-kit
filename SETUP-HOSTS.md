# Configuração de Hosts para Multi-Tenancy Local

## Por que configurar /etc/hosts?

Para testar o multi-tenancy localmente, precisamos que domínios como `app.test`, `tenant1.test` e `tenant2.test` apontem para `127.0.0.1`.

## Instruções

### Linux / macOS

1. **Abra o arquivo hosts com sudo:**
   ```bash
   sudo nano /etc/hosts
   ```

2. **Adicione as seguintes linhas:**
   ```
   # Multi-Tenant SaaS - Domains para testes locais
   127.0.0.1  app.test
   127.0.0.1  tenant1.test
   127.0.0.1  tenant2.test
   127.0.0.1  tenant3.test
   ```

3. **Salve e feche:**
   - No nano: `Ctrl+O`, `Enter`, `Ctrl+X`
   - No vi/vim: `Esc`, `:wq`, `Enter`

4. **Verifique:**
   ```bash
   ping tenant1.test
   # Deve responder de 127.0.0.1
   ```

### Windows

1. **Abra o Bloco de Notas como Administrador**

2. **Abra o arquivo:**
   ```
   C:\Windows\System32\drivers\etc\hosts
   ```

3. **Adicione as seguintes linhas:**
   ```
   # Multi-Tenant SaaS - Domains para testes locais
   127.0.0.1  app.test
   127.0.0.1  tenant1.test
   127.0.0.1  tenant2.test
   127.0.0.1  tenant3.test
   ```

4. **Salve o arquivo**

5. **Flush DNS:**
   ```cmd
   ipconfig /flushdns
   ```

## Testando

Após configurar, você poderá acessar:
- **Central App:** `http://app.test`
- **Tenant 1:** `http://tenant1.test`
- **Tenant 2:** `http://tenant2.test`
- **Tenant 3:** `http://tenant3.test`

## Com Laravel Sail

Se estiver usando Laravel Sail, os domains `.test` funcionam automaticamente na porta 80 após configurar o hosts file.

Acesse:
- Central: `http://app.test`
- Tenant: `http://tenant1.test`

**Nota:** Certifique-se de que Sail está rodando na porta 80:
```bash
sail up -d
# Verifique: http://app.test deve mostrar a aplicação
```

Se Sail estiver em outra porta (ex: 8000), use:
- `http://app.test:8000`
- `http://tenant1.test:8000`

## Próximos Passos

Após configurar os hosts, você estará pronto para:
1. Criar migrations de tenants e domains (Etapa 2)
2. Criar seeders com tenants de teste
3. Testar o multi-tenancy acessando diferentes domínios
