<?php

declare(strict_types=1);

use App\Core\Router;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth;
use App\Http\Controllers\Site;

return static function (Router $r): void {
    // --- system ---------------------------------------------------------------
    $r->get('/health', [Site\SystemController::class, 'health']);
    $r->get('/ready', [Site\SystemController::class, 'ready']);
    $r->get('/robots.txt', [Site\SeoController::class, 'robots']);
    $r->get('/sitemap.xml', [Site\SeoController::class, 'sitemap']);
    $r->get('/cron/run', [Site\SystemController::class, 'cron']);
    $r->get(config('app.media_path') . '/{path*}', [Site\MediaController::class, 'serve']);

    // --- public forms -----------------------------------------------------------
    $r->post('/forms/submit', [Site\FormController::class, 'submit']);
    $r->get('/forms/render/{slug}', [Site\FormController::class, 'render']);
    $r->get('/lead-magnet/{slug}/download', [Site\FormController::class, 'download']);

    // --- authentication ---------------------------------------------------------
    $r->get(config('app.login_path'), [Auth\LoginController::class, 'show']);
    $r->post(config('app.login_path'), [Auth\LoginController::class, 'login']);
    $r->post('/auth/logout', [Auth\LoginController::class, 'logout']);
    $r->get('/auth/setup-2fa', [Auth\MfaController::class, 'setup']);
    $r->post('/auth/setup-2fa', [Auth\MfaController::class, 'confirmSetup']);
    $r->get('/auth/verify-2fa', [Auth\MfaController::class, 'verify']);
    $r->post('/auth/verify-2fa', [Auth\MfaController::class, 'confirmVerify']);
    $r->get('/auth/recovery-codes', [Auth\MfaController::class, 'recoveryCodes']);

    // --- previews (signed-in only) ----------------------------------------------
    $r->get('/preview/page/{id}', [Admin\PreviewController::class, 'page']);
    $r->get('/preview/post/{id}', [Admin\PreviewController::class, 'post']);
    $r->get('/preview/product/{id}', [Admin\PreviewController::class, 'product']);

    // --- admin ------------------------------------------------------------------
    $r->get('/admin', [Admin\DashboardController::class, 'index']);
    $r->post('/admin/country', [Admin\DashboardController::class, 'switchCountry']);
    $r->get('/admin/search', [Admin\DashboardController::class, 'search']);

    // Pages & sections
    $r->get('/admin/pages', [Admin\PageController::class, 'index']);
    $r->get('/admin/pages/new', [Admin\PageController::class, 'create']);
    $r->post('/admin/pages', [Admin\PageController::class, 'store']);
    $r->get('/admin/pages/{id}', [Admin\PageController::class, 'edit']);
    $r->post('/admin/pages/{id}', [Admin\PageController::class, 'update']);
    $r->post('/admin/pages/{id}/delete', [Admin\PageController::class, 'destroy']);
    $r->post('/admin/pages/{id}/duplicate', [Admin\PageController::class, 'duplicate']);
    $r->post('/admin/pages/{id}/status', [Admin\PageController::class, 'status']);
    $r->post('/admin/pages/{id}/copy-to-market', [Admin\PageController::class, 'copyToMarket']);
    $r->post('/admin/pages/{id}/sections', [Admin\SectionController::class, 'store']);
    $r->post('/admin/pages/{id}/sections/reorder', [Admin\SectionController::class, 'reorder']);
    $r->get('/admin/sections/{id}', [Admin\SectionController::class, 'edit']);
    $r->post('/admin/sections/{id}', [Admin\SectionController::class, 'update']);
    $r->post('/admin/sections/{id}/delete', [Admin\SectionController::class, 'destroy']);
    $r->post('/admin/sections/{id}/duplicate', [Admin\SectionController::class, 'duplicate']);
    $r->post('/admin/sections/{id}/toggle', [Admin\SectionController::class, 'toggle']);
    $r->post('/admin/sections/{id}/move', [Admin\SectionController::class, 'move']);
    $r->get('/admin/page-categories', [Admin\PageCategoryController::class, 'index']);
    $r->post('/admin/page-categories', [Admin\PageCategoryController::class, 'save']);
    $r->post('/admin/page-categories/{id}/delete', [Admin\PageCategoryController::class, 'destroy']);

    // Cities
    $r->get('/admin/cities', [Admin\CityController::class, 'index']);
    $r->post('/admin/cities', [Admin\CityController::class, 'save']);
    $r->get('/admin/cities/generator', [Admin\CityController::class, 'generator']);
    $r->post('/admin/cities/generator', [Admin\CityController::class, 'generate']);
    $r->post('/admin/cities/{id}/delete', [Admin\CityController::class, 'destroy']);

    // Products
    $r->get('/admin/products', [Admin\ProductController::class, 'index']);
    $r->get('/admin/products/new', [Admin\ProductController::class, 'create']);
    $r->post('/admin/products', [Admin\ProductController::class, 'store']);
    $r->get('/admin/products/order', [Admin\ProductController::class, 'order']);
    $r->post('/admin/products/order', [Admin\ProductController::class, 'saveOrder']);
    $r->get('/admin/products/categories', [Admin\ProductTaxonomyController::class, 'categories']);
    $r->post('/admin/products/categories', [Admin\ProductTaxonomyController::class, 'saveCategory']);
    $r->post('/admin/products/categories/{id}/delete', [Admin\ProductTaxonomyController::class, 'deleteCategory']);
    $r->get('/admin/products/brands', [Admin\ProductTaxonomyController::class, 'brands']);
    $r->post('/admin/products/brands', [Admin\ProductTaxonomyController::class, 'saveBrand']);
    $r->post('/admin/products/brands/{id}/delete', [Admin\ProductTaxonomyController::class, 'deleteBrand']);
    $r->get('/admin/products/settings', [Admin\ProductController::class, 'settings']);
    $r->post('/admin/products/settings', [Admin\ProductController::class, 'saveSettings']);
    $r->get('/admin/products/{id}', [Admin\ProductController::class, 'edit']);
    $r->post('/admin/products/{id}', [Admin\ProductController::class, 'update']);
    $r->post('/admin/products/{id}/delete', [Admin\ProductController::class, 'destroy']);
    $r->post('/admin/products/{id}/duplicate', [Admin\ProductController::class, 'duplicate']);

    // Blog
    $r->get('/admin/blog', [Admin\BlogController::class, 'index']);
    $r->get('/admin/blog/new', [Admin\BlogController::class, 'create']);
    $r->post('/admin/blog', [Admin\BlogController::class, 'store']);
    $r->get('/admin/blog/categories', [Admin\BlogTaxonomyController::class, 'categories']);
    $r->post('/admin/blog/categories', [Admin\BlogTaxonomyController::class, 'saveCategory']);
    $r->post('/admin/blog/categories/{id}/delete', [Admin\BlogTaxonomyController::class, 'deleteCategory']);
    $r->get('/admin/blog/tags', [Admin\BlogTaxonomyController::class, 'tags']);
    $r->post('/admin/blog/tags', [Admin\BlogTaxonomyController::class, 'saveTag']);
    $r->post('/admin/blog/tags/{id}/delete', [Admin\BlogTaxonomyController::class, 'deleteTag']);
    $r->get('/admin/blog/settings', [Admin\BlogController::class, 'settings']);
    $r->post('/admin/blog/settings', [Admin\BlogController::class, 'saveSettings']);
    $r->get('/admin/blog/{id}', [Admin\BlogController::class, 'edit']);
    $r->post('/admin/blog/{id}', [Admin\BlogController::class, 'update']);
    $r->post('/admin/blog/{id}/delete', [Admin\BlogController::class, 'destroy']);

    // Forms
    $r->get('/admin/forms', [Admin\FormController::class, 'index']);
    $r->get('/admin/forms/new', [Admin\FormController::class, 'create']);
    $r->post('/admin/forms', [Admin\FormController::class, 'store']);
    $r->get('/admin/forms/{id}', [Admin\FormController::class, 'edit']);
    $r->post('/admin/forms/{id}', [Admin\FormController::class, 'update']);
    $r->post('/admin/forms/{id}/delete', [Admin\FormController::class, 'destroy']);
    $r->post('/admin/forms/{id}/duplicate', [Admin\FormController::class, 'duplicate']);
    $r->get('/admin/forms/{id}/submissions', [Admin\FormController::class, 'submissions']);

    // CRM
    $r->get('/admin/leads', [Admin\LeadController::class, 'index']);
    $r->get('/admin/leads/new', [Admin\LeadController::class, 'create']);
    $r->post('/admin/leads', [Admin\LeadController::class, 'store']);
    $r->get('/admin/leads/export', [Admin\LeadController::class, 'export']);
    $r->post('/admin/leads/bulk', [Admin\LeadController::class, 'bulk']);
    $r->get('/admin/leads/{id}', [Admin\LeadController::class, 'show']);
    $r->post('/admin/leads/{id}', [Admin\LeadController::class, 'update']);
    $r->post('/admin/leads/{id}/note', [Admin\LeadController::class, 'note']);
    $r->post('/admin/leads/{id}/activity', [Admin\LeadController::class, 'activity']);
    $r->post('/admin/leads/{id}/convert', [Admin\LeadController::class, 'convert']);
    $r->post('/admin/leads/{id}/delete', [Admin\LeadController::class, 'destroy']);
    $r->post('/admin/leads/{id}/consent', [Admin\LeadController::class, 'consent']);
    $r->get('/admin/pipeline', [Admin\PipelineController::class, 'index']);
    $r->post('/admin/pipeline/move', [Admin\PipelineController::class, 'move']);
    $r->get('/admin/customers', [Admin\CustomerController::class, 'index']);
    $r->get('/admin/customers/new', [Admin\CustomerController::class, 'create']);
    $r->post('/admin/customers', [Admin\CustomerController::class, 'store']);
    $r->get('/admin/customers/{id}', [Admin\CustomerController::class, 'show']);
    $r->post('/admin/customers/{id}', [Admin\CustomerController::class, 'update']);
    $r->post('/admin/customers/{id}/note', [Admin\CustomerController::class, 'note']);
    $r->post('/admin/customers/{id}/product', [Admin\CustomerController::class, 'addProduct']);
    $r->post('/admin/customers/{id}/product/{pid}/delete', [Admin\CustomerController::class, 'removeProduct']);
    $r->post('/admin/customers/{id}/delete', [Admin\CustomerController::class, 'destroy']);
    $r->get('/admin/reports', [Admin\ReportController::class, 'index']);

    // Media
    $r->get('/admin/media', [Admin\MediaController::class, 'index']);
    $r->get('/admin/media/picker', [Admin\MediaController::class, 'picker']);
    $r->post('/admin/media/upload', [Admin\MediaController::class, 'upload']);
    $r->post('/admin/media/folders', [Admin\MediaController::class, 'createFolder']);
    $r->post('/admin/media/folders/{id}/delete', [Admin\MediaController::class, 'deleteFolder']);
    $r->post('/admin/media/{id}/rename', [Admin\MediaController::class, 'rename']);
    $r->post('/admin/media/{id}', [Admin\MediaController::class, 'update']);
    $r->post('/admin/media/{id}/delete', [Admin\MediaController::class, 'destroy']);

    // Navigation
    $r->get('/admin/navigation', [Admin\NavigationController::class, 'index']);
    $r->post('/admin/navigation/{location}', [Admin\NavigationController::class, 'save']);

    // SEO
    $r->get('/admin/seo', [Admin\SeoController::class, 'index']);
    $r->post('/admin/seo', [Admin\SeoController::class, 'save']);
    $r->get('/admin/redirects', [Admin\RedirectController::class, 'index']);
    $r->post('/admin/redirects', [Admin\RedirectController::class, 'save']);
    $r->post('/admin/redirects/{id}/delete', [Admin\RedirectController::class, 'destroy']);

    // Marketing
    $r->get('/admin/marketing', [Admin\MarketingController::class, 'index']);
    $r->post('/admin/marketing', [Admin\MarketingController::class, 'save']);
    $r->post('/admin/marketing/scripts', [Admin\MarketingController::class, 'saveScript']);
    $r->post('/admin/marketing/scripts/{id}/delete', [Admin\MarketingController::class, 'deleteScript']);
    $r->get('/admin/popups', [Admin\PopupController::class, 'index']);
    $r->get('/admin/popups/new', [Admin\PopupController::class, 'create']);
    $r->post('/admin/popups', [Admin\PopupController::class, 'store']);
    $r->get('/admin/popups/{id}', [Admin\PopupController::class, 'edit']);
    $r->post('/admin/popups/{id}', [Admin\PopupController::class, 'update']);
    $r->post('/admin/popups/{id}/delete', [Admin\PopupController::class, 'destroy']);
    $r->get('/admin/lead-magnets', [Admin\LeadMagnetController::class, 'index']);
    $r->get('/admin/lead-magnets/new', [Admin\LeadMagnetController::class, 'create']);
    $r->post('/admin/lead-magnets', [Admin\LeadMagnetController::class, 'store']);
    $r->get('/admin/lead-magnets/{id}', [Admin\LeadMagnetController::class, 'edit']);
    $r->post('/admin/lead-magnets/{id}', [Admin\LeadMagnetController::class, 'update']);
    $r->post('/admin/lead-magnets/{id}/delete', [Admin\LeadMagnetController::class, 'destroy']);

    // Settings
    // Visual builder
    $r->get('/admin/builder/templates', [Admin\BuilderController::class, 'templates']);
    $r->post('/admin/builder/templates', [Admin\BuilderController::class, 'createTemplate']);
    $r->get('/admin/builder/templates.json', [Admin\BuilderController::class, 'templateList']);
    $r->post('/admin/builder/templates/from-selection', [Admin\BuilderController::class, 'saveSelection']);
    $r->get('/admin/builder/templates/{id}/elements', [Admin\BuilderController::class, 'templateElements']);
    $r->post('/admin/builder/templates/{id}', [Admin\BuilderController::class, 'updateTemplate']);
    $r->post('/admin/pages/{id}/builder', [Admin\BuilderController::class, 'enablePage']);
    $r->post('/admin/products/{id}/design', [Admin\BuilderController::class, 'productDesign']);
    $r->post('/admin/pages/{id}/sections-editor', [Admin\BuilderController::class, 'disablePage']);
    $r->get('/admin/builder/{kind}/{id}', [Admin\BuilderController::class, 'editor']);
    $r->get('/admin/builder/{kind}/{id}/canvas', [Admin\BuilderController::class, 'canvas']);
    $r->post('/admin/builder/{kind}/{id}/render', [Admin\BuilderController::class, 'render']);
    $r->post('/admin/builder/{kind}/{id}/save', [Admin\BuilderController::class, 'save']);
    $r->post('/admin/builder/{kind}/{id}/discard', [Admin\BuilderController::class, 'discard']);
    $r->post('/admin/builder/{kind}/{id}/settings', [Admin\BuilderController::class, 'settings']);
    $r->get('/admin/builder/{kind}/{id}/revisions', [Admin\BuilderController::class, 'revisions']);
    $r->get('/admin/builder/{kind}/{id}/revisions/{revision}', [Admin\BuilderController::class, 'revision']);

    $r->get('/admin/settings', [Admin\SettingsController::class, 'index']);
    $r->post('/admin/settings/system', [Admin\SettingsController::class, 'saveSystem']);
    $r->post('/admin/settings/system/migrate', [Admin\SettingsController::class, 'migrate']);
    $r->post('/admin/settings/system/key', [Admin\SettingsController::class, 'revealKey']);
    $r->post('/admin/settings/{group}', [Admin\SettingsController::class, 'save']);
    $r->post('/admin/settings/email/test', [Admin\SettingsController::class, 'testEmail']);
    $r->get('/admin/countries', [Admin\CountryController::class, 'index']);
    $r->post('/admin/countries', [Admin\CountryController::class, 'save']);
    $r->post('/admin/countries/{id}/delete', [Admin\CountryController::class, 'destroy']);
    $r->get('/admin/email-templates', [Admin\SettingsController::class, 'templates']);
    $r->post('/admin/email-templates/{id}', [Admin\SettingsController::class, 'saveTemplate']);

    // Staff & security
    $r->get('/admin/staff', [Admin\StaffController::class, 'index']);
    $r->get('/admin/staff/new', [Admin\StaffController::class, 'create']);
    $r->post('/admin/staff', [Admin\StaffController::class, 'store']);
    $r->get('/admin/staff/roles', [Admin\RoleController::class, 'index']);
    $r->post('/admin/staff/roles', [Admin\RoleController::class, 'save']);
    $r->post('/admin/staff/roles/{id}/delete', [Admin\RoleController::class, 'destroy']);
    $r->get('/admin/staff/{id}', [Admin\StaffController::class, 'edit']);
    $r->post('/admin/staff/{id}', [Admin\StaffController::class, 'update']);
    $r->post('/admin/staff/{id}/reset-mfa', [Admin\StaffController::class, 'resetMfa']);
    $r->post('/admin/staff/{id}/revoke-sessions', [Admin\StaffController::class, 'revokeSessions']);
    $r->post('/admin/staff/{id}/delete', [Admin\StaffController::class, 'destroy']);
    $r->get('/admin/profile', [Admin\ProfileController::class, 'index']);
    $r->post('/admin/profile', [Admin\ProfileController::class, 'update']);
    $r->post('/admin/profile/password', [Admin\ProfileController::class, 'password']);
    $r->post('/admin/profile/sessions/revoke', [Admin\ProfileController::class, 'revokeSessions']);
    $r->post('/admin/profile/recovery-codes', [Admin\ProfileController::class, 'regenerateCodes']);
    $r->get('/admin/audit', [Admin\AuditController::class, 'index']);

    // System
    $r->get('/admin/trash', [Admin\TrashController::class, 'index']);
    $r->post('/admin/trash/{type}/{id}/restore', [Admin\TrashController::class, 'restore']);
    $r->post('/admin/trash/{type}/{id}/purge', [Admin\TrashController::class, 'purge']);
    $r->get('/admin/backups', [Admin\BackupController::class, 'index']);
    $r->post('/admin/backups', [Admin\BackupController::class, 'create']);
    $r->post('/admin/backups/settings', [Admin\BackupController::class, 'settings']);
    $r->get('/admin/backups/{id}/download', [Admin\BackupController::class, 'download']);
    $r->post('/admin/backups/{id}/restore', [Admin\BackupController::class, 'restore']);
    $r->post('/admin/backups/{id}/delete', [Admin\BackupController::class, 'destroy']);

    // --- public site (last: the catch-all) ---------------------------------------
    $r->get('/{path*}', [Site\PageController::class, 'route']);
};
