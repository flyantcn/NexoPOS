<?php

namespace Modules\NsMultiStore\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Modules\NsMultiStore\Models\Store;

/**
 * Renders the store switcher displayed on the dashboard header.
 *
 * The markup is injected through the "ns-dashboard-header-tools" filter, so
 * no core view is aware of the module. The switching itself reuses the
 * already implemented "switchStore" endpoint.
 */
class StoreSwitcher
{
    /**
     * Append the switcher to the header tools.
     */
    public static function render( string $html ): string
    {
        $user = Auth::user();

        if ( ! $user instanceof User ) {
            return $html;
        }

        /**
         * @var StoreService $storeService
         */
        $storeService = app()->make( StoreService::class );

        $stores = $storeService->getUserStores( $user );
        $current = $storeService->getCurrentStore();

        /**
         * A switcher is pointless when the user has access to a single store
         * or to no store at all.
         */
        if ( $stores->count() <= 1 ) {
            return $html;
        }

        return $html . self::buildSwitcher( $stores, $current );
    }

    /**
     * Build the markup and the small script handling the switch.
     *
     * @param Collection<int, Store> $stores
     */
    protected static function buildSwitcher( $stores, ?Store $current ): string
    {
        $currentName = $current instanceof Store ? $current->name : __m( 'Select Store', 'NsMultiStore' );
        $currentId = $current instanceof Store ? $current->id : null;
        $options = '';

        foreach ( $stores as $store ) {
            $active = $store->id === $currentId ? ' bg-info-primary text-white' : '';
            $options .= '<button type="button" class="ns-store-option flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-info-primary' . $active . '" data-store-id="' . $store->id . '">'
                . '<i class="las la-store"></i>'
                . '<span>' . e( $store->name ) . '</span>'
                . '</button>';
        }

        $switchBaseUrl = ns()->url( '/dashboard/multistore/stores/switch' );
        $csrfToken = csrf_token();

        return '
        <div class="px-2" id="ns-store-switcher">
            <div class="relative">
                <button type="button" id="ns-store-switcher-toggle" class="flex items-center gap-2 border py-2 px-3 rounded-lg cursor-pointer hover:shadow-lg hover:border-opacity-0 text-sm">
                    <i class="las la-store text-lg"></i>
                    <span id="ns-store-switcher-current">' . e( $currentName ) . '</span>
                    <i class="las la-angle-down text-xs"></i>
                </button>
                <div id="ns-store-switcher-menu" class="hidden absolute right-0 z-50 mt-1 w-56 bg-box-background border rounded-lg shadow-lg overflow-hidden">
                    ' . $options . '
                </div>
            </div>
        </div>
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                const switcher = document.getElementById("ns-store-switcher");
                const toggle = document.getElementById("ns-store-switcher-toggle");
                const menu = document.getElementById("ns-store-switcher-menu");

                if (! switcher || ! toggle || ! menu) {
                    return;
                }

                toggle.addEventListener("click", function (event) {
                    event.stopPropagation();
                    menu.classList.toggle("hidden");
                });

                document.addEventListener("click", function (event) {
                    if (! switcher.contains(event.target)) {
                        menu.classList.add("hidden");
                    }
                });

                menu.querySelectorAll(".ns-store-option").forEach(function (option) {
                    option.addEventListener("click", function () {
                        const storeId = option.getAttribute("data-store-id");
                        const token = document.querySelector("meta[name=\"csrf-token\"]")?.getAttribute("content") || "' . $csrfToken . '";

                        fetch("' . $switchBaseUrl . '" + "/" + storeId, {
                            method: "POST",
                            credentials: "same-origin",
                            headers: {
                                "X-CSRF-TOKEN": token,
                                "Accept": "application/json"
                            }
                        }).then(function (response) {
                            if (response.ok) {
                                window.location.reload();
                            } else {
                                response.json().then(function (body) {
                                    alert(body.message || "Unable to switch store.");
                                }).catch(function () {
                                    alert("Unable to switch store.");
                                });
                            }
                        }).catch(function () {
                            alert("Unable to switch store.");
                        });
                    });
                });
            });
        </script>';
    }
}
