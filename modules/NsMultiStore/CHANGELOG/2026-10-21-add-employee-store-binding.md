# Add Employee Store Binding

**Date:** 2026-10-21
**Type:** Feature
**Module:** NsMultiStore
**Version:** 1.0

## Summary

Phase 2 of the multi store module: employees can be bound to one or several stores, the store used for a request is resolved automatically and can be switched manually.

## Changes Made

- `Migrations/2026_10_21_000300_create_nsmultistore_users_stores_relations_table.php`: creates the `nsmultistore_users_stores_relations` table (user, store, role on store, author, uuid) with a unique constraint on the user/store combinaison.
- `Migrations/2026_10_21_000400_create_nsmultistore_users_stores_permissions.php`: creates `nexopos.{create,read,update,delete}.multistore-users-stores` and grants them to the administrator role.
- `Models/UserStoreRelation.php`: binding model with the role namespaces an employee can have on a store.
- `Database/Factories/UserStoreRelationFactory.php`: factory used by the tests.
- `Services/StoreService.php`: resolves the stores of an employee, the store of the current request and persists the selected store on the session.
- `Middleware/ResolveCurrentStore.php`: resolves the current store once the session has started and shares it with the application.
- `Providers/NsMultiStoreServiceProvider.php`: appends the middleware to the "web" and "api" groups and binds `current_store_id` and `current_store`.
- `Crud/UserStoreCrud.php` and `Http/Controllers/UserStoreController.php`: CRUD used to bind an employee to a store.
- `Http/Controllers/StoreController.php`: `switchStore` updates the store used by the current user.
- `Routes/web.php`: `/dashboard/multistore/users-stores`, `/create`, `/edit/{relation}` and `POST /dashboard/multistore/stores/switch/{store}`.
- `Filters/DashboardMenusFilter.php`: adds the employee stores menus.
- `Lang/en.json` and `Lang/zh.json`: module translations.
- `Tests/Feature/StoreSwitchTest.php`, `Tests/Feature/UserStoreBindingTest.php` and `Tests/Traits/WithEmployees.php`: store resolution, store switching, access control and binding coverage.

## Migration Required

```bash
php artisan modules:migrate NsMultiStore
```

## Behaviour

- Administrators and store administrators can use every active store.
- Other employees are limited to the active stores they are bound to.
- A disabled store is never resolved, even when it's stored on the session.
- `app( 'current_store_id' )` returns `null` when no store is resolved, which keeps console commands and scheduled tasks unaffected (used by the `StoreScope` of Phase 3).

## Breaking Changes

None.

## Dependencies

None.
