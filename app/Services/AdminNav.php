<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;

/** The admin sidebar: modules, their links and the permission each needs. */
final class AdminNav
{
    /** @return list<array{title:string, items:list<array{label:string,href:string,icon:string,perm:string|list<string>}>}> */
    public static function groups(): array
    {
        return [
            ['title' => 'Overview', 'items' => [
                ['label' => 'Dashboard', 'href' => '/admin', 'icon' => 'home', 'perm' => 'dashboard.view'],
            ]],
            ['title' => 'Website', 'items' => [
                ['label' => 'Pages', 'href' => '/admin/pages', 'icon' => 'file-text', 'perm' => 'pages.view'],
                ['label' => 'Builder templates', 'href' => '/admin/builder/templates', 'icon' => 'layout', 'perm' => 'pages.edit'],
                ['label' => 'Cities', 'href' => '/admin/cities', 'icon' => 'map-pin', 'perm' => 'pages.view'],
                ['label' => 'Navigation', 'href' => '/admin/navigation', 'icon' => 'navigation', 'perm' => 'navigation.manage'],
                ['label' => 'Media library', 'href' => '/admin/media', 'icon' => 'image', 'perm' => 'media.view'],
                ['label' => 'Import site', 'href' => '/admin/site-import', 'icon' => 'upload', 'perm' => 'pages.create'],
            ]],
            ['title' => 'Catalogue', 'items' => [
                ['label' => 'Products', 'href' => '/admin/products', 'icon' => 'package', 'perm' => 'products.view'],
                ['label' => 'Categories & brands', 'href' => '/admin/products/categories', 'icon' => 'tag', 'perm' => 'products.edit'],
            ]],
            ['title' => 'Blog', 'items' => [
                ['label' => 'Articles', 'href' => '/admin/blog', 'icon' => 'pen', 'perm' => 'blog.view'],
                ['label' => 'Categories & tags', 'href' => '/admin/blog/categories', 'icon' => 'folder', 'perm' => ['blog.categories', 'blog.tags']],
            ]],
            ['title' => 'Sales', 'items' => [
                ['label' => 'Leads', 'href' => '/admin/leads', 'icon' => 'inbox', 'perm' => 'leads.view'],
                ['label' => 'Pipeline', 'href' => '/admin/pipeline', 'icon' => 'kanban', 'perm' => 'leads.view'],
                ['label' => 'Customers', 'href' => '/admin/customers', 'icon' => 'briefcase', 'perm' => 'customers.view'],
                ['label' => 'Reports', 'href' => '/admin/reports', 'icon' => 'chart', 'perm' => 'leads.view'],
            ]],
            ['title' => 'Marketing', 'items' => [
                ['label' => 'Forms', 'href' => '/admin/forms', 'icon' => 'list', 'perm' => 'forms.view'],
                ['label' => 'Popups', 'href' => '/admin/popups', 'icon' => 'layers', 'perm' => 'marketing.manage'],
                ['label' => 'Lead magnets', 'href' => '/admin/lead-magnets', 'icon' => 'gift', 'perm' => 'marketing.manage'],
                ['label' => 'Tracking & tags', 'href' => '/admin/marketing', 'icon' => 'activity', 'perm' => 'marketing.manage'],
            ]],
            ['title' => 'SEO', 'items' => [
                ['label' => 'SEO settings', 'href' => '/admin/seo', 'icon' => 'search', 'perm' => 'seo.manage'],
                ['label' => 'Redirects', 'href' => '/admin/redirects', 'icon' => 'arrow-up-right', 'perm' => 'seo.manage'],
            ]],
            ['title' => 'Settings', 'items' => [
                ['label' => 'Website settings', 'href' => '/admin/settings', 'icon' => 'settings', 'perm' => 'settings.manage'],
                ['label' => 'Countries', 'href' => '/admin/countries', 'icon' => 'globe', 'perm' => 'countries.manage'],
                ['label' => 'Email templates', 'href' => '/admin/email-templates', 'icon' => 'mail', 'perm' => 'settings.manage'],
            ]],
            ['title' => 'Team', 'items' => [
                ['label' => 'Staff', 'href' => '/admin/staff', 'icon' => 'users', 'perm' => 'staff.manage'],
                ['label' => 'Roles & permissions', 'href' => '/admin/staff/roles', 'icon' => 'shield', 'perm' => 'staff.manage'],
                ['label' => 'Audit log', 'href' => '/admin/audit', 'icon' => 'history', 'perm' => 'audit.view'],
            ]],
            ['title' => 'System', 'items' => [
                ['label' => 'Backup & restore', 'href' => '/admin/backups', 'icon' => 'database', 'perm' => 'backup.view'],
                ['label' => 'Recycle bin', 'href' => '/admin/trash', 'icon' => 'trash-restore', 'perm' => ['pages.delete', 'products.delete', 'blog.delete', 'forms.delete', 'leads.delete']],
            ]],
        ];
    }

    /** Groups filtered to what the signed-in user may open. */
    public static function visible(): array
    {
        $out = [];
        foreach (self::groups() as $group) {
            $items = array_values(array_filter($group['items'], static function (array $item): bool {
                return is_array($item['perm']) ? Auth::canAny($item['perm']) : Auth::can($item['perm']);
            }));
            if ($items !== []) {
                $out[] = ['title' => $group['title'], 'items' => $items];
            }
        }
        return $out;
    }

    public static function isActive(string $href, string $path): bool
    {
        if ($href === '/admin') {
            return $path === '/admin';
        }
        return $path === $href || str_starts_with($path, $href . '/');
    }
}
