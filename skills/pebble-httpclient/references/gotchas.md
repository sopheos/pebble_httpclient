# pebble-httpclient — gotchas

Things the method names don't tell you, grouped by class. Every item below is pinned by a test in `tests/`. Items marked **(bug)** are listed in the package's `TODO.md` and may be fixed in a later version. Check the test of the same name in `vendor/sopheos/pebble_httpclient/tests/` to see the current behavior.

## Http — errors

- **No exceptions by default.** With `$httpErrors = false`, a 404 or a 500 comes back as a `Response` carrying the real status, headers and body. (`testErrorStatusIsReturnedAsAResponseByDefault`)
- **`$httpErrors = true` lets Guzzle's exceptions through**: `ClientException` for 4xx, `ServerException` for 5xx, in `one()` and in `all()`. (`testErrorStatusThrowsWhenHttpErrorsIsEnabled`, `testAllThrowsOnErrorStatusWhenHttpErrorsIsEnabled`)
- **Transport errors become a synthetic 504.** A timeout, DNS failure or refused connection returns `Response(504, [], $guzzleMessage)`: no headers, and the body is something like `cURL error 28: …`. It can't be told apart from a real 504 except by the missing headers. (`testTransportErrorBecomesASynthetic504`)
- **With `$httpErrors = true`, transport errors throw** (`ConnectException`). (`testTransportErrorThrowsWhenHttpErrorsIsEnabled`)
- **A per-request `http_errors => true` doesn't make `one()` throw.** The `BadResponseException` is caught and turned back into a `Response`, because the client-level flag decides. (`testPerRequestHttpErrorsStillReturnsAResponse`)
- **Too many redirects returns the last redirect response** (e.g. a 302) instead of throwing. (`testTooManyRedirectsReturnsTheLastRedirect`)

## Http — all()

- **Keys are preserved, and a 4xx/5xx only affects its own key.** (`testAllKeepsTheRequestKeys`)
- **An empty array returns an empty array.** (`testAllWithNoRequestsReturnsAnEmptyArray`)
- **(bug) One transport error turns every response into the same 504.** `Utils::unwrap()` throws on the first rejected promise, and the catch fills all keys with `Response(504, [], $message)`. Successful responses are lost, and you can't tell which request failed. (`testAllTurnsEveryResponseInto504WhenOneRequestFails`)

## Http — configuration

- **(bug) The path of the base URI is dropped.** The constructor `rtrim`s the trailing `/`, and RFC 3986 resolution then replaces the last segment: `new Http('http://api.test/v1/')` sends both `get('users')` and `get('/users')` to `http://api.test/users`. Keep the base URI to scheme + host. (`testBaseUriPathIsDroppedBecauseTheTrailingSlashIsTrimmed`)
- **Client options apply to every request, and client headers are merged with request headers.** An `auth()` on `Http` and an `addHeader()` on the `Request` are both sent. (`testClientHeadersAreMergedWithRequestHeaders`)
- **`get()`, `post()`… on `Http` take a URL only.** There is no body or options parameter. Use `one(Request::post($url)->jsonParams(...))`. (`testShortcutsSendTheMatchingVerb`)

## Request / OptionTrait

- **The default URL is `/`.** (`testDefaultUrlIsSlash`)
- **Setters replace, they don't merge.** A second `queryParams()` overwrites the first. (`testAddOptionOverwritesTheSameKey`)
- **`setOptions()` replaces everything, headers included.** On an `Http`, it also wipes `base_uri` and `http_errors`. (`testSetOptionsReplacesEverything`, `testSetOptionsWipesBaseUriAndHttpErrors`)
- **`setHeaders()` replaces all headers.** (`testSetHeadersReplacesAllHeaders`)
- **`auth($token, '')` sends the bare token** (`Authorization: tok`), useful for APIs that don't want a scheme. (`testAuthWithEmptyTypeSendsTheBareToken`)
- **(bug) `timeout()` is typed `int`.** `timeout(0.5)` stores `0`, which Guzzle reads as *no timeout at all*. Use `addOption('timeout', 0.5)`. (`testSubSecondTimeoutIsTruncatedToZero`)

## Response

- **Header names are lowercased, and `header()` looks them up case-insensitively.** A missing header gives `null`. (`testHeaderNamesAreLowercasedAndLookupIsCaseInsensitive`)
- **(bug) `header()` lowercases the value too.** A `Location` of `https://Api.test/Users/AbC?Token=XyZ` comes back as `https://api.test/users/abc?token=xyz`, and an ETag `"AbC"` as `"abc"`. `headers()` keeps values intact: use `headers()['location'][0]`. (`testHeaderLowercasesTheValue`)
- **`header()` returns only the first value** of a multi-value header like `Set-Cookie`. (`testHeaderReturnsOnlyTheFirstValue`)
- **`header()` expects array values.** A hand-built `new Response(200, ['X-A' => 'abc'], '')` makes `header('x-a')` return `'a'`. Guzzle always gives arrays. (`testHeaderGivenAsStringReturnsItsFirstCharacter`)
- **`json()` only decodes JSON content types** (`application/json`, `application/vnd.api+json`, any case). Without a Content-Type, or with `text/plain`, it returns `[]` even for a valid JSON body. (`testJsonIsDecodedForJsonContentTypes`, `testJsonIsEmptyWithoutAJsonContentType`)
- **`json()` returns `[]` for scalar JSON, invalid JSON or a null body.** There is no error to tell "empty" from "unreadable". (`testJsonIsEmptyForScalarsInvalidJsonOrNullBody`)
- **`isSuccess()` is true for 200-208 only.** 226 and every 3xx are not successes. (`testIsSuccessOnlyForListed2xx`)

## Helper

- **`arrayLowerKey()` lowercases keys only, recursively**, keeps numeric keys and returns non-arrays as is. (`testArrayLowerKeyIsRecursiveAndKeepsValues`)
