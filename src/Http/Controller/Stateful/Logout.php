<?php

namespace Marketredesign\MrdAuth0Laravel\Http\Controller\Stateful;

use Illuminate\Support\Facades\Auth;

class Logout
{
    public function __invoke()
    {
        $guard = Auth::guard('pc-oidc');

        if (!$guard->check()) {
            return redirect()->intended(config('pricecypher-oidc.routes.home', '/'));
        }

        request()->session()->invalidate();
        $guard->logout();

        return redirect()->away($this->getLogoutUri());
    }

    private function getLogoutUri()
    {
        $logoutEndpoint = config('pricecypher-oidc.logout_endpoint');

        if ($logoutEndpoint === null) {
            return route(config('pricecypher-oidc.routes.home', '/'));
        }

        $query = http_build_query([
            'client_id' => config('pricecypher-oidc.client_id'),
            'logout_uri' => url('/'),
        ]);

        return "$logoutEndpoint?$query";
    }
}
