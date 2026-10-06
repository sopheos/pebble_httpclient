# TODO — pebble_httpclient

Problèmes restant à traiter, détectés lors de l'audit du 2026-10-06. Le code `src/` n'a **pas** été modifié. Chaque bug est figé par un test qui vérifie le comportement actuel : il faut l'adapter au moment de la correction.

## Bugs

- [ ] **`Response::header()` met la valeur en minuscules.** `src/Response.php:118`.
  - `mb_strtolower()` est appliqué à la valeur, pas seulement au nom. Un `Location` `https://Api.test/Users/AbC?Token=XyZ` revient en `https://api.test/users/abc?token=xyz`, un `ETag` `"AbC"` en `"abc"`. Les URL de redirection, ETag, tokens et identifiants lus par `header()` sont corrompus. `headers()` renvoie, lui, les valeurs intactes.
  - Correctif : `return $this->headers[$name][0];`. `parseJson()` compare alors le Content-Type avec `str_contains()`, il faut donc lui passer `mb_strtolower($this->header('Content-Type'))` pour garder la détection insensible à la casse.
  - Test : `tests/ResponseTest.php::testHeaderLowercasesTheValue`.
- [ ] **`all()` renvoie 504 pour toutes les requêtes dès qu'une seule échoue.** `src/Http.php:140, 152-163`.
  - `Utils::unwrap()` lève à la première promesse rejetée (timeout, DNS…). Le `catch` remplit alors **toutes** les clés avec `Response(504, [], message)`, même celles qui ont réussi. Leurs réponses sont perdues, et on ne sait pas quelle requête a échoué. Les 4xx/5xx ne sont pas concernés quand `$httpErrors = false`, car Guzzle ne rejette pas la promesse.
  - Correctif : utiliser `Utils::settle($promises)->wait()` puis convertir chaque résultat : `fulfilled` donne la réponse, `rejected` passe par les mêmes conversions que `one()` (`BadResponseException` vers sa réponse, `TransferException` vers 504).
  - Test : `tests/HttpTest.php::testAllTurnsEveryResponseInto504WhenOneRequestFails`.
- [ ] **Le chemin d'une `base_uri` est perdu.** `src/Http.php:23`.
  - `rtrim($baseUri, '/')` retire le slash final dont Guzzle (RFC 3986) a besoin pour résoudre une URL relative. Avec `new Http('http://api.test/v1/')`, `get('users')` et `get('/users')` appellent tous deux `http://api.test/users`, sans le `/v1`.
  - Correctif : ne pas retirer le slash final, ou en ajouter un (`rtrim($baseUri, '/') . '/'` si non vide). Il faut alors documenter qu'une URL de requête doit être relative (`users`, pas `/users`) pour garder le chemin.
  - Test : `tests/HttpTest.php::testBaseUriPathIsDroppedBecauseTheTrailingSlashIsTrimmed`.
- [ ] **`timeout()` tronque les valeurs inférieures à la seconde à 0, c'est-à-dire « pas de timeout ».** `src/OptionTrait.php:63`.
  - Le paramètre est typé `int`. `timeout(0.5)` émet une dépréciation et stocke `0`, ce qui désactive le timeout dans Guzzle. Le résultat est l'inverse de l'intention.
  - Correctif : typer `float $seconds`.
  - Test : `tests/RequestTest.php::testSubSecondTimeoutIsTruncatedToZero`.

## Dette / qualité

- [ ] `src/Http.php:89, 160` : une erreur de transport devient une `Response` 504 synthétique, impossible à distinguer d'un vrai 504 du serveur. Il faudrait au minimum un indicateur (`Response::isTransportError()` ou un statut 0).
- [ ] `src/Http.php:152` : `all()` attrape `Exception`, donc aussi les erreurs de programmation (option invalide…), transformées en 504. `one()` n'attrape que `TransferException`.
- [ ] `src/Http.php` : les raccourcis `get()`, `post()`… n'acceptent que l'URL. Pour envoyer un corps ou des options, il faut passer par `one(Request::post(...)->jsonParams(...))`.
- [ ] `src/Http.php:31` : un `GuzzleHttp\Client` est recréé à chaque appel, donc aucune réutilisation de connexion entre deux `one()`.
- [ ] `src/Http.php` : `$httpErrors` est stocké à part de l'option `http_errors`. `setOptions()` ou `addOption('http_errors', …)` les désynchronise.
- [ ] `src/Response.php:92` : `isSuccess()` liste les 2xx à la main et oublie `226 IM_USED`.
- [ ] `src/Response.php:112` : `header()` suppose une valeur tableau. Avec `['X-A' => 'abc']` passé à la main, il renvoie `'a'`.
- [ ] `src/Response.php` : `json()` renvoie `[]` sans Content-Type JSON, ou pour un JSON scalaire ou invalide, sans distinguer « vide » et « illisible ».
- [ ] `src/Http.php:123` : le docblock `@param Request[]` n'a pas de nom de variable.
- [ ] Pas d'option `connect_timeout` dédiée (passer par `addOption()`).
