<?php


namespace Marketredesign\MrdAuth0Laravel\Tests\Feature;

use Auth0\SDK\Configuration\SdkConfiguration;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Marketredesign\MrdAuth0Laravel\Logging\NewRelicLogger;
use Marketredesign\MrdAuth0Laravel\Tests\TestCase;

class NewRelicLoggerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('auth.defaults.guard', 'auth0');

        Config::set('auth0', [
            'strategy' => SdkConfiguration::STRATEGY_REGULAR,
            'domain'   => 'auth.marketredesign.com',
            'audience' => ['https://api.pricecypher.com'],
            'clientId' => '123',
            'cookieSecret' => 'abc',
        ]);
    }

    /**
     * Verifies that logging metadata can be included in NewRelicLoger when app is running in console.
     */
    public function testIncludeMetaRunningInConsole()
    {
        // Mock app to think it's running in console.
        App::shouldReceive('runningInConsole')->andReturn(true);

        $logger = new NewRelicLogger();

        // Execute function under test.
        $meta = $logger->includeMetaData(['context' => []]);

        // Verify running in console metadata.
        self::assertTrue($meta['state']['running_in_console']);
        // Verify no user metadata since we are running in console.
        self::assertArrayNotHasKey('user', $meta);
    }
}
