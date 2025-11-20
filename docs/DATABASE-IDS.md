# Database IDs Architecture

## Decision: Auto-Increment IDs (bigint)

This project uses **auto-increment integer IDs** (PostgreSQL BIGSERIAL/bigint) for all primary keys, including multi-tenant models.

## Rationale

### 1. Security Through Proper Authorization

Multi-tenancy security relies on global scopes (`BelongsToTenant` trait), route model binding validation, and middleware—NOT on ID obscurity. UUIDs provide zero additional security.

### 2. Performance Benefits

- **50% smaller storage**: 8 bytes vs 16-36 bytes per ID
- **Faster queries**: Integer comparison vs string/binary
- **Better index performance**: Sequential inserts, less B-tree fragmentation
- **Optimal PostgreSQL performance**

### 3. Developer Experience

- **Clean URLs**: `/projects/123` vs `/projects/550e8400-e29b-41d4-a716...`
- **Easy debugging**: "Project 123 updated" vs "Project 550e8400... updated"
- **Consistent TypeScript types**: `id: number` everywhere

### 4. Future-Proof

Auto-increment IDs work perfectly for database-per-tenant migration (if needed). Each tenant's isolated database will have independent ID sequences without conflicts.

## Security Model

```php
// Example: Route model binding with tenant validation
Route::bind('project', function (string $value) {
    if (tenancy()->initialized) {
        return Project::where('id', $value)
            ->where('tenant_id', tenant('id'))  // ✅ Validates tenant ownership
            ->firstOrFail();
    }
    return Project::findOrFail($value);
});
```

## Attack Scenario (Mitigated)

```
Attacker in Tenant 1 tries: GET /projects/123
→ Global Scope filters: WHERE tenant_id = 1 AND id = 123
→ If project 123 belongs to Tenant 2: 404 Not Found
→ Attack fails regardless of ID type
```

## Configuration

- `config/tenancy.php:10` → `'id_generator' => null` (auto-increment)
- All migrations use `$table->id()` (bigint auto-increment)
- TypeScript interfaces use `id: number` consistently

**Note:** Previous versions had a type inconsistency where `Tenant.id` and `TenantInfo.id` were typed as `string` in TypeScript but used `number` in the backend. This has been corrected.
