<?php

namespace Marketredesign\MrdAuth0Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetRequestType
{
    /**
     * Store the given {@code $reqType} in the request. This allows us, at a later point in the middleware stack, to
     * determine e.g. whether the current request is stateful (for web routes) or stateless (for api routes).
     *
     * @param Request $request
     * @param Closure $next
     * @param string $reqType
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $reqType)
    {
        $request->merge(['__internal__request_type' => $reqType]);

        return $next($request);
    }
}
