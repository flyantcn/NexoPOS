# Add Store Scope And Data Isolation

**Date:** 2026-10-23
**Type:** Feature
**Module:** NsMultiStore
**Version:** 1.0

## Summary

Phase 3 of the multi store module: the 8 core tables are isolated by store using a Laravel global scope. When a store is resolved for a request, every read is automatically limited to that store and every new record is automatically assigned to it.

## Changes Made

### Module

- `Scopes/StoreScope.php`: global scope adding `where(table.store_id, current_store_id)` whenever a store is resolved. Returns all records when `app('current_store_id')` is null (console commands and scheduled tasks).
- `Traits/StoreScoped.php`: marks a model as owned by a store. It registers the `StoreScope` and assigns the current store to new records during the `creating` event.
- `Migrations/2026_10_23_000100_add_store_id_to_core_tables.php`: adds a nullable indexed `store_id` column to the 8 core tables.
- `Tests/Feature/StoreScopeTest.php`: coverage for the column, the read filtering, the write assignment, the explicit store override and the "no store" behaviour.

### Core (minimal changes allowed by Phase 3)

The `StoreScoped` trait is applied to the 8 core models:

- `app/Models/Order.php`
- `app/Models/OrderProduct.php`
- `app/Models/ProductUnitQuantity.php`
- `app/Models/Register.php`
- `app/Models/RegisterHistory.php`
- `app/Models/Transaction.php`
- `app/Models/Procurement.php`
- `app/Models/ProductHistory.php`

`app/Services/ProcurementService.php`: `persistProcurement` assigns `store_id` manually because the procurement is saved inside `withoutEvents`, where the `creating` event (used by the trait) never runs.

## Isolated Tables

| Table | Purpose |
|---|---|
| `nexopos_orders` | Sales orders |
| `nexopos_orders_products` | Order lines |
| `nexopos_products_unit_quantities` | Stock quantities |
| `nexopos_registers` | Cash registers |
| `nexopos_registers_history` | Register actions |
| `nexopos_transactions` | Accounting transactions |
| `nexopos_procurements` | Purchases |
| `nexopos_products_histories` | Stock movements |

## Migration Required

```bash
php artisan cache:clear
php artisan modules:migrate NsMultiStore
```

## Behaviour

- On a dashboard request, only the records of the current store are returned.
- New records are stamped with the current store id.
- With no current store, no filter is applied and no store is assigned.

## Breaking Changes

Existing records keep a `null` store id until Phase 6 migrates them to their store. They are only visible when no store is resolved.

## Dependencies

None.
