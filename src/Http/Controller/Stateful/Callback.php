<?php

namespace Marketredesign\MrdAuth0Laravel\Http\Controller\Stateful;

use Facile\OpenIDClient\Client\ClientInterface;
use Facile\OpenIDClient\Service\AuthorizationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ServerRequestInterface;

class Callback
{
    public function __invoke(AuthorizationService $authService, ClientInterface $client, ServerRequestInterface $sri)
    {
        $guard = Auth::guard('pc-oidc');

        if ($guard->check()) {
            return redirect()->intended(config('pricecypher-oidc.routes.home', '/'));
        }

        $params = $authService->getCallbackParams($sri, $client);

        try {
            $tokenSet = $authService->callback($client, $params, route('oidc-callback'));
        } catch (\Throwable $e) {
            $guard->logout();
            Log::debug("OIDC callback failed with message {$e->getMessage()}.", [
                'exception' => $e,
            ]);
            abort(401, 'Not authenticated');
        }

        if ($tokenSet->getIdToken() === null) {
            abort(401, 'Not authenticated');
        }

        dd($tokenSet->getIdToken(), $tokenSet->getAccessToken());

        request()->session()->put('pc-oidc-session', (object) [
            'user' => $tokenSet->claims(),
            'access_token' => $tokenSet->getAccessToken(),
            'refresh_token' => $tokenSet->getRefreshToken(),
        ]);

        if (!$guard->check()) {
            abort(401, 'Not authenticated');
        }

        return redirect()->intended(config('pricecypher-oidc.routes.home', '/'));
    }
}
