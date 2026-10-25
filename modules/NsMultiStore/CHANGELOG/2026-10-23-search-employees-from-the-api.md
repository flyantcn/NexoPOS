# Search Employees From The API

**Date:** 2026-10-23
**Type:** Feature
**Module:** NsMultiStore
**Version:** 1.0

## Summary

The employee field of the binding form was loading every user of the application at once. It now searches the employees from a dedicated CRUD resource, which keeps the form responsive whether there are 10 or 10.000 employees.

## Changes Made

- `Crud/EmployeeCrud.php`: read only CRUD (`ns.multistore-employees`) used as a search source by the `search-select` fields of the module. The entries expose a `display_name` built from the full name and the username, and the customers sharing the users table are filtered out by their role.
- `Crud/UserStoreCrud.php`: the employee field provides a `search` configuration plus `optionAttributes` instead of a static list of options. The label displayed stays human readable while the id remains what gets submitted.
- `Lang/en.json` and `Lang/zh.json`: translations added for the search hint and the employees list.
- `Tests/Feature/UserStoreBindingTest.php`: coverage for the search, for the exclusion of the customers and for the label exposed by a single employee.

## Migration Required

No.

## Behaviour

- Opening the binding form loads at most 10 employees, the remaining ones are searched as the administrator types.
- While an existing binding is edited, the selected employee is fetched alone and displays the same label.
- Only the users having a role other than `nexopos.store.customer` can be bound to a store.

## Breaking Changes

None. Existing bindings keep working as their `user_id` is left untouched.

## Dependencies

The new CRUD class is cached with the other module CRUD resources, therefore:

```bash
php artisan cache:clear
```
