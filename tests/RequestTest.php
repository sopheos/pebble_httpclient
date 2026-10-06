<?php

use Pebble\HttpClient\Request;
use PHPUnit\Framework\TestCase;

class RequestTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Factories
    // -------------------------------------------------------------------------

    /**
     * @dataProvider verbProvider
     */
    public function testFactoriesSetTheVerb(string $factory, string $expected)
    {
        $request = Request::{$factory}('/path');

        self::assertSame($expected, $request->getMethod());
        self::assertSame('/path', $request->getUrl());
    }

    public static function verbProvider(): array
    {
        return [
            'head' => ['head', 'HEAD'],
            'get' => ['get', 'GET'],
            'post' => ['post', 'POST'],
            'put' => ['put', 'PUT'],
            'patch' => ['patch', 'PATCH'],
            'delete' => ['delete', 'DELETE'],
            'options' => ['options', 'OPTIONS'],
        ];
    }

    public function testDefaultUrlIsSlash()
    {
        self::assertSame('/', Request::get()->getUrl());
    }

    // -------------------------------------------------------------------------
    // OptionTrait
    // -------------------------------------------------------------------------

    public function testParamHelpersMapToGuzzleOptions()
    {
        $request = Request::post()
            ->queryParams(['q' => 1])
            ->jsonParams(['a' => 2])
            ->formParams(['b' => 3])
            ->fileParams([['name' => 'f', 'contents' => 'x']])
            ->body('raw')
            ->timeout(5);

        self::assertSame([
            'query' => ['q' => 1],
            'json' => ['a' => 2],
            'form_params' => ['b' => 3],
            'multipart' => [['name' => 'f', 'contents' => 'x']],
            'body' => 'raw',
            'timeout' => 5,
        ], $request->getOptions());
    }

    public function testAddOptionOverwritesTheSameKey()
    {
        $request = Request::get()->queryParams(['a' => 1])->queryParams(['b' => 2]);

        self::assertSame(['query' => ['b' => 2]], $request->getOptions());
    }

    public function testSetOptionsReplacesEverything()
    {
        $request = Request::get()->timeout(5)->auth('t')->setOptions(['verify' => false]);

        self::assertSame(['verify' => false], $request->getOptions());
        self::assertSame([], $request->getHeaders());
    }

    public function testHeaderHelpers()
    {
        $request = Request::get()
            ->auth('abc')
            ->userAgent('Pebble')
            ->addHeaders(['X-A' => '1']);

        self::assertSame([
            'Authorization' => 'Bearer abc',
            'User-Agent' => 'Pebble',
            'X-A' => '1',
        ], $request->getHeaders());
    }

    public function testAuthWithEmptyTypeSendsTheBareToken()
    {
        self::assertSame(['Authorization' => 'tok'], Request::get()->auth('tok', '')->getHeaders());
    }

    public function testSetHeadersReplacesAllHeaders()
    {
        $request = Request::get()->auth('abc')->setHeaders(['X-B' => '2']);

        self::assertSame(['X-B' => '2'], $request->getHeaders());
    }

    // -------------------------------------------------------------------------
    // Known bugs (see TODO.md)
    // -------------------------------------------------------------------------

    public function testSubSecondTimeoutIsTruncatedToZero()
    {
        $request = @Request::get()->timeout(0.5);

        // BUG: timeout(int) truncates 0.5 to 0, and 0 means "no timeout" for Guzzle.
        self::assertSame(['timeout' => 0], $request->getOptions());
    }
}
