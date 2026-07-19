# Progress

## 2026-05-05
- Created cross-package execution plan for Laravel 13 support release.
- Updated Composer constraints and documentation/changelogs for Laravel 13 support across the target packages.
- Composer validation passed for all seven packages.
- Composer dry-run dependency solving passed for all packages; payment-bill Laravel 13 was verified with a local path repository for the unpublished enum-options 1.4.0.
- Inspected all package composer.json files and git states.
- Some packages already have local composer changes adding Laravel 13 support: apifox-sync, dictionary, enum-options.
- IAM already declares Laravel 13 support in require; only unrelated local settings change is present.
- openobserve, payment-bill, and schedule-monitor-api still need Laravel 13 constraints.
- Need to preserve existing dirty files and avoid reverting user changes.

## 2026-07-19 Alipay fee sign compatibility
- User approved a general PaymentBill package fix and a v1.5.2 release.
- Created isolated worktree `fix/alipay-fee-sign` from clean v1.5.1 main.
- Next step: write failing importer tests covering both raw sign conventions before implementation.
- Added seven importer regression tests; they failed against v1.5.1 because `normalizeServiceFee()` did not exist and summary fees retained the CSV sign.
- Implemented business-type-aware normalization and detail-derived summary fees; all seven new tests now pass.
- Updated changelog and bill operation documentation for the v1.5.2 compatibility fix.
- Package validation passed: Composer metadata valid, PHP syntax valid, targeted tests 7/7, full suite 14 tests with 18 assertions, and `git diff --check` clean.
- The package does not install Pint; using the SettleHub Pint binary on the whole legacy importer reports existing file-wide style differences, so no unrelated bulk formatting was applied.
