<?php


namespace Marketredesign\MrdAuth0Laravel\Logging;


use Illuminate\Support\Facades\Config;
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
            'repository' => Config::has('app.repository') ? config('app.repository') : 'Not implemented',
            'version' => Config::has('app.version') ? config('app.version') : 'Not implemented',
            'name' => config('app.name'),
            'hostname' => gethostname(),
            'url' => config('app.url'),
        ];

        // Add info about the Auth0 user performing the request we are running (if any)
        $record['user'] = [
            'authenticated' => optional(request())->user_id,
            'user_id' => optional(request())->user_id,
        ];

        // Add info about the state which we are running for
        $record['state'] = [
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
