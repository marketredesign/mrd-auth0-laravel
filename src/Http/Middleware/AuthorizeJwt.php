<?php

namespace Marketredesign\MrdAuth0Laravel\Http\Middleware;

use App;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Marketredesign\MrdAuth0Laravel\Model\Stateless\User;

class AuthorizeJwt
{
    private string $scopePrefix;

    public function __construct()
    {
        $this->scopePrefix = config('pricecypher-oidc.scope_prefix', '');
    }

    /**
     * Authorize bearer token included in the request.
     *
     * @param Request $request Illuminate HTTP Request object.
     * @param Closure $next Function to call when middleware is complete.
     * @param string $scope OAuth scope that should be included in the bearer token.
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $scope = ''): mixed
    {
        $guard = Auth::guard('jwt');
        $user = $guard->user();

        if (!($user instanceof User)) {
            abort(401, 'Unauthorized');
        }

        if ($scope !== '' && !$guard->hasScope($this->scopePrefix . $scope)) {
            abort(403, 'Forbidden');
        }

        return $next($request);
    }
}
