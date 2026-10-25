# Add Store Visibility Rules

**Date:** 2026-10-25
**Type:** Feature
**Module:** NsMultiStore
**Version:** 1.0

## Summary

The data isolation now follows the store type: a chain store shares its data with the other chain stores of its brand (cross-store transfer), while a franchise store stays strongly isolated from its siblings and only reports aggregates to its headquarter. A single store only sees itself.

## Changes Made

### Module

- `Scopes/StoreScope.php`: filters on the visible store ids (`whereIn`) instead of a single store id.
- `Services/StoreService.php`:
  - `computeVisibleStoreIds()`: resolves the stores visible for a given store. A single or franchise store only sees itself; a chain store also sees the active chain stores sharing its parent (or, for a headquarter, its chain children — never its franchise children).
  - `defineVisibleStoreIds()` / `getVisibleStoreIds()`: hold the visible store ids for the current request.
  - `defineCurrentStore()` now also computes the visible store ids, keeping the existing call sites backward compatible.
- `Providers/NsMultiStoreServiceProvider.php`: binds `visible_store_ids` to the container.
- `Middleware/ResolveCurrentStore.php`: no extra change required, `defineCurrentStore()` now resolves the visible stores.
- `Tests/Feature/StoreVisibilityTest.php`: covers the visibility rules per store type.
- `Tests/Feature/StoreScopeTest.php`: verifies a chain store actually sees its sibling store records.

## Migration Required

No migration. Run the tests and clear the cache:

```bash
php artisan cache:clear
```

## Behaviour

- Single store: sees itself only.
- Chain store: sees itself plus the active chain stores of its brand (cross-store transfer).
- Franchise store: sees itself only (strong isolation); its headquarter only receives aggregated reports, not raw rows.

## Breaking Changes

None. The default store type remains unchanged.

## Dependencies

None.
