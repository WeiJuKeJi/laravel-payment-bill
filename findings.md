# Findings

## Initial assumptions
- Laravel 13 Illuminate packages exist on Packagist and require PHP ^8.3.
- Testbench 11.x targets Laravel 13.
- Packages should keep Laravel 10-12 support unless a dependency forces otherwise.

## Composer inspection
- laravel-apifox-sync: require already includes ^13.0; require-dev includes testbench ^11.0 and phpunit ^11.0; dirty README.md and composer.json pre-existed.
- laravel-dictionary: require includes Laravel 13 but require-dev only testbench ^9.0/phpunit ^11.0.
- laravel-enum-options: require includes Laravel 13 but require-dev only testbench ^8.0|^9.0/phpunit ^10.0.
- laravel-iam: require includes Laravel 13; dev tooling only testbench ^9.0/phpunit ^11.0.
- laravel-openobserve: require only Laravel 11/12; dev testbench only ^9.0|^10.0.
- laravel-payment-bill: require only Laravel 10/11/12; dev testbench only ^8.0|^9.0/phpunit ^10.0.
- laravel-schedule-monitor-api: require only Laravel 12; dev testbench only ^10.0.

## Alipay fee sign compatibility
- The unconditional sign inversion was introduced by commit `1c61e60` based on a single assumed Alipay CSV convention.
- Production contains two source conventions. A general importer must use `biz_type`, not the incoming sign, as the business meaning.
- Canonical package convention matches WeChat: payment fee positive, refund fee negative.
- `bill_summary_fee_amount` should be derived from normalized detail totals after import, because summary CSV sign conventions can also vary.

## Validation
- Laravel 13 Illuminate packages resolve to v13.7.0 on the current PHP 8.4 runtime.
- Testbench 11.x resolves with Laravel 13.
- spatie/laravel-schedule-monitor 4.3.0 supports Laravel 13.
- spatie/laravel-permission 7.4.1 supports Laravel 13.
- kalnoy/nestedset v7 supports Laravel 13 only; v6 supports up to Laravel 12, so laravel-iam uses ^6.0 || ^7.0.
