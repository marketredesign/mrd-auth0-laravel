<?php

namespace Marketredesign\MrdAuth0Laravel\Http\Middleware;

use App;
use Auth0\Login\Auth0Service;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CheckPermissions
{
    /**
     * Validate the user is logged in, optionally requiring some permission.
     *
     * @param Request $request - Illuminate HTTP Request object.
     * @param Closure $next - Function to call when middleware is complete.
     * @param string|null $permissionRequired - Optional, Oauth permission required
     *
     * @return mixed
     * @throws Exception
     */
    public function handle(Request $request, Closure $next, string $permissionRequired = null)
    {
        // Make sure the user is logged in
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        // Fetch the access token of the user
        $token = Auth::user()->getAuthPassword();
        if ($token === null) {
            if (!config('laravel-auth0.persist_access_token')) {
                Log::error("The CheckPermissions middleware is being used, but a logged in user does not have 
                    an access token attached. Set the config laravel-auth0.persist_access_token to true to fix this.");
            }
            abort(401, 'No access token present');
        }

        // Verify the user has the required permission
        $auth0 = app()->make(Auth0Service::class);
        $decoded = $auth0->decodeJWT($token);
        if ($permissionRequired !== null && !in_array($permissionRequired, $decoded['permissions'])) {
            abort(401, 'Insufficient permissions');
        }

        return $next($request);
    }
}
