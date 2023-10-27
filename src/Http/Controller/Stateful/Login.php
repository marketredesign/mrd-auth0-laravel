<?php

namespace Marketredesign\MrdAuth0Laravel\Http\Controller\Stateful;

use Facile\OpenIDClient\Authorization\AuthRequestInterface;
use Facile\OpenIDClient\Client\ClientInterface;
use Facile\OpenIDClient\Service\AuthorizationService;
use Illuminate\Support\Facades\Auth;

class Login
{
    public function __invoke(
        AuthRequestInterface $authRequest,
        AuthorizationService $authService,
        ClientInterface $oidcClient,
    ) {
        $guard = Auth::guard('pc-oidc');

        if ($guard->check()) {
            return redirect()->intended(config('pricecypher-oidc.routes.home', '/'));
        }

        $uri = $authService->getAuthorizationUri($oidcClient, $authRequest->createParams());

        return redirect()->away($uri);
    }
}
