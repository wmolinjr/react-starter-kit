# Implementation Plan: Central Admin Audit Log Feature

## Executive Summary

This plan details the implementation of the Central Admin Audit Log feature, following the existing architecture patterns. The goal is to create **shared components** that work seamlessly in both Central and Tenant contexts, adhering to the DRY principle and maintaining a single source of truth.

---

## 1. Architecture Overview

### Current State Analysis

**Backend (Already Implemented)**:
- `App\Services\Shared\AuditLogService` - Abstract base with all filtering, pagination, export logic
- `App\Services\Central\AuditLogService` - Central-specific service (uses `Central\Activity` model)
- `App\Services\Tenant\AuditLogService` - Tenant-specific service (with plan-based retention)
- `App\Http\Resources\Shared\ActivityResource` - Works in both contexts
- `App\Models\Central\Activity` - Uses `CentralConnection` trait (always writes to central DB)
- `App\Models\Shared\Activity` - Standard model for tenant context

**Frontend (Tenant Only - Needs Refactoring)**:
- `/resources/js/pages/tenant/admin/audit/index.tsx` - Monolithic 637-line component
- No shared components exist

### Target Architecture

```
Backend:
app/Enums/CentralPermission.php
  + AUDIT_VIEW = 'audit:view'           # NEW
  + AUDIT_EXPORT = 'audit:export'       # NEW

app/Http/Controllers/Central/Admin/AuditLogController.php  # NEW

Frontend (Shared Components):
resources/js/components/shared/audit/
  - audit-filters.tsx                   # Filter card component
  - audit-table.tsx                     # Table with pagination
  - audit-detail-dialog.tsx             # Activity details modal
  - index.ts                            # Barrel export
  - types.ts                            # Shared types/interfaces

Frontend (Pages):
resources/js/pages/central/admin/audit/index.tsx    # NEW - Uses shared components
resources/js/pages/tenant/admin/audit/index.tsx     # REFACTORED - Uses shared components
```

---

## 2. Implementation Sequence

### Phase 1: Backend (Tasks 1-4)

| # | Task | File | Description |
|---|------|------|-------------|
| 1 | Add CentralPermission enum entries | `app/Enums/CentralPermission.php` | Add `AUDIT_VIEW`, `AUDIT_EXPORT` |
| 2 | Create UserSummaryResource | `app/Http/Resources/Central/UserSummaryResource.php` | Minimal admin user info |
| 3 | Create AuditLogController | `app/Http/Controllers/Central/Admin/AuditLogController.php` | Index, show, export |
| 4 | Add routes | `routes/central.php` | `/admin/audit` routes |

### Phase 2: Frontend Shared Components (Tasks 5-9)

| # | Task | File | Description |
|---|------|------|-------------|
| 5 | Create types | `resources/js/components/shared/audit/types.ts` | Interfaces and config |
| 6 | Create AuditFilters | `resources/js/components/shared/audit/audit-filters.tsx` | Filter card component |
| 7 | Create AuditTable | `resources/js/components/shared/audit/audit-table.tsx` | Table with pagination |
| 8 | Create AuditDetailDialog | `resources/js/components/shared/audit/audit-detail-dialog.tsx` | Details modal |
| 9 | Create barrel export | `resources/js/components/shared/audit/index.ts` | Export all components |

### Phase 3: Frontend Pages (Tasks 10-11)

| # | Task | File | Description |
|---|------|------|-------------|
| 10 | Create Central page | `resources/js/pages/central/admin/audit/index.tsx` | Uses shared components |
| 11 | Refactor Tenant page | `resources/js/pages/tenant/admin/audit/index.tsx` | 637 lines → ~100 lines |

### Phase 4: Navigation & Translations (Tasks 12-13)

| # | Task | File | Description |
|---|------|------|-------------|
| 12 | Update sidebar | `resources/js/components/central/navigation/nav-items.tsx` | Add audit nav item |
| 13 | Add translations | `lang/en.json`, `lang/pt_BR.json` | `admin.audit.*` keys |

### Phase 5: Generate & Test (Tasks 14-17)

| # | Task | Command | Description |
|---|------|---------|-------------|
| 14 | Sync permissions | `sail artisan permissions:sync` | Register new permissions |
| 15 | Generate routes | `sail artisan wayfinder:generate --with-form` | TypeScript route helpers |
| 16 | Generate types | `sail artisan types:generate` | TypeScript interfaces |
| 17 | Run tests | `sail artisan test && sail npm run types` | Verify everything works |

---

## 3. Detailed Implementation

### 3.1 CentralPermission Enum Updates

**File**: `app/Enums/CentralPermission.php`

```php
// Add after Federation section (around line 66):

// Audit Log (2 permissions)
case AUDIT_VIEW = 'audit:view';
case AUDIT_EXPORT = 'audit:export';
```

**Update `description()` method:**
```php
// Audit Log
self::AUDIT_VIEW => ['en' => 'View audit logs', 'pt_BR' => 'Visualizar logs de auditoria'],
self::AUDIT_EXPORT => ['en' => 'Export audit logs', 'pt_BR' => 'Exportar logs de auditoria'],
```

**Update `name()` method `categoryNames` array:**
```php
'audit' => ['en' => 'Audit', 'pt_BR' => 'Auditoria'],
```

**Update `icon()` method:**
```php
'audit' => 'ClipboardList',
```

**Update `color()` method:**
```php
'audit' => 'amber',
```

**Update `categoryDescription()` method:**
```php
'audit' => ['en' => 'Audit Log', 'pt_BR' => 'Log de Auditoria'],
```

### 3.2 Central AuditLogController

**File**: `app/Http/Controllers/Central/Admin/AuditLogController.php`

```php
<?php

namespace App\Http\Controllers\Central\Admin;

use App\Enums\CentralPermission;
use App\Http\Controllers\Controller;
use App\Http\Resources\Central\UserSummaryResource;
use App\Http\Resources\Shared\ActivityResource;
use App\Models\Central\Activity;
use App\Models\Central\User;
use App\Services\Central\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller implements HasMiddleware
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:'.CentralPermission::AUDIT_VIEW->value, only: ['index', 'show']),
            new Middleware('permission:'.CentralPermission::AUDIT_EXPORT->value, only: ['export']),
        ];
    }

    public function index(Request $request): Response
    {
        $filters = [
            'user_id' => $request->input('user_id'),
            'event' => $request->input('event'),
            'subject_type' => $request->input('subject_type'),
            'log_name' => $request->input('log_name'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'search' => $request->input('search'),
        ];

        $activities = $this->auditLogService->getActivities($filters);
        $filterOptions = $this->auditLogService->getFilterOptions();

        $adminUsers = User::select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return Inertia::render('central/admin/audit/index', [
            'activities' => ActivityResource::collection($activities),
            'adminUsers' => UserSummaryResource::collection($adminUsers),
            'eventTypes' => $filterOptions['eventTypes'],
            'subjectTypes' => $filterOptions['subjectTypes'],
            'logNames' => $filterOptions['logNames'],
            'filters' => $filters,
        ]);
    }

    public function show(Activity $activity)
    {
        $activity->load(['causer:id,name,email', 'subject']);

        return response()->json([
            'activity' => new ActivityResource($activity),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = [
            'user_id' => $request->input('user_id'),
            'event' => $request->input('event'),
            'subject_type' => $request->input('subject_type'),
            'log_name' => $request->input('log_name'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'search' => $request->input('search'),
        ];

        return $this->auditLogService->exportToCsv($filters);
    }
}
```

### 3.3 Routes

**File**: `routes/central.php` (add inside `admin.` group)

```php
use App\Http\Controllers\Central\Admin\AuditLogController;

// Audit Log Management
Route::prefix('audit')->name('audit.')->group(function () {
    Route::get('/', [AuditLogController::class, 'index'])->name('index');
    Route::get('/export', [AuditLogController::class, 'export'])->name('export');
    Route::get('/{activity}', [AuditLogController::class, 'show'])->name('show');
});
```

### 3.4 Shared Component Types

**File**: `resources/js/components/shared/audit/types.ts`

```typescript
import type { ActivityResource, UserSummaryResource, InertiaPaginatedResponse } from '@/types';

export interface SubjectType {
    value: string;
    label: string;
}

export interface AuditFilters {
    user_id: string | null;
    event: string | null;
    subject_type: string | null;
    log_name: string | null;
    date_from: string | null;
    date_to: string | null;
    search: string | null;
}

export interface AuditPageConfig {
    translationPrefix: string;  // 'tenant.audit' or 'admin.audit'
    baseUrl: string;            // '/admin/audit'
    exportUrl: string;          // '/admin/audit/export'
}
```

### 3.5 Translations to Add

**`lang/en.json`:**
```json
"admin.audit.activity_details": "Activity Details",
"admin.audit.changes": "Changes",
"admin.audit.column_action": "Action",
"admin.audit.column_date": "Date",
"admin.audit.column_description": "Description",
"admin.audit.column_subject": "Subject",
"admin.audit.column_user": "Admin",
"admin.audit.description": "Track all administrative actions in the central panel.",
"admin.audit.event_created": "Created",
"admin.audit.event_deleted": "Deleted",
"admin.audit.event_login": "Login",
"admin.audit.event_logout": "Logout",
"admin.audit.event_updated": "Updated",
"admin.audit.export_csv": "Export CSV",
"admin.audit.filter_date_from": "From Date",
"admin.audit.filter_date_to": "To Date",
"admin.audit.filter_event": "Event Type",
"admin.audit.filter_log_name": "Log Name",
"admin.audit.filter_subject": "Subject Type",
"admin.audit.filter_user": "Admin",
"admin.audit.new_value": "New Value",
"admin.audit.no_activities": "No audit entries found.",
"admin.audit.no_changes": "No changes recorded.",
"admin.audit.old_value": "Old Value",
"admin.audit.page_title": "Audit Log",
"admin.audit.search_placeholder": "Search by description or admin...",
"admin.audit.system": "System",
"admin.audit.title": "Audit Log"
```

**`lang/pt_BR.json`:**
```json
"admin.audit.activity_details": "Detalhes da Atividade",
"admin.audit.changes": "Alterações",
"admin.audit.column_action": "Ação",
"admin.audit.column_date": "Data",
"admin.audit.column_description": "Descrição",
"admin.audit.column_subject": "Objeto",
"admin.audit.column_user": "Admin",
"admin.audit.description": "Acompanhe todas as ações administrativas no painel central.",
"admin.audit.event_created": "Criado",
"admin.audit.event_deleted": "Deletado",
"admin.audit.event_login": "Login",
"admin.audit.event_logout": "Logout",
"admin.audit.event_updated": "Atualizado",
"admin.audit.export_csv": "Exportar CSV",
"admin.audit.filter_date_from": "Data Inicial",
"admin.audit.filter_date_to": "Data Final",
"admin.audit.filter_event": "Tipo de Evento",
"admin.audit.filter_log_name": "Nome do Log",
"admin.audit.filter_subject": "Tipo de Objeto",
"admin.audit.filter_user": "Admin",
"admin.audit.new_value": "Novo Valor",
"admin.audit.no_activities": "Nenhum registro de auditoria encontrado.",
"admin.audit.no_changes": "Nenhuma alteração registrada.",
"admin.audit.old_value": "Valor Antigo",
"admin.audit.page_title": "Log de Auditoria",
"admin.audit.search_placeholder": "Buscar por descrição ou admin...",
"admin.audit.system": "Sistema",
"admin.audit.title": "Log de Auditoria"
```

---

## 4. Key Benefits

1. **DRY**: Shared components reduce code duplication from ~1200 lines to ~300 lines
2. **Consistency**: Same UI/UX in both Central and Tenant contexts
3. **Maintainability**: Single source of truth for audit UI logic
4. **Type Safety**: Full TypeScript support with auto-generated types
5. **i18n Ready**: Configurable translation prefixes for different contexts

---

## 5. Testing Checklist

- [ ] Permissions sync without errors
- [ ] Central audit page renders correctly
- [ ] Filters work (user, event, subject, log name, dates, search)
- [ ] Pagination works
- [ ] Export CSV downloads correctly
- [ ] Activity details modal shows all information
- [ ] Tenant audit page still works after refactoring
- [ ] TypeScript compiles without errors
- [ ] ESLint passes
