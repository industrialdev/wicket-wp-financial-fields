# Repository Guidelines

## What This Plugin Is

Wicket Financial Fields is a WordPress/WooCommerce plugin for finance mapping and deferred revenue tracking. It extends WooCommerce with GL code mapping, revenue deferral scheduling, and dynamic membership term date management.

Dependencies: WordPress 6.0+, PHP 8.3+, WooCommerce 10.0+, Wicket Base Plugin (active), Wicket Memberships plugin (active).

For architecture (service layer, data model, key workflows, hooks and filters, important behavior notes, testing setup): `@see atlas://packages/wicket-wp-financial-fields.md`.

## Development Commands

```bash
composer install
composer test              # Pest suite
composer test:unit         # unit tests only
composer test:coverage     # HTML coverage report
composer test:browser      # requires https://localhost
composer lint              # PHP CS Fixer check (dry-run)
composer format            # auto-fix code style
composer check             # lint + test
```

## Coding Standards

- PHP 8.3+, `declare(strict_types=1);`, PSR-12, namespace `Wicket\Finance\`.
- Singleton entry (`Wicket\Finance\Plugin::get_instance()`); PSR-4 service classes under `src/`.
- Use WooCommerce CRUD methods (HPOS compatible); sanitize input, escape output, nonces on admin actions.

## Non-Negotiable Behavior Rules

- Validation: end date >= start date (blocks save with an error).
- Audit: every line item date change (manual or system) creates an order note with user and timestamp.
- Dynamic dates always trigger on Processing regardless of the configurable trigger set.
- Membership creation overwrites line item dates with the authoritative post meta.

## Release & Branch Workflow

All work happens on branches. `main` is locked; changes land via peer-reviewed Pull Request. Never commit to `main` directly, and never push or open a PR without explicit human approval.

Merging a PR to `main` **auto-releases** via the `wicket-release-bot` GitHub App: version bump, `CHANGELOG.md` update, git tag. Never bump versions or create tags by hand. The bump level comes from a marker in the PR title (squash-merge makes it the commit message): _(none)_ / `#patch` = patch, `#minor`, `#major`, or `#norelease` (no release; use for docs/tooling-only merges). Conventional commit prefixes (`feat:`, `fix:`, `docs:`, ...) drive changelog grouping; a `!` (e.g. `feat!:`) flags a BREAKING change.
