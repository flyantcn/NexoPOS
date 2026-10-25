# Add Store Switcher

**Date:** 2026-10-23
**Type:** Feature
**Module:** NsMultiStore
**Version:** 1.0

## Summary

Phase 4 of the multi store module: a store switcher is displayed on the dashboard header. Employees bound to several stores can switch without logging out, the page reloads with the newly selected store.

## Changes Made

- `Services/StoreSwitcher.php`: renders the switcher (list of the stores the user can use, current store highlighted) and a small script that switches the store through the existing `POST /dashboard/multistore/stores/switch/{store}` endpoint.
- `Providers/NsMultiStoreServiceProvider.php`: registers the `ns-dashboard-header-tools` filter pointing to the switcher.
- `Lang/en.json` and `Lang/zh.json`: `Select Store` translation.

### Core

- `resources/views/common/dashboard-header.blade.php`: adds a single hook slot `{!! \App\Classes\Hook::filter( 'ns-dashboard-header-tools', '' ) !!}` in the header tools so that the module injects its markup without the core being aware of it.

## Migration Required

No.

## Behaviour

- The switcher is shown only when the user has access to more than one store.
- Selecting a store posts to the switch endpoint and reloads the current page, so every list is filtered by the newly selected store (Phase 3).
- Guests and users bound to a single store don't see it.

## Breaking Changes

None.

## Dependencies

None. The switcher is server rendered, no front-end build is required.
