<?php

declare(strict_types=1);

namespace App\Core;

/**
 * The permission catalogue and the built-in roles. The database stores which
 * role holds which permission; this file is the source of truth for what
 * exists, used by the seeder and by the role editor.
 */
final class Permissions
{
    public const ALL = [
        'dashboard.view' => ['dashboard', 'View dashboard'],

        'pages.view' => ['pages', 'View pages'],
        'pages.create' => ['pages', 'Create pages'],
        'pages.edit' => ['pages', 'Edit pages'],
        'pages.delete' => ['pages', 'Delete pages'],
        'pages.publish' => ['pages', 'Publish pages'],
        'builder.code' => ['pages', 'Add custom HTML and CSS in the visual builder'],

        'products.view' => ['products', 'View products'],
        'products.create' => ['products', 'Create products'],
        'products.edit' => ['products', 'Edit products'],
        'products.delete' => ['products', 'Delete products'],

        'leads.view' => ['leads', 'View leads'],
        'leads.create' => ['leads', 'Create leads'],
        'leads.edit' => ['leads', 'Edit leads'],
        'leads.delete' => ['leads', 'Delete leads'],
        'leads.export' => ['leads', 'Export leads'],
        'leads.assign' => ['leads', 'Assign leads'],
        'leads.manageConsent' => ['leads', 'Manage consent records and notices'],

        'customers.view' => ['customers', 'View customers'],
        'customers.create' => ['customers', 'Create customers'],
        'customers.edit' => ['customers', 'Edit customers'],
        'customers.delete' => ['customers', 'Delete customers'],

        'blog.view' => ['blog', 'View blog posts'],
        'blog.create' => ['blog', 'Create blog posts'],
        'blog.edit' => ['blog', 'Edit blog posts'],
        'blog.delete' => ['blog', 'Delete blog posts'],
        'blog.publish' => ['blog', 'Publish blog posts'],
        'blog.categories' => ['blog', 'Manage blog categories'],
        'blog.tags' => ['blog', 'Manage blog tags'],
        'blog.design' => ['blog', 'Manage blog design settings'],

        'forms.view' => ['forms', 'View forms'],
        'forms.create' => ['forms', 'Create forms'],
        'forms.edit' => ['forms', 'Edit forms'],
        'forms.delete' => ['forms', 'Delete forms'],

        'media.view' => ['media', 'View media'],
        'media.upload' => ['media', 'Upload media'],
        'media.edit' => ['media', 'Edit media metadata'],
        'media.delete' => ['media', 'Delete media'],

        'backup.view' => ['backup', 'View backups'],
        'backup.create' => ['backup', 'Create backups'],
        'backup.download' => ['backup', 'Download backups'],
        'backup.restore' => ['backup', 'Restore from a backup'],
        'backup.delete' => ['backup', 'Delete backups'],
        'backup.settings' => ['backup', 'Configure backup schedule'],

        'user.mfa.reset' => ['security', "Reset another user's authenticator"],

        'seo.manage' => ['seo', 'Manage SEO and redirects'],
        'marketing.manage' => ['marketing', 'Manage marketing and tracking'],
        'navigation.manage' => ['navigation', 'Manage navigation menus'],
        'settings.manage' => ['settings', 'Manage website settings'],
        'countries.manage' => ['settings', 'Manage countries and markets'],
        'staff.manage' => ['staff', 'Manage staff and roles'],
        'audit.view' => ['audit', 'View audit log'],
    ];

    public const GROUP_LABELS = [
        'dashboard' => 'Dashboard', 'pages' => 'Pages', 'products' => 'Products', 'leads' => 'Leads',
        'customers' => 'Customers', 'blog' => 'Blog', 'forms' => 'Forms', 'media' => 'Media', 'seo' => 'SEO',
        'marketing' => 'Marketing', 'navigation' => 'Navigation', 'settings' => 'Settings', 'staff' => 'Staff',
        'audit' => 'Audit log', 'backup' => 'Backup & restore', 'security' => 'Account security',
    ];

    /** @return list<array{slug:string,name:string,description:string,rank:int,permissions:list<string>}> */
    public static function systemRoles(): array
    {
        $all = array_keys(self::ALL);
        return [
            [
                'slug' => 'super-admin',
                'name' => 'Super Admin',
                'description' => 'Unrestricted access to every part of the platform.',
                'rank' => 0,
                'permissions' => $all,
            ],
            [
                'slug' => 'admin',
                'name' => 'Admin',
                'description' => 'Full administrative access except staff, restore, backup deletion and MFA resets.',
                'rank' => 10,
                'permissions' => array_values(array_diff($all, ['staff.manage', 'backup.restore', 'backup.delete', 'user.mfa.reset'])),
            ],
            [
                'slug' => 'sales',
                'name' => 'Sales',
                'description' => 'Works leads and customers. Read-only on marketing content.',
                'rank' => 30,
                'permissions' => [
                    'dashboard.view', 'leads.view', 'leads.create', 'leads.edit', 'leads.export', 'leads.assign',
                    'customers.view', 'customers.create', 'customers.edit', 'products.view', 'forms.view',
                    'pages.view', 'blog.view', 'media.view',
                ],
            ],
            [
                'slug' => 'content-marketing',
                'name' => 'Content & Marketing',
                'description' => 'Owns the website, blog, media, SEO and marketing configuration.',
                'rank' => 30,
                'permissions' => [
                    'dashboard.view', 'pages.view', 'pages.create', 'pages.edit', 'pages.delete', 'pages.publish',
                    'blog.view', 'blog.create', 'blog.edit', 'blog.delete', 'blog.publish', 'blog.categories',
                    'blog.tags', 'blog.design', 'products.view', 'products.create', 'products.edit',
                    'forms.view', 'forms.create', 'forms.edit', 'media.view', 'media.upload', 'media.edit',
                    'media.delete', 'seo.manage', 'marketing.manage', 'navigation.manage', 'leads.view',
                ],
            ],
        ];
    }

    /** Write the catalogue and built-in roles. Idempotent. */
    public static function sync(): void
    {
        foreach (self::ALL as $key => [$group, $label]) {
            DB::upsert('permissions', ['key' => $key, 'group' => $group, 'label' => $label], ['group', 'label']);
        }
        $ids = [];
        foreach (DB::all('SELECT id, `key` FROM permissions') as $row) {
            $ids[$row['key']] = (int) $row['id'];
        }
        foreach (self::systemRoles() as $role) {
            DB::upsert('roles', [
                'name' => $role['name'],
                'slug' => $role['slug'],
                'description' => $role['description'],
                'is_system' => 1,
                'rank' => $role['rank'],
            ], ['description', 'rank', 'is_system']);
            $roleId = (int) DB::value('SELECT id FROM roles WHERE slug = ?', [$role['slug']]);
            // Super Admin always holds everything, including permissions added later.
            if ($role['slug'] === 'super-admin') {
                foreach ($ids as $pid) {
                    DB::query('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)', [$roleId, $pid]);
                }
                continue;
            }
            $existing = (int) DB::value('SELECT COUNT(*) FROM role_permissions WHERE role_id = ?', [$roleId]);
            if ($existing === 0) {
                foreach ($role['permissions'] as $key) {
                    if (isset($ids[$key])) {
                        DB::query('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)', [$roleId, $ids[$key]]);
                    }
                }
            }
        }
    }
}
