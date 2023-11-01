<?php

namespace Marketredesign\MrdAuth0Laravel\Http\Controller\Stateful;

use Facile\OpenIDClient\Client\ClientInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class Logout
{
    private ClientInterface $oidcClient;
    private string $home;
    private ?string $logoutEndp;

    public function __construct(ClientInterface $oidcClient)
    {
        $this->oidcClient = $oidcClient;

        $this->home = url(config('pricecypher-oidc.routes.home', '/'));
        $this->logoutEndp = config('pricecypher-oidc.logout_endpoint');
    }

    public function __invoke(): RedirectResponse
    {
        $guard = Auth::guard('pc-oidc');

        if (!$guard->check()) {
            return redirect()->intended($this->home);
        }

        request()->session()->invalidate();
        $guard->logout();

        return redirect()->away($this->getLogoutUri());
    }

    private function getLogoutUri(): string
    {
        $metaEndSessionEndp = $this->oidcClient->getIssuer()->getMetadata()->get('end_session_endpoint');
        $logoutEndpoint = $this->logoutEndp ?? $metaEndSessionEndp;

        if ($logoutEndpoint === null) {
            return $this->home;
        }

        $query = http_build_query([
            'client_id' => config('pricecypher-oidc.client_id'),
            'post_logout_redirect_uri' => url('/'),
        ]);

        return "$logoutEndpoint?$query";
    }
}
