# Pebble/HttpClient

Client HTTP pour PHP 8.1+. Wrapper de Guzzle.

La lib construit des requêtes, les envoie une par une ou en parallèle, et renvoie des réponses simples à lire. Par défaut, elle ne lève aucune exception : une erreur HTTP (4xx/5xx) est une réponse comme une autre, et une erreur réseau (timeout, DNS, connexion refusée) devient une réponse 504.

## Installation

```bash
composer require sopheos/pebble_httpclient
```

## Claude Code

Ce package fournit un skill Claude Code dans [`skills/pebble-httpclient/`](skills/pebble-httpclient/). Il documente les patterns d'usage et les pièges de la librairie : 504 synthétique sur erreur réseau, `all()` qui perd toutes les réponses si une seule échoue, `header()` qui met la valeur en minuscules, chemin de `base_uri` perdu, etc.

Dans un projet qui dépend de `sopheos/pebble_httpclient`, copie-le une fois dans `.claude/skills/` après `composer install` pour que Claude Code le charge automatiquement. Le nom du dossier doit correspondre au `name` déclaré dans `SKILL.md` :

```bash
cp -r vendor/sopheos/pebble_httpclient/skills/pebble-httpclient .claude/skills/pebble-httpclient
```

Pour la maintenance de la lib elle-même, voir [`CLAUDE.md`](CLAUDE.md). Les bugs connus sont listés dans [`TODO.md`](TODO.md).

## Http

`\Pebble\HttpClient\Http` est le client. Ses options (voir [Options](#options)) s'appliquent à toutes les requêtes qu'il envoie.

* `__construct(string $baseUri = "", bool $httpErrors = false)` URL de base et gestion des erreurs. Le `/` final de `$baseUri` est retiré, ce qui fait perdre son chemin (voir [`TODO.md`](TODO.md)).
* `one(Request $request) : Response` Envoie une requête.
* `get(string $url = "/") : Response` Raccourci sans option ni corps. Idem pour `post()`, `put()`, `patch()`, `delete()` et `options()`.
* `all(Request[] $requests) : Response[]` Envoie les requêtes en parallèle. Les clés du tableau sont conservées.

Gestion des erreurs :

* `$httpErrors = false` (défaut) : une réponse 4xx/5xx est renvoyée telle quelle. Une erreur de transport renvoie une `Response` 504 sans en-tête dont le corps est le message d'erreur Guzzle. Dans `all()`, une seule erreur de transport fait renvoyer ce 504 pour **toutes** les requêtes.
* `$httpErrors = true` : les exceptions Guzzle (`ClientException`, `ServerException`, `ConnectException`…) remontent, dans `one()` comme dans `all()`.

```php
use Pebble\HttpClient\Http;
use Pebble\HttpClient\Request;

$http = (new Http('https://api.example.com'))
    ->auth($token)
    ->timeout(10);

$response = $http->one(Request::post('/users')->jsonParams(['name' => 'Bob']));

if ($response->isSuccess()) {
    $id = $response->get('id');
}

$responses = $http->all([
    'me' => Request::get('/me'),
    'news' => Request::get('/news')->queryParams(['limit' => 5]),
]);
```

## Request

`\Pebble\HttpClient\Request` porte le verbe, l'URL et les options propres à une requête. Ces options sont fusionnées avec celles du client, et les en-têtes s'ajoutent à ceux du client.

* `__construct(string $method, string $url = "/")`
* `head()`, `get()`, `post()`, `put()`, `patch()`, `delete()`, `options()` Fabriques statiques, avec `string $url = "/"`.
* `getMethod() : string` / `getUrl() : string`

## Options

Méthodes communes à `Http` et `Request` (trait `OptionTrait`). Elles sont toutes chaînables. Elles écrivent des [options de requête Guzzle](https://docs.guzzlephp.org/en/stable/request-options.html), et un second appel remplace la valeur précédente.

* `getOptions() : array` / `setOptions(array $options = []) : static` Lit ou remplace toutes les options, en-têtes compris.
* `addOption(string $name, mixed $value) : static` / `addOptions(array $options) : static` Option Guzzle brute (`verify`, `connect_timeout`, `allow_redirects`, `handler`…).
* `queryParams(array $data) : static` Query string (`query`).
* `jsonParams(array $data) : static` Corps JSON (`json`).
* `formParams(array $data) : static` Corps `application/x-www-form-urlencoded` (`form_params`).
* `fileParams(array $data) : static` Corps multipart (`multipart`).
* `body(mixed $data) : static` Corps brut (`body`).
* `timeout(int $seconds) : static` Timeout total. Un flottant est tronqué : `timeout(0.5)` donne `0`, soit aucun timeout.
* `getHeaders() : array` / `setHeaders(array $headers = []) : static` / `addHeaders(array $headers = []) : static` / `addHeader(string $name, mixed $value) : static`
* `auth(string $token, string $type = 'Bearer') : static` En-tête `Authorization`.
* `userAgent(string $userAgent) : static` En-tête `User-Agent`.

## Response

`\Pebble\HttpClient\Response` expose aussi les constantes de statut (`Response::OK`, `Response::NOT_FOUND`, `Response::GATEWAY_TIMEOUT`…).

* `status() : int`
* `isSuccess() : bool` Vrai pour 200 à 208. Faux pour 226, 3xx, 4xx et 5xx.
* `headers() : array` Tous les en-têtes, noms en minuscules, valeurs intactes : `['content-type' => ['application/json']]`.
* `header(string $name) : ?string` Première valeur d'un en-tête, nom insensible à la casse. **Attention** : la valeur est renvoyée en minuscules. Pour un `Location`, un `ETag` ou un token, lire `headers()[$name][0]`.
* `body() : ?string` Corps brut.
* `json() : array` Corps décodé, seulement si le Content-Type contient `application/json` ou `application/vnd.api+json`. Sinon, ou si le JSON est invalide ou scalaire : `[]`.
* `get(string $key) : mixed` Une clé de `json()`, ou `null`.

## Helper

* `Helper::arrayLowerKey($data)` Met les clés non numériques en minuscules, récursivement. Les valeurs ne sont pas modifiées.

## Tests

```bash
composer install
vendor/bin/phpunit
```

Aucun appel réseau : les tests injectent un `MockHandler` Guzzle via `addOption('handler', …)`. Les bugs connus sont figés par des tests annotés `// BUG:` qui vérifient le comportement actuel.
