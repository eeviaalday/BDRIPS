<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * What each role can reach, in one place.
 *
 * The navigation used to be an if/else chain inside layouts/navigation.blade.php
 * with the link lists written inline. That put the permission model in a view,
 * where it cannot be asserted, and it gave Secretary and Admin an identical list
 * because they were written once and both fell through to the same branch.
 *
 * This is that list as data, and the Secretary/Admin split is explicit.
 *
 * available() filters to routes that are actually registered. The areas are
 * being built in phases, and a navigation link to a route that does not exist
 * yet is a fatal error rather than a dead link - route() throws. Filtering
 * means Phase 1 renders only the destinations that work, and each later phase
 * needs no navigation change at all: adding the route is enough for the link to
 * appear. defined() returns the whole intended list regardless, so the
 * specification can be asserted in full today and the rendered output checked
 * separately against what exists.
 */
final class RoleNavigation
{
    /**
     * The staff sidebar, per role.
     *
     * `group` separates the destinations from the account group so they can be
     * rendered as two lists rather than one undifferentiated run of links.
     *
     * @return list<array{route: string, label: string, icon: string, group: string}>
     */
    public static function sidebarFor(string $role): array
    {
        $account = self::accountLink();

        return match ($role) {
            // A Kagawad gets their OWN dashboard route. Pointing them at
            // staff.dashboard would be a 403: that route is role:admin,secretary,
            // so the link would render and then refuse.
            User::ROLE_KAGAWAD => [
                ['route' => 'kagawad.dashboard', 'label' => 'Dashboard', 'icon' => 'grid', 'group' => 'main'],
                ['route' => 'kagawad.relief', 'label' => 'Relief Distribution', 'icon' => 'gift', 'group' => 'main'],
                $account,
            ],

            // A Kagawad may not see the resident queue, document requests, or
            // anything about the system, so this list is deliberately short.
            User::ROLE_SECRETARY => [
                self::staffDashboardLink(),
                ['route' => 'staff.verifications.index', 'label' => 'Resident Verification', 'icon' => 'id-badge', 'group' => 'main'],
                ['route' => 'staff.residents.index', 'label' => 'Residents', 'icon' => 'users', 'group' => 'main'],
                ['route' => 'staff.requests.index', 'label' => 'Document Requests', 'icon' => 'document', 'group' => 'main'],
                ['route' => 'staff.batches.index', 'label' => 'Relief Distribution', 'icon' => 'gift', 'group' => 'main'],
                ['route' => 'staff.announcements.index', 'label' => 'Announcements', 'icon' => 'megaphone', 'group' => 'main'],
                $account,
            ],

            // Admin can reach everything, so it is the Secretary's list plus the
            // five destinations that are Admin-only. Written out in full rather
            // than as a merge, so removing one from the Secretary list is a
            // visible edit here instead of a silent one.
            User::ROLE_ADMIN => [
                self::staffDashboardLink(),
                ['route' => 'staff.verifications.index', 'label' => 'Resident Verification', 'icon' => 'id-badge', 'group' => 'main'],
                ['route' => 'staff.residents.index', 'label' => 'Residents', 'icon' => 'users', 'group' => 'main'],
                ['route' => 'staff.requests.index', 'label' => 'Document Requests', 'icon' => 'document', 'group' => 'main'],
                ['route' => 'staff.batches.index', 'label' => 'Relief Distribution', 'icon' => 'gift', 'group' => 'main'],
                ['route' => 'staff.announcements.index', 'label' => 'Announcements', 'icon' => 'megaphone', 'group' => 'main'],
                ['route' => 'staff.accounts.index', 'label' => 'User Accounts', 'icon' => 'users', 'group' => 'admin'],
                ['route' => 'staff.sectors.index', 'label' => 'Sectors & Kagawad', 'icon' => 'folder', 'group' => 'admin'],
                ['route' => 'staff.settings.index', 'label' => 'Settings', 'icon' => 'settings', 'group' => 'admin'],
                ['route' => 'staff.audit.index', 'label' => 'Audit Logs', 'icon' => 'shield-check', 'group' => 'admin'],
                ['route' => 'staff.status.index', 'label' => 'System Status', 'icon' => 'chart', 'group' => 'admin'],
                $account,
            ],

            default => [],
        };
    }

    /**
     * The resident's top navigation bar, and the contents of its profile menu.
     *
     * A resident gets the public design plus their two portal destinations, and
     * a profile menu rather than two more bars' worth of items. There is no
     * Dashboard entry: a resident's home is the public home page.
     *
     * @return array{primary: list<array{route: string, label: string}>, profile: list<array{route: string, label: string}>}
     */
    public static function residentBar(): array
    {
        return [
            'primary' => [
                ['route' => 'home', 'label' => 'Home', 'pattern' => 'home'],
                ['route' => 'services.index', 'label' => 'Services', 'pattern' => 'services.*'],
                ['route' => 'about', 'label' => 'About', 'pattern' => 'about'],
                ['route' => 'announcements.index', 'label' => 'Announcements', 'pattern' => 'announcements.*'],
                ['route' => 'contact', 'label' => 'Contact', 'pattern' => 'contact'],
                // A request's detail page is still "in" Document Requests, so the
                // pattern is a prefix rather than the exact name.
                ['route' => 'resident.requests.index', 'label' => 'Document Requests', 'pattern' => 'resident.requests.*'],
                ['route' => 'resident.relief', 'label' => 'Relief Transparency', 'pattern' => 'resident.relief'],
            ],
            'profile' => [
                ['route' => 'account.edit', 'label' => 'Edit Profile', 'pattern' => 'account.edit'],
                ['route' => 'account.password', 'label' => 'Change Password', 'pattern' => 'account.password'],
                ['route' => 'resident.verification', 'label' => 'Verification Status', 'pattern' => 'resident.verification'],
                ['route' => 'logout', 'label' => 'Log out', 'pattern' => 'logout'],
            ],
        ];
    }

    /**
     * The resident bar filtered to destinations that exist, which is the same
     * rule the staff sidebar uses.
     *
     * Log out is never filtered: it is a POST form, not a link, and it is the
     * one entry that must always be there.
     *
     * @return array{primary: list<array{route: string, label: string}>, profile: list<array{route: string, label: string}>}
     */
    public static function availableResidentBar(): array
    {
        $bar = self::residentBar();

        $filter = fn (array $items): array => array_values(array_filter(
            $items,
            fn (array $item): bool => $item['route'] === 'logout' || Route::has($item['route'])
        ));

        return ['primary' => $filter($bar['primary']), 'profile' => $filter($bar['profile'])];
    }

    /**
     * The staff sidebar for a role, filtered to destinations that are registered.
     *
     * @return list<array{route: string, label: string, icon: string, group: string}>
     */
    public static function availableSidebarFor(string $role): array
    {
        return array_values(array_filter(
            self::sidebarFor($role),
            fn (array $link): bool => Route::has($link['route'])
        ));
    }

    /**
     * The staff sidebar split into its two groups, after filtering.
     *
     * @return array{main: list<array{route: string, label: string, icon: string, group: string}>, account: list<array{route: string, label: string, icon: string, group: string}>}
     */
    public static function availableSidebarGroupsFor(string $role): array
    {
        $links = self::availableSidebarFor($role);

        return [
            'main' => array_values(array_filter($links, fn (array $l): bool => $l['group'] === 'main')),
            'account' => array_values(array_filter($links, fn (array $l): bool => $l['group'] !== 'main')),
        ];
    }

    /**
     * Whether a link is the current page, honouring the extra patterns a
     * destination declares so a detail page still lights its parent.
     *
     * @param  array{route: string, label?: string, icon?: string, group?: string}  $link
     */
    public static function isCurrent(array $link): bool
    {
        return request()->routeIs($link['route']);
    }

    /**
     * @return array{route: string, label: string, icon: string, group: string}
     */
    private static function staffDashboardLink(): array
    {
        return ['route' => 'staff.dashboard', 'label' => 'Dashboard', 'icon' => 'grid', 'group' => 'main'];
    }

    /**
     * @return array{route: string, label: string, icon: string, group: string}
     */
    private static function accountLink(): array
    {
        return ['route' => 'account.edit', 'label' => 'My Account', 'icon' => 'user', 'group' => 'account'];
    }
}
