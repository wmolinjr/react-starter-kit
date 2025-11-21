# 11 - Tenant Settings & Branding

## Settings JSON Structure

```json
{
  "branding": {
    "logo_url": "https://...",
    "primary_color": "#3b82f6",
    "secondary_color": "#8b5cf6",
    "custom_css": "/* custom styles */"
  },
  "features": {
    "api_enabled": true,
    "custom_domain": true,
    "sso_enabled": false,
    "two_factor_required": false
  },
  "limits": {
    "max_users": 50,
    "max_projects": null,
    "storage_mb": 10000
  },
  "notifications": {
    "email_digest": "daily",
    "slack_webhook": "https://..."
  }
}
```

## Controller

```php
class SettingsController extends Controller
{
    public function updateBranding(Request $request)
    {
        $request->validate([
            'logo' => 'nullable|image|max:2048',
            'primary_color' => 'nullable|regex:/^#[0-9A-F]{6}$/i',
        ]);

        $tenant = current_tenant();

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('logos', 'tenant_uploads');
            $tenant->updateSetting('branding.logo_url', Storage::disk('tenant_uploads')->url($path));
        }

        if ($request->primary_color) {
            $tenant->updateSetting('branding.primary_color', $request->primary_color);
        }

        return back()->with('success', 'Branding atualizado!');
    }

    public function addDomain(Request $request)
    {
        $request->validate([
            'domain' => 'required|url|unique:domains,domain',
        ]);

        $tenant = current_tenant();

        if (!$tenant->hasFeature('custom_domain')) {
            return back()->withErrors(['domain' => 'Feature não disponível no seu plano.']);
        }

        $tenant->domains()->create([
            'domain' => parse_url($request->domain, PHP_URL_HOST),
            'is_primary' => false,
        ]);

        return back()->with('success', 'Domínio adicionado! Configure o DNS.');
    }
}
```

## React Settings Page

```tsx
// resources/js/pages/settings/branding.tsx

export default function BrandingSettings() {
  const { data, setData, post, processing } = useForm({
    logo: null,
    primary_color: '#3b82f6',
  });

  return (
    <form onSubmit={(e) => { e.preventDefault(); post('/settings/branding'); }}>
      <Input
        type="file"
        accept="image/*"
        onChange={(e) => setData('logo', e.target.files[0])}
      />
      <Input
        type="color"
        value={data.primary_color}
        onChange={(e) => setData('primary_color', e.target.value)}
      />
      <Button type="submit" disabled={processing}>Salvar</Button>
    </form>
  );
}
```

---

**Versão:** 1.0
