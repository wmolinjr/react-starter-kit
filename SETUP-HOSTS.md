# Configuração de Hosts para Multi-Tenancy Local

## Por que configurar /etc/hosts?

Para testar o multi-tenancy localmente, precisamos que subdomínios como `tenant1.localhost` e `tenant2.localhost` apontem para `127.0.0.1`.

## Instruções

### Linux / macOS

1. **Abra o arquivo hosts com sudo:**
   ```bash
   sudo nano /etc/hosts
   ```

2. **Adicione as seguintes linhas:**
   ```
   # Multi-Tenant SaaS - Subdomains para testes locais
   127.0.0.1  localhost
   127.0.0.1  tenant1.localhost
   127.0.0.1  tenant2.localhost
   127.0.0.1  tenant3.localhost
   ```

3. **Salve e feche:**
   - No nano: `Ctrl+O`, `Enter`, `Ctrl+X`
   - No vi/vim: `Esc`, `:wq`, `Enter`

4. **Verifique:**
   ```bash
   ping tenant1.localhost
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
   # Multi-Tenant SaaS - Subdomains para testes locais
   127.0.0.1  localhost
   127.0.0.1  tenant1.localhost
   127.0.0.1  tenant2.localhost
   127.0.0.1  tenant3.localhost
   ```

4. **Salve o arquivo**

5. **Flush DNS:**
   ```cmd
   ipconfig /flushdns
   ```

## Testando

Após configurar, você poderá acessar:
- **Central App:** `http://localhost`
- **Tenant 1:** `http://tenant1.localhost`
- **Tenant 2:** `http://tenant2.localhost`
- **Tenant 3:** `http://tenant3.localhost`

## Alternativa: Usar .test em vez de .localhost

Se você usar Laravel Herd ou Valet (macOS), pode usar `.test`:
```
127.0.0.1  myapp.test
127.0.0.1  tenant1.myapp.test
127.0.0.1  tenant2.myapp.test
```

Acesso:
- `http://myapp.test`
- `http://tenant1.myapp.test`

## Com Laravel Sail

Se estiver usando Laravel Sail, os subdomains `*.localhost` já funcionam automaticamente na porta 80.

Acesse:
- Central: `http://localhost`
- Tenant: `http://tenant1.localhost`

**Nota:** Certifique-se de que Sail está rodando na porta 80:
```bash
sail up -d
# Verifique: http://localhost deve mostrar a aplicação
```

Se Sail estiver em outra porta (ex: 8000), use:
- `http://localhost:8000`
- `http://tenant1.localhost:8000`

## Próximos Passos

Após configurar os hosts, você estará pronto para:
1. Criar migrations de tenants e domains (Etapa 2)
2. Criar seeders com tenants de teste
3. Testar o multi-tenancy acessando diferentes subdomínios
