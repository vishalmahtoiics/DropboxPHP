<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\DB;
use App\Core\Response;
use App\Http\AdminController;
use App\Services\Countries;
use App\Services\Leads;

/** Lead reports by date, source, campaign, product, landing page, form, placement and owner. */
final class ReportController extends AdminController
{
    public function index(): Response
    {
        $this->authorize('leads.view');
        $country = Countries::adminCurrent();
        $range = $this->request->queryStr('range', '30');
        $to = date('Y-m-d');
        $from = match ($range) {
            '7' => date('Y-m-d', strtotime('-6 days')),
            '90' => date('Y-m-d', strtotime('-89 days')),
            '365' => date('Y-m-d', strtotime('-364 days')),
            'custom' => date('Y-m-d', strtotime($this->request->queryStr('from') ?: '-29 days') ?: time()),
            default => date('Y-m-d', strtotime('-29 days')),
        };
        if ($range === 'custom' && ($t = strtotime($this->request->queryStr('to')))) {
            $to = date('Y-m-d', $t);
        }
        $base = 'l.deleted_at IS NULL AND l.country_id = ? AND l.created_at BETWEEN ? AND ?';
        $p = [(int) $country['id'], $from . ' 00:00:00', $to . ' 23:59:59'];
        $group = static fn (string $expr, int $limit = 10) => DB::all(
            "SELECT $expr AS label, COUNT(*) AS n, SUM(l.status = 'WON') AS won, COALESCE(SUM(CASE WHEN l.status = 'WON' THEN l.value END), 0) AS revenue
               FROM leads l LEFT JOIN products pr ON pr.id = l.product_id LEFT JOIN pages pg ON pg.id = l.landing_page_id
          LEFT JOIN forms f ON f.id = l.form_id LEFT JOIN users u ON u.id = l.assigned_to_id
              WHERE $base GROUP BY label ORDER BY n DESC LIMIT $limit",
            $p
        );
        $total = (int) DB::value("SELECT COUNT(*) FROM leads l WHERE $base", $p);
        $won = (int) DB::value("SELECT COUNT(*) FROM leads l WHERE $base AND l.status = 'WON'", $p);
        $spam = (int) DB::value("SELECT COUNT(*) FROM leads l WHERE $base AND l.status = 'SPAM'", $p);
        $revenue = (float) DB::value("SELECT COALESCE(SUM(value),0) FROM leads l WHERE $base AND l.status = 'WON'", $p);
        $days = (int) ((strtotime($to) - strtotime($from)) / 86400) + 1;
        $byDay = array_column(DB::all("SELECT DATE(l.created_at) d, COUNT(*) n FROM leads l WHERE $base GROUP BY d", $p), 'n', 'd');
        $series = [];
        $bucket = $days > 120 ? 7 : 1;
        for ($i = 0; $i < $days; $i += $bucket) {
            $n = 0;
            for ($j = 0; $j < $bucket && $i + $j < $days; $j++) {
                $n += (int) ($byDay[date('Y-m-d', strtotime($from . " +" . ($i + $j) . " days"))] ?? 0);
            }
            $series[] = ['label' => date('d M', strtotime("$from +$i days")), 'n' => $n];
        }
        $funnel = [];
        $statusCounts = array_column(DB::all("SELECT l.status, COUNT(*) n FROM leads l WHERE $base GROUP BY l.status", $p), 'n', 'status');
        $reached = 0;
        foreach (array_reverse(['NEW', 'CONTACTED', 'QUALIFIED', 'PROPOSAL', 'NEGOTIATION', 'WON']) as $st) {
            $reached += (int) ($statusCounts[$st] ?? 0);
            $funnel[$st] = $reached;
        }
        $funnel = array_reverse($funnel, true);
        $funnel['NEW'] = max($funnel['NEW'] ?? 0, $total - $spam - (int) ($statusCounts['LOST'] ?? 0) + (int) ($statusCounts['LOST'] ?? 0));

        return $this->screen('leads/reports', [
            'title' => 'Reports', 'range' => $range, 'from' => $from, 'to' => $to,
            'total' => $total, 'won' => $won, 'spam' => $spam, 'revenue' => $revenue, 'series' => $series, 'funnel' => $funnel,
            'statusCounts' => $statusCounts,
            'tables' => [
                'Source' => $group("COALESCE(NULLIF(l.utm_source,''), NULLIF(l.source,''), 'Direct')"),
                'Medium' => $group("COALESCE(NULLIF(l.utm_medium,''), '(none)')"),
                'Campaign' => $group("COALESCE(NULLIF(l.utm_campaign,''), '(no campaign)')"),
                'First-touch source' => $group("COALESCE(NULLIF(l.first_utm_source,''), 'Direct')"),
                'Product' => $group("COALESCE(pr.name, 'No product')"),
                'Landing page' => $group("COALESCE(pg.title, '(unknown)')"),
                'Form' => $group("COALESCE(f.name, '(none)')"),
                'Placement' => $group("COALESCE(NULLIF(l.cta_location,''), '(not recorded)')"),
                'Owner' => $group("COALESCE(u.name, 'Unassigned')"),
            ],
        ]);
    }
}
