# CLAUDE.md — pebble_httpclient

Ce fichier guide Claude Code quand il **maintient** cette librairie. Pour l'**utiliser** depuis un projet, voir le skill [`skills/pebble-httpclient/`](skills/pebble-httpclient/SKILL.md).

## Rôle

`sopheos/pebble_httpclient`, namespace `Pebble\HttpClient\`, PHP >= 8.1, dépend de `guzzlehttp/guzzle` ^7.9 et de `ext-mbstring`. La lib fournit :
- un client `Http` qui envoie une requête (`one()`, `get()`, `post()`…) ou plusieurs en parallèle (`all()`) ;
- un builder `Request` (verbe, URL, options Guzzle) ;
- une `Response` immuable (statut, en-têtes en minuscules, corps, JSON décodé).

Par défaut (`$httpErrors = false`), aucune exception ne remonte : les 4xx/5xx sont des `Response`, et les erreurs de transport (timeout, DNS, connexion refusée) deviennent une `Response` 504 synthétique dont le corps est le message d'erreur.

## Commandes

```bash
composer install
vendor/bin/phpunit            # toute la suite
vendor/bin/phpunit --filter HttpTest
```

## Carte de `src/`

| Fichier | Rôle |
|---|---|
| `Http.php` | Client. Ses options deviennent la config du `GuzzleHttp\Client` (recréé à chaque appel). `one()`, raccourcis par verbe, `all()` parallèle via `Utils::unwrap()`, conversion des exceptions en `Response` |
| `Request.php` | Verbe + URL + options par requête. Fabriques statiques `head()`, `get()`, `post()`, `put()`, `patch()`, `delete()`, `options()` |
| `OptionTrait.php` | Options Guzzle partagées par `Http` et `Request` : `addOption()`, `queryParams()`, `jsonParams()`, `formParams()`, `fileParams()`, `body()`, `timeout()`, en-têtes, `auth()`, `userAgent()` |
| `Response.php` | Constantes de statut, `status()`, `isSuccess()`, `headers()`, `header()`, `body()`, `json()`, `get()` |
| `Helper.php` | `arrayLowerKey()` : met les clés en minuscules, récursivement |

## Tests

- PHPUnit 9.5. Aucun appel réseau : les tests de `Http` injectent un `GuzzleHttp\Handler\MockHandler` via `$http->addOption('handler', $stack)`, et un middleware `Middleware::history()` pour inspecter la requête envoyée.
- Les classes de test n'ont pas de namespace. Les méthodes s'appellent `testPhraseEnCamelCase`, les assertions passent par `self::assertSame`, et des bannières `// ----` séparent les sections.
- Une erreur de transport se simule en mettant une `GuzzleHttp\Exception\ConnectException` dans la file du `MockHandler`.

## Conventions du code

Respecter le style existant, sans le « moderniser » au passage :
- pas de `declare(strict_types=1)` ;
- constantes de classe sans visibilité ;
- méthodes fluides typées `: static` ;
- bannières `// ----` entre les groupes de méthodes.

Une modification de comportement doit être répercutée dans `skills/pebble-httpclient/` (SKILL.md, `references/api-reference.md`, `references/gotchas.md`) et dans le `README.md`.

## Bugs connus

Ils sont listés dans [`TODO.md`](TODO.md). Chacun est **figé par un test** annoté `// BUG:` qui vérifie le comportement *actuel*, dans la section « Known bugs » de `tests/HttpTest.php`, `tests/RequestTest.php` ou `tests/ResponseTest.php`.

Pour corriger un bug :
1. Corriger `src/`.
2. Réécrire le test `// BUG:` pour qu'il vérifie le comportement attendu.
3. Mettre à jour l'entrée « (bug) » de `skills/pebble-httpclient/references/gotchas.md` et le SKILL.md.
4. Retirer l'entrée de `TODO.md` (il ne liste que ce qui reste à faire).
