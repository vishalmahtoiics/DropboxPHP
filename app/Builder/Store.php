<?php

declare(strict_types=1);

namespace App\Builder;

use App\Core\Auth;
use App\Core\DB;
use App\Core\HttpException;
use App\Services\Countries;

/**
 * Where builder layouts live and who may change them.
 *
 * Three kinds of owner: a page, a product, or a template (reusable layout;
 * "product" templates also design every product page that uses them). Each
 * has a published layout and an optional draft; publishing keeps a revision.
 */
final class Store
{
    public const KINDS = ['page', 'product', 'template'];
    public const REVISIONS_KEPT = 30;

    /** The owner row, after checking it exists and the user may edit it. */
    public static function owner(string $kind, int $id): array
    {
        $owner = match ($kind) {
            'page' => DB::first('SELECT * FROM pages WHERE id = ? AND deleted_at IS NULL', [$id]),
            'product' => DB::first('SELECT * FROM products WHERE id = ? AND deleted_at IS NULL', [$id]),
            'template' => DB::first('SELECT * FROM builder_templates WHERE id = ?', [$id]),
            default => null,
        };
        if (!$owner) {
            throw HttpException::notFound();
        }
        if (!Auth::can(self::permission($kind, $owner))) {
            throw HttpException::forbidden();
        }
        if ($kind === 'page') {
            Countries::assertAccess((int) $owner['country_id']);
        }
        return $owner;
    }

    public static function permission(string $kind, array $owner): string
    {
        return match ($kind) {
            'product' => 'products.edit',
            'template' => ($owner['kind'] ?? '') === 'product' ? 'products.edit' : 'pages.edit',
            default => 'pages.edit',
        };
    }

    /** May this user make the layout live? */
    public static function canPublish(string $kind, array $owner): bool
    {
        if ($kind === 'page' && $owner['status'] === 'PUBLISHED') {
            return Auth::can('pages.publish');
        }
        return true;
    }

    public static function title(string $kind, array $owner): string
    {
        return (string) ($kind === 'product' ? $owner['name'] : ($kind === 'template' ? $owner['name'] : $owner['title']));
    }

    /** @return array{live: array, draft: ?array} */
    public static function layouts(string $kind, array $owner): array
    {
        $liveCol = $kind === 'template' ? 'layout' : 'builder_layout';
        $draftCol = $kind === 'template' ? 'draft' : 'builder_draft';
        $live = Layout::decode($owner[$liveCol] ?? null);
        $draft = ($owner[$draftCol] ?? null) !== null ? Layout::decode($owner[$draftCol]) : null;
        return ['live' => $live, 'draft' => $draft];
    }

    /** Save a draft, or publish (live layout + revision, draft cleared). */
    public static function save(string $kind, array $owner, array $layout, bool $publish): void
    {
        $layout = Layout::normalize($layout);
        $json = Layout::encode($layout);
        if (strlen($json) > Layout::MAX_BYTES) {
            throw new \App\Core\ValidationException(['layout' => 'This layout is too large to save.'], 'This layout is too large to save.');
        }
        $id = (int) $owner['id'];
        $user = Auth::user();
        $table = ['page' => 'pages', 'product' => 'products', 'template' => 'builder_templates'][$kind];
        $liveCol = $kind === 'template' ? 'layout' : 'builder_layout';
        $draftCol = $kind === 'template' ? 'draft' : 'builder_draft';

        if (!$publish) {
            DB::update($table, [$draftCol => $json], ['id' => $id]);
            return;
        }
        DB::transaction(static function () use ($kind, $table, $id, $json, $liveCol, $draftCol, $user): void {
            $data = [$liveCol => $json, $draftCol => null];
            if ($kind === 'page') {
                $data['editor'] = 'builder';
                $data['updated_by_id'] = $user['id'] ?? null;
            } elseif ($kind === 'product') {
                $data['page_layout'] = 'builder';
            } else {
                $data['updated_by_id'] = $user['id'] ?? null;
            }
            DB::update($table, $data, ['id' => $id]);
            DB::insert('builder_revisions', ['owner_type' => $kind, 'owner_id' => $id, 'layout' => $json, 'user_id' => $user['id'] ?? null]);
            $keep = DB::column('SELECT id FROM builder_revisions WHERE owner_type = ? AND owner_id = ? ORDER BY id DESC LIMIT ' . self::REVISIONS_KEPT, [$kind, $id]);
            if ($keep) {
                DB::query('DELETE FROM builder_revisions WHERE owner_type = ? AND owner_id = ? AND id < ?', [$kind, $id, min(array_map('intval', $keep))]);
            }
        });
    }

    public static function discardDraft(string $kind, array $owner): void
    {
        $table = ['page' => 'pages', 'product' => 'products', 'template' => 'builder_templates'][$kind];
        DB::update($table, [$kind === 'template' ? 'draft' : 'builder_draft' => null], ['id' => (int) $owner['id']]);
    }

    /** @return list<array{id:int, created_at:string, user:string}> */
    public static function revisions(string $kind, int $id): array
    {
        return array_map(static fn ($r) => ['id' => (int) $r['id'], 'created_at' => (string) $r['created_at'], 'user' => (string) ($r['name'] ?? '')], DB::all(
            'SELECT r.id, r.created_at, u.name FROM builder_revisions r LEFT JOIN users u ON u.id = r.user_id
              WHERE r.owner_type = ? AND r.owner_id = ? ORDER BY r.id DESC LIMIT ' . self::REVISIONS_KEPT,
            [$kind, $id]
        ));
    }

    public static function revision(string $kind, int $ownerId, int $revisionId): ?array
    {
        $json = DB::value('SELECT layout FROM builder_revisions WHERE id = ? AND owner_type = ? AND owner_id = ?', [$revisionId, $kind, $ownerId]);
        return is_string($json) ? Layout::decode($json) : null;
    }

    /**
     * The layout a product page shows, or null for the classic product page.
     * Previews use the draft when there is one.
     */
    public static function productLayout(array $product, bool $preview = false): ?array
    {
        $mode = (string) ($product['page_layout'] ?? 'template');
        if ($mode === 'builder') {
            $json = $preview && ($product['builder_draft'] ?? null) !== null ? $product['builder_draft'] : ($product['builder_layout'] ?? null);
            $layout = Layout::decode($json);
            return Layout::isEmpty($layout) ? null : $layout;
        }
        if ($mode === 'template') {
            $tpl = !empty($product['template_id'])
                ? DB::first("SELECT layout, draft FROM builder_templates WHERE id = ? AND kind = 'product'", [(int) $product['template_id']])
                : null;
            $tpl ??= DB::first("SELECT layout, draft FROM builder_templates WHERE kind = 'product' AND is_default = 1 ORDER BY id LIMIT 1");
            if ($tpl) {
                $layout = Layout::decode($preview && $tpl['draft'] !== null ? $tpl['draft'] : $tpl['layout']);
                return Layout::isEmpty($layout) ? null : $layout;
            }
        }
        return null;
    }

    /** The page's builder layout (draft first in previews), or null when it uses sections. */
    public static function pageLayout(array $page, bool $preview = false): ?array
    {
        // A preview shows the builder draft even before the page switches over.
        if ($preview && ($page['builder_draft'] ?? null) !== null) {
            return Layout::decode($page['builder_draft']);
        }
        if (($page['editor'] ?? 'sections') !== 'builder') {
            return null;
        }
        return Layout::decode($page['builder_layout'] ?? null);
    }

    /** A product to show in a product template while it is being designed. */
    public static function sampleProductId(): ?int
    {
        $id = DB::value("SELECT id FROM products WHERE deleted_at IS NULL ORDER BY status = 'PUBLISHED' DESC, is_featured DESC, sort_order, id LIMIT 1");
        return $id ? (int) $id : null;
    }
}
