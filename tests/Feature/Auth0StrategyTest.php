<?php


namespace Marketredesign\MrdAuth0Laravel\Tests\Feature;

use Auth0\Laravel\Facade\Auth0;
use Auth0\Laravel\Store\LaravelSession;
use Auth0\SDK\Configuration\SdkConfiguration;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Marketredesign\MrdAuth0Laravel\Tests\TestCase;
use ReflectionClass;

class Auth0StrategyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('auth0', [
            'domain'   => 'auth.marketredesign.com',
            'audience' => ['https://api.pricecypher.com'],
            'redirectUri' => 'https://redirect.com/oauth/callback',
            'sessionStorage' => new LaravelSession(),
            'transientStorage' => new LaravelSession(),
            'clientId' => '123',
            'clientSecret' => '123',
            'cookieSecret' => 'abc',
        ]);
    }

    /**
     * Registers a new GET route with the provided middleware and optional request handler. Then calls that new endpoint
     * and returns the {@code TestResponse}.
     *
     * @param array|string $middleware
     * @param Closure|null $requestHandler
     * @return TestResponse
     */
    private function request(array|string $middleware, ?Closure $requestHandler)
    {
        $uri = uuid_create();

        // Define a very simple testing endpoint.
        Route::middleware($middleware)
            ->get($uri, $requestHandler ?? function () {
                return response()->json('test_response');
            });

        return $this->getJson($uri);
    }

    /**
     * Verifies that the request's {@code __internal_request_type} is set to stateless for API requests.
     */
    public function testRequestTypeStateless()
    {
        // Set auth0 strategy to regular and api and verify request type as expected.
        foreach ([SdkConfiguration::STRATEGY_REGULAR, SdkConfiguration::STRATEGY_API] as $strat) {
            Config::set('auth0.strategy', $strat);
            $this->resetAuth0Config();

            // Send API request and verify 'stateless' request type.
            $this->request('api', function (Request $request) {
                return $request->__internal__request_type;
            })->assertOk()->assertSee('stateless');
        }

        // Now use other strategies as well and verify OK response.
        foreach ([SdkConfiguration::STRATEGY_NONE, SdkConfiguration::STRATEGY_MANAGEMENT_API] as $strat) {
            Config::set('auth0.strategy', $strat);
            $this->resetAuth0Config();

            // Send API request and verify OK response.
            $this->request('api', function (Request $request) {
                return $request->__internal__request_type;
            })->assertOk();
        }
    }

    /**
     * Verifies that the request's {@code __internal_request_type} is set to stateful for web requests.
     */
    public function testRequestTypeStateful()
    {
        // Set auth0 strategy to regular and api and verify request type as expected.
        foreach ([SdkConfiguration::STRATEGY_REGULAR, SdkConfiguration::STRATEGY_API] as $strat) {
            Config::set('auth0.strategy', $strat);
            $this->resetAuth0Config();

            // Send API request and verify 'stateless' request type.
            $this->request('web', function (Request $request) {
                return $request->__internal__request_type;
            })->assertOk()->assertSee('stateful');
        }

        // Now use other strategies as well and verify OK response.
        foreach ([SdkConfiguration::STRATEGY_NONE, SdkConfiguration::STRATEGY_MANAGEMENT_API] as $strat) {
            Config::set('auth0.strategy', $strat);
            $this->resetAuth0Config();

            // Send API request and verify OK response.
            $this->request('web', function (Request $request) {
                return $request->__internal__request_type;
            })->assertOk();
        }
    }

    /**
     * Verifies that the auth0 strategy during a request is set dynamically based on the route type, irrespective of
     * the configured strategy, when it is set to API or WEBAPP.
     */
    public function testAuth0SdkStrategy()
    {
        // We need reflections of the Auth0 class to reset the STATIC :( configuration instance back to null.
        $auth0Reflect = new ReflectionClass(\Auth0\Laravel\Auth0::class);

        // First set auth0 strategy to API in the config.
        Config::set('auth0.strategy', SdkConfiguration::STRATEGY_API);

        // Ensure auth0 configuration not initialized by setting it to null.
        $auth0Reflect->setStaticPropertyValue('configuration', null);
        // Login, send WEB request, and verify WEB strategy is used (even though we set API in the config initially).
        $this->actingAsAuth0User([], false);
        $this->request(['web', 'auth0.authenticate'], function () {
            return Auth0::getConfiguration()->getStrategy();
        })->assertOk()->assertSee(SdkConfiguration::STRATEGY_REGULAR);

        // Ensure auth0 configuration not initialized by setting it to null.
        $auth0Reflect->setStaticPropertyValue('configuration', null);
        // Authorize, send API request, and verify API strategy is used.
        $this->actingAsAuth0User();
        $this->request(['api', 'auth0.authorize'], function () {
            return Auth0::getConfiguration()->getStrategy();
        })->assertOk()->assertSee(SdkConfiguration::STRATEGY_API);

        // Now set auth0 strategy to webapp in the config.
        Config::set('auth0.strategy', SdkConfiguration::STRATEGY_REGULAR);

        // Ensure auth0 configuration not initialized by setting it to null.
        $auth0Reflect->setStaticPropertyValue('configuration', null);
        // Authorize, send API request and verify API strategy is used (even though we set WEB in the config initially).
        $this->actingAsAuth0User();
        $this->request(['api', 'auth0.authorize'], function () {
            return Auth0::getConfiguration()->getStrategy();
        })->assertOk()->assertSee(SdkConfiguration::STRATEGY_API);

        // Ensure auth0 configuration not initialized by setting it to null.
        $auth0Reflect->setStaticPropertyValue('configuration', null);
        // Login, send WEB request, and verify WEB strategy is used.
        $this->actingAsAuth0User([], false);
        $this->request(['web', 'auth0.authenticate'], function () {
            return Auth0::getConfiguration()->getStrategy();
        })->assertOk()->assertSee(SdkConfiguration::STRATEGY_REGULAR);

        // Lastly, also set other auth0 strategies in the config and verify no errors.
        foreach ([SdkConfiguration::STRATEGY_NONE, SdkConfiguration::STRATEGY_MANAGEMENT_API] as $strat) {
            Config::set('auth0.strategy', $strat);

            // Ensure auth0 configuration not initialized by setting it to null.
            $auth0Reflect->setStaticPropertyValue('configuration', null);
            // Authorize, send API request and verify OK response.
            $this->actingAsAuth0User();
            $this->request(['api', 'auth0.authorize'], function () {
                return Auth0::getConfiguration()->getStrategy();
            })->assertOk();

            // Ensure auth0 configuration not initialized by setting it to null.
            $auth0Reflect->setStaticPropertyValue('configuration', null);
            // Login, send WEB request, and verify WEB strategy is used.
            $this->actingAsAuth0User([], false);
            $this->request(['web', 'auth0.authenticate'], function () {
                return Auth0::getConfiguration()->getStrategy();
            })->assertOk();
        }
    }
}
