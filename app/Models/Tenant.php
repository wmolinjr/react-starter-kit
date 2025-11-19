<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'subdomain',
        'domain',
        'settings',
        'status',
        'description',
        'logo',
        'favicon',
        'primary_color',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tenant) {
            if (empty($tenant->slug)) {
                $tenant->slug = Str::slug($tenant->name);
            }

            // Auto-generate subdomain from slug if not provided
            if (empty($tenant->subdomain)) {
                $tenant->subdomain = $tenant->slug;
            }
        });
    }

    /**
     * Get all users that belong to this tenant.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get all pages that belong to this tenant.
     */
    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    /**
     * Get all custom domains for this tenant.
     */
    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    /**
     * Get all invitations for this tenant.
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(TenantInvitation::class);
    }

    /**
     * Get the primary domain for this tenant.
     */
    public function primaryDomain(): HasOne
    {
        return $this->hasOne(Domain::class)->where('is_primary', true);
    }

    /**
     * Get the owner of this tenant.
     */
    public function owner()
    {
        return $this->users()->wherePivot('role', 'owner')->first();
    }

    /**
     * Add a custom domain to this tenant.
     */
    public function addDomain(string $domain, bool $isPrimary = false): Domain
    {
        $domainModel = $this->domains()->create([
            'domain' => $domain,
            'is_primary' => $isPrimary,
            'verification_status' => 'pending',
        ]);

        // Generate verification token
        $domainModel->generateVerificationToken();

        // If this should be primary, ensure it is
        if ($isPrimary) {
            $domainModel->setPrimary();
        }

        return $domainModel->fresh();
    }

    /**
     * Check if tenant is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Scope a query to only include active tenants.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Get the logo URL.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo) {
            return null;
        }

        return asset('storage/' . $this->logo);
    }

    /**
     * Get the favicon URL.
     */
    public function getFaviconUrlAttribute(): ?string
    {
        if (!$this->favicon) {
            return null;
        }

        return asset('storage/' . $this->favicon);
    }

    /**
     * Update branding for this tenant.
     */
    public function updateBranding(array $data): void
    {
        $this->update([
            'description' => $data['description'] ?? $this->description,
            'logo' => $data['logo'] ?? $this->logo,
            'favicon' => $data['favicon'] ?? $this->favicon,
            'primary_color' => $data['primary_color'] ?? $this->primary_color,
        ]);
    }
}
