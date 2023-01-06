<?php

namespace Marketredesign\MrdAuth0Laravel\Listeners;

use Auth0\Laravel\Contract\Event\Configuration\Building;
use Auth0\SDK\Configuration\SdkConfiguration;

class SetAuth0Strategy
{
    /**
     * Set Auth0 SDK strategy based on the type of the current request (stateful vs stateless).
     *
     * @param Building $event
     * @return void
     */
    public function __invoke(Building $event): void
    {
        $config = $event->getConfiguration();
        $type = request()->__internal__request_type;

        if (!in_array($config['strategy'], [SdkConfiguration::STRATEGY_REGULAR, SdkConfiguration::STRATEGY_API])) {
            return;
        }

        // We assume all requests to be stateful, unless it is explicitly set to stateless.
        if ($type === 'stateless') {
            $config['strategy'] = SdkConfiguration::STRATEGY_API;
        } else {
            $config['strategy'] = SdkConfiguration::STRATEGY_REGULAR;
        }

        $event->setConfiguration($config);
    }
}
