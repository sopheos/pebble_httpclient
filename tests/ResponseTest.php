<?php

use Pebble\HttpClient\Helper;
use Pebble\HttpClient\Response;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    private function json(string $body, string $contentType = 'application/json'): Response
    {
        return new Response(200, ['Content-Type' => [$contentType]], $body);
    }

    // -------------------------------------------------------------------------
    // Status
    // -------------------------------------------------------------------------

    public function testIsSuccessOnlyForListed2xx()
    {
        self::assertTrue((new Response(200, [], ''))->isSuccess());
        self::assertTrue((new Response(204, [], ''))->isSuccess());
        self::assertFalse((new Response(226, [], ''))->isSuccess());
        self::assertFalse((new Response(304, [], ''))->isSuccess());
        self::assertFalse((new Response(404, [], ''))->isSuccess());
    }

    // -------------------------------------------------------------------------
    // Headers
    // -------------------------------------------------------------------------

    public function testHeaderNamesAreLowercasedAndLookupIsCaseInsensitive()
    {
        $response = new Response(200, ['X-Request-Id' => ['42']], '');

        self::assertSame(['x-request-id' => ['42']], $response->headers());
        self::assertSame('42', $response->header('X-REQUEST-ID'));
        self::assertNull($response->header('X-Missing'));
    }

    public function testHeaderReturnsOnlyTheFirstValue()
    {
        $response = new Response(200, ['Set-Cookie' => ['a=1', 'b=2']], '');

        self::assertSame('a=1', $response->header('set-cookie'));
        self::assertSame(['a=1', 'b=2'], $response->headers()['set-cookie']);
    }

    public function testHeaderGivenAsStringReturnsItsFirstCharacter()
    {
        $response = new Response(200, ['X-A' => 'abc'], '');

        self::assertSame('a', $response->header('x-a'));
    }

    // -------------------------------------------------------------------------
    // JSON
    // -------------------------------------------------------------------------

    public function testJsonIsDecodedForJsonContentTypes()
    {
        self::assertSame(['a' => 1], $this->json('{"a":1}')->json());
        self::assertSame(['a' => 1], $this->json('{"a":1}', 'Application/JSON; charset=UTF-8')->json());
        self::assertSame(['a' => 1], $this->json('{"a":1}', 'application/vnd.api+json')->json());
        self::assertSame(1, $this->json('{"a":1}')->get('a'));
        self::assertNull($this->json('{"a":1}')->get('b'));
    }

    public function testJsonIsEmptyWithoutAJsonContentType()
    {
        self::assertSame([], (new Response(200, [], '{"a":1}'))->json());
        self::assertSame([], $this->json('{"a":1}', 'text/plain')->json());
    }

    public function testJsonIsEmptyForScalarsInvalidJsonOrNullBody()
    {
        self::assertSame([], $this->json('"x"')->json());
        self::assertSame([], $this->json('{oops')->json());
        self::assertSame([], (new Response(200, ['Content-Type' => ['application/json']], null))->json());
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    public function testArrayLowerKeyIsRecursiveAndKeepsValues()
    {
        self::assertSame(
            ['a' => ['b' => 'VaLuE'], 0 => 'X'],
            Helper::arrayLowerKey(['A' => ['B' => 'VaLuE'], 0 => 'X'])
        );
        self::assertSame('NotAnArray', Helper::arrayLowerKey('NotAnArray'));
    }

    // -------------------------------------------------------------------------
    // Known bugs (see TODO.md)
    // -------------------------------------------------------------------------

    public function testHeaderLowercasesTheValue()
    {
        $response = new Response(201, [
            'Location' => ['https://Api.test/Users/AbC?Token=XyZ'],
            'ETag' => ['"AbC"'],
        ], '');

        // BUG: header() lowercases the value, corrupting URLs, ETags and tokens.
        self::assertSame('https://api.test/users/abc?token=xyz', $response->header('Location'));
        self::assertSame('"abc"', $response->header('ETag'));
        self::assertSame(['"AbC"'], $response->headers()['etag']);
    }
}
