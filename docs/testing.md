# Testing & how the fast validation path is verified

The compiled fast path ([Performance](performance.md)) is only worth having if it can **never** change an answer. It is built to be conservative — it
only ever says "certainly passes", and anything doubtful goes to Laravel — and it is verified in layers, all run against Laravel's real validator:

| Layer | What it proves | Where |
| --- | --- | --- |
| **Differential fuzz** | For random rule sets (supported rules, rules only Laravel can do, rule objects, closures, odd parameters, in any order and combination) and a pool of ~170 hand-picked hostile values (blank / whitespace / unicode / numeric look-alikes / boundary lengths / RFC e-mail oddities / arrays / objects / INF / NAN), *fast-path + Laravel-for-the-rest* gives the same verdict as Laravel alone — including "throws" | `tests/Fuzz/DifferentialTest.php` |
| **Generated inputs** | Same, for generated strings (random glyph mixes) and generated numbers (ints, floats, numeric strings of every magnitude, exponent forms) | same |
| **Exhaustive tables** | Every single rule × every pool value, and every *pair* of rules × values (thinned in the default run), including rules the fast path must not compile | `tests/Fuzz/PipelineTest.php` |
| **Boundaries** | E-mail addresses at every length boundary (local part, each label, domain, total, TLD); every numeric-looking string for `numeric` / `integer` with `min` / `max`, incl. 15-digit precision edges; the field *absent* from the data | `tests/Fuzz/DifferentialTest.php` |
| **Whole pipeline** | Whole rows through `RowValidator` with **messages, custom attribute names and column casts** (integer / numeric / boolean): identical output with and without the fast path | `tests/Fuzz/PipelineTest.php` |
| **Real import** | The same CSV imported with and without `ValidatesQuickly` records identical rows and identical failed-row messages | same |
| **Cross-field & custom** | `same`, `required_with`, `confirmed`, data-aware rule objects, importers overriding `validateData()` stay with Laravel | `tests/FastRulesTest.php`, `DifferentialTest.php` |
| **E-mail soundness** | 40,000 generated addresses: the fast e-mail check never accepts one `email:rfc` rejects | `tests/FastRulesTest.php` |
| **Mutation test** | 27 deliberately broken versions of the fast path (off-by-one limits, loose comparisons, removed guards, …) — the suite must fail for each | `workbench/tools/mutation-test.php` |

## Continuous integration

`.github/workflows/ci.yml` runs on every push and pull request:

| Job | What |
| --- | --- |
| **Code style** | `composer lint` (Pint) |
| **PHPStan level 10** | `composer analyse` (Larastan) |
| **Pest matrix** | the full suite on PHP 8.2 / 8.3 / 8.4 × Laravel 12 / 13 (the combinations each Laravel supports), incl. the fuzz suites at their quick size |
| **Security** | `pest --group=security` — XSS escaping, locked wizard state, out-of-order calls, importer-option whitelisting, chunk-upload confinement (traversal, allow-listed types, owner-only), approval / audit authorisation — plus `composer audit` for known vulnerabilities in dependencies |

`.github/workflows/deep-checks.yml` runs weekly (and on demand): the 300,000-case differential fuzz, the mutation test (at most the 2 documented equivalent survivors)
and the 50,000-row stress test. Dependabot opens weekly PRs for Composer and Actions updates.

To run every job on your own runners set the repository variable `CI_RUNNER` (e.g. `self-hosted`); it defaults to GitHub-hosted Ubuntu. `composer check` runs the
lint, analysis, tests and security checks locally in one go.

## Running

```bash
vendor/bin/pest                # normal run — includes the fuzz suites at a quick size (~45 s total)
composer fuzz                  # 300,000 random cases per run, thicker exhaustive tables
IMPORT_WIZARD_FUZZ_CASES=2000000 vendor/bin/pest tests/Fuzz   # as large as you like
composer mutate                # mutation test (about 30 s per mutant)
composer stress                # 50,000-row stress test
```

Fuzzing is seeded, so a failure reproduces; the failing rule set, value and both verdicts are printed.

## What it found

Writing this suite found **three real bugs** in the first version of the fast path, all fixed and now pinned by tests:

1. An **empty array** passed `required` (Laravel rejects it) — non-scalar values now always go to Laravel.
2. Some **long e-mail addresses** were accepted that Laravel's RFC validator rejects (it starts rejecting domains well below the nominal limit) — the fast e-mail check now stays far inside the safe region.
3. **`INF` / exponent / over-precise numbers** with `numeric` + `min` / `max`: Laravel compares them with arbitrary precision and throws where the fast path passed — numeric limits are now decided only for plain decimals of at most 15 significant digits.

## Results of the large run

A one-off run of **~1.6 million randomized comparisons** (900,000 random rule sets × values, 450,000 generated strings, 225,000 generated numbers) found **zero** mismatches, on top of
the exhaustive tables (over 50,000 checks per run), the e-mail and numeric boundary tables and 40,000 e-mail fuzz cases. Of the 27 mutants, **25 are killed**; the 2 survivors are
*equivalent mutants* (they change no behaviour): the e-mail *total-length* guard is redundant next to the domain guard, and Laravel accepts long local parts, so the local-part guard is
merely conservative.

## What this does not prove

Random testing and mutation testing raise confidence a lot, but they cannot prove the absence of a bug. The design limits the blast radius: a wrong answer is possible only
if the fast path says "passes" where Laravel would say "fails", and only for the small set of rules it implements. If you want zero risk for a particular importer, don't add
`ValidatesQuickly`, or turn the Review fast path off with `->fastValidation(false)` on the plugin. Please also run `composer fuzz` after upgrading Laravel: a new Laravel
release can change a rule's semantics, and this suite is what will notice.
