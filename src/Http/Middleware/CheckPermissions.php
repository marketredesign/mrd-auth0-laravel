<?php

namespace Marketredesign\MrdAuth0Laravel\Http\Middleware;

use App;
use Auth0\Login\Auth0Service;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        // Verify the user has the required permission
        $auth0 = app()->make(Auth0Service::class);
        $token = $auth0->decodeJWT(Auth::user()->getAuthPassword());
        if ($permissionRequired !== null && !in_array($permissionRequired, $token['permissions'])) {
            abort(401, 'Insufficient permissions');
        }

        return $next($request);
    }
}
