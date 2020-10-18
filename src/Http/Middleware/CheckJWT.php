<?php

namespace Marketredesign\MrdAuth0Laravel\Http\Middleware;

use App;
use Auth0\Login\Auth0Service;
use Auth0\Login\Contract\Auth0UserRepository;
use Auth0\SDK\API\Authentication;
use Auth0\SDK\Exception\InvalidTokenException;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CheckJWT
{
    // Time to store user info in cache, in seconds.
    private const CACHE_TTL = 600;
    // Delimiter used to separate different scopes within the JWTs.
    private const SCOPE_DELIMITER = ' ';

    private $auth;
    private $userRepository;

    /**
     * CheckJWT constructor.
     *
     * @param Authentication $auth
     * @param Auth0UserRepository $userRepository
     */
    public function __construct(Authentication $auth, Auth0UserRepository $userRepository)
    {
        $this->auth = $auth;
        $this->userRepository = $userRepository;
    }

    /**
     * Validate an incoming JWT access token.
     *
     * @param Request $request - Illuminate HTTP Request object.
     * @param Closure $next - Function to call when middleware is complete.
     * @param string|null $scopeRequired - Optional, Oauth scope (permission) required
     *
     * @return mixed
     * @throws Exception
     */
    public function handle(Request $request, Closure $next, string $scopeRequired = null)
    {
        $auth0 = app()->make(Auth0Service::class);

        // Find the bearer token in the request.
        $bearerToken = $request->bearerToken();

        if (empty($bearerToken)) {
            abort(401, 'Bearer token missing');
        }

        try {
            $tokenInfo = $auth0->decodeJWT($bearerToken);
            $user = $this->userRepository->getUserByDecodedJWT($tokenInfo);
        } catch (InvalidTokenException $e) {
            abort(401, $e->getMessage());
        }

        // Verify the token has the required scope, if one was provided.
        if ($scopeRequired !== null && !$this->tokenHasScope($tokenInfo, $scopeRequired)) {
            abort(403, 'Insufficient scope');
        }

        $userId = $user->getAuthIdentifier();

        $request->merge(['user_id' => $userId]);
        $request->setUserResolver(function () use ($userId, $bearerToken) {
            return $this->getUserInfo($userId, $bearerToken);
        });

        return $next($request);
    }

    /**
     * Get the OIDC user info, given a bearer token.
     * This user info is retrieved from the user info endpoint, if it is not yet present in cache.
     *
     * @param string $userId User ID the bearer token belongs to.
     * @param string $bearerToken Bearer token to retrieve the user info with.
     * @return mixed Decoded response as provided by the OIDC issuer.
     */
    protected function getUserInfo(string $userId, string $bearerToken)
    {
        return Cache::remember('UserInfo-' . $userId, self::CACHE_TTL, function () use ($bearerToken) {
            return $this->userRepository->getUserByDecodedJWT($this->auth->userinfo($bearerToken));
        });
    }

    /**
     * Check if a token has a specific scope.
     *
     * @param array $token - JWT access token to check.
     * @param string $scopeRequired - Scope to check for.
     *
     * @return bool true iff the provided token has the required scope.
     */
    protected function tokenHasScope(array $token, string $scopeRequired)
    {
        if (!isset($token['scope'])) {
            return false;
        }

        $tokenScopes = explode(self::SCOPE_DELIMITER, $token['scope']);
        return in_array($scopeRequired, $tokenScopes);
    }
}
