<?php

namespace Modules\NsMultiStore\Filters;

use App\Classes\AsideMenu;

class DashboardMenusFilter
{
    /**
     * Inject the multi store menus within the dashboard menus.
     *
     * @param  array $menus
     * @return array
     */
    public static function register( $menus )
    {
        $multiStoreMenu = AsideMenu::menu(
            label: __m( 'Multi Store', 'NsMultiStore' ),
            identifier: 'ns-multistore',
            icon: 'la-store',
            permissions: [ 'nexopos.read.multistore-stores' ],
            childrens: AsideMenu::childrens(
                AsideMenu::subMenu(
                    label: __m( 'Stores', 'NsMultiStore' ),
                    identifier: 'ns-multistore-stores',
                    href: ns()->route( 'ns.multistore.stores.list' ),
                    permissions: [ 'nexopos.read.multistore-stores' ]
                ),
                AsideMenu::subMenu(
                    label: __m( 'Create Store', 'NsMultiStore' ),
                    identifier: 'ns-multistore-stores-create',
                    href: ns()->route( 'ns.multistore.stores.create' ),
                    permissions: [ 'nexopos.create.multistore-stores' ]
                ),
                AsideMenu::subMenu(
                    label: __m( 'Employee Stores', 'NsMultiStore' ),
                    identifier: 'ns-multistore-users-stores',
                    href: ns()->route( 'ns.multistore.users-stores.list' ),
                    permissions: [ 'nexopos.read.multistore-users-stores' ]
                ),
                AsideMenu::subMenu(
                    label: __m( 'Bind Employee', 'NsMultiStore' ),
                    identifier: 'ns-multistore-users-stores-create',
                    href: ns()->route( 'ns.multistore.users-stores.create' ),
                    permissions: [ 'nexopos.create.multistore-users-stores' ]
                )
            )
        );

        return array_insert_before( $menus, 'settings', $multiStoreMenu );
    }
}
