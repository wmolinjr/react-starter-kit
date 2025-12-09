# Components Reorganization Plan

## Overview

Reorganize `resources/js/components/` into three contexts with logical subfolders:
- `shared/` - Components used in both contexts or generic utilities
- `tenant/` - Components specific to tenant context
- `central/` - Components specific to central admin context
- `ui/` - **DO NOT TOUCH** - shadcn/ui components

## Current Structure Analysis

### Root-level Components
| File | Description |
|------|-------------|
| `heading.tsx` | Generic heading component |
| `heading-small.tsx` | Generic small heading component |
| `flash-messages.tsx` | Flash toast messages |
| `user-info.tsx` | User avatar/info display |
| `dynamic-icon.tsx` | Dynamic Lucide icon renderer |
| `app-shell.tsx` | Application shell wrapper |
| `app-logo-icon.tsx` | SVG logo icon |
| `app-logo.tsx` | Full logo with text |
| `impersonation-banner.tsx` | Tenant-only impersonation banner |
| `icon-selector.tsx` | Icon picker for forms (Central only) |
| `invite-member-dialog.tsx` | Team invite dialog (Tenant only) |
| `badge-selector.tsx` | Badge picker for forms (Central only) |
| `alert-error.tsx` | Error alert display |
| `text-link.tsx` | Styled link component |
| `nav-footer.tsx` | Sidebar footer navigation |
| `can.tsx` | Permission-based rendering |
| `icon.tsx` | Icon wrapper component |
| `two-factor-setup-modal.tsx` | Tenant 2FA setup |
| `two-factor-recovery-codes.tsx` | 2FA recovery codes |
| `input-error.tsx` | Form input error message |
| `appearance-tabs.tsx` | Theme toggle tabs |
| `app-content.tsx` | Main content wrapper |
| `translatable-input.tsx` | Multi-language input (Central only) |
| `nav-main.tsx` | Main sidebar navigation |
| `appearance-dropdown.tsx` | Theme dropdown |
| `language-selector.tsx` | Language picker |
| `page.tsx` | Page structure components |
| `app-header.tsx` | Header navigation bar |
| `central-two-factor-setup-modal.tsx` | Central 2FA setup |
| `breadcrumbs.tsx` | Breadcrumb navigation |
| `color-selector.tsx` | Color picker (Central only) |

### Existing Subfolders
- `sidebar/` - Sidebar components (mixed tenant/central)
- `tenant/` - Tenant-specific nav user components
- `central/` - Central-specific nav user components
- `addons/` - Addon components (Tenant only)

---

## Migration Plan

### Shared Components

| Current Location | New Location | Reasoning |
|-----------------|--------------|-----------|
| `heading.tsx` | `shared/typography/heading.tsx` | Generic component used in both contexts |
| `heading-small.tsx` | `shared/typography/heading-small.tsx` | Generic component used in both contexts |
| `text-link.tsx` | `shared/typography/text-link.tsx` | Generic link component used everywhere |
| `flash-messages.tsx` | `shared/feedback/flash-messages.tsx` | Used by Sonner toaster across all pages |
| `alert-error.tsx` | `shared/feedback/alert-error.tsx` | Error display used in both contexts |
| `input-error.tsx` | `shared/feedback/input-error.tsx` | Form validation error in all forms |
| `user-info.tsx` | `shared/user/user-info.tsx` | User avatar used by both nav-user components |
| `icon.tsx` | `shared/icons/icon.tsx` | Generic icon wrapper |
| `dynamic-icon.tsx` | `shared/icons/dynamic-icon.tsx` | Dynamic icon loader |
| `can.tsx` | `shared/auth/can.tsx` | Permission component works with both guards |
| `two-factor-recovery-codes.tsx` | `shared/auth/two-factor-recovery-codes.tsx` | Recovery codes used by both 2FA modals |
| `app-shell.tsx` | `shared/layout/app-shell.tsx` | Application shell used by all layouts |
| `app-content.tsx` | `shared/layout/app-content.tsx` | Content wrapper used by all layouts |
| `page.tsx` | `shared/layout/page.tsx` | Page structure components |
| `app-logo-icon.tsx` | `shared/branding/app-logo-icon.tsx` | SVG logo icon |
| `app-logo.tsx` | `shared/branding/app-logo.tsx` | Full logo with text |
| `breadcrumbs.tsx` | `shared/navigation/breadcrumbs.tsx` | Breadcrumb nav used everywhere |
| `nav-main.tsx` | `shared/navigation/nav-main.tsx` | Sidebar main nav |
| `nav-footer.tsx` | `shared/navigation/nav-footer.tsx` | Sidebar footer nav |
| `sidebar/app-sidebar-header.tsx` | `shared/navigation/sidebar-header.tsx` | Header with breadcrumbs |
| `appearance-tabs.tsx` | `shared/settings/appearance-tabs.tsx` | Theme toggle tabs |
| `appearance-dropdown.tsx` | `shared/settings/appearance-dropdown.tsx` | Theme dropdown |
| `language-selector.tsx` | `shared/settings/language-selector.tsx` | Language picker |

### Tenant Components

| Current Location | New Location | Reasoning |
|-----------------|--------------|-----------|
| `impersonation-banner.tsx` | `tenant/feedback/impersonation-banner.tsx` | Only in tenant layouts |
| `invite-member-dialog.tsx` | `tenant/dialogs/invite-member-dialog.tsx` | Team invite only in tenant |
| `two-factor-setup-modal.tsx` | `tenant/dialogs/two-factor-setup-modal.tsx` | Uses tenant routes |
| `app-header.tsx` | `tenant/layout/app-header.tsx` | Uses tenant routes |
| `sidebar/app-sidebar.tsx` | `tenant/navigation/app-sidebar.tsx` | Basic tenant sidebar |
| `sidebar/tenant-admin-sidebar.tsx` | `tenant/navigation/admin-sidebar.tsx` | Tenant admin sidebar |
| `sidebar/tenant-nav-items.tsx` | `tenant/navigation/nav-items.tsx` | Tenant nav items config |
| `tenant/nav-user.tsx` | `tenant/navigation/nav-user.tsx` | Tenant user menu |
| `tenant/user-menu-content.tsx` | `tenant/navigation/user-menu-content.tsx` | Tenant user dropdown |
| `addons/usage-meter.tsx` | `tenant/addons/usage-meter.tsx` | Addon usage meter |
| `addons/billing-period-toggle.tsx` | `tenant/addons/billing-period-toggle.tsx` | Billing period toggle |
| `addons/addon-card.tsx` | `tenant/addons/addon-card.tsx` | Addon display card |
| `addons/quantity-selector.tsx` | `tenant/addons/quantity-selector.tsx` | Quantity input |
| `addons/active-addon-card.tsx` | `tenant/addons/active-addon-card.tsx` | Active addon display |
| `addons/purchase-modal.tsx` | `tenant/addons/purchase-modal.tsx` | Addon purchase dialog |

### Central Components

| Current Location | New Location | Reasoning |
|-----------------|--------------|-----------|
| `central-two-factor-setup-modal.tsx` | `central/dialogs/two-factor-setup-modal.tsx` | Uses central routes |
| `icon-selector.tsx` | `central/forms/icon-selector.tsx` | Only in central forms |
| `badge-selector.tsx` | `central/forms/badge-selector.tsx` | Only in central forms |
| `color-selector.tsx` | `central/forms/color-selector.tsx` | Only in central forms |
| `translatable-input.tsx` | `central/forms/translatable-input.tsx` | Only in central forms |
| `sidebar/central-admin-sidebar.tsx` | `central/navigation/admin-sidebar.tsx` | Central admin sidebar |
| `sidebar/central-nav-items.tsx` | `central/navigation/nav-items.tsx` | Central nav items config |
| `central/nav-user.tsx` | `central/navigation/nav-user.tsx` | Central user menu |
| `central/user-menu-content.tsx` | `central/navigation/user-menu-content.tsx` | Central user dropdown |

---

## Proposed Final Structure

```
resources/js/components/
├── ui/                              # shadcn/ui (DO NOT TOUCH)
│
├── shared/
│   ├── auth/
│   │   ├── can.tsx
│   │   └── two-factor-recovery-codes.tsx
│   ├── branding/
│   │   ├── app-logo.tsx
│   │   └── app-logo-icon.tsx
│   ├── feedback/
│   │   ├── flash-messages.tsx
│   │   ├── alert-error.tsx
│   │   └── input-error.tsx
│   ├── icons/
│   │   ├── icon.tsx
│   │   └── dynamic-icon.tsx
│   ├── layout/
│   │   ├── app-shell.tsx
│   │   ├── app-content.tsx
│   │   └── page.tsx
│   ├── navigation/
│   │   ├── breadcrumbs.tsx
│   │   ├── nav-main.tsx
│   │   ├── nav-footer.tsx
│   │   └── sidebar-header.tsx
│   ├── settings/
│   │   ├── appearance-tabs.tsx
│   │   ├── appearance-dropdown.tsx
│   │   └── language-selector.tsx
│   ├── typography/
│   │   ├── heading.tsx
│   │   ├── heading-small.tsx
│   │   └── text-link.tsx
│   └── user/
│       └── user-info.tsx
│
├── tenant/
│   ├── addons/
│   │   ├── active-addon-card.tsx
│   │   ├── addon-card.tsx
│   │   ├── billing-period-toggle.tsx
│   │   ├── purchase-modal.tsx
│   │   ├── quantity-selector.tsx
│   │   └── usage-meter.tsx
│   ├── dialogs/
│   │   ├── invite-member-dialog.tsx
│   │   └── two-factor-setup-modal.tsx
│   ├── feedback/
│   │   └── impersonation-banner.tsx
│   ├── layout/
│   │   └── app-header.tsx
│   └── navigation/
│       ├── admin-sidebar.tsx
│       ├── app-sidebar.tsx
│       ├── nav-items.tsx
│       ├── nav-user.tsx
│       └── user-menu-content.tsx
│
└── central/
    ├── dialogs/
    │   └── two-factor-setup-modal.tsx
    ├── forms/
    │   ├── badge-selector.tsx
    │   ├── color-selector.tsx
    │   ├── icon-selector.tsx
    │   └── translatable-input.tsx
    └── navigation/
        ├── admin-sidebar.tsx
        ├── nav-items.tsx
        ├── nav-user.tsx
        └── user-menu-content.tsx
```

---

## Implementation Steps

### Phase 1: Create Directory Structure
```bash
mkdir -p resources/js/components/shared/{auth,branding,feedback,icons,layout,navigation,settings,typography,user}
mkdir -p resources/js/components/tenant/{addons,dialogs,feedback,layout,navigation}
mkdir -p resources/js/components/central/{dialogs,forms,navigation}
```

### Phase 2: Move Shared Components
1. Move typography components
2. Move feedback components
3. Move layout components
4. Move navigation components
5. Move settings components
6. Move auth components
7. Move branding components
8. Move icons components
9. Move user components

### Phase 3: Move Tenant Components
1. Move existing `addons/` folder contents
2. Move dialogs (invite, 2FA)
3. Move feedback (impersonation banner)
4. Move layout (app-header)
5. Move navigation (sidebar, nav items, nav user)

### Phase 4: Move Central Components
1. Move dialogs (2FA)
2. Move forms (selectors, translatable input)
3. Move navigation (sidebar, nav items, nav user)

### Phase 5: Update All Imports
Run sed commands to update import paths across all files:
- `@/components/heading` → `@/components/shared/typography/heading`
- `@/components/app-shell` → `@/components/shared/layout/app-shell`
- etc.

### Phase 6: Cleanup
1. Remove empty old directories (`sidebar/`, `addons/`)
2. Verify TypeScript compilation
3. Test the application

---

## Files Requiring Import Updates

### High-Impact Files (many imports)
1. `resources/js/layouts/tenant/admin-layout.tsx`
2. `resources/js/layouts/central/admin-layout.tsx`
3. `resources/js/app.tsx`
4. All pages in `resources/js/pages/`

### Estimated Import Updates
- ~50+ files will need import path updates
- Use `find` and `sed` for bulk updates

---

## Rollback Plan

If issues arise:
1. Git revert the changes
2. Keep a backup branch before starting

---

## Notes

- The `ui/` folder is managed by shadcn CLI and must not be modified
- Some components may need internal import updates after moving
- Consider creating barrel exports (`index.ts`) for cleaner imports after reorganization
