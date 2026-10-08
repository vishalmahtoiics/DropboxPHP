<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Response;
use App\Core\Session;
use App\Http\AdminController;
use App\Services\Countries;

final class DashboardController extends AdminController
{
    public function index(): Response
    {
        if (!Auth::can('dashboard.view')) {
            return $this->page('welcome', ['title' => 'Welcome']);
        }
        $cid = (int) Countries::adminCurrent()['id'];
        $stats = [
            'leads_month' => (int) DB::value("SELECT COUNT(*) FROM leads WHERE deleted_at IS NULL AND country_id = ? AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')", [$cid]),
            'leads_prev' => (int) DB::value("SELECT COUNT(*) FROM leads WHERE deleted_at IS NULL AND country_id = ? AND created_at >= DATE_FORMAT(NOW() - INTERVAL 1 MONTH, '%Y-%m-01') AND created_at < DATE_FORMAT(NOW(), '%Y-%m-01')", [$cid]),
            'new_leads' => (int) DB::value("SELECT COUNT(*) FROM leads WHERE deleted_at IS NULL AND country_id = ? AND status = 'NEW'", [$cid]),
            'won' => (int) DB::value("SELECT COUNT(*) FROM leads WHERE deleted_at IS NULL AND country_id = ? AND status = 'WON' AND updated_at >= DATE_FORMAT(NOW(), '%Y-%m-01')", [$cid]),
            'pipeline_value' => (float) DB::value("SELECT COALESCE(SUM(value),0) FROM leads WHERE deleted_at IS NULL AND country_id = ? AND status IN ('QUALIFIED','PROPOSAL','NEGOTIATION')", [$cid]),
            'pages' => (int) DB::value("SELECT COUNT(*) FROM pages WHERE deleted_at IS NULL AND country_id = ? AND status = 'PUBLISHED'", [$cid]),
            'products' => (int) DB::value("SELECT COUNT(*) FROM products WHERE deleted_at IS NULL AND status = 'PUBLISHED'"),
            'posts' => (int) DB::value("SELECT COUNT(*) FROM blog_posts WHERE deleted_at IS NULL AND country_id = ? AND status = 'PUBLISHED'", [$cid]),
            'customers' => (int) DB::value('SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL'),
        ];
        $daily = DB::all(
            "SELECT DATE(created_at) d, COUNT(*) n FROM leads WHERE deleted_at IS NULL AND country_id = ? AND created_at >= CURDATE() - INTERVAL 29 DAY GROUP BY DATE(created_at)",
            [$cid]
        );
        $byDay = [];
        foreach ($daily as $row) {
            $byDay[$row['d']] = (int) $row['n'];
        }
        $series = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $series[] = ['date' => $d, 'count' => $byDay[$d] ?? 0];
        }
        $sources = DB::all(
            "SELECT COALESCE(NULLIF(utm_source,''), NULLIF(source,''), 'Direct') s, COUNT(*) n FROM leads
              WHERE deleted_at IS NULL AND country_id = ? AND created_at >= CURDATE() - INTERVAL 90 DAY GROUP BY s ORDER BY n DESC LIMIT 6",
            [$cid]
        );
        $statusCounts = DB::all("SELECT status, COUNT(*) n FROM leads WHERE deleted_at IS NULL AND country_id = ? GROUP BY status", [$cid]);
        $recent = Auth::can('leads.view') ? DB::all(
            'SELECT l.id, l.name, l.email, l.company, l.status, l.created_at, p.name product
               FROM leads l LEFT JOIN products p ON p.id = l.product_id
              WHERE l.deleted_at IS NULL AND l.country_id = ? ORDER BY l.created_at DESC LIMIT 8',
            [$cid]
        ) : [];
        $followUps = Auth::can('leads.view') ? DB::all(
            "SELECT id, name, follow_up_at, status FROM leads WHERE deleted_at IS NULL AND country_id = ? AND follow_up_at IS NOT NULL
               AND status NOT IN ('WON','LOST','SPAM') ORDER BY follow_up_at LIMIT 6",
            [$cid]
        ) : [];
        return $this->page('dashboard', compact('stats', 'series', 'sources', 'statusCounts', 'recent', 'followUps') + ['title' => 'Dashboard']);
    }

    public function switchCountry(): Response
    {
        $id = $this->request->int('country_id');
        if (in_array($id, Countries::accessibleIds(), true)) {
            Session::put('admin_country_id', $id);
        }
        return $this->back();
    }

    /** Global admin search across the modules the user can see. */
    public function search(): Response
    {
        $q = mb_substr($this->request->queryStr('q'), 0, 100);
        $results = [];
        if (mb_strlen($q) >= 2) {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q) . '%';
            if (Auth::can('pages.view')) {
                foreach (DB::all('SELECT id, title, slug FROM pages WHERE deleted_at IS NULL AND (title LIKE ? OR slug LIKE ?) LIMIT 8', [$like, $like]) as $r) {
                    $results['Pages'][] = ['title' => $r['title'], 'sub' => '/' . $r['slug'], 'href' => '/admin/pages/' . $r['id']];
                }
            }
            if (Auth::can('products.view')) {
                foreach (DB::all('SELECT id, name, slug FROM products WHERE deleted_at IS NULL AND (name LIKE ? OR slug LIKE ? OR sku LIKE ?) LIMIT 8', [$like, $like, $like]) as $r) {
                    $results['Products'][] = ['title' => $r['name'], 'sub' => $r['slug'], 'href' => '/admin/products/' . $r['id']];
                }
            }
            if (Auth::can('blog.view')) {
                foreach (DB::all('SELECT id, title, slug FROM blog_posts WHERE deleted_at IS NULL AND (title LIKE ? OR slug LIKE ?) LIMIT 8', [$like, $like]) as $r) {
                    $results['Articles'][] = ['title' => $r['title'], 'sub' => $r['slug'], 'href' => '/admin/blog/' . $r['id']];
                }
            }
            if (Auth::can('leads.view')) {
                foreach (DB::all('SELECT id, name, email, company FROM leads WHERE deleted_at IS NULL AND (name LIKE ? OR email LIKE ? OR company LIKE ? OR phone LIKE ?) ORDER BY created_at DESC LIMIT 10', [$like, $like, $like, $like]) as $r) {
                    $results['Leads'][] = ['title' => $r['name'], 'sub' => $r['email'] . ($r['company'] ? ' · ' . $r['company'] : ''), 'href' => '/admin/leads/' . $r['id']];
                }
            }
            if (Auth::can('customers.view')) {
                foreach (DB::all('SELECT id, name, email, company FROM customers WHERE deleted_at IS NULL AND (name LIKE ? OR email LIKE ? OR company LIKE ?) LIMIT 8', [$like, $like, $like]) as $r) {
                    $results['Customers'][] = ['title' => $r['name'], 'sub' => $r['email'], 'href' => '/admin/customers/' . $r['id']];
                }
            }
            if (Auth::can('forms.view')) {
                foreach (DB::all('SELECT id, name, slug FROM forms WHERE deleted_at IS NULL AND (name LIKE ? OR slug LIKE ?) LIMIT 5', [$like, $like]) as $r) {
                    $results['Forms'][] = ['title' => $r['name'], 'sub' => $r['slug'], 'href' => '/admin/forms/' . $r['id']];
                }
            }
        }
        return $this->page('search', ['title' => 'Search', 'q' => $q, 'results' => $results]);
    }
}
