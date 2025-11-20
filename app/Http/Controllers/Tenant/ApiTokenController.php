<?php

namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApiTokenController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            // View permission
            new Middleware('permission:tenant.apiTokens:view', only: ['index']),

            // Create permission
            new Middleware('permission:tenant.apiTokens:create', only: ['store', 'update']),

            // Delete permission
            new Middleware('permission:tenant.apiTokens:delete', only: ['destroy']),
        ];
    }

    /**
     * Display API tokens management page.
     */
    public function index()
    {
        $tokens = auth()->user()->tokens()
            ->where('tenant_id', tenant('id'))
            ->orderBy('created_at', 'desc')
            ->get();

        return inertia('settings/api-tokens', [
            'tokens' => $tokens->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities,
                'last_used_at' => $token->last_used_at?->diffForHumans(),
                'created_at' => $token->created_at->diffForHumans(),
            ]),
        ]);
    }

    /**
     * Create a new API token.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['array'],
            'abilities.*' => ['string'],
        ]);

        // Create token with abilities (default to all if not specified)
        $token = $request->user()->createToken(
            $request->name,
            $request->abilities ?? ['*']
        );

        // Associate token with current tenant
        DB::table('personal_access_tokens')
            ->where('id', $token->accessToken->id)
            ->update(['tenant_id' => tenant('id')]);

        return back()->with([
            'flash' => [
                'token' => $token->plainTextToken,
                'message' => 'API token created successfully. Make sure to copy it now - you won\'t be able to see it again!',
            ],
        ]);
    }

    /**
     * Update token abilities.
     */
    public function update(Request $request, $tokenId)
    {
        $request->validate([
            'abilities' => ['required', 'array'],
            'abilities.*' => ['string'],
        ]);

        $token = $request->user()->tokens()
            ->where('id', $tokenId)
            ->where('tenant_id', tenant('id'))
            ->firstOrFail();

        $token->forceFill([
            'abilities' => $request->abilities,
        ])->save();

        return back()->with('success', 'Token abilities updated successfully.');
    }

    /**
     * Delete API token.
     */
    public function destroy(Request $request, $tokenId)
    {
        $token = $request->user()->tokens()
            ->where('id', $tokenId)
            ->where('tenant_id', tenant('id'))
            ->firstOrFail();

        $token->delete();

        return back()->with('success', 'API token deleted successfully.');
    }
}
