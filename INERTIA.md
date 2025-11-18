# INERTIA.md

Guia de Boas Práticas para Inertia.js v2 neste projeto Laravel + React.

## Visão Geral

**Versões Atuais:**
- `@inertiajs/react`: v2.1.4
- `inertiajs/inertia-laravel`: v2.0
- React: 19.2.0
- TypeScript: 5.7.2 (strict mode)
- Laravel Wayfinder: ^0.1.9

Este projeto usa **Inertia.js v2**, que traz mudanças significativas em relação ao v1, especialmente no formato de respostas (JSON ao invés de XML) e na API de formulários.

## Diferenças Críticas: Inertia v1 vs v2

### Formato de Resposta: XML → JSON

**v1 (DEPRECADO):**
- Respostas em formato XML com props embutidas
- Parsing manual de XML no cliente
- Menos eficiente e sem type safety

**v2 (ATUAL):**
- Respostas JSON puras
- Type safety completa com TypeScript
- Performance superior
- Integração nativa com ferramentas modernas

**⚠️ IMPORTANTE:** Nunca tente parsear ou retornar XML em respostas Inertia. O v2 usa exclusivamente JSON.

### API de Formulários

**v1 (Pattern Antigo):**
```tsx
// ❌ EVITAR - useForm hook manual
import { useForm } from '@inertiajs/inertia-react';

const { data, setData, post, processing, errors } = useForm({
    email: '',
    password: '',
});

const submit = (e) => {
    e.preventDefault();
    post('/login');
};

<form onSubmit={submit}>
    <input value={data.email} onChange={e => setData('email', e.target.value)} />
    {/* Gerenciamento manual de estado */}
</form>
```

**v2 (Pattern Moderno):**
```tsx
// ✅ CORRETO - Form component com render props
import { Form } from '@inertiajs/react';
import { store } from '@/routes/login';

<Form {...store.form()} resetOnSuccess={['password']}>
    {({ processing, errors }) => (
        <>
            <Input name="email" />
            <InputError message={errors.email} />
            <Button disabled={processing}>Login</Button>
        </>
    )}
</Form>
```

**Vantagens do v2:**
- Menos código boilerplate
- Estado gerenciado automaticamente
- Validação integrada
- Type safety com Wayfinder
- Render props para estados reativos

## Hooks e Componentes Modernos

### 1. Form Component (Recomendado)

O Form component é a maneira preferida de lidar com formulários no Inertia v2.

#### Uso Básico

```tsx
import { Form } from '@inertiajs/react';
import { store } from '@/routes/profile';

<Form {...store.form()}>
    {({ processing, errors, recentlySuccessful }) => (
        <div>
            <Input name="name" required />
            <InputError message={errors.name} />
            <Button disabled={processing}>Salvar</Button>
            {recentlySuccessful && <p>Salvo com sucesso!</p>}
        </div>
    )}
</Form>
```

#### Render Props Disponíveis

```tsx
{({
    processing,           // boolean - true durante submissão
    errors,              // Record<string, string> - erros de validação
    recentlySuccessful,  // boolean - true após sucesso (2 segundos)
    clearErrors,         // () => void - limpar erros manualmente
    resetAndClearErrors, // () => void - resetar form e limpar erros
}) => (
    // Seu JSX aqui
)}
```

#### Opções do Form Component

**`resetOnSuccess`** - Resetar campos após sucesso:
```tsx
<Form
    {...store.form()}
    resetOnSuccess={['password', 'password_confirmation']}
>
    {/* Senhas são limpas após sucesso */}
</Form>

// Ou resetar todos os campos:
<Form {...store.form()} resetOnSuccess>
```

**`resetOnError`** - Resetar campos após erro:
```tsx
<Form
    {...store.form()}
    resetOnError={['password', 'password_confirmation', 'current_password']}
>
    {/* Senhas são limpas após erro de validação */}
</Form>
```

**`options`** - Opções do Inertia:
```tsx
<Form
    {...store.form()}
    options={{
        preserveScroll: true,      // Manter posição do scroll
        preserveState: true,       // Manter estado local
        onSuccess: () => {...},    // Callback de sucesso
        onError: () => {...},      // Callback de erro
    }}
>
```

**`transform`** - Transformar dados antes do envio:
```tsx
<Form
    {...update.form()}
    transform={(data) => ({
        ...data,
        token: token,
        email: email,
        // Adicionar campos extras
    })}
>
```

**`onError`** - Handler customizado de erros:
```tsx
<Form
    {...store.form()}
    onError={(errors) => {
        if (errors.password) {
            passwordInput.current?.focus();
        }
        if (errors.current_password) {
            currentPasswordInput.current?.focus();
        }
    }}
>
```

#### Exemplos Reais do Projeto

**Perfil de Usuário** (`resources/js/pages/settings/profile.tsx`):
```tsx
<Form
    {...ProfileController.update.form()}
    options={{ preserveScroll: true }}
    className="space-y-6"
>
    {({ processing, recentlySuccessful, errors }) => (
        <>
            <Input
                defaultValue={auth.user.name}
                name="name"
                required
            />
            <InputError message={errors.name} />
            <Button disabled={processing}>Salvar</Button>
            {recentlySuccessful && <p>Salvo!</p>}
        </>
    )}
</Form>
```

**Atualização de Senha** (`resources/js/pages/settings/password.tsx`):
```tsx
<Form
    {...PasswordController.update.form()}
    options={{ preserveScroll: true }}
    resetOnError={['password', 'password_confirmation', 'current_password']}
    resetOnSuccess
    onError={(errors) => {
        if (errors.password) passwordInput.current?.focus();
        if (errors.current_password) currentPasswordInput.current?.focus();
    }}
>
    {({ errors, processing, recentlySuccessful }) => (
        // Form fields aqui
    )}
</Form>
```

**Deletar Conta** (`resources/js/components/delete-user.tsx`):
```tsx
<Form
    {...ProfileController.destroy.form()}
    options={{ preserveScroll: true }}
    onError={() => passwordInput.current?.focus()}
    resetOnSuccess
>
    {({ resetAndClearErrors, processing, errors }) => (
        // Confirmação de deleção
    )}
</Form>
```

### 2. usePage Hook

Use `usePage()` para acessar shared data e evitar prop drilling.

#### Uso com TypeScript

```tsx
import { usePage } from '@inertiajs/react';
import { type SharedData } from '@/types';

export default function Profile() {
    const { auth, name, quote, sidebarOpen } = usePage<SharedData>().props;

    return (
        <div>
            <h1>Bem-vindo, {auth.user.name}</h1>
            <p>{quote.message} - {quote.author}</p>
        </div>
    );
}
```

#### Props Disponíveis via usePage()

```tsx
const page = usePage<SharedData>();

page.component  // Nome do componente atual (ex: "settings/profile")
page.props      // Props da página (tipados com SharedData)
page.url        // URL atual
page.version    // Versão dos assets (para cache busting)
```

#### Shared Data Disponível

Definido em `app/Http/Middleware/HandleInertiaRequests.php`:

```php
public function share(Request $request): array {
    return [
        ...parent::share($request),
        'name' => config('app.name'),
        'quote' => ['message' => '...', 'author' => '...'],
        'auth' => ['user' => $request->user()],
        'sidebarOpen' => ! $request->hasCookie('sidebar_state') || ...,
    ];
}
```

Interface TypeScript (`resources/js/types/index.d.ts`):

```typescript
export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    sidebarOpen: boolean;
}
```

**✅ CORRETO - Usar usePage():**
```tsx
function UserProfile() {
    const { auth } = usePage<SharedData>().props;
    return <p>{auth.user.name}</p>;
}
```

**❌ EVITAR - Prop drilling:**
```tsx
function App({ auth }) {
    return <Layout auth={auth}>
        <UserProfile auth={auth} />
    </Layout>;
}
```

### 3. Link Component

Use `Link` para navegação entre páginas Inertia.

#### Uso Básico

```tsx
import { Link } from '@inertiajs/react';
import { edit } from '@/routes/profile';

<Link href={edit()}>
    Configurações
</Link>
```

#### Prefetching para Performance

```tsx
<Link href={edit()} prefetch>
    Configurações
</Link>
// Carrega a página em background ao passar o mouse
```

#### Link como Botão

```tsx
<Link
    href={logout()}
    as="button"
    method="post"
    className="btn"
>
    Sair
</Link>
```

#### Link com Click Handler

```tsx
<Link
    href={logout()}
    onClick={(e) => {
        if (!confirm('Tem certeza?')) {
            e.preventDefault();
        }
    }}
>
    Sair
</Link>
```

#### Exemplos do Projeto

**Navegação do Menu** (`resources/js/components/user-menu-content.tsx`):
```tsx
import { Link } from '@inertiajs/react';
import { edit } from '@/routes/profile';
import { logout } from '@/routes';

<DropdownMenuItem asChild>
    <Link
        href={edit()}
        as="button"
        prefetch
        onClick={cleanup}
    >
        <Settings className="mr-2" />
        Configurações
    </Link>
</DropdownMenuItem>

<DropdownMenuItem asChild>
    <Link
        href={logout()}
        as="button"
        onClick={handleLogout}
    >
        <LogOut className="mr-2" />
        Sair
    </Link>
</DropdownMenuItem>
```

### 4. Head Component

Use `Head` para definir título e meta tags da página.

```tsx
import { Head } from '@inertiajs/react';

export default function Login() {
    return (
        <>
            <Head title="Login" />
            <h1>Entrar na sua conta</h1>
        </>
    );
}
```

O título é configurado no `app.tsx`:
```tsx
createInertiaApp({
    title: (title) => title ? `${title} - ${appName}` : appName,
    // "Login" vira "Login - Laravel"
});
```

### 5. router (Uso Mínimo)

Use `router` apenas para casos especiais. Prefira `Link` e `Form`.

#### Quando Usar router

```tsx
import { router } from '@inertiajs/react';

// Limpar cache após logout
const handleLogout = () => {
    cleanup();
    router.flushAll();  // ✅ Caso válido
};

// Navegação programática (preferir Link quando possível)
router.visit(url, {
    method: 'get',
    data: {},
    preserveScroll: true,
});
```

#### Métodos Disponíveis

```tsx
router.visit(url, options)    // Navegar para URL
router.get(url, data, options)
router.post(url, data, options)
router.put(url, data, options)
router.patch(url, data, options)
router.delete(url, options)
router.reload(options)         // Recarregar página atual
router.flushAll()             // Limpar cache de requisições
```

**⚠️ IMPORTANTE:** Para formulários, use `<Form>`. Para navegação, use `<Link>`. Use `router` apenas quando necessário.

## TypeScript e Type Safety

### Tipagem de Props de Página

Sempre defina interfaces para props de páginas:

```tsx
interface LoginProps {
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}

export default function Login({
    status,
    canResetPassword,
    canRegister,
}: LoginProps) {
    // Props totalmente tipadas
}
```

### Interface SharedData

Defina tipos para shared data em `resources/js/types/index.d.ts`:

```typescript
export interface Auth {
    user: User;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    sidebarOpen: boolean;
}
```

Use com `usePage()`:
```tsx
const { auth } = usePage<SharedData>().props;
// auth.user.email é totalmente tipado!
```

### Laravel Wayfinder - Rotas Type-Safe

Wayfinder gera helpers TypeScript a partir das rotas Laravel.

#### Configuração

Em `vite.config.ts`:
```typescript
import { wayfinder } from '@laravel/vite-plugin-wayfinder';

export default defineConfig({
    plugins: [
        laravel({ ... }),
        react({ ... }),
        tailwindcss(),
        wayfinder({
            formVariants: true,  // ✅ Ativar .form() em rotas
        }),
    ],
});
```

#### Como Funciona

**Rotas Laravel** (`routes/web.php`):
```php
Route::get('/dashboard', ...)->name('dashboard');
Route::post('/login', ...)->name('login');
Route::patch('/settings/profile', ...)->name('profile.update');
```

**Helpers TypeScript Gerados** (importados de `@/routes`):
```tsx
import { dashboard } from '@/routes';
import { store } from '@/routes/login';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';

// Uso em Links
<Link href={dashboard()}>Dashboard</Link>

// Uso em Forms com .form()
<Form {...store.form()}>
    {/* POST para /login */}
</Form>

<Form {...ProfileController.update.form()}>
    {/* PATCH para /settings/profile */}
</Form>
```

#### Vantagens do Wayfinder

✅ **Type Safety Completa:**
- Autocomplete no IDE para todas as rotas
- Erros de compilação se rota não existir
- Sem strings mágicas ou typos

✅ **Form Variants:**
- `.form()` retorna `{ method, action }` corretos
- Integração automática com `<Form>`

✅ **Parâmetros Tipados:**
```tsx
import { show } from '@/routes/posts';

// Se rota requer {id}, TypeScript exige:
<Link href={show({ id: 1 })}>Ver Post</Link>
// show() sem parâmetro = erro de compilação ✅
```

#### Exemplos Reais

**Login:**
```tsx
import { store } from '@/routes/login';

<Form {...store.form()} resetOnSuccess={['password']}>
    {/* POST /login */}
</Form>
```

**Perfil:**
```tsx
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';

<Form {...ProfileController.update.form()}>
    {/* PATCH /settings/profile */}
</Form>

<Link href={ProfileController.edit()}>
    {/* GET /settings/profile */}
</Link>
```

**Navegação:**
```tsx
import { dashboard } from '@/routes';
import { edit } from '@/routes/profile';

const navItems: NavItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Perfil', href: edit() },
];
```

### TSConfig para Path Aliases

`tsconfig.json`:
```json
{
    "compilerOptions": {
        "strict": true,
        "target": "ESNext",
        "module": "ESNext",
        "moduleResolution": "bundler",
        "baseUrl": ".",
        "paths": {
            "@/*": ["./resources/js/*"]
        }
    }
}
```

Permite imports limpos:
```tsx
import { SharedData } from '@/types';
import { Button } from '@/components/ui/button';
import { usePage } from '@inertiajs/react';
```

## SSR (Server-Side Rendering)

### Setup SSR

**Arquivo SSR:** `resources/js/ssr.tsx`

```tsx
import { createInertiaApp } from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import ReactDOMServer from 'react-dom/server';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createServer((page) =>
    createInertiaApp({
        page,
        render: ReactDOMServer.renderToString,
        title: (title) => title ? `${title} - ${appName}` : appName,
        resolve: (name) =>
            resolvePageComponent(
                `./pages/${name}.tsx`,
                import.meta.glob('./pages/**/*.tsx'),
            ),
        setup: ({ App, props }) => <App {...props} />,
    }),
);
```

### Comandos SSR

```bash
# Build para SSR (client + server)
npm run build:ssr

# Rodar em desenvolvimento com SSR
composer dev:ssr

# Inicia servidor SSR (após build)
php artisan inertia:start-ssr
```

### Quando Usar SSR

**✅ Benefícios:**
- SEO melhorado (HTML completo no primeiro carregamento)
- Faster First Contentful Paint (FCP)
- Melhor experiência em conexões lentas
- Conteúdo visível antes do JavaScript carregar

**⚠️ Considerações:**
- Build mais complexo (client + server bundle)
- Maior uso de memória no servidor
- Componentes devem ser SSR-safe (sem `window`, `document` no render inicial)

### Performance e Prefetching

#### Prefetching com Link

```tsx
<Link href={edit()} prefetch>
    Configurações
</Link>
// Carrega página em background ao hover
```

#### preserveScroll

```tsx
<Form
    {...store.form()}
    options={{ preserveScroll: true }}
>
    {/* Não rola para topo após submit */}
</Form>
```

Útil para:
- Forms de configurações
- Atualizações inline
- Formulários longos

#### resetOnSuccess e resetOnError

```tsx
<Form
    {...store.form()}
    resetOnSuccess={['password']}      // Limpar senhas após sucesso
    resetOnError={['current_password']} // Limpar senha atual após erro
>
```

Benefícios:
- Melhor UX (campos sensíveis limpos)
- Evita resubmissão acidental
- Segurança (senhas não ficam em memória)

## Padrões Recomendados

### Padrão 1: Form com Render Props

```tsx
<Form {...store.form()}>
    {({ processing, errors, recentlySuccessful }) => (
        <>
            <Input name="email" />
            <InputError message={errors.email} />
            <Button disabled={processing}>
                {processing ? 'Enviando...' : 'Enviar'}
            </Button>
            {recentlySuccessful && (
                <Alert>Salvo com sucesso!</Alert>
            )}
        </>
    )}
</Form>
```

### Padrão 2: Props Tipadas

```tsx
interface PageProps {
    mustVerifyEmail: boolean;
    status?: string;
}

export default function Page({ mustVerifyEmail, status }: PageProps) {
    // ✅ Type safety completo
}
```

### Padrão 3: Shared Data com usePage

```tsx
const { auth } = usePage<SharedData>().props;

{auth.user && (
    <UserMenu user={auth.user} />
)}
```

### Padrão 4: Wayfinder Type-Safe Routes

```tsx
import { store } from '@/routes/profile';

<Form {...store.form()}>
    {/* ✅ Rota tipada, sem strings */}
</Form>
```

### Padrão 5: Error Handling com Focus

```tsx
<Form
    {...store.form()}
    onError={(errors) => {
        if (errors.email) emailRef.current?.focus();
        if (errors.password) passwordRef.current?.focus();
    }}
>
    {({ errors }) => (
        <>
            <Input name="email" ref={emailRef} />
            <InputError message={errors.email} />
            <Input name="password" ref={passwordRef} />
            <InputError message={errors.password} />
        </>
    )}
</Form>
```

### Padrão 6: Transform Data

```tsx
<Form
    {...update.form()}
    transform={(data) => ({
        ...data,
        token: resetToken,
        email: userEmail,
    })}
>
    {/* Dados extras adicionados automaticamente */}
</Form>
```

### Padrão 7: Conditional Rendering

```tsx
const page = usePage<SharedData>();
const { auth, canRegister } = page.props;

{auth.user ? (
    <DashboardLink href={dashboard()} />
) : canRegister ? (
    <RegisterLink href={register()} />
) : (
    <LoginLink href={login()} />
)}
```

## Anti-Padrões a Evitar

### ❌ Anti-Padrão 1: Prop Drilling

**EVITAR:**
```tsx
function App({ auth, user, settings }) {
    return (
        <Layout auth={auth} user={user}>
            <Page auth={auth} user={user} settings={settings}>
                <Component user={user} />
            </Page>
        </Layout>
    );
}
```

**✅ CORRETO:**
```tsx
function Component() {
    const { auth } = usePage<SharedData>().props;
    return <p>{auth.user.name}</p>;
}
```

### ❌ Anti-Padrão 2: String Literals em Rotas

**EVITAR:**
```tsx
<Link href="/settings/profile">Perfil</Link>
// ❌ Quebra se rota mudar
// ❌ Sem type checking
// ❌ Typos não são detectados
```

**✅ CORRETO:**
```tsx
import { edit } from '@/routes/profile';

<Link href={edit()}>Perfil</Link>
// ✅ Type-safe
// ✅ Autocomplete
// ✅ Erros em compile-time
```

### ❌ Anti-Padrão 3: Props sem Tipagem

**EVITAR:**
```tsx
export default function Login(props: any) {
    return <p>{props.status}</p>;
}
```

**✅ CORRETO:**
```tsx
interface LoginProps {
    status?: string;
    canResetPassword: boolean;
}

export default function Login({ status, canResetPassword }: LoginProps) {
    return <p>{status}</p>;
}
```

### ❌ Anti-Padrão 4: Estado Manual em Forms

**EVITAR:**
```tsx
const [email, setEmail] = useState('');
const [password, setPassword] = useState('');
const [errors, setErrors] = useState<Record<string, string>>({});
const [loading, setLoading] = useState(false);

const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    // ... lógica complexa de submit
};

<form onSubmit={handleSubmit}>
    <input
        value={email}
        onChange={e => setEmail(e.target.value)}
    />
    {/* Muito boilerplate ❌ */}
</form>
```

**✅ CORRETO:**
```tsx
<Form {...store.form()}>
    {({ processing, errors }) => (
        <>
            <Input name="email" />
            <InputError message={errors.email} />
            {/* Tudo gerenciado automaticamente ✅ */}
        </>
    )}
</Form>
```

### ❌ Anti-Padrão 5: Tentar Usar XML

**EVITAR:**
```tsx
// ❌ NUNCA FAÇA ISSO - v1 legacy
const response = await fetch('/api/data');
const xml = await response.text();
const parser = new DOMParser();
// Inertia v2 NÃO usa XML!
```

**✅ CORRETO:**
```tsx
// Inertia v2 usa JSON automaticamente
// Não precisa fazer nada, apenas use Form/Link
```

### ❌ Anti-Padrão 6: router para Navegação Simples

**EVITAR:**
```tsx
<button onClick={() => router.visit('/settings')}>
    Configurações
</button>
// ❌ Mais verboso
// ❌ Sem prefetching
```

**✅ CORRETO:**
```tsx
import { edit } from '@/routes/settings';

<Link href={edit()} prefetch>
    Configurações
</Link>
// ✅ Mais limpo
// ✅ Prefetching disponível
// ✅ Type-safe
```

### ❌ Anti-Padrão 7: Gerenciamento Manual de Erros

**EVITAR:**
```tsx
const [errors, setErrors] = useState({});

{errors.email && (
    <div className="text-red-500">{errors.email}</div>
)}
// ❌ Estado manual
// ❌ Sem componente reutilizável
```

**✅ CORRETO:**
```tsx
<Form {...store.form()}>
    {({ errors }) => (
        <>
            <Input name="email" />
            <InputError message={errors.email} />
            {/* ✅ Componente consistente */}
        </>
    )}
</Form>
```

## Referência Rápida

### Tabela: v1 vs v2

| Recurso | v1 (Deprecado) | v2 (Atual) | Uso Recomendado |
|---------|---------------|-----------|-----------------|
| **Resposta** | XML | JSON | Automático (sem config) |
| **Forms** | `useForm()` manual | `<Form>` com render props | Form component sempre |
| **Props** | Sem tipos | TypeScript interfaces | Sempre tipar props |
| **Rotas** | Strings literais | Wayfinder type-safe | Importar de @/routes |
| **Shared Data** | Prop drilling | `usePage<SharedData>()` | usePage em qualquer lugar |
| **Navegação** | `router.post()` direto | `<Link>` ou `<Form>` | Link/Form > router |
| **SSR** | Limitado | Nativo com `createServer` | ssr.tsx configurado |
| **Type Safety** | Parcial | Strict mode completo | strict: true no tsconfig |

### Comandos Úteis

```bash
# Desenvolvimento
npm run dev              # Vite dev server
composer dev             # Laravel + Queue + Logs + Vite
composer dev:ssr         # Laravel + Queue + Logs + SSR

# Build
npm run build            # Build para produção
npm run build:ssr        # Build com SSR

# Type Checking
npm run types            # Verificar TypeScript (sem emitir)

# Code Quality
npm run lint             # ESLint
npm run format           # Prettier
npm run format:check     # Verificar formatação
```

### Arquivos Importantes

**Frontend:**
- `resources/js/app.tsx` - Entry point do cliente
- `resources/js/ssr.tsx` - Entry point do servidor (SSR)
- `resources/js/types/index.d.ts` - Definições TypeScript
- `resources/js/pages/**/*.tsx` - Páginas Inertia
- `resources/js/layouts/**/*.tsx` - Layouts
- `resources/js/components/**/*.tsx` - Componentes

**Backend:**
- `app/Http/Middleware/HandleInertiaRequests.php` - Shared data
- `app/Http/Controllers/**/*.php` - Controllers
- `routes/web.php` - Rotas principais
- `routes/settings.php` - Rotas de configurações

**Configuração:**
- `vite.config.ts` - Build config (Wayfinder aqui)
- `tsconfig.json` - TypeScript config
- `components.json` - shadcn/ui config (aliases)

## Checklist de Boas Práticas

Ao desenvolver com Inertia v2, sempre:

- [ ] Usar `<Form>` com render props ao invés de `useForm()` manual
- [ ] Tipar props de páginas com interfaces TypeScript
- [ ] Usar `usePage<SharedData>()` ao invés de prop drilling
- [ ] Importar rotas de `@/routes` com Wayfinder (nunca strings)
- [ ] Usar `<Link>` para navegação (não `router.visit()` direto)
- [ ] Adicionar `prefetch` em Links importantes
- [ ] Usar `preserveScroll` em forms de configurações
- [ ] Limpar campos sensíveis com `resetOnSuccess`/`resetOnError`
- [ ] Focar inputs com erro via `onError` callback
- [ ] Definir `<Head title>` em todas as páginas
- [ ] Validar que respostas são JSON (nunca XML)
- [ ] Testar SSR se habilitado (`npm run build:ssr`)

## Recursos Adicionais

- [Inertia.js Documentação Oficial](https://inertiajs.com)
- [Laravel Wayfinder](https://github.com/laravel/wayfinder)
- [React 19 Docs](https://react.dev)
- [TypeScript Handbook](https://www.typescriptlang.org/docs)

---

**Última atualização:** Baseado em Inertia v2.1.4 e React 19.2.0
