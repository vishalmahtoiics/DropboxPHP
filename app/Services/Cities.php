<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\ValidationException;

/**
 * Cities and the City Page Generator.
 *
 * A city owns the first segment of its market's addresses: /delhi is its
 * landing page and /delhi/anything are its other pages. They are ordinary
 * pages edited in the page builder; the generator only copies a template page
 * into many cities at once, filling {{city.*}} placeholders. Every copy is
 * independent from the moment it exists — nothing syncs it afterwards.
 */
final class Cities
{
    public const TOKENS = ['city.name', 'city.slug', 'city.state', 'city.phone', 'city.email', 'city.address', 'country.name'];

    /** @return array<string,string> token => value */
    public static function tokens(array $city, array $country): array
    {
        $s = json_decode((string) ($city['settings'] ?? ''), true) ?: [];
        return [
            '{{city.name}}' => (string) $city['name'],
            '{{city.slug}}' => (string) $city['slug'],
            '{{city.state}}' => (string) ($city['state'] ?? ''),
            '{{city.phone}}' => (string) ($s['phone'] ?? Countries::setting($country, 'sales_phone', '')),
            '{{city.email}}' => (string) ($s['email'] ?? Countries::setting($country, 'sales_email', '')),
            '{{city.address}}' => (string) ($s['address'] ?? ''),
            '{{country.name}}' => (string) $country['name'],
        ];
    }

    /** Replace tokens in every string of a decoded JSON structure. */
    public static function fill(mixed $value, array $tokens): mixed
    {
        if (is_string($value)) {
            return strtr($value, $tokens);
        }
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = self::fill($v, $tokens);
            }
        }
        return $value;
    }

    /**
     * Generate pages from a template for the given cities.
     *
     * @param list<int> $cityIds
     * @return array{created: list<array{city:string,id:int,slug:string}>, skipped: list<string>}
     */
    public static function generate(int $templateId, array $cityIds, string $slugPattern, string $titlePattern, bool $publish, int $userId): array
    {
        $template = DB::first('SELECT * FROM pages WHERE id = ? AND deleted_at IS NULL', [$templateId]);
        if (!$template) {
            throw ValidationException::with('template_id', 'Choose a template page.');
        }
        if (!str_contains($slugPattern, '{{city.slug}}')) {
            throw ValidationException::with('slug_pattern', 'The address must contain {{city.slug}}.');
        }
        $country = Countries::find((int) $template['country_id']);
        $sections = DB::all('SELECT * FROM page_sections WHERE page_id = ? ORDER BY sort_order', [$templateId]);
        $batch = bin2hex(random_bytes(8));
        $created = [];
        $skipped = [];
        foreach ($cityIds as $cid) {
            $city = DB::first('SELECT * FROM cities WHERE id = ? AND country_id = ?', [(int) $cid, $template['country_id']]);
            if (!$city) {
                continue;
            }
            $tokens = self::tokens($city, $country);
            $slug = trim(strtr($slugPattern, $tokens), '/');
            if (!preg_match('#^[a-z0-9]+(?:[-/][a-z0-9]+)*$#', $slug) || explode('/', $slug)[0] !== $city['slug']) {
                $skipped[] = $city['name'] . ': the address must start with /' . $city['slug'];
                continue;
            }
            if (DB::value('SELECT id FROM pages WHERE country_id = ? AND slug = ?', [$template['country_id'], $slug])) {
                $skipped[] = $city['name'] . ': /' . $slug . ' already exists';
                continue;
            }
            // One transaction per city: a failure in one never leaves another half-made.
            $id = DB::transaction(static function () use ($template, $tokens, $slug, $titlePattern, $publish, $userId, $city, $templateId, $batch, $sections): int {
                $row = $template;
                unset($row['id'], $row['created_at'], $row['updated_at']);
                foreach (['seo_title', 'seo_description', 'og_title', 'og_description', 'primary_keywords'] as $k) {
                    $row[$k] = $row[$k] === null ? null : strtr((string) $row[$k], $tokens);
                }
                $row['title'] = strtr($titlePattern !== '' ? $titlePattern : $template['title'], $tokens);
                $row['slug'] = $slug;
                $row['status'] = $publish ? 'PUBLISHED' : 'DRAFT';
                $row['published_at'] = $publish ? now() : null;
                $row['is_homepage'] = 0;
                $row['city_id'] = $city['id'];
                $row['generated_from_id'] = $templateId;
                $row['generation_batch'] = $batch;
                $row['generated_at'] = now();
                $row['created_by_id'] = $userId;
                $row['updated_by_id'] = $userId;
                $id = DB::insert('pages', $row);
                foreach ($sections as $s) {
                    DB::insert('page_sections', [
                        'page_id' => $id, 'block_type' => $s['block_type'], 'name' => $s['name'], 'sort_order' => $s['sort_order'], 'is_visible' => $s['is_visible'],
                        'content' => self::fill(json_decode((string) $s['content'], true) ?: [], $tokens),
                        'settings' => json_decode((string) $s['settings'], true) ?: [],
                    ]);
                }
                return $id;
            });
            $created[] = ['city' => $city['name'], 'id' => $id, 'slug' => $slug];
        }
        return ['created' => $created, 'skipped' => $skipped];
    }
}
