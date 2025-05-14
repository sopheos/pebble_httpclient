<?php

namespace Pebble\HttpClient;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\TooManyRedirectsException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;

class Http
{
    use OptionTrait;

    private bool $httpErrors;

    public function __construct(string $baseUri = "", bool $httpErrors = false)
    {
        $this->httpErrors = $httpErrors;
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
        try {
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
        } catch (TooManyRedirectsException $ex) {
            return $this->tooManyRedirectsException($ex);
        } catch (BadResponseException $ex) {
            return $this->badResponseException($ex);
        } catch (TransferException $ex) {
            return $this->transferException($ex);
        }
    }

    private function badResponseException(BadResponseException $ex)
    {
        if ($this->httpErrors) {
            throw $ex;
        }

        return new Response(
            $ex->getResponse()->getStatusCode(),
            $ex->getResponse()->getHeaders(),
            $ex->getResponse()->getBody()->getContents()
        );
    }

    private function tooManyRedirectsException(TooManyRedirectsException $ex)
    {
        if ($this->httpErrors) {
            throw $ex;
        }

        return new Response(
            $ex->getResponse()->getStatusCode(),
            $ex->getResponse()->getHeaders(),
            $ex->getResponse()->getBody()->getContents()
        );
    }

    private function transferException(TransferException $ex)
    {
        if ($this->httpErrors) {
            throw $ex;
        }

        return new Response(504, [], $ex->getMessage());
    }

    public function get(string $url = "/"): Response
    {
        return $this->one(Request::get($url));
    }

    public function post(string $url = "/"): Response
    {
        return $this->one(Request::post($url));
    }

    public function put(string $url = "/"): Response
    {
        return $this->one(Request::put($url));
    }

    public function patch(string $url = "/"): Response
    {
        return $this->one(Request::patch($url));
    }

    public function delete(string $url = "/"): Response
    {
        return $this->one(Request::delete($url));
    }

    public function options(string $url = "/"): Response
    {
        return $this->one(Request::options($url));
    }

    /**
     * @param Request[]
     * @return Response[]
     */
    public function all(array $requests): array
    {
        try {
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
        } catch (Exception $ex) {

            if ($this->httpErrors) {
                throw $ex;
            }

            $responses = [];
            foreach (array_keys($requests) as $key) {
                $responses[$key] = new Response(504, [], $ex->getMessage());
            }

            return $responses;
        }
    }

    // -------------------------------------------------------------------------
}
