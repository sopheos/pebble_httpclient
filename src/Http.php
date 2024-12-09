<?php

namespace Pebble\HttpClient;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;

class Http
{
    use OptionTrait;

    public function __construct(string $baseUri = "", bool $httpErrors = false)
    {
        $this->addOption('base_uri', rtrim($baseUri, '/'));
        $this->addOption(RequestOptions::HTTP_ERRORS, $httpErrors);
    }

    // -------------------------------------------------------------------------

    protected function getClient(): Client
    {
        return new Client($this->getOptions());
    }

    public function one(Request $request): Response
    {
        $result = $this->getClient()->request(
            $request->getMethod(),
            $request->getUrl(),
            $request->getOptions(),
        );

        return new Response(
            $result->getStatusCode(),
            $result->getHeaders(),
            $result->getBody()->getContents()
        );
    }

    /**
     * @param Request[]
     * @return Response[]
     */
    public function all(array $requests)
    {
        $client = $this->getClient();
        $promises = [];
        foreach ($requests as $key => $request) {
            $promises[$key] = $client->requestAsync(
                $request->getMethod(),
                $request->getUrl(),
                $request->getOptions(),
            );
        }

        /** @var ResponseInterface[] $results */
        $results = Utils::unwrap($promises);

        $responses = [];
        foreach ($results as $key => $result) {
            $responses[$key] = new Response(
                $result->getStatusCode(),
                $result->getHeaders(),
                $result->getBody()->getContents()
            );
        }

        return $responses;
    }

    // -------------------------------------------------------------------------
}
