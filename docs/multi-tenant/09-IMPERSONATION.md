# 09 - Impersonation (Super Admin)

## Sistema de Impersonation

### Controller

```php
class ImpersonationController extends Controller
{
    public function start(Tenant $tenant, User $user = null)
    {
        // Apenas super admin
        if (!auth()->user()->is_super_admin) {
            abort(403);
        }

        session()->put('impersonating_tenant', $tenant->id);

        if ($user) {
            session()->put('impersonating_user', $user->id);
            auth()->login($user);
        }

        return redirect()->to($tenant->url() . '/dashboard');
    }

    public function stop()
    {
        session()->forget(['impersonating_tenant', 'impersonating_user']);
        return redirect()->route('admin.dashboard');
    }
}
```

### Middleware

```php
class PreventActionsWhileImpersonating
{
    public function handle(Request $request, Closure $next)
    {
        if (session()->has('impersonating_user')) {
            // Prevenir ações sensíveis
            if ($request->routeIs('billing.*')) {
                abort(403, 'Ação não permitida durante impersonation.');
            }
        }

        return $next($request);
    }
}
```

---

**Versão:** 1.0
