<?php


namespace Marketredesign\MrdAuth0Laravel\Logging;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Monolog\Handler\BufferHandler;
use Monolog\Logger;
use NewRelic\Monolog\Enricher\Handler;
use NewRelic\Monolog\Enricher\Processor;

class NewRelicLogger
{
    public function __invoke(array $config)
    {
        // Add the new relic logger
        $log = new Logger('newrelic');
        $log->pushProcessor(new Processor());

        // Create a handler for it with license key
        $handler = new Handler();
        $handler->setLicenseKey($config['license_key']);
        $log->pushHandler(new BufferHandler($handler));

        // Add processor to include some extra data
        foreach ($log->getHandlers() as $handler) {
            $handler->pushProcessor([$this, 'includeMetaData']);
        }

        return $log;
    }

    /**
     * Add extra metadata to every request
     */
    public function includeMetaData(array $record): array
    {
        // Include info about which service this is
        $record['app'] = [
            'repository' => config('app.repository', 'Not implemented'),
            'version' => config('app.version', 'Not implemented'),
            'name' => config('app.name'),
            'hostname' => gethostname(),
            'url' => config('app.url'),
        ];

        // Add info about the Auth0 user performing the request we are running (if any)
        if (!App::runningInConsole()) {
            $record['user'] = [
                'authenticated' => Auth::check(),
                'authorized' => Auth::guard('pc-jwt')->check(),
                'user_id' => Auth::id() ?? Auth::guard('pc-jwt')->id(),
            ];
        }

        // Add info about the state which we are running for
        $record['state'] = [
            'running_in_console' => App::runningInConsole(),
            'request_method' => optional(request())->getMethod(),
            'route_name' => optional(optional(request())->route())->getName(),
            'uri' => optional(request())->getRequestUri(),
        ];

        // For exceptions, include the stack trace
        if (array_key_exists('exception', $record['context'])) {
            $record['context']['trace'] = $record['context']['exception']->getTraceAsString();
        }

        return $record;
    }
}
