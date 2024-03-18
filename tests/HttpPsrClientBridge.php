<?php

namespace Marketredesign\MrdAuth0Laravel\Tests;

use Exception;
use Illuminate\Support\Facades\Http;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class HttpPsrClientBridge implements ClientInterface
{
    protected array $httpOptions;

    public function __construct(array $httpOptions = [])
    {
        $this->httpOptions = $httpOptions;
    }

    /**
     * @throws Exception
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return Http::withOptions($this->httpOptions)
            ->withHeaders($request->getHeaders())
            ->send($request->getMethod(), (string)$request->getUri(), ['body' => $request->getBody()])
            ->toPsrResponse();
    }
}