<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApiTokenController extends Controller
{
    /**
     * Display a listing of the user's API tokens.
     */
    public function index(Request $request)
    {
        return response()->json([
            'tokens' => $request->user()->tokens->map(function ($token) {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'abilities' => $token->abilities,
                    'last_used_at' => $token->last_used_at,
                    'created_at' => $token->created_at,
                    'expires_at' => $token->expires_at,
                ];
            }),
        ]);
    }

    /**
     * Create a new API token for the user.
     */
    public function store(Request $request)
    {
        // Validate input
        $request->validate([
            'name' => 'required|string|max:255',
            'abilities' => 'array',
            'abilities.*' => 'string',
        ]);

        $user = $request->user();

        // Create token with specified abilities (default to all)
        $token = $user->createToken(
            $request->name,
            $request->abilities ?? ['*']
        );

        // Associate token with current tenant
        $tenantId = current_tenant_id();

        if ($tenantId) {
            DB::table('personal_access_tokens')
                ->where('id', $token->accessToken->id)
                ->update(['tenant_id' => $tenantId]);
        }

        // Return the plain text token (only shown once)
        return response()->json([
            'token' => $token->plainTextToken,
            'type' => 'Bearer',
            'name' => $request->name,
            'abilities' => $request->abilities ?? ['*'],
        ], 201);
    }

    /**
     * Revoke a specific API token.
     */
    public function destroy(Request $request, $tokenId)
    {
        $user = $request->user();

        // Find and delete the token
        $deleted = $user->tokens()
            ->where('id', $tokenId)
            ->delete();

        if (!$deleted) {
            throw ValidationException::withMessages([
                'token' => ['Token not found or already revoked.'],
            ]);
        }

        return response()->json([
            'message' => 'Token revoked successfully.',
        ]);
    }

    /**
     * Revoke all API tokens except the current one.
     */
    public function destroyAll(Request $request)
    {
        $user = $request->user();
        $currentToken = $user->currentAccessToken();

        // Delete all tokens except current
        $count = $user->tokens()
            ->where('id', '!=', $currentToken?->id)
            ->delete();

        return response()->json([
            'message' => "Revoked {$count} token(s) successfully.",
            'revoked_count' => $count,
        ]);
    }
}
