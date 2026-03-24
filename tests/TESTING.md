# CreatorReactor Test Suites

This plugin now uses PHPUnit with Brain Monkey for fast isolated tests without booting a full WordPress runtime.

## Suite layout

- `tests/unit`
  - Pure logic tests with direct input/output assertions.
  - No database, no network, no WordPress boot.
  - Focus: deterministic helpers and mapping logic.

- `tests/regression`
  - Behavior-lock tests for historically fragile flows.
  - Uses mocked WordPress functions to assert branching behavior and prevent regressions during refactors.
  - Focus: editor context detection and request-path decisions.

## Framework stack

- PHPUnit 13
- Brain Monkey 2.7
- Mockery 1.6

## Commands

- `composer test`
- `composer test:unit`
- `composer test:regression`

## Next expansions

- Add regression tests for `Admin_Settings::sanitize_options()` (mode switching and OAuth input handling).
- Add regression tests for dashboard connection state rendering decisions.
- Add unit tests for `Entitlements::tier_filter_match_values()` via a public wrapper or extraction to dedicated helper.
- Add integration tests in a separate suite once a WP test environment is added (database-backed entitlement queries and migrations).
