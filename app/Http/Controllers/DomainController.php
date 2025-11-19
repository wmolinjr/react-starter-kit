<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Models\Tenant;
use App\Services\DomainVerificationService;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    public function __construct(
        protected DomainVerificationService $verificationService
    ) {}

    /**
     * Display a listing of the domains for this tenant.
     */
    public function index(string $slug)
    {
        $tenant = Tenant::where('slug', $slug)->firstOrFail();
        $this->authorize('view', $tenant);

        $domains = $tenant->domains()->orderBy('is_primary', 'desc')->orderBy('created_at', 'desc')->get();

        return response()->json($domains);
    }

    /**
     * Store a newly created domain for the tenant.
     */
    public function store(Request $request, string $slug)
    {
        $tenant = Tenant::where('slug', $slug)->firstOrFail();
        $this->authorize('update', $tenant);

        $validated = $request->validate([
            'domain' => [
                'required',
                'string',
                'max:255',
                'regex:/^(?!:\/\/)([a-zA-Z0-9-_]+\.)*[a-zA-Z0-9][a-zA-Z0-9-_]+\.[a-zA-Z]{2,11}?$/',
                'unique:domains,domain',
            ],
        ], [
            'domain.regex' => 'Please enter a valid domain name (e.g., example.com or www.example.com)',
            'domain.unique' => 'This domain is already in use by another workspace',
        ]);

        // Prevent using the app's base domain
        $appDomain = config('app.domain', 'localhost');
        if (str_ends_with($validated['domain'], '.'.$appDomain) || $validated['domain'] === $appDomain) {
            return back()->withErrors([
                'domain' => 'You cannot use the application\'s base domain',
            ]);
        }

        try {
            $domain = $tenant->addDomain($validated['domain']);

            return back()->with([
                'flash' => [
                    'data' => $domain,
                    'message' => 'Domain added successfully. Please verify ownership via DNS.',
                ],
            ]);
        } catch (\Exception $e) {
            return back()->withErrors([
                'domain' => 'Failed to add domain: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Update the specified domain (set as primary).
     */
    public function update(Request $request, string $slug, Domain $domain)
    {
        $tenant = Tenant::where('slug', $slug)->firstOrFail();
        $this->authorize('update', $tenant);

        if ($domain->tenant_id !== $tenant->id) {
            abort(403, 'This domain does not belong to this tenant');
        }

        if (! $domain->isVerified()) {
            return back()->withErrors([
                'domain' => 'Only verified domains can be set as primary',
            ]);
        }

        $domain->setPrimary();

        return back()->with([
            'flash' => [
                'message' => 'Primary domain updated successfully',
            ],
        ]);
    }

    /**
     * Verify domain ownership via DNS.
     */
    public function verify(Request $request, string $slug, Domain $domain)
    {
        $tenant = Tenant::where('slug', $slug)->firstOrFail();
        $this->authorize('update', $tenant);

        if ($domain->tenant_id !== $tenant->id) {
            abort(403, 'This domain does not belong to this tenant');
        }

        if ($domain->isVerified()) {
            return back()->with([
                'flash' => [
                    'message' => 'Domain is already verified',
                ],
            ]);
        }

        try {
            $verified = $this->verificationService->verifyDomain($domain);

            if ($verified) {
                return back()->with([
                    'flash' => [
                        'data' => $domain->fresh(),
                        'message' => 'Domain verified successfully!',
                    ],
                ]);
            }

            return back()->withErrors([
                'domain' => 'Domain verification failed. Please ensure the TXT record is correctly configured.',
            ]);
        } catch (\Exception $e) {
            return back()->withErrors([
                'domain' => 'Verification error: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Remove the specified domain.
     */
    public function destroy(Request $request, string $slug, Domain $domain)
    {
        $tenant = Tenant::where('slug', $slug)->firstOrFail();
        $this->authorize('update', $tenant);

        if ($domain->tenant_id !== $tenant->id) {
            abort(403, 'This domain does not belong to this tenant');
        }

        // Prevent deletion of primary domain if there are other domains
        if ($domain->is_primary && $tenant->domains()->count() > 1) {
            return back()->withErrors([
                'domain' => 'Please set another domain as primary before deleting this one',
            ]);
        }

        $domain->delete();

        return back()->with([
            'flash' => [
                'message' => 'Domain removed successfully',
            ],
        ]);
    }
}
