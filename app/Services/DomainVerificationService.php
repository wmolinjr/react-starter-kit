<?php

namespace App\Services;

use App\Models\Domain;
use Illuminate\Support\Facades\Log;

class DomainVerificationService
{
    /**
     * Verify domain ownership via DNS TXT record.
     *
     * Checks for a TXT record at _tenant-verify.{domain}
     * that matches the domain's verification_token.
     *
     * @return bool True if verified, false otherwise
     */
    public function verifyDomain(Domain $domain): bool
    {
        // For localhost/development, allow verification to pass
        if (app()->environment('local') && str_contains($domain->domain, 'localhost')) {
            Log::info("Development environment: Auto-verifying domain {$domain->domain}");
            $domain->markAsVerified();

            return true;
        }

        try {
            // Construct the verification hostname
            $verificationHost = "_tenant-verify.{$domain->domain}";

            // Get TXT records for the verification hostname
            $records = dns_get_record($verificationHost, DNS_TXT);

            if (! $records || ! is_array($records)) {
                Log::warning("No DNS TXT records found for {$verificationHost}");
                $domain->markAsFailed();

                return false;
            }

            // Check if any TXT record matches the verification token
            foreach ($records as $record) {
                if (isset($record['txt']) && $record['txt'] === $domain->verification_token) {
                    Log::info("Domain {$domain->domain} verified successfully");
                    $domain->markAsVerified();

                    return true;
                }
            }

            // No matching verification token found
            Log::warning("Verification token mismatch for {$domain->domain}");
            $domain->markAsFailed();

            return false;
        } catch (\Exception $e) {
            Log::error("DNS verification error for {$domain->domain}: {$e->getMessage()}");
            $domain->markAsFailed();

            return false;
        }
    }

    /**
     * Get DNS verification instructions for a domain.
     *
     * Returns the DNS record configuration needed to verify domain ownership.
     *
     * @return array{record_type: string, host: string, value: string, ttl: int}
     */
    public function getVerificationInstructions(Domain $domain): array
    {
        return [
            'record_type' => 'TXT',
            'host' => "_tenant-verify.{$domain->domain}",
            'value' => $domain->verification_token,
            'ttl' => 3600,
        ];
    }
}
