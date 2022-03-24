<?php

namespace Marketredesign\MrdAuth0Laravel\Http\Middleware;

use App;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CheckPermissions
{
    /**
     * @var string Property name of the custom permissions claim that is included in the ID tokens issued by Auth0.
     */
    protected string $permissionsClaim;

    public function __construct()
    {
        $this->permissionsClaim = config('mrd-auth0.permissions_claim');
    }

    /**
     * Validate the user is logged in, optionally requiring some permission.
     *
     * @param Request $request - Illuminate HTTP Request object.
     * @param Closure $next - Function to call when middleware is complete.
     * @param string|null $permissionRequired - Optional, Auth0 permission (scope) required.
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

        // Verify the logged in user has the required permissions (scope), if one was provided.
        if ($permissionRequired !== null && !$this->userHasPermission($permissionRequired)) {
            abort(403, 'Insufficient permissions');
        }

        return $next($request);
    }

    /**
     * Check if the logged in user has a specific permission (scope).
     *
     * @param string $permissionRequired - Permission (scope) to check for.
     *
     * @return bool true iff the logged in user has the required permission (scope).
     */
    protected function userHasPermission(string $permissionRequired)
    {
        // Find permissions claim in the userinfo (ID token).
        $permissions = Auth::user()->{$this->permissionsClaim};

        // The permissions claim is always expected in the ID token, so give warning if it's not present.
        if (!isset($permissions)) {
            Log::warning('Encountered user info (ID token) without permissions claim. This probably indicates
                a misconfiguration somewhere in this application (like the mrd-auth0.permissions_claim config value) or 
                within Auth0.');
            return false;
        }

        return in_array($permissionRequired, $permissions);
    }
}
