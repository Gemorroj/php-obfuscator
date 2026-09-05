# AGENTS.md

PHP obfuscator library. Requires PHP >= 8.4. Sources in `src/`, tests in `tests/`.

- Entry: `src/PhpObfuscator.php`, modules in `src/Module/` implement `ModuleInterface`.
- Style: `declare(strict_types=1);`, PSR-12. Use `token_get_all()`, keep output runnable.
- Commands: `composer install`, `vendor/bin/phpunit`, `vendor/bin/phpstan analyse`, `PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix`.
- Tests: update `tests/` on logic change, verify obfuscated code via `eval`.
