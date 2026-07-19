# Laravel 13 Support Release Plan

## Goal
Update the listed Packagist Laravel extension packages to support Laravel 13 and release each with a minor version bump.

## Packages
- /Users/oran/Documents/Coding/Packagist/laravel-apifox-sync
- /Users/oran/Documents/Coding/Packagist/laravel-dictionary
- /Users/oran/Documents/Coding/Packagist/laravel-enum-options
- /Users/oran/Documents/Coding/Packagist/laravel-iam
- /Users/oran/Documents/Coding/Packagist/laravel-openobserve
- /Users/oran/Documents/Coding/Packagist/laravel-payment-bill
- /Users/oran/Documents/Coding/Packagist/laravel-schedule-monitor-api

## Phases
- [complete] Inspect composer constraints, git status, and latest local tags
- [complete] Update Laravel 13 dependency constraints and related dev tooling
- [complete] Validate Composer metadata and dependency solving
- [pending] Commit, tag minor releases, and push
- [pending] Final report

# Alipay Fee Sign Compatibility Fix

## Goal
Normalize Alipay fees to the package-wide business convention regardless of source CSV sign convention: payments positive, refunds negative.

## Phases
- [complete] Confirm production evidence and obtain user approval for a general package change.
- [complete] Add importer regression tests for both source sign conventions.
- [complete] Implement business-type-aware fee normalization and detail-derived summaries.
- [in_progress] Run package tests, update changelog, commit, tag v1.5.2, and push.
- [pending] Update SettleHub dependency and repair production history.

## Compatibility boundary
- Keep the database/API contract unchanged; only canonicalize `service_fee_amount` values.
- Do not reference SettleHub models or paths from the package.
- Accept both legacy Alipay source convention (payment negative/refund positive) and current convention (payment positive/refund negative).
