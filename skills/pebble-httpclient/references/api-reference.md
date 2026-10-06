# pebble-httpclient — API cheat sheet

Quick lookup by intent. This is not exhaustive. Read the source in `vendor/sopheos/pebble_httpclient/src/` for exact signatures and for edge cases not covered here.

## Http (`Pebble\HttpClient\Http`)

`new Http(string $baseUri = "", bool $httpErrors = false)`. The constructor sets the options `base_uri` (with trailing `/` trimmed) and `http_errors`. Uses `OptionTrait`: every option set on `Http` becomes `GuzzleHttp\Client` config and applies to all requests.

| Intent | Method |
| ------ | ------ |
| Send one request | `one(Request $request): Response` |
| Quick call, URL only, no body or options | `get(string $url = "/"): Response`, same for `post`, `put`, `patch`, `delete`, `options` |
| Send several requests concurrently, keys preserved | `all(array $requests): array` (array of `Response`) |

Error handling:

| Situation | `$httpErrors = false` (default) | `$httpErrors = true` |
| --------- | ------------------------------- | -------------------- |
| 4xx / 5xx in `one()` | `Response` with the real status, headers and body | `ClientException` / `ServerException` |
| Too many redirects in `one()` | `Response` of the last redirect | `TooManyRedirectsException` |
| Transport error in `one()` (timeout, DNS, refused) | `Response(504, [], $message)` | `ConnectException` (or other `TransferException`) |
| 4xx / 5xx in `all()` | per-key `Response` | exception |
| Transport error in `all()` | **every key** gets `Response(504, [], $message)` | exception |

## Request (`Pebble\HttpClient\Request`)

| Intent | Method |
| ------ | ------ |
| Build | `new Request(string $method, string $url = "/")` |
| Factories | `Request::head()`, `get()`, `post()`, `put()`, `patch()`, `delete()`, `options()`, all with `string $url = "/"`, returning `static` |
| Read back | `getMethod(): string` / `getUrl(): string` |

Request options are merged over the client options by Guzzle. Headers are merged by name.

## OptionTrait (`Pebble\HttpClient\OptionTrait`, used by Http and Request)

All setters return `static`. Each writes one Guzzle request option and replaces any previous value.

| Intent | Method | Guzzle option |
| ------ | ------ | ------------- |
| Read / replace all options (headers included) | `getOptions(): array` / `setOptions(array $options = [])` | — |
| Any raw option | `addOption(string $name, mixed $value)` / `addOptions(array $options)` | as given |
| Query string | `queryParams(array $data)` | `query` |
| JSON body | `jsonParams(array $data)` | `json` |
| URL-encoded form body | `formParams(array $data)` | `form_params` |
| Multipart body | `fileParams(array $data)` | `multipart` |
| Raw body | `body(mixed $data)` | `body` |
| Total timeout, whole seconds | `timeout(int $seconds)` | `timeout` |
| Read / replace headers | `getHeaders(): array` / `setHeaders(array $headers = [])` | `headers` |
| Add headers | `addHeader(string $name, mixed $value)` / `addHeaders(array $headers = [])` | `headers` |
| `Authorization: {type} {token}` | `auth(string $token, string $type = 'Bearer')` | `headers` |
| `User-Agent` | `userAgent(string $userAgent)` | `headers` |

## Response (`Pebble\HttpClient\Response`)

`new Response(int $status, array $headers, ?string $body)`. Header names are lowercased at construction.

| Intent | Method |
| ------ | ------ |
| Status code | `status(): int` |
| 200-208 | `isSuccess(): bool` |
| All headers, lowercase names, original values | `headers(): array`, shaped `['content-type' => ['application/json']]` |
| First value of one header, case-insensitive name, **lowercased value** | `header(string $name): ?string` |
| Raw body | `body(): ?string` |
| Decoded JSON object/array (cached), `[]` otherwise | `json(): array` |
| One key of `json()` | `get(string $key): mixed` |

Constants: every standard status, from `CONTINUE = 100` to `NETWORK_AUTHENTICATION_REQUIRED = 511` (`OK`, `CREATED`, `NO_CONTENT`, `NOT_FOUND`, `UNPROCESSABLE_ENTITY`, `TOO_MANY_REQUESTS`, `GATEWAY_TIMEOUT`…).

## Helper (`Pebble\HttpClient\Helper`)

| Intent | Method |
| ------ | ------ |
| Lowercase non-numeric keys recursively; non-arrays returned as is | `Helper::arrayLowerKey($data)` |
