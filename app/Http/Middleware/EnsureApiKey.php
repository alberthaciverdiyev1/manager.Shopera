<?php

namespace App\Http\Middleware;

use App\Models\SiteOwner;
use Closure;
use Illuminate\Http\Request;

/**
 * Authenticates a calling ShopEra instance.
 * - Preferred: "Authorization: Bearer <owner api_token>" → resolves the owner.
 * - Legacy:    "X-Api-Key: <shared key>" + ?host= → host-based lookup.
 */
class EnsureApiKey
{
    public function handle(Request $request, Closure $next)
    {
        $bearer = $request->bearerToken();

        if ($bearer) {
            $owner = SiteOwner::query()->where('api_token', $bearer)->first();

            if (! $owner) {
                return $this->unauthorized();
            }

            $request->attributes->set('site_owner', $owner);

            return $next($request);
        }

        $shared = (string) config('manager.api_key');
        $provided = (string) $request->header('X-Api-Key');

        if ($shared !== '' && $provided !== '' && hash_equals($shared, $provided)) {
            return $next($request);
        }

        return $this->unauthorized();
    }

    private function unauthorized()
    {
        return response()->json([
            'success' => false,
            'status_code' => 401,
            'message' => 'Unauthorized.',
            'data' => null,
        ], 401);
    }
}
