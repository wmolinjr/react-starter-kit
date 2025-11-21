# 10 - API Tokens (Laravel Sanctum)

## Sanctum Tenant-Scoped

### 1. Migration: Add tenant_id

```php
Schema::table('personal_access_tokens', function (Blueprint $table) {
    $table->foreignId('tenant_id')->nullable()->after('tokenable_id')->constrained();
    $table->index('tenant_id');
});
```

### 2. Controller

```php
class ApiTokenController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'abilities' => 'array',
        ]);

        $token = $request->user()->createToken(
            $request->name,
            $request->abilities ?? ['*']
        );

        // Associar ao tenant
        DB::table('personal_access_tokens')
            ->where('id', $token->accessToken->id)
            ->update(['tenant_id' => current_tenant_id()]);

        return back()->with('token', $token->plainTextToken);
    }
}
```

### 3. API Routes com Tenant Context

```php
// routes/tenant.php

Route::middleware(['auth:sanctum', InitializeTenancyByDomain::class])
    ->prefix('api')
    ->group(function () {
        Route::get('/projects', [Api\ProjectController::class, 'index']);
    });
```

---

**Versão:** 1.0
