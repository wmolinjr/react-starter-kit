# Customer Portal (Billing Portal)

> **Status**: Implemented
> **Version**: 1.0.0
> **Last Updated**: December 2024

## Overview

The Customer Portal is a centralized billing management system that allows real people (Customers) to manage billing for all their tenants (workspaces) from a single dashboard.

### Problem Solved

Previous architecture tied Stripe billing to `Tenant`, not to the person:
- User with 5 tenants = 5 separate Stripe Customers
- No unified billing view across tenants
- Payment methods duplicated per tenant
- Future non-tenant products had no billing entity

### Solution

**Central Customer + Resource Syncing**

```
┌─────────────────────────────────────────────────────────────────────┐
│                       BILLING ARCHITECTURE                          │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│   Central\Customer (Billable)                                       │
│   ├── stripe_id, pm_type, pm_last_four                             │
│   ├── billing_address, tax_ids                                      │
│   └── tenants() → owns multiple tenants                            │
│                                                                     │
│   Tenant                                                            │
│   ├── customer_id (FK) → who pays                                  │
│   ├── payment_method_id → override (optional)                       │
│   └── subscription → via Customer                                   │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Authentication Architecture

### Three-Guard System

| Guard | Model | Domain | Purpose |
|-------|-------|--------|---------|
| `central` | Central\User | app.test/admin | Platform admins (Super Admin, Support) |
| `customer` | Central\Customer | app.test/account | Billing portal (customers who pay) |
| `tenant` | Tenant\User | {tenant}.test | Workspace access (team members) |

### Configuration

```php
// config/auth.php

'guards' => [
    'customer' => [
        'driver' => 'session',
        'provider' => 'customers',
    ],
],

'providers' => [
    'customers' => [
        'driver' => 'eloquent',
        'model' => App\Models\Central\Customer::class,
    ],
],

'passwords' => [
    'customers' => [
        'provider' => 'customers',
        'table' => 'customer_password_reset_tokens',
        'expire' => 60,
        'throttle' => 60,
    ],
],
```

---

## Routes

All routes are prefixed with `/account` and use the `customer.` name prefix.

### Guest Routes

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | /account/register | customer.register | Registration form |
| POST | /account/register | - | Process registration |
| GET | /account/login | customer.login | Login form |
| POST | /account/login | - | Process login |
| GET | /account/forgot-password | customer.password.request | Password reset request |
| POST | /account/forgot-password | customer.password.email | Send reset link |
| GET | /account/reset-password/{token} | customer.password.reset | Reset password form |
| POST | /account/reset-password | customer.password.update | Update password |
| GET | /account/transfers/{token}/accept | customer.transfers.accept.show | View transfer invitation |

### Authenticated Routes

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| POST | /account/logout | customer.logout | Logout |
| GET | /account/verify-email | customer.verification.notice | Email verification notice |
| GET | /account/verify-email/{id}/{hash} | customer.verification.verify | Verify email |
| POST | /account/email/verification-notification | customer.verification.send | Resend verification |

### Verified Routes (require email verification)

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | /account | customer.dashboard | Dashboard |
| GET | /account/profile | customer.profile.edit | Edit profile |
| PATCH | /account/profile | customer.profile.update | Update profile |
| PATCH | /account/profile/password | customer.profile.password | Update password |
| PATCH | /account/profile/billing | customer.profile.billing | Update billing address |
| DELETE | /account/profile | customer.profile.destroy | Delete account |
| GET | /account/tenants | customer.tenants.index | List workspaces |
| GET | /account/tenants/create | customer.tenants.create | Create workspace form |
| POST | /account/tenants | customer.tenants.store | Store workspace |
| GET | /account/tenants/{tenant} | customer.tenants.show | Workspace details |
| GET | /account/tenants/{tenant}/billing | customer.tenants.billing | Workspace billing |
| PATCH | /account/tenants/{tenant}/payment-method | customer.tenants.payment-method | Update payment method |
| GET | /account/tenants/{tenant}/transfer | customer.transfers.create | Transfer form |
| POST | /account/tenants/{tenant}/transfer | customer.transfers.store | Initiate transfer |
| POST | /account/transfers/{token}/confirm | customer.transfers.confirm | Confirm transfer |
| POST | /account/transfers/{transfer}/cancel | customer.transfers.cancel | Cancel transfer |
| POST | /account/transfers/{transfer}/reject | customer.transfers.reject | Reject transfer |
| GET | /account/payment-methods | customer.payment-methods.index | List payment methods |
| GET | /account/payment-methods/create | customer.payment-methods.create | Add payment method |
| POST | /account/payment-methods | customer.payment-methods.store | Store payment method |
| DELETE | /account/payment-methods/{id} | customer.payment-methods.destroy | Remove payment method |
| POST | /account/payment-methods/{id}/default | customer.payment-methods.default | Set as default |
| GET | /account/invoices | customer.invoices.index | List invoices |
| GET | /account/invoices/{invoice} | customer.invoices.show | Invoice details |
| GET | /account/invoices/{invoice}/download | customer.invoices.download | Download PDF |
| GET | /account/billing-portal | customer.billing-portal | Stripe portal redirect |

---

## Database Schema

### Central Database Tables

#### customers

```sql
CREATE TABLE customers (
    id UUID PRIMARY KEY,
    global_id VARCHAR UNIQUE NOT NULL,
    name VARCHAR NOT NULL,
    email VARCHAR UNIQUE NOT NULL,
    phone VARCHAR NULL,
    password VARCHAR NOT NULL,
    remember_token VARCHAR NULL,

    -- Stripe Billable (Laravel Cashier)
    stripe_id VARCHAR UNIQUE NULL,
    pm_type VARCHAR NULL,
    pm_last_four VARCHAR(4) NULL,
    trial_ends_at TIMESTAMP NULL,

    -- Billing Information
    billing_address JSON NULL,
    locale VARCHAR(10) DEFAULT 'pt_BR',
    currency VARCHAR(3) DEFAULT 'brl',
    tax_ids JSON NULL,

    -- Authentication
    email_verified_at TIMESTAMP NULL,
    two_factor_secret TEXT NULL,
    two_factor_recovery_codes TEXT NULL,
    two_factor_confirmed_at TIMESTAMP NULL,

    -- Metadata
    metadata JSON NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL
);
```

#### customer_tenants (pivot)

```sql
CREATE TABLE customer_tenants (
    id UUID PRIMARY KEY,
    global_id VARCHAR NOT NULL,  -- Customer's global_id
    tenant_id VARCHAR NOT NULL,  -- Tenant's id
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    UNIQUE(global_id, tenant_id),
    FOREIGN KEY (global_id) REFERENCES customers(global_id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

#### customer_password_reset_tokens

```sql
CREATE TABLE customer_password_reset_tokens (
    email VARCHAR PRIMARY KEY,
    token VARCHAR NOT NULL,
    created_at TIMESTAMP NULL
);
```

#### tenants (additions)

```sql
ALTER TABLE tenants ADD COLUMN customer_id UUID NULL REFERENCES customers(id) ON DELETE SET NULL;
ALTER TABLE tenants ADD COLUMN payment_method_id VARCHAR NULL;
```

---

## Models

### Central\Customer

Location: `app/Models/Central/Customer.php`

Key traits:
- `Billable` (Laravel Cashier)
- `CentralConnection` (always uses central DB)
- `HasUuids`
- `SoftDeletes`
- `TwoFactorAuthenticatable`

Key relationships:
- `ownedTenants()` - Tenants this customer pays for (via customer_id FK)
- `tenants()` - Tenants with access (via pivot table)
- `subscriptions()` - All Stripe subscriptions
- `addonPurchases()` - One-time addon purchases
- `addonSubscriptions()` - Recurring addon subscriptions
- `initiatedTransfers()` - Transfers initiated by customer
- `receivedTransfers()` - Transfers received by customer

Key methods:
- `createTenant(array $data)` - Create a new tenant
- `subscriptionForTenant(Tenant $tenant)` - Get subscription for specific tenant
- `paymentMethodForTenant(Tenant $tenant)` - Get payment method (with override support)
- `getTotalMonthlyBilling()` - Sum of all active subscriptions
- `hasActiveSubscription()` - Check if any subscription is active

### Central\Tenant (additions)

Key relationships:
- `customer()` - BelongsTo Customer who pays

Key methods:
- `getBillable()` - Returns the Customer
- `hasActiveSubscription()` - Checks via Customer
- `subscription()` - Gets active subscription via Customer
- `getPaymentMethod()` - Gets payment method (tenant override or customer default)

---

## Controllers

All controllers are in `app/Http/Controllers/Customer/`:

| Controller | Purpose |
|------------|---------|
| `Auth/LoginController` | Customer login |
| `Auth/LogoutController` | Customer logout |
| `Auth/RegisterController` | Customer registration |
| `Auth/ForgotPasswordController` | Password reset request |
| `Auth/ResetPasswordController` | Password reset |
| `Auth/VerifyEmailController` | Email verification |
| `DashboardController` | Dashboard with stats |
| `ProfileController` | Profile, password, billing management |
| `TenantController` | Workspace management |
| `PaymentMethodController` | Stripe payment methods |
| `InvoiceController` | Invoice listing and download |
| `TransferController` | Tenant ownership transfers |

---

## Frontend Pages

All pages are in `resources/js/pages/customer/`:

### Layout

`resources/js/layouts/customer-layout.tsx`

Sidebar navigation:
- Dashboard (`/account`)
- Workspaces (`/account/tenants`)
- Payment Methods (`/account/payment-methods`)
- Invoices (`/account/invoices`)

Footer navigation:
- Profile (`/account/profile`)
- Logout

### Page Structure

```
resources/js/pages/customer/
├── auth/
│   ├── login.tsx
│   ├── register.tsx
│   ├── forgot-password.tsx
│   ├── reset-password.tsx
│   └── verify-email.tsx
├── dashboard.tsx
├── profile/
│   └── edit.tsx
├── tenants/
│   ├── index.tsx
│   ├── create.tsx
│   └── show.tsx
├── payment-methods/
│   ├── index.tsx
│   └── create.tsx
├── invoices/
│   ├── index.tsx
│   └── show.tsx
└── transfers/
    ├── create.tsx
    ├── accept.tsx
    ├── expired.tsx
    └── invalid.tsx
```

---

## Test Users (Seeders)

Created by `CustomerSeeder`:

| Email | Password | Description |
|-------|----------|-------------|
| `customer@example.com` | `password` | Test customer with tenant |

To seed:
```bash
sail artisan db:seed --class=CustomerSeeder
```

---

## Running Tests

```bash
# All Customer tests
sail artisan test --filter=Customer --parallel

# Specific test files
sail artisan test tests/Feature/Customer/Auth/CustomerAuthenticationTest.php
sail artisan test tests/Feature/Customer/CustomerDashboardTest.php
sail artisan test tests/Feature/Customer/CustomerProfileTest.php
```

### Test Coverage

| Test File | Coverage |
|-----------|----------|
| CustomerAuthenticationTest | Login, logout, redirect flows |
| CustomerPasswordResetTest | Password reset flow |
| CustomerEmailVerificationTest | Email verification flow |
| CustomerDashboardTest | Dashboard access and stats |
| CustomerProfileTest | Profile, password, billing, account deletion |

---

## Translations

All Customer Portal translations use the `customer.` namespace.

Key translations (in `lang/en.json` and `lang/pt_BR.json`):

```json
{
    "customer.dashboard": "Dashboard",
    "customer.workspaces": "Workspaces",
    "customer.payment_methods": "Payment Methods",
    "customer.invoices": "Invoices",
    "customer.profile": "Profile",
    "customer.billing_portal": "Billing Portal",
    "customer.create_workspace": "Create Workspace",
    "customer.no_workspaces": "No workspaces yet",
    "customer.welcome_back": "Welcome back, :name",
    // ... more translations
}
```

---

## Middleware

### customer.verified

Location: `app/Http/Middleware/Customer/EnsureCustomerEmailIsVerified.php`

Registered in: `bootstrap/app.php`

```php
$middleware->alias([
    'customer.verified' => \App\Http\Middleware\Customer\EnsureCustomerEmailIsVerified::class,
]);
```

This middleware ensures the customer has verified their email before accessing protected routes.

---

## Integration with Stripe

The Customer model uses Laravel Cashier's `Billable` trait:

```php
use Laravel\Cashier\Billable;

class Customer extends Authenticatable
{
    use Billable;

    // Stripe metadata
    public function stripeName(): ?string
    {
        return $this->name;
    }

    public function stripeEmail(): ?string
    {
        return $this->email;
    }
}
```

### Payment Method per Tenant

Each tenant can optionally override the default payment method:

```php
// In Tenant model
public function getPaymentMethod(): ?object
{
    if ($this->payment_method_id) {
        return $this->customer?->findPaymentMethod($this->payment_method_id);
    }

    return $this->customer?->defaultPaymentMethod();
}
```

---

## Tenant Transfers (Future)

The system supports transferring tenant ownership:

1. **Initiate**: Owner initiates transfer to email
2. **Invite**: Recipient receives email with link
3. **Accept/Reject**: Recipient can accept or reject
4. **Complete**: Ownership transfers, subscriptions migrate

Transfer fee: 5% of remaining subscription value

---

## Related Documentation

- [SYSTEM-ARCHITECTURE.md](SYSTEM-ARCHITECTURE.md) - Plans, features, limits
- [PERMISSIONS.md](PERMISSIONS.md) - Permission system
- [SESSION-SECURITY.md](SESSION-SECURITY.md) - Session configuration
- [ADDONS.md](ADDONS.md) - Add-ons and bundles

---

## Changelog

| Date | Version | Changes |
|------|---------|---------|
| 2024-12-11 | 1.0.0 | Initial implementation |
