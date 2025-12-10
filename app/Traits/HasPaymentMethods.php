<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Central\PaymentMethod;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Trait for models that can have payment methods (Customer).
 *
 * Provides payment method management without coupling to any specific payment provider.
 */
trait HasPaymentMethods
{
    /**
     * Get all payment methods for this customer.
     */
    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class, 'customer_id');
    }

    /**
     * Get payment methods by type.
     */
    public function paymentMethodsByType(string $type): Collection
    {
        return $this->paymentMethods()
            ->where('type', $type)
            ->whereNull('deleted_at')
            ->get();
    }

    /**
     * Get payment methods by provider.
     */
    public function paymentMethodsByProvider(string $provider): Collection
    {
        return $this->paymentMethods()
            ->where('provider', $provider)
            ->whereNull('deleted_at')
            ->get();
    }

    /**
     * Get the default payment method (from relationship).
     * Note: defaultPaymentMethod() BelongsTo is defined in Customer model.
     */
    public function getDefaultPaymentMethod(): ?PaymentMethod
    {
        // First check the FK relationship
        if ($this->default_payment_method_id) {
            return $this->defaultPaymentMethod;
        }

        // Fallback to first payment method marked as default
        return $this->paymentMethods()
            ->where('is_default', true)
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * Set a payment method as default.
     */
    public function setDefaultPaymentMethod(PaymentMethod $paymentMethod): void
    {
        // Unset previous default
        $this->paymentMethods()
            ->where('is_default', true)
            ->update(['is_default' => false]);

        // Set new default
        $paymentMethod->update(['is_default' => true]);

        // Update FK reference
        $this->update(['default_payment_method_id' => $paymentMethod->id]);
    }

    /**
     * Check if customer has any payment method.
     */
    public function hasPaymentMethod(): bool
    {
        return $this->paymentMethods()
            ->whereNull('deleted_at')
            ->exists();
    }

    /**
     * Check if customer has a specific type of payment method.
     */
    public function hasPaymentMethodType(string $type): bool
    {
        return $this->paymentMethods()
            ->where('type', $type)
            ->whereNull('deleted_at')
            ->exists();
    }

    /**
     * Get card payment methods.
     */
    public function cardPaymentMethods(): Collection
    {
        return $this->paymentMethodsByType('card');
    }

    /**
     * Get the default card or first available card.
     */
    public function defaultCard(): ?PaymentMethod
    {
        $default = $this->getDefaultPaymentMethod();

        if ($default && $default->type === 'card') {
            return $default;
        }

        return $this->cardPaymentMethods()->first();
    }

    /**
     * Find a payment method by provider method ID.
     */
    public function findPaymentMethod(string $providerMethodId): ?PaymentMethod
    {
        return $this->paymentMethods()
            ->where('provider_method_id', $providerMethodId)
            ->first();
    }

    /**
     * Remove a payment method (soft delete).
     */
    public function removePaymentMethod(PaymentMethod $paymentMethod): bool
    {
        // If this was the default, clear the default
        if ($this->default_payment_method_id === $paymentMethod->id) {
            $this->update(['default_payment_method_id' => null]);
        }

        return $paymentMethod->delete();
    }
}
