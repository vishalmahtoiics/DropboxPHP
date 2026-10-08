<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Builder\Elements;
use App\Builder\Layout;
use App\Builder\Renderer;
use App\Builder\Store;
use App\Cms\Icons;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\ValidationException;
use App\Core\View;
use App\Http\AdminController;
use App\Services\Content;
use App\Services\Countries;
use App\Services\Options;
use App\Services\SiteContext;

/**
 * The visual (drag-and-drop) builder: the editor screen, its canvas iframe,
 * live rendering, saving and publishing, revisions and templates.
 */
final class BuilderController extends AdminController
{
    // --- editor ---------------------------------------------------------------------------

    public function editor(string $kind, string $id): Response
    {
        $owner = $this->owner($kind, $id);
        $layouts = Store::layouts($kind, $owner);
        if ($kind === 'product' && $layouts['draft'] === null && Layout::isEmpty($layouts['live'])) {
            // A product's first layout starts from the template it used, or the
            // classic product page rebuilt in widgets.
            $start = Store::productLayout(['page_layout' => 'template'] + $owner) ?? self::starterProductLayout();
            $layouts['live'] = ['version' => Layout::VERSION, 'elements' => Layout::regenerateIds($start['elements'])];
        }
        $base = '/admin/builder/' . $kind . '/' . $owner['id'];
        $config = [
            'kind' => $kind,
            'id' => (int) $owner['id'],
            'title' => Store::title($kind, $owner),
            'templateKind' => $kind === 'template' ? $owner['kind'] : null,
            'layout' => $layouts['draft'] ?? $layouts['live'],
            'hasDraft' => $layouts['draft'] !== null,
            'canPublish' => Store::canPublish($kind, $owner),
            'status' => $owner['status'] ?? null,
            'productContext' => $kind === 'product' || ($kind === 'template' && $owner['kind'] === 'product'),
            'urls' => [
                'canvas' => $base . '/canvas',
                'render' => $base . '/render',
                'save' => $base . '/save',
                'discard' => $base . '/discard',
                'revisions' => $base . '/revisions',
                'templates' => '/admin/builder/templates.json',
                'saveTemplate' => '/admin/builder/templates/from-selection',
                'back' => $this->backUrl($kind, $owner),
                'preview' => $this->previewUrl($kind, $owner),
            ],
            'elements' => Elements::forEditor(),
            'categories' => Elements::CATEGORIES,
            'options' => [
                'form' => self::pairs(Options::forms()),
                'magnet' => self::pairs(Options::magnets()),
                'category' => self::pairs(Options::productCategories()),
                'brand' => self::pairs(Options::brands()),
                'blogcategory' => self::pairs(Options::blogCategories()),
                'products' => self::pairs(Options::products()),
            ],
            'icons' => array_map(static fn ($n) => Icons::svg($n, 'icon', 18), array_combine(Icons::names(), Icons::names())),
        ];
        return Response::html(View::render('admin/builder/editor', [
            'title' => 'Edit with builder: ' . $config['title'],
            'config' => $config,
        ]))->header('Cache-Control', 'no-store, private');
    }

    /** The page as visitors see it, minus the layout, which the editor injects. */
    public function canvas(string $kind, string $id): Response
    {
        $owner = $this->owner($kind, $id);
        $ctx = $this->context($kind, $owner);
        View::share('builderCanvas', true);
        $html = View::render('admin/builder/canvas', [
            'title' => Store::title($kind, $owner),
            'page' => $kind === 'page' ? $owner : null,
            'seo' => \App\Services\Seo::meta(['title' => Store::title($kind, $owner), 'noindex' => true]),
            'productContext' => $ctx['product'] !== null,
        ], 'layouts/site');
        return Response::html($html)
            ->header('X-Frame-Options', 'SAMEORIGIN')
            ->header('Content-Security-Policy', "frame-ancestors 'self'; default-src 'self'; script-src 'self' 'nonce-" . nonce() . "'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' data: https://fonts.gstatic.com; img-src 'self' data: blob: https:; frame-src https://www.youtube-nocookie.com https://player.vimeo.com; connect-src 'self'; form-action 'none'; base-uri 'self'; object-src 'none'")
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'no-store');
    }

    /** Live render for the canvas: the same renderer the public site uses. */
    public function render(string $kind, string $id): Response
    {
        $owner = $this->owner($kind, $id);
        $layout = Layout::normalize($this->layoutInput());
        $out = Renderer::render($layout, $this->context($kind, $owner) + ['editor' => true]);
        return $this->json(['ok' => true, 'html' => $out['html'], 'css' => $out['css'], 'layout' => $layout]);
    }

    public function save(string $kind, string $id): Response
    {
        $owner = $this->owner($kind, $id);
        $publish = !empty($this->request->body['publish']);
        if ($publish && !Store::canPublish($kind, $owner)) {
            throw HttpException::forbidden();
        }
        $layout = Layout::normalize($this->layoutInput());
        Store::save($kind, $owner, $layout, $publish);
        if ($publish) {
            Audit::record('updated', ucfirst($kind), (int) $owner['id'], 'Published the builder layout of "' . Store::title($kind, $owner) . '"');
        }
        return $this->json(['ok' => true, 'published' => $publish, 'layout' => $layout, 'savedAt' => date('H:i')]);
    }

    public function discard(string $kind, string $id): Response
    {
        $owner = $this->owner($kind, $id);
        Store::discardDraft($kind, $owner);
        return $this->json(['ok' => true, 'layout' => Store::layouts($kind, Store::owner($kind, (int) $owner['id']))['live']]);
    }

    public function revisions(string $kind, string $id): Response
    {
        $owner = $this->owner($kind, $id);
        return $this->json(['ok' => true, 'items' => Store::revisions($kind, (int) $owner['id'])]);
    }

    public function revision(string $kind, string $id, string $revision): Response
    {
        $owner = $this->owner($kind, $id);
        $layout = Store::revision($kind, (int) $owner['id'], $this->id($revision));
        if ($layout === null) {
            throw HttpException::notFound();
        }
        return $this->json(['ok' => true, 'layout' => $layout]);
    }

    // --- switching a page to the builder -----------------------------------------------

    public function enablePage(string $id): Response
    {
        $page = Store::owner('page', $this->id($id));
        if (($page['builder_layout'] ?? null) === null && ($page['builder_draft'] ?? null) === null) {
            $layout = Layout::fromSections(Content::sections((int) $page['id'], true));
            DB::update('pages', ['builder_draft' => Layout::encode($layout)], ['id' => $page['id']]);
        }
        Audit::record('updated', 'Page', (int) $page['id'], 'Opened in the visual builder');
        return $this->redirect('/admin/builder/page/' . $page['id']);
    }

    public function disablePage(string $id): Response
    {
        $page = Store::owner('page', $this->id($id));
        DB::update('pages', ['editor' => 'sections'], ['id' => $page['id']]);
        Audit::record('updated', 'Page', (int) $page['id'], 'Switched back to the section editor');
        $this->flash('The page uses the section editor again. Its builder layout is kept, in case you switch back.');
        return $this->redirect('/admin/pages/' . $page['id']);
    }

    /** Which design a product page uses: classic, a template, or its own layout. */
    public function productDesign(string $id): Response
    {
        $product = Store::owner('product', $this->id($id));
        $mode = $this->request->str('page_layout');
        if (!in_array($mode, ['classic', 'template', 'builder'], true)) {
            throw ValidationException::with('page_layout', 'Choose a design.');
        }
        $templateId = $this->request->int('template_id') ?: null;
        if ($templateId && !DB::value("SELECT id FROM builder_templates WHERE id = ? AND kind = 'product'", [$templateId])) {
            $templateId = null;
        }
        DB::update('products', ['page_layout' => $mode, 'template_id' => $templateId], ['id' => $product['id']]);
        Audit::record('updated', 'Product', (int) $product['id'], 'Product page design: ' . $mode);
        if ($mode === 'builder') {
            return $this->redirect('/admin/builder/product/' . $product['id']);
        }
        $this->flash('Product page design saved.');
        return $this->redirect('/admin/products/' . $product['id']);
    }

    // --- templates -----------------------------------------------------------------------

    public function templates(): Response
    {
        $this->authorizeAny(['pages.edit', 'products.edit']);
        return $this->screen('builder/templates', [
            'title' => 'Builder templates',
            'templates' => DB::all('SELECT t.id, t.name, t.kind, t.is_default, t.updated_at, t.draft IS NOT NULL AS has_draft, u.name AS editor
                FROM builder_templates t LEFT JOIN users u ON u.id = t.updated_by_id ORDER BY t.kind, t.name'),
            'usage' => array_column(DB::all("SELECT template_id, COUNT(*) AS n FROM products WHERE template_id IS NOT NULL AND deleted_at IS NULL GROUP BY template_id"), 'n', 'template_id'),
        ]);
    }

    public function createTemplate(): Response
    {
        $kind = $this->request->str('kind');
        if (!in_array($kind, ['page', 'section', 'product'], true)) {
            throw ValidationException::with('kind', 'Choose a template type.');
        }
        $this->authorize($kind === 'product' ? 'products.edit' : 'pages.edit');
        $name = $this->request->str('name');
        if ($name === '' || mb_strlen($name) > 160) {
            throw ValidationException::with('name', 'Give the template a name (up to 160 characters).');
        }
        $layout = $kind === 'product' ? self::starterProductLayout() : Layout::empty();
        // Never made the default here: that would change every live product page
        // at once. "Make default" on the templates screen does it, deliberately.
        $id = DB::insert('builder_templates', [
            'name' => $name, 'kind' => $kind, 'layout' => Layout::encode($layout),
            'created_by_id' => $this->me['id'], 'updated_by_id' => $this->me['id'],
        ]);
        Audit::record('created', 'Template', $id, 'Created builder template "' . $name . '"');
        return $this->redirect('/admin/builder/template/' . $id);
    }

    public function updateTemplate(string $id): Response
    {
        $tpl = Store::owner('template', $this->id($id));
        $action = $this->request->str('action');
        if ($action === 'default' && $tpl['kind'] === 'product') {
            DB::transaction(static function () use ($tpl): void {
                DB::query("UPDATE builder_templates SET is_default = 0 WHERE kind = 'product'");
                DB::update('builder_templates', ['is_default' => 1], ['id' => $tpl['id']]);
            });
            $this->flash('"' . $tpl['name'] . '" now designs every product page that uses the default template.');
        } elseif ($action === 'undefault') {
            DB::update('builder_templates', ['is_default' => 0], ['id' => $tpl['id']]);
            $this->flash('Products without their own layout use the classic product page again.');
        } elseif ($action === 'rename') {
            $name = $this->request->str('name');
            if ($name === '' || mb_strlen($name) > 160) {
                throw ValidationException::with('name', 'Give the template a name (up to 160 characters).');
            }
            DB::update('builder_templates', ['name' => $name], ['id' => $tpl['id']]);
            $this->flash('Template renamed.');
        } elseif ($action === 'duplicate') {
            $newId = DB::insert('builder_templates', [
                'name' => mb_substr($tpl['name'] . ' (copy)', 0, 160), 'kind' => $tpl['kind'], 'layout' => $tpl['layout'],
                'created_by_id' => $this->me['id'], 'updated_by_id' => $this->me['id'],
            ]);
            $this->flash('Template duplicated.');
            return $this->redirect('/admin/builder/template/' . $newId);
        } elseif ($action === 'delete') {
            DB::delete('builder_templates', ['id' => $tpl['id']]);
            DB::query("DELETE FROM builder_revisions WHERE owner_type = 'template' AND owner_id = ?", [$tpl['id']]);
            Audit::record('deleted', 'Template', (int) $tpl['id'], 'Deleted builder template "' . $tpl['name'] . '"');
            $this->flash('Template deleted.');
        }
        return $this->redirect('/admin/builder/templates');
    }

    /** Templates that can be inserted into the layout being edited. */
    public function templateList(): Response
    {
        $this->authorizeAny(['pages.edit', 'products.edit']);
        return $this->json(['ok' => true, 'items' => array_map(static fn ($t) => ['id' => (int) $t['id'], 'name' => $t['name'], 'kind' => $t['kind']], DB::all(
            "SELECT id, name, kind FROM builder_templates WHERE kind IN ('page','section','product') ORDER BY kind, name"
        ))]);
    }

    public function templateElements(string $id): Response
    {
        $this->authorizeAny(['pages.edit', 'products.edit']);
        $json = DB::value('SELECT layout FROM builder_templates WHERE id = ?', [$this->id($id)]);
        if (!is_string($json)) {
            throw HttpException::notFound();
        }
        return $this->json(['ok' => true, 'elements' => Layout::regenerateIds(Layout::decode($json)['elements'])]);
    }

    /** Save the selected element (or the whole layout) as a reusable template. */
    public function saveSelection(): Response
    {
        $this->authorize('pages.edit');
        $name = $this->request->str('name');
        $kind = $this->request->str('kind') === 'page' ? 'page' : 'section';
        if ($name === '' || mb_strlen($name) > 160) {
            throw ValidationException::with('name', 'Give the template a name (up to 160 characters).');
        }
        $layout = Layout::normalize($this->layoutInput());
        if (Layout::isEmpty($layout)) {
            throw ValidationException::with('layout', 'There is nothing to save.');
        }
        $id = DB::insert('builder_templates', [
            'name' => $name, 'kind' => $kind, 'layout' => Layout::encode($layout),
            'created_by_id' => $this->me['id'], 'updated_by_id' => $this->me['id'],
        ]);
        Audit::record('created', 'Template', $id, 'Saved builder template "' . $name . '"');
        return $this->json(['ok' => true, 'id' => $id]);
    }

    // --- helpers -------------------------------------------------------------------------

    private function owner(string $kind, string $id): array
    {
        if (!in_array($kind, Store::KINDS, true)) {
            throw HttpException::notFound();
        }
        return Store::owner($kind, $this->id($id));
    }

    /** @return array{product: ?array, page: ?array} */
    private function context(string $kind, array $owner): array
    {
        if ($kind === 'page') {
            SiteContext::boot($this->request, Countries::find((int) $owner['country_id']));
            return ['product' => null, 'page' => $owner];
        }
        $country = Countries::adminCurrent();
        SiteContext::boot($this->request, $country);
        $productId = null;
        if ($kind === 'product') {
            $productId = (int) $owner['id'];
        } elseif ($owner['kind'] === 'product') {
            $productId = Store::sampleProductId();
        }
        return ['product' => $productId ? Content::productById($productId, $country) : null, 'page' => null];
    }

    private function layoutInput(): array
    {
        $raw = $this->request->body['layout'] ?? null;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        return is_array($raw) ? $raw : Layout::empty();
    }

    private function backUrl(string $kind, array $owner): string
    {
        return match ($kind) {
            'page' => '/admin/pages/' . $owner['id'],
            'product' => '/admin/products/' . $owner['id'],
            default => '/admin/builder/templates',
        };
    }

    private function previewUrl(string $kind, array $owner): ?string
    {
        return match ($kind) {
            'page' => '/preview/page/' . $owner['id'],
            'product' => '/preview/product/' . $owner['id'],
            default => $owner['kind'] === 'product' && ($pid = Store::sampleProductId()) ? '/preview/product/' . $pid . '?template=' . $owner['id'] : null,
        };
    }

    /** @param array<int|string, string> $map @return list<array{0:string,1:string}> */
    private static function pairs(array $map): array
    {
        $out = [];
        foreach ($map as $k => $v) {
            $out[] = [(string) $k, (string) $v];
        }
        return $out;
    }

    /** A sensible first product template: the classic product page, rebuilt in containers. */
    public static function starterProductLayout(): array
    {
        $w = static fn (string $type, array $s = []) => ['type' => $type, 'settings' => $s];
        return Layout::normalize(['elements' => [
            ['type' => 'container', 'settings' => ['direction' => 'row', 'direction_tablet' => 'column', 'gap' => ['size' => 48, 'unit' => 'px'], '_padding' => ['top' => '48', 'right' => '', 'bottom' => '48', 'left' => '', 'unit' => 'px', 'linked' => false]], 'children' => [
                ['type' => 'container', 'settings' => ['width' => ['size' => 58, 'unit' => '%'], 'width_tablet' => ['size' => 100, 'unit' => '%']], 'children' => [
                    $w('product_breadcrumbs'),
                    $w('product_title'),
                    $w('product_short_description'),
                    $w('product_image'),
                    $w('product_gallery'),
                ]],
                ['type' => 'container', 'settings' => ['_bg_type' => 'color', '_bg_color' => '#ffffff', '_border_style' => 'solid', '_border_color' => '#e3e8f0', '_radius' => ['top' => '16', 'right' => '16', 'bottom' => '16', 'left' => '16', 'unit' => 'px', 'linked' => true], '_padding' => ['top' => '24', 'right' => '24', 'bottom' => '24', 'left' => '24', 'unit' => 'px', 'linked' => true], '_shadow' => 'md', '_align_self' => 'flex-start'], 'children' => [
                    $w('product_price'),
                    $w('product_specs'),
                    $w('product_button'),
                ]],
            ]],
            ['type' => 'container', 'children' => [$w('product_features'), $w('product_description')]],
            ['type' => 'container', 'settings' => ['_bg_type' => 'color', '_bg_color' => '#f6f8fc'], 'children' => [$w('product_benefits')]],
            ['type' => 'container', 'children' => [$w('product_form')]],
            ['type' => 'container', 'children' => [$w('product_related')]],
        ]]);
    }
}
