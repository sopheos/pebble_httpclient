<?php

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Pebble\HttpClient\Http;
use Pebble\HttpClient\Request;
use Pebble\HttpClient\Response;
use PHPUnit\Framework\TestCase;

class HttpTest extends TestCase
{
    private array $history = [];

    /**
     * Client branché sur un MockHandler : aucun appel réseau.
     * L'option Guzzle `handler` est transmise telle quelle au constructeur de Client.
     */
    private function http(array $queue, string $baseUri = 'http://api.test', bool $httpErrors = false): Http
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($queue));
        $stack->push(Middleware::history($this->history));

        $http = new Http($baseUri, $httpErrors);
        $http->addOption('handler', $stack);
        return $http;
    }

    private function sentUri(int $index = 0): string
    {
        return (string) $this->history[$index]['request']->getUri();
    }

    private function timeout(string $path = '/'): ConnectException
    {
        return new ConnectException('cURL error 28: Operation timed out', new Psr7Request('GET', $path));
    }

    // -------------------------------------------------------------------------
    // Construction
    // -------------------------------------------------------------------------

    public function testConstructorSetsBaseUriAndHttpErrors()
    {
        $http = new Http('http://api.test/', true);

        self::assertSame(['base_uri' => 'http://api.test', 'http_errors' => true], $http->getOptions());
    }

    public function testSetOptionsWipesBaseUriAndHttpErrors()
    {
        $http = (new Http('http://api.test', true))->setOptions(['verify' => false]);

        self::assertSame(['verify' => false], $http->getOptions());
    }

    // -------------------------------------------------------------------------
    // one()
    // -------------------------------------------------------------------------

    public function testOneReturnsAResponse()
    {
        $http = $this->http([new Psr7Response(200, ['Content-Type' => 'application/json'], '{"id":42}')]);
        $response = $http->one(Request::post('/users')->jsonParams(['name' => 'Bob']));

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(200, $response->status());
        self::assertSame(42, $response->get('id'));
        self::assertSame('POST', $this->history[0]['request']->getMethod());
        self::assertSame('http://api.test/users', $this->sentUri());
        self::assertSame('{"name":"Bob"}', (string) $this->history[0]['request']->getBody());
    }

    public function testShortcutsSendTheMatchingVerb()
    {
        $http = $this->http(array_fill(0, 6, new Psr7Response(204)));
        foreach (['get', 'post', 'put', 'patch', 'delete', 'options'] as $verb) {
            $http->{$verb}('/x');
        }

        $methods = array_map(fn($entry) => $entry['request']->getMethod(), $this->history);
        self::assertSame(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $methods);
    }

    public function testClientHeadersAreMergedWithRequestHeaders()
    {
        $http = $this->http([new Psr7Response(200)])->auth('T1');
        $http->one(Request::get('/')->addHeader('X-Trace', 'abc'));

        $sent = $this->history[0]['request'];
        self::assertSame('Bearer T1', $sent->getHeaderLine('Authorization'));
        self::assertSame('abc', $sent->getHeaderLine('X-Trace'));
    }

    // -------------------------------------------------------------------------
    // HTTP errors
    // -------------------------------------------------------------------------

    public function testErrorStatusIsReturnedAsAResponseByDefault()
    {
        $response = $this->http([new Psr7Response(404, [], 'not found')])->get('/missing');

        self::assertSame(404, $response->status());
        self::assertSame('not found', $response->body());
        self::assertFalse($response->isSuccess());
    }

    public function testErrorStatusThrowsWhenHttpErrorsIsEnabled()
    {
        $this->expectException(ServerException::class);

        $this->http([new Psr7Response(500)], 'http://api.test', true)->get('/');
    }

    public function testPerRequestHttpErrorsStillReturnsAResponse()
    {
        $http = $this->http([new Psr7Response(500, [], 'boom')]);
        $response = $http->one(Request::get('/')->addOption('http_errors', true));

        self::assertSame(500, $response->status());
        self::assertSame('boom', $response->body());
    }

    public function testTooManyRedirectsReturnsTheLastRedirect()
    {
        $http = $this->http([
            new Psr7Response(302, ['Location' => '/a']),
            new Psr7Response(302, ['Location' => '/b']),
        ]);
        $response = $http->one(Request::get('/')->addOption('allow_redirects', ['max' => 1]));

        self::assertSame(302, $response->status());
    }

    // -------------------------------------------------------------------------
    // Transport errors (timeout, DNS, connection refused)
    // -------------------------------------------------------------------------

    public function testTransportErrorBecomesASynthetic504()
    {
        $response = $this->http([$this->timeout()])->get('/');

        self::assertSame(Response::GATEWAY_TIMEOUT, $response->status());
        self::assertSame('cURL error 28: Operation timed out', $response->body());
        self::assertSame([], $response->headers());
    }

    public function testTransportErrorThrowsWhenHttpErrorsIsEnabled()
    {
        $this->expectException(ConnectException::class);

        $this->http([$this->timeout()], 'http://api.test', true)->get('/');
    }

    // -------------------------------------------------------------------------
    // all()
    // -------------------------------------------------------------------------

    public function testAllKeepsTheRequestKeys()
    {
        $http = $this->http([new Psr7Response(200, [], 'A'), new Psr7Response(404, [], 'B')]);
        $responses = $http->all(['a' => Request::get('/a'), 'b' => Request::get('/b')]);

        self::assertSame(['a', 'b'], array_keys($responses));
        self::assertSame('A', $responses['a']->body());
        self::assertSame(404, $responses['b']->status());
    }

    public function testAllWithNoRequestsReturnsAnEmptyArray()
    {
        self::assertSame([], $this->http([])->all([]));
    }

    public function testAllThrowsOnErrorStatusWhenHttpErrorsIsEnabled()
    {
        $this->expectException(ClientException::class);

        $http = $this->http([new Psr7Response(200), new Psr7Response(404)], 'http://api.test', true);
        $http->all([Request::get('/a'), Request::get('/b')]);
    }

    // -------------------------------------------------------------------------
    // Known bugs (see TODO.md)
    // -------------------------------------------------------------------------

    public function testAllTurnsEveryResponseInto504WhenOneRequestFails()
    {
        $http = $this->http([
            new Psr7Response(200, [], 'A'),
            $this->timeout('/b'),
            new Psr7Response(200, [], 'C'),
        ]);
        $responses = $http->all(['a' => Request::get('/a'), 'b' => Request::get('/b'), 'c' => Request::get('/c')]);

        // BUG: a and c succeeded but their responses are lost; every key gets the error of b.
        foreach (['a', 'b', 'c'] as $key) {
            self::assertSame(504, $responses[$key]->status());
            self::assertSame('cURL error 28: Operation timed out', $responses[$key]->body());
        }
    }

    public function testBaseUriPathIsDroppedBecauseTheTrailingSlashIsTrimmed()
    {
        $http = $this->http([new Psr7Response(200), new Psr7Response(200)], 'http://api.test/v1/');
        $http->get('users');
        $http->get('/users');

        // BUG: rtrim() removes the slash Guzzle needs, so "users" resolves to /users instead of /v1/users.
        self::assertSame('http://api.test/v1', $http->getOptions()['base_uri']);
        self::assertSame('http://api.test/users', $this->sentUri(0));
        self::assertSame('http://api.test/users', $this->sentUri(1));
    }
}
