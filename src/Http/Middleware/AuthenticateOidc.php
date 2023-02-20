<?php

namespace Marketredesign\MrdAuth0Laravel\Http\Middleware;

use App;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Marketredesign\MrdAuth0Laravel\Model\Stateful\User;

class AuthenticateOidc
{
    /**
     * Check whether the user is authenticated.
     *
     * @param Request $request Illuminate HTTP Request object.
     * @param Closure $next Function to call when middleware is complete.
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $guard = Auth::guard('pc-oidc');
        $user = $guard->user();

        if (!($user instanceof User)) {
            return redirect(route('oidc-login'));
        }

        return $next($request);
    }
}
