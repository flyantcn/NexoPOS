# Add Store Permission Matrix

**Date:** 2026-10-24
**Type:** Feature
**Module:** NsMultiStore
**Version:** 1.0

## Summary

Phase 5 of the multi store module: the permission chain now respects the role × store matrix. A store administrator (store manager) is limited to the stores he is bound to, while only the main administrator (the headquarter) can access every store. Store administrators can manage the employees of their own store only.

## Changes Made

### Module

- `Services/StoreService.php`:
  - `hasAccessToAllStores()` now returns true only for the `admin` role, so a store administrator no longer bypasses his bindings.
  - New fine-grained role × store helpers: `getRoleOnStore()`, `getUserRoleBindings()` and `hasRoleOnStore()`.
  - New permission assertions used by the entry points: `canManageStore()`, `assertUserHasAccess()` and `assertCanManageStore()`.
- `Http/Controllers/StoreController.php`: `switchStore` now uses `assertUserHasAccess()`.
- `Http/Controllers/UserStoreController.php`: employee binding routes apply a store-level gate — the main administrator manages every store, a store administrator only the store he manages.
- `Crud/UserStoreCrud.php`: `hook()` limits the bindings listed to the current store (for non administrators) and `assertCanBindToStore()` blocks a store administrator from binding employees to a store he doesn't manage.
- `Migrations/2026_10_24_000100_grant_multistore_users_stores_permissions_to_store_admin.php`: grants the `nexopos.*.multistore-users-stores` permissions to the store administrator role, so the global permission opens the door while the role × store matrix locks it.
- `Tests/Feature/StoreSwitchTest.php`: coverage for the matrix and the assertions.

## Migration Required

```bash
php artisan cache:clear
php artisan modules:migrate NsMultiStore
```

## Behaviour

- The main administrator (`admin`) can switch to and manage every store.
- A store administrator (`nexopos.store.administrator`) only sees and manages the stores he is bound to.
- A cashier is limited to the stores he is bound to and cannot manage any store.

## Breaking Changes

Store administrators previously had access to every store. They are now limited to their bindings, which must be defined on the employee-store binding screen.

## Dependencies

None.
