<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Builder\Elements;
use App\Builder\Layout;
use App\Cms\Blocks;
use App\Cms\Design;
use App\Core\Audit;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Core\ValidationException;
use App\Http\AdminController;
use App\Services\Countries;

/** The page builder's sections: add, edit (content + design), reorder, hide, copy, delete. */
final class SectionController extends AdminController
{
    public function store(string $pageId): Response
    {
        $this->authorize('pages.edit');
        $page = $this->page_((int) $this->id($pageId));
        $type = $this->request->str('block_type');
        if (!Blocks::exists($type)) {
            throw ValidationException::with('block_type', 'Unknown section type.');
        }
        $order = (int) DB::value('SELECT COALESCE(MAX(sort_order), -10) + 10 FROM page_sections WHERE page_id = ?', [$page['id']]);
        $id = DB::insert('page_sections', [
            'page_id' => $page['id'],
            'block_type' => $type,
            'sort_order' => $order,
            'content' => Blocks::defaults($type),
            'settings' => Design::defaults(),
        ]);
        $this->touch($page);
        Audit::record('created', 'Section', $id, Blocks::get($type)['label'] . ' added to "' . $page['title'] . '"');
        return $this->redirect('/admin/sections/' . $id);
    }

    public function edit(string $id): Response
    {
        $this->authorize('pages.edit');
        [$section, $page] = $this->find($this->id($id));
        $def = Blocks::get($section['block_type']);
        if (!$def) {
            throw HttpException::notFound();
        }
        return $this->screen('pages/section', [
            'title' => 'Edit ' . $def['label'],
            'section' => $section,
            'p' => $page,
            'def' => $def,
            'content' => Blocks::normalize($section['block_type'], json_decode((string) $section['content'], true) ?: []),
            'design' => Design::normalize(json_decode((string) $section['settings'], true) ?: []),
        ]);
    }

    public function update(string $id): Response
    {
        $this->authorize('pages.edit');
        [$section, $page] = $this->find($this->id($id));
        $content = Blocks::normalize($section['block_type'], $this->request->arr('content'));
        $design = Design::normalize($this->request->arr('design'));
        if ($design['anchor'] !== '') {
            $clash = DB::value(
                "SELECT id FROM page_sections WHERE page_id = ? AND id <> ? AND JSON_UNQUOTE(JSON_EXTRACT(settings, '$.anchor')) = ?",
                [$page['id'], $section['id'], $design['anchor']]
            );
            if ($clash) {
                throw ValidationException::with('design.anchor', 'Another section on this page already uses this anchor ID.');
            }
        }
        DB::update('page_sections', [
            'content' => $content,
            'settings' => $design,
            'name' => Sanitizer::line($this->request->str('name'), 120) ?: null,
            'is_visible' => $this->request->bool('is_visible') ? 1 : 0,
        ], ['id' => $section['id']]);
        $this->touch($page);
        Audit::record('updated', 'Section', $section['id'], 'Edited a ' . $section['block_type'] . ' section on "' . $page['title'] . '"');
        $this->flash('Section saved.');
        $next = $this->request->str('after') === 'stay' ? '/admin/sections/' . $section['id'] : '/admin/pages/' . $page['id'];
        return $this->redirect($next);
    }

    public function destroy(string $id): Response
    {
        $this->authorize('pages.edit');
        [$section, $page] = $this->find($this->id($id));
        DB::delete('page_sections', ['id' => $section['id']]);
        $this->touch($page);
        Audit::record('deleted', 'Section', $section['id'], 'Removed a ' . $section['block_type'] . ' section from "' . $page['title'] . '"', ['content' => json_decode((string) $section['content'], true)]);
        $this->flash('Section deleted.');
        return $this->redirect('/admin/pages/' . $page['id']);
    }

    public function duplicate(string $id): Response
    {
        $this->authorize('pages.edit');
        [$section, $page] = $this->find($this->id($id));
        DB::transaction(static function () use ($section): void {
            DB::query('UPDATE page_sections SET sort_order = sort_order + 10 WHERE page_id = ? AND sort_order > ?', [$section['page_id'], $section['sort_order']]);
            $settings = json_decode((string) $section['settings'], true) ?: [];
            $settings['anchor'] = ''; // anchors must stay unique on the page
            DB::insert('page_sections', [
                'page_id' => $section['page_id'], 'block_type' => $section['block_type'],
                'name' => $section['name'] ? $section['name'] . ' (copy)' : null,
                'sort_order' => (int) $section['sort_order'] + 5, 'is_visible' => $section['is_visible'],
                'content' => $section['content'], 'settings' => $settings,
            ]);
        });
        $this->renumber((int) $page['id']);
        $this->touch($page);
        $this->flash('Section duplicated.');
        return $this->redirect('/admin/pages/' . $page['id']);
    }

    public function toggle(string $id): Response
    {
        $this->authorize('pages.edit');
        [$section, $page] = $this->find($this->id($id));
        DB::update('page_sections', ['is_visible' => (int) $section['is_visible'] ? 0 : 1], ['id' => $section['id']]);
        $this->touch($page);
        $this->flash((int) $section['is_visible'] ? 'Section hidden. It stays on the page but is not published.' : 'Section visible again.');
        return $this->redirect('/admin/pages/' . $page['id']);
    }

    public function move(string $id): Response
    {
        $this->authorize('pages.edit');
        [$section, $page] = $this->find($this->id($id));
        $ids = array_map('intval', DB::column('SELECT id FROM page_sections WHERE page_id = ? ORDER BY sort_order, id', [$page['id']]));
        $pos = array_search((int) $section['id'], $ids, true);
        $swap = $this->request->str('dir') === 'up' ? $pos - 1 : $pos + 1;
        if ($pos !== false && isset($ids[$swap])) {
            [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];
            $this->saveOrder((int) $page['id'], $ids);
            $this->touch($page);
        }
        return $this->redirect('/admin/pages/' . $page['id']);
    }

    /** Drag-and-drop order: a comma-separated id list. */
    public function reorder(string $pageId): Response
    {
        $this->authorize('pages.edit');
        $page = $this->page_($this->id($pageId));
        $ids = array_values(array_filter(array_map('intval', explode(',', $this->request->str('order')))));
        $existing = array_map('intval', DB::column('SELECT id FROM page_sections WHERE page_id = ?', [$page['id']]));
        sort($existing);
        $check = $ids;
        sort($check);
        if ($check !== $existing) {
            return $this->json(['ok' => false, 'error' => 'The page changed in another tab. Reload and try again.'], 409);
        }
        $this->saveOrder((int) $page['id'], $ids);
        $this->touch($page);
        return $this->json(['ok' => true]);
    }

    /** @param list<int> $ids */
    private function saveOrder(int $pageId, array $ids): void
    {
        DB::transaction(static function () use ($pageId, $ids): void {
            foreach ($ids as $i => $sid) {
                DB::update('page_sections', ['sort_order' => $i * 10], ['id' => $sid, 'page_id' => $pageId]);
            }
        });
    }

    private function renumber(int $pageId): void
    {
        $this->saveOrder($pageId, array_map('intval', DB::column('SELECT id FROM page_sections WHERE page_id = ? ORDER BY sort_order, id', [$pageId])));
    }

    private function touch(array $page): void
    {
        DB::update('pages', ['updated_by_id' => $this->me['id'], 'updated_at' => now()], ['id' => $page['id']]);
    }

    /** @return array<string,mixed> */
    private function page_(int $id): array
    {
        $page = DB::first('SELECT * FROM pages WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$page) {
            throw HttpException::notFound();
        }
        Countries::assertAccess((int) $page['country_id']);
        return $page;
    }

    /**
     * Copy: the section in the builder's clipboard format (a container
     * holding the block), so it pastes into the classic editor of any page
     * and into the visual builder alike.
     */
    public function clipboard(string $id): Response
    {
        $this->authorize('pages.view');
        [$section] = $this->find($this->id($id));
        $section['content'] = json_decode((string) $section['content'], true) ?: [];
        $section['settings'] = json_decode((string) $section['settings'], true) ?: [];
        $layout = Layout::fromSections([$section]);
        if (Layout::isEmpty($layout)) {
            throw ValidationException::with('section', 'This section cannot be copied.');
        }
        return $this->json(['ok' => true, 'element' => $layout['elements'][0]]);
    }

    /**
     * Paste copied sections at the end of a page. Anything copied in the
     * builder works as long as it contains ready-made sections; other
     * widgets only exist in the builder.
     */
    public function paste(string $pageId): Response
    {
        $this->authorize('pages.edit');
        $page = $this->page_((int) $this->id($pageId));
        $element = $this->request->body['element'] ?? null;
        $layout = Layout::normalize(['elements' => [is_array($element) ? $element : []]]);
        $blocks = [];
        $walk = static function (array $elements) use (&$walk, &$blocks): void {
            foreach ($elements as $el) {
                $def = Elements::get($el['type']);
                if (isset($def['block'])) {
                    $blocks[] = [$def['block'], $el['settings']];
                }
                if (!empty($el['children'])) {
                    $walk($el['children']);
                }
            }
        };
        $walk($layout['elements']);
        if (!$blocks) {
            throw ValidationException::with('element', 'That was copied from the visual builder and is not a ready-made section. Open this page with "Edit with Builder" to paste it there.');
        }
        $order = (int) DB::value('SELECT COALESCE(MAX(sort_order), -10) FROM page_sections WHERE page_id = ?', [$page['id']]);
        foreach ($blocks as [$type, $settings]) {
            $order += 10;
            $design = Design::normalize(array_merge(
                is_array($settings['_design'] ?? null) ? $settings['_design'] : [],
                array_filter(['preset' => $settings['_preset'] ?? null, 'width' => $settings['_section_width'] ?? null])
            ));
            $hidden = !empty($settings['_hide_desktop']) && !empty($settings['_hide_tablet']) && !empty($settings['_hide_mobile']);
            DB::insert('page_sections', [
                'page_id' => $page['id'], 'block_type' => $type, 'sort_order' => $order, 'is_visible' => $hidden ? 0 : 1,
                'content' => Blocks::normalize($type, $settings), 'settings' => $design,
            ]);
        }
        $this->touch($page);
        Audit::record('created', 'Section', (int) $page['id'], 'Pasted ' . count($blocks) . ' section(s) into "' . $page['title'] . '"');
        $this->flash(count($blocks) === 1 ? 'Section pasted at the end of the page.' : count($blocks) . ' sections pasted at the end of the page.');
        return $this->json(['ok' => true, 'count' => count($blocks)]);
    }

    /** @return array{0:array<string,mixed>,1:array<string,mixed>} */
    private function find(int $id): array
    {
        $section = DB::first('SELECT * FROM page_sections WHERE id = ?', [$id]);
        if (!$section) {
            throw HttpException::notFound();
        }
        return [$section, $this->page_((int) $section['page_id'])];
    }
}
