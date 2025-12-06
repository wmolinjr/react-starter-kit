# Documentation Cleanup Plan

## 1. Executive Summary

This plan provides a comprehensive analysis of the `/docs/` folder and recommends actions to organize, update, and improve documentation quality. The codebase has undergone significant architectural changes including:

- **Model Namespace Restructuring**: Models now organized into `Central/`, `Tenant/`, and `Universal/` namespaces
- **Services Namespace Restructuring**: Services now organized into `Central/`, `Tenant/`, and `Universal/` namespaces
- **Controller Refactoring**: Business logic extracted to dedicated service classes with Form Requests
- **Option C User Architecture**: Tenant-only users implemented (Central\User for admins, Tenant\User for tenant users)

**Total Documentation Files**: 19 (15 in docs/, 4 in docs/archive/)

---

## 2. Current State Analysis

### 2.1 Documentation Inventory

| File | Status | Action |
|------|--------|--------|
| **Main Docs (docs/)** | | |
| ADDONS.md | Current | KEEP |
| CONTROLLER-REFACTORING-PLAN.md | Completed | ARCHIVE |
| DATABASE-IDS.md | Minor Updates Needed | UPDATE |
| I18N.md | Current | KEEP |
| IMPLEMENTATION-LOG.md | Active Log | KEEP |
| MCP-WORKFLOW.md | Current | KEEP |
| MEDIALIBRARY.md | Current | KEEP |
| PERMISSIONS.md | Minor Updates Needed | UPDATE |
| POST-RESTRUCTURE-CLEANUP-REPORT.md | Completed Report | ARCHIVE |
| SERVICES-NAMESPACE-RESTRUCTURE-PLAN.md | Completed | ARCHIVE |
| SESSION-SECURITY.md | Current | KEEP |
| STANCL-FEATURES.md | Current | KEEP |
| SYSTEM-ARCHITECTURE.md | Major Updates Needed | UPDATE |
| TENANCY-V4-IMPLEMENTATION-LOG.md | Completed Log | ARCHIVE |
| TENANCY-V4-IMPROVEMENT-PLAN.md | Active Plan | KEEP |
| **Archive (docs/archive/)** | | |
| MODELS-NAMESPACE-RESTRUCTURE-PLAN.md | Completed | KEEP IN ARCHIVE |
| MULTI-DATABASE-MIGRATION-PLAN.md | Completed | KEEP IN ARCHIVE |
| TENANT-USERS-ANALYSIS.md | Completed | KEEP IN ARCHIVE |
| TENANT-USERS-OPTION-C-IMPLEMENTATION.md | Completed | KEEP IN ARCHIVE |

### 2.2 Current Architecture State (Verified)

**Models Structure** (IMPLEMENTED):
```
app/Models/
  Central/   (9 files): Addon, AddonBundle, AddonPurchase, AddonSubscription,
                        Domain, Plan, Tenant, TenantInvitation, User
  Tenant/    (5 files): Activity, Media, Project, TenantTranslationOverride, User
  Universal/ (2 files): Permission, Role
```

**Services Structure** (IMPLEMENTED):
```
app/Services/
  Central/  (11 files): AddonService, CheckoutService, ImpersonationService,
                        MeteredBillingService, PlanFeatureResolver,
                        PlanPermissionResolver, PlanService, PlanSyncService,
                        RoleService, StripeSyncService
  Tenant/   (5 files): AuditLogService, BillingService, RoleService,
                       TeamService, TenantSettingsService
```

---

## 3. Files to ARCHIVE (Move to docs/archive/)

| File | Reason |
|------|--------|
| `CONTROLLER-REFACTORING-PLAN.md` | Plan completed - controllers refactored with Services and Form Requests |
| `POST-RESTRUCTURE-CLEANUP-REPORT.md` | Historical report of namespace restructuring completion |
| `SERVICES-NAMESPACE-RESTRUCTURE-PLAN.md` | Plan completed - Services now in Central/Tenant structure |
| `TENANCY-V4-IMPLEMENTATION-LOG.md` | Historical implementation log, no longer active |

---

## 4. Files to UPDATE

### 4.1 HIGH PRIORITY

#### `SYSTEM-ARCHITECTURE.md`
**Issues**:
- Contains outdated model references without namespaces
- Services section doesn't reflect Central/Tenant organization
- May reference old patterns

**Required Updates**:
1. Update all model references to use new namespaces (Central/, Tenant/, Universal/)
2. Update Services section to show Central/ and Tenant/ organization
3. Add section on Controller patterns (thin controllers with Services)
4. Update code examples to use current patterns

#### `CLAUDE.md` (Main project file)
**Issues**:
- Services documentation needs updating
- Missing Controller refactoring patterns

**Required Updates**:
1. Update Services section with Central/Tenant structure
2. Add Controller patterns documentation
3. Verify all references are current

### 4.2 MEDIUM PRIORITY

#### `DATABASE-IDS.md`
**Issues**:
- May reference old model names (TenantAddon, TenantAddonPurchase)

**Required Updates**:
1. Update model references to AddonSubscription, AddonPurchase
2. Use full namespaces in references

#### `PERMISSIONS.md`
**Issues**:
- May reference `App\Models\Permission` without full namespace

**Required Updates**:
1. Update to `App\Models\Shared\Permission`
2. Update to `App\Models\Shared\Role`

---

## 5. Files to KEEP AS-IS

| File | Reason |
|------|--------|
| `ADDONS.md` | Comprehensive and current addon system documentation |
| `I18N.md` | Current internationalization guide |
| `IMPLEMENTATION-LOG.md` | Active log for ongoing work |
| `MCP-WORKFLOW.md` | Current MCP tools workflow guide |
| `MEDIALIBRARY.md` | Current media library integration docs |
| `SESSION-SECURITY.md` | Current security documentation |
| `STANCL-FEATURES.md` | Current Stancl Tenancy v4 features |
| `TENANCY-V4-IMPROVEMENT-PLAN.md` | Active improvement roadmap |

---

## 6. NEW Files to CREATE

### 6.1 HIGH PRIORITY

#### `docs/SERVICES.md`
Document the Services layer architecture:
1. Service Architecture Overview (Central/Tenant separation)
2. Service List with Descriptions
3. Service Patterns and Conventions
4. Exception handling patterns
5. Usage Examples

#### `docs/MODELS.md`
Document the Models layer architecture:
1. Model Architecture Overview (Central/Tenant/Universal)
2. Model List by Namespace
3. Model Traits (HasUuids, CentralConnection, etc.)
4. MorphMap configuration
5. Cross-Database relationships

### 6.2 MEDIUM PRIORITY

#### `docs/CONTROLLERS.md`
Document controller patterns:
1. Controller Namespace Structure (Central/Tenant/Universal)
2. Thin Controller Pattern
3. Service injection patterns
4. Form Request validation
5. Code Examples

#### `docs/FORM-REQUESTS.md`
Document Form Request patterns:
1. Form Request List by Context
2. Validation patterns
3. Authorization in Form Requests
4. Code Examples

### 6.3 LOW PRIORITY

#### `docs/ENUMS.md`
Document all PHP Enums:
1. Permission Enums (CentralPermission, TenantPermission)
2. Role Enum (TenantRole)
3. Plan Enums (PlanFeature, PlanLimit)
4. Addon Enums (AddonType, AddonStatus, BillingPeriod)

---

## 7. Improvement Points Found

### 7.1 Documentation Gaps

| Gap | Priority | Impact |
|-----|----------|--------|
| No Services layer documentation | HIGH | Developers don't know service patterns |
| No Models architecture documentation | HIGH | Unclear Central/Tenant separation |
| No Controller patterns documentation | MEDIUM | Inconsistent controller implementations |
| No Form Requests documentation | MEDIUM | Validation patterns unclear |
| Testing guide is brief | LOW | Harder to write good tests |

### 7.2 Inconsistencies Found

1. **Archive Organization**: Completed planning docs still in main docs/ folder
2. **Cross-References**: Some docs reference deprecated model names
3. **Code Examples**: Some examples use old import statements
4. **Namespace References**: Inconsistent use of full vs short namespaces

### 7.3 Areas Needing Better Explanation

1. **Multi-Database Tenancy Flow**: How data flows between central and tenant databases
2. **Authentication Flows**: Central admin auth vs tenant user auth (Option C)
3. **Permission Resolution**: How plan permissions map to user permissions
4. **Service vs Controller**: When to put logic in services vs controllers

---

## 8. Recommended Final Structure

```
docs/
├── README.md                    # Index of all documentation
├── ARCHITECTURE/
│   ├── MODELS.md               # NEW: Model layer documentation
│   ├── SERVICES.md             # NEW: Service layer documentation
│   ├── CONTROLLERS.md          # NEW: Controller patterns
│   └── SYSTEM-ARCHITECTURE.md  # UPDATED: Overall architecture
├── FEATURES/
│   ├── ADDONS.md               # Current addon system
│   ├── PERMISSIONS.md          # UPDATED: Permissions system
│   ├── I18N.md                 # Internationalization
│   ├── MEDIALIBRARY.md         # Media library integration
│   └── SESSION-SECURITY.md     # Session security
├── DEVELOPMENT/
│   ├── MCP-WORKFLOW.md         # MCP tools workflow
│   ├── TESTING.md              # NEW: Testing guide
│   └── FORM-REQUESTS.md        # NEW: Form Request patterns
├── TENANCY/
│   ├── STANCL-FEATURES.md      # Stancl v4 features
│   ├── DATABASE-IDS.md         # UPDATED: UUID architecture
│   └── TENANCY-V4-IMPROVEMENT-PLAN.md  # Active roadmap
├── LOGS/
│   └── IMPLEMENTATION-LOG.md   # Active implementation log
└── archive/                    # Historical documents
    ├── MODELS-NAMESPACE-RESTRUCTURE-PLAN.md
    ├── MULTI-DATABASE-MIGRATION-PLAN.md
    ├── TENANT-USERS-ANALYSIS.md
    ├── TENANT-USERS-OPTION-C-IMPLEMENTATION.md
    ├── CONTROLLER-REFACTORING-PLAN.md      # MOVED
    ├── POST-RESTRUCTURE-CLEANUP-REPORT.md   # MOVED
    ├── SERVICES-NAMESPACE-RESTRUCTURE-PLAN.md # MOVED
    └── TENANCY-V4-IMPLEMENTATION-LOG.md     # MOVED
```

---

## 9. Implementation Checklist

### Phase 1: Cleanup (Immediate)
- [ ] Move completed plans to archive:
  - [ ] `CONTROLLER-REFACTORING-PLAN.md`
  - [ ] `POST-RESTRUCTURE-CLEANUP-REPORT.md`
  - [ ] `SERVICES-NAMESPACE-RESTRUCTURE-PLAN.md`
  - [ ] `TENANCY-V4-IMPLEMENTATION-LOG.md`

### Phase 2: Critical Updates
- [ ] Update `SYSTEM-ARCHITECTURE.md` with current namespaces
- [ ] Update `CLAUDE.md` with Services/Controller patterns
- [ ] Update `DATABASE-IDS.md` model references
- [ ] Update `PERMISSIONS.md` namespace references

### Phase 3: New Documentation
- [ ] Create `SERVICES.md`
- [ ] Create `MODELS.md`
- [ ] Create `CONTROLLERS.md`
- [ ] Create `FORM-REQUESTS.md`

### Phase 4: Organization (Optional)
- [ ] Create folder structure (ARCHITECTURE/, FEATURES/, etc.)
- [ ] Create `README.md` index
- [ ] Move files to appropriate folders

### Phase 5: Polish
- [ ] Create `TESTING.md`
- [ ] Create `ENUMS.md`
- [ ] Add diagrams where helpful
- [ ] Review all cross-references

---

## 10. Summary

| Category | Count |
|----------|-------|
| Files to Archive | 4 |
| Files to Update | 4 |
| Files to Keep | 8 |
| New Files to Create | 6 |
| **Total Actions** | **22** |

**Priority Order**:
1. Archive completed plans (quick wins)
2. Update SYSTEM-ARCHITECTURE.md and CLAUDE.md (critical)
3. Create SERVICES.md and MODELS.md (high value)
4. Remaining updates and new files
