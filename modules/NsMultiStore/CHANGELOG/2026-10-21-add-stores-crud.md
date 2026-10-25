# Add Stores CRUD

**Date:** 2026-10-21
**Type:** Feature
**Module:** NsMultiStore
**Version:** 1.0

## Summary

Phase 1 of the multi store module: stores can now be created, listed, edited and deleted from the dashboard.

## Changes Made

- `Migrations/2026_10_21_000100_create_nsmultistore_stores_table.php`: creates the `nsmultistore_stores` table (name, unique code, type, status, headquarter, description, author, uuid).
- `Migrations/2026_10_21_000200_create_nsmultistore_stores_permissions.php`: creates `nexopos.{create,read,update,delete}.multistore-stores` and grants them to the administrator role.
- `Models/Store.php`: store model with the supported types (`single`, `chain`, `franchise`) and statuses (`active`, `disabled`).
- `Database/Factories/StoreFactory.php`: factory used by the tests.
- `Crud/StoreCrud.php`: CRUD definition (columns, form, links, row actions, bulk delete).
- `Http/Controllers/StoreController.php` and `Routes/web.php`: `/dashboard/multistore/stores`, `/create` and `/edit/{store}`.
- `Providers/NsMultiStoreServiceProvider.php` and `Filters/DashboardMenusFilter.php`: injects the "Multi Store" menu before the settings menu.
- `Lang/en.json` and `Lang/zh.json`: module translations.
- `Tests/Feature/StoreEnableModuleTest.php` and `Tests/Feature/StoreCreateTest.php`: module discovery, migration and store creation coverage.

## Migration Required

```bash
php artisan modules:migrate NsMultiStore
```

## Breaking Changes

None.

## Dependencies

None.
