---
name: pebble-httpclient
description: How to correctly send HTTP requests, run them in parallel and read their responses using the sopheos/pebble_httpclient PHP library (namespace Pebble\HttpClient — classes Http, Request, Response, Helper and the OptionTrait shared by Http and Request), a thin wrapper around Guzzle 7. Use this whenever the project's composer.json requires sopheos/pebble_httpclient, code imports from Pebble\HttpClient\*, or you're asked to call a REST API, a webhook, a third-party service, to add an auth token, a timeout, query or JSON parameters, to fire several calls in parallel, or to handle an HTTP error or a timeout in a PHP project that has this library available — even if the request is phrased generically like "call this endpoint" or "fetch these URLs concurrently" without naming the library. Also check this before writing raw Guzzle, curl_* or file_get_contents calls in such a project, since this library replaces those and has non-obvious and in places broken behavior (no exceptions by default, network errors become a fake 504 whose body is the error message, all() returns 504 for every request as soon as one fails, header() lowercases the value, the base URI path is dropped, timeout() truncates sub-second values to "no timeout") that hand-rolled code would miss.
---

# pebble-httpclient

`sopheos/pebble_httpclient` is a small PHP 8.1+ layer over Guzzle 7. You configure an `Http` client (base URI, shared headers and options), build `Request` objects (verb, URL, per-request options), send them with `one()` or in parallel with `all()`, and read a plain `Response` (status, lowercased header names, body, decoded JSON). By default nothing throws: HTTP errors come back as responses, and transport errors come back as a synthetic 504.

Namespace: `Pebble\HttpClient\*`. Source lives in `vendor/sopheos/pebble_httpclient/src/`. Read it directly when you need an exact method signature; this skill focuses on *how the pieces fit together* and the behavior that isn't obvious from the method names.

## Orientation

- `Http` is the client. Its options become the `GuzzleHttp\Client` config, so they apply to every request it sends. A new Guzzle client is built on every call.
- `Request` carries a verb, a URL and per-request options. Build it with the static factories `Request::get('/x')`, `Request::post(...)`, etc.
- `OptionTrait` is shared by both: `queryParams()`, `jsonParams()`, `formParams()`, `fileParams()`, `body()`, `timeout()`, `auth()`, `userAgent()`, `addHeader()`, `addOption()` for any raw Guzzle option. Everything is fluent.
- `Response` holds `status()`, `isSuccess()`, `headers()`, `header()`, `body()`, `json()` and `get($key)`, plus HTTP status constants (`Response::NOT_FOUND`…).
- `Helper::arrayLowerKey()` lowercases array keys recursively. `Response` uses it on headers.

For a full method cheat sheet, see `references/api-reference.md`. For the complete list of easy-to-miss behaviors, see `references/gotchas.md`. Read it before debugging a response that "should" look different.

## Core recipes

### Configured client and one request

```php
use Pebble\HttpClient\Http;
use Pebble\HttpClient\Request;
use Pebble\HttpClient\Response;

$http = (new Http('https://api.example.com'))   // no path in the base URI, see below
    ->auth($token)                               // Authorization: Bearer <token>
    ->userAgent('my-app/1.0')
    ->timeout(10);                               // whole seconds only

$response = $http->one(
    Request::post('/v1/users')->jsonParams(['name' => 'Bob'])
);

if ($response->isSuccess()) {
    $id = $response->get('id');                  // from json(), null if missing
} elseif ($response->status() === Response::NOT_FOUND) {
    // ...
}
```

`$http->get('/x')`, `post()`, `put()`, `patch()`, `delete()` and `options()` take a URL only. To send a body, query or per-request header, use `one(Request::...)`.

### Telling an HTTP error from a network error

```php
$response = $http->one(Request::get('/v1/status'));

if (!$response->headers() && $response->status() === Response::GATEWAY_TIMEOUT) {
    // Most likely a transport error (timeout, DNS, refused): body() is Guzzle's message.
}
```

There is no flag for this. A synthetic 504 has no headers, while a real one usually has some. If you need exceptions instead, build the client with `new Http($baseUri, true)`: Guzzle's `ClientException`, `ServerException` and `ConnectException` then propagate.

### Parallel requests

```php
$responses = $http->all([
    'me' => Request::get('/v1/me'),
    'news' => Request::get('/v1/news')->queryParams(['limit' => 5]),
]);
$responses['me']->json();
```

Keys are preserved. A 4xx/5xx only affects its own key. **But a single transport error (timeout…) replaces every response with the same 504**, including the ones that succeeded. If partial results matter, send the calls with `one()` or retry the whole batch.

### Base URI with a path

`new Http('https://api.example.com/v1/')` loses `/v1`: the trailing slash is trimmed, and Guzzle then resolves both `users` and `/users` against the host root. Put the path in each request URL instead:

```php
$http = new Http('https://api.example.com');
$http->one(Request::get('/v1/users'));
```

### Reading headers

```php
$location = $response->headers()['location'][0] ?? null;   // value intact
$response->header('Location');                              // lowercased value: do not use for URLs, ETags, tokens
```

### Tests without network

`Http` passes its options to `new GuzzleHttp\Client(...)`, so a mock handler can be injected:

```php
$stack = GuzzleHttp\HandlerStack::create(new GuzzleHttp\Handler\MockHandler([
    new GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/json'], '{"id":1}'),
]));
$http->addOption('handler', $stack);
```

## Behavior to keep in mind while writing code

- **No exceptions by default.** 4xx/5xx come back as a `Response`. Check `isSuccess()` or `status()`.
- **Network errors become a fake 504** with no headers and Guzzle's error message as the body.
- **`all()` is all-or-nothing on transport errors.** One timeout gives a 504 for every key.
- **`header()` lowercases the value.** Use `headers()[$name][0]` for anything case-sensitive. `header()` also returns only the first value.
- **The base URI path is dropped.** Keep the base URI to scheme + host and put the path in each request.
- **`timeout()` takes an int.** `timeout(0.5)` stores `0`, which means *no* timeout. For sub-second values use `addOption('timeout', 0.5)`.
- **`json()` needs a JSON Content-Type.** Without `application/json` or `application/vnd.api+json` it returns `[]`, as it does for invalid or scalar JSON.
- **`isSuccess()` is 200-208 only.** 226 and 3xx are not successes.
- **Option setters replace, they don't merge.** A second `queryParams()` overwrites the first, and `setOptions()` also wipes the headers. On an `Http`, `setOptions()` wipes `base_uri` and `http_errors` too.
- **Client and request headers are merged.** A request header with the same name wins.

Read `references/gotchas.md` for the rest before assuming the client behaves like raw Guzzle.
