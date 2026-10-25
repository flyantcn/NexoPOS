# Backfill Legacy Data With Store Id

**Date:** 2026-10-25
**Type:** Feature
**Module:** NsMultiStore
**Version:** 1.0

## Summary

Phase 6 of the multi store module: the legacy rows created before the `store_id` column existed are assigned to the main store so they remain visible once the store scope is active.

## Changes Made

### Module

- `Migrations/2026_10_24_000200_backfill_existing_data_with_store_id.php`: assigns every `store_id` null row of the 8 isolated tables to the main store (the first active store, otherwise the first store ever created). It is a no-op when no store exists yet.
- `Tests/Feature/DataBackfillTest.php`: coverage for the backfill, the main store resolution and the "no store" behaviour.

## Migration Required

```bash
php artisan cache:clear
php artisan modules:migrate NsMultiStore
```

## Behaviour

- Legacy rows (`store_id` null) are stamped with the main store id.
- Without any store, the rows are left untouched and remain globally visible.

## Breaking Changes

None. Rows explicitly assigned to the main store after the backfill are also reset by the rollback, because the migration cannot distinguish them from the backfilled ones.

## Dependencies

Requires the `store_id` columns added in Phase 3.
