# Database IDs Architecture

## Decision: UUID Everywhere

This project uses **UUID** for all model primary keys for consistency and best practices.

## Rationale

### 1. Consistency
- One pattern everywhere - no decisions needed
- All foreign keys are UUID
- All TypeScript types use `string` for IDs
- MediaLibrary `uuidMorphs` works with all models automatically

### 2. Security Benefits
- No enumeration attacks on any endpoint
- No ID guessing (sequential IDs leak information)
- Safe to expose in URLs, logs, and APIs

### 3. Distributed-Ready
- UUIDs are globally unique
- Safe for multi-database tenancy
- No conflicts when merging/importing data
- Works across microservices

### 4. Laravel 11+ Uses UUID v7
Laravel's `HasUuids` trait generates **ordered UUIDs (v7)** which:
- Are time-sortable (better index performance than random UUIDs)
- Have minimal B-tree fragmentation
- Work well with PostgreSQL

## Implementation

### All Models Use HasUuids

```php
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class MyModel extends Model
{
    use HasUuids;
}
```

### All Migrations Use UUID Primary Keys

```php
Schema::create('my_table', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('related_id')->constrained();
    // ...
});
```

### Polymorphic Relations Use uuidMorphs

```php
$table->uuidMorphs('model'); // For MediaLibrary, Activity Log, etc.
```

## Model Overview

### Central Database (`App\Models\Central\`)

| Model | ID Type | Notes |
|-------|---------|-------|
| `User` | UUID | Central administrators (Super Admin, Central Admin) |
| `Tenant` | UUID | Multi-database tenancy |
| `Plan` | UUID | Subscription plans |
| `Domain` | UUID | Tenant domains |
| `Addon` | UUID | Add-on catalog |
| `AddonBundle` | UUID | Bundle of addons |
| `AddonSubscription` | UUID | Tenant add-on subscriptions |
| `AddonPurchase` | UUID | Purchase history |

### Tenant Database (`App\Models\Tenant\`)

| Model | ID Type | Notes |
|-------|---------|-------|
| `User` | UUID | Tenant users (owner, admin, member) |
| `Project` | UUID | Tenant projects |
| `Activity` | UUID | Spatie Activity Log |
| `Media` | UUID | Spatie MediaLibrary |
| `UserInvitation` | UUID | Team invitations (isolated per tenant) |

### Shared (`App\Models\Shared\`)

| Model | ID Type | Notes |
|-------|---------|-------|
| `Role` | UUID | Spatie Permission (isolated by database) |
| `Permission` | UUID | Spatie Permission (isolated by database) |

## TypeScript Types

All IDs are typed as `string`:

```typescript
interface Project {
  id: string; // UUID
  user_id: string; // UUID
  name: string;
}

interface User {
  id: string; // UUID
  email: string;
  name: string;
}
```

## Migration Notes

When creating new models:

1. Add `HasUuids` trait to the model
2. Use `$table->uuid('id')->primary()` in migration
3. Use `$table->foreignUuid()` for foreign keys
4. Use `$table->uuidMorphs()` for polymorphic relations

## Performance Considerations

With PostgreSQL and UUID v7:
- Ordered UUIDs minimize index fragmentation
- 16 bytes vs 8 bytes for bigint (acceptable trade-off)
- Slightly larger indexes, but excellent query performance
- No practical performance impact for most applications

## Timezone: UTC Everywhere

All timestamps are stored in **UTC** regardless of server configuration. This is enforced through multiple layers:

### 1. PostgreSQL Connection (`config/database.php`)

All PostgreSQL connections have `'timezone' => 'UTC'`:

```php
'central' => [
    'driver' => 'pgsql',
    // ...
    'timezone' => 'UTC',
],
```

### 2. PHP Default (`AppServiceProvider`)

PHP timezone is explicitly set to UTC:

```php
date_default_timezone_set('UTC');
```

### 3. Connection Event Listener (`SetDatabaseTimezone`)

Every database connection runs `SET TIMEZONE TO 'UTC'` to catch dynamically created tenant connections:

```php
Event::listen(ConnectionEstablished::class, SetDatabaseTimezone::class);
```

### Why UTC?

- **Consistency**: All timestamps use the same reference point
- **Multi-region**: Works correctly across different server locations
- **Multi-tenant**: Tenants can have different display timezones without data inconsistency
- **APIs**: ISO 8601 dates with UTC are the standard for APIs
- **Debugging**: Easier to correlate events across systems

### Display vs Storage

- **Storage**: Always UTC in database
- **Display**: Convert to user's timezone in frontend (using `locale` and `timezone` tenant settings)

```typescript
// Frontend: Convert UTC to user's timezone
const userDate = new Date(utcTimestamp).toLocaleString('pt-BR', {
  timeZone: 'America/Sao_Paulo'
});
```

### Testing

Run timezone tests to verify enforcement:

```bash
sail artisan test --filter=TimezoneEnforcementTest
```
