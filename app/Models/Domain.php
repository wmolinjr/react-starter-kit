<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Domain extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'domain',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    /**
     * Domain pertence a um tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Tornar este domínio primário
     * (remove is_primary de outros domínios do mesmo tenant)
     */
    public function makePrimary(): bool
    {
        // Remove primary de outros domínios
        $this->tenant->domains()->update(['is_primary' => false]);

        // Define este como primary
        return $this->update(['is_primary' => true]);
    }

    /**
     * Validar formato do domínio
     */
    public static function isValidDomain(string $domain): bool
    {
        return (bool) filter_var("http://{$domain}", FILTER_VALIDATE_URL);
    }
}
