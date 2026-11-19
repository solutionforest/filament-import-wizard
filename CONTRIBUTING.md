# Contributing

Thanks for helping! The short version:

```bash
composer install
composer check      # Pint + PHPStan level 10 + Pest + security tests + composer audit
composer serve      # try the change in the demo panel: http://127.0.0.1:8765/admin
```

- **Style and types:** Laravel Pint, PHPStan **level 10** with no baseline and no ignores — fix the cause, not the message.
- **Tests:** every behaviour change needs a Pest test. Touching the compiled validation fast path (`src/Support/FastRules.php`)? Also run `composer fuzz` and `composer mutate` ([why](docs/testing.md)).
- **Docs:** user-visible changes update the README / `docs/` and the [CHANGELOG](CHANGELOG.md). New strings go in all four locales (`resources/lang`); a test checks they match.
- **Screenshots, video, share image:** regenerate them after UI changes (`workbench/tools/record-tutorial.js`, `art/tools/render.js`).
- **Commits:** Conventional Commits (`feat:`, `fix:`, `docs:`, `test:`, `ci:`, `perf:`).

Security issues: see [SECURITY.md](SECURITY.md) — do not file them publicly.
