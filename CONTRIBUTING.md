# Contributing

Thanks for helping. This SDK follows the shared Unicom360 SDK contract in
[`unicom360-openapi`](https://github.com/TechRode-Solution-Limited/unicom360-openapi)
(`docs/sdk-design.md`), and its behaviour is pinned by the conformance fixtures vendored in
`tests/conformance`. A change in behaviour starts in that repository, not here.

## Setup

PHP 8.1 or later with `ext-curl` and `ext-json`, and Composer 2.

```sh
composer install
composer check        # composer validate --strict, php-cs-fixer (dry run), phpstan, phpunit
```

The individual steps:

```sh
vendor/bin/phpunit                         # conformance and unit tests
vendor/bin/phpstan analyse                 # level max: src, examples and tests
vendor/bin/php-cs-fixer fix --dry-run --diff   # PER-CS 2.0
composer validate --strict
grep -rn $'\xe2\x80\x94' . --exclude-dir=vendor --exclude-dir=.git   # must print nothing
```

Never write the em dash character anywhere: code, comments, docs or commit messages.

### Other PHP versions

CI runs PHPUnit on PHP 8.1 to 8.5. To run one version locally in Docker (Composer resolves
PHPUnit 10.5 on PHP 8.1 and 11.5 on later versions):

```sh
docker run --rm -v "$PWD":/src:ro -v "$(command -v composer)":/usr/local/bin/composer:ro php:8.1-cli bash -c '
  apt-get update -qq && apt-get install -y -qq unzip git >/dev/null &&
  cp -r /src /work && cd /work && rm -rf vendor composer.lock &&
  composer install -q && vendor/bin/phpunit'
```

## Conformance fixtures

`tests/conformance` is a pinned copy of `unicom360-openapi/conformance`, written by that
repository's sync script. Never edit it by hand: `tests/ConformanceSuite/FixturesTest.php`
recomputes its sha256 and fails on any change.

To update the fixtures to a new contract version, from a checkout of `unicom360-openapi` at the
new tag:

```sh
scripts/sync-conformance ../unicom360-php/tests/conformance
scripts/sync-conformance --check ../unicom360-php/tests/conformance
```

Then run the tests. The harness runs every vector of every file and fails on a vector or a
fixture file it does not understand, so a new rule shows up as a failing test, never as a
silently skipped one.

## Contract test against Prism

`tests/Contract` sends every `requests.json` call through the real cURL transport to a Prism
mock of `openapi.yaml`, which rejects anything that breaks the spec. It is not part of the
default run:

```sh
docker run --rm -d --name unicom360-php-prism -p 4012:4010 \
  -v /path/to/unicom360-openapi:/spec:ro stoplight/prism:5 \
  mock -h 0.0.0.0 --multiprocess=false --errors /spec/openapi.yaml
vendor/bin/phpunit --group contract
docker stop unicom360-php-prism
```

`UNICOM360_CONTRACT_BASE_URL` changes the base URL (default `http://127.0.0.1:4012`).

There is no sandbox: tests and examples never call the live API.

## Pull requests

- Keep each change focused and covered by a test.
- Update `README.md` and `CHANGELOG.md` (under "Unreleased") when behaviour changes.
- Commit messages are plain sentences; no AI attribution trailers.

## Releasing

Packagist reads versions from git tags through its GitHub hook, so there is no publish
command and `composer.json` has no `version` field.

1. Move the "Unreleased" entries into a new `## [X.Y.Z] - YYYY-MM-DD` section of
   `CHANGELOG.md` and update the links at the bottom.
2. Set `Client::VERSION` to `X.Y.Z` (a test checks it against the newest CHANGELOG section).
3. Commit, then tag and push: `git tag vX.Y.Z && git push origin main vX.Y.Z`.
4. `release.yml` runs the tests and creates the GitHub Release from the CHANGELOG section;
   Packagist updates itself from the tag.
