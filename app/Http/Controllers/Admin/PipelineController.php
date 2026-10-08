<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\DB;
use App\Core\Response;
use App\Http\AdminController;
use App\Services\Countries;
use App\Services\Leads;

/** Kanban board. A move persists the new status and the destination column's full order. */
final class PipelineController extends AdminController
{
    public function index(): Response
    {
        $this->authorize('leads.view');
        $country = Countries::adminCurrent();
        $where = 'l.deleted_at IS NULL AND l.country_id = ?';
        $params = [(int) $country['id']];
        if ($this->request->queryStr('mine') === '1') {
            $where .= ' AND l.assigned_to_id = ?';
            $params[] = $this->me['id'];
        }
        $rows = DB::all(
            "SELECT l.id, l.name, l.company, l.status, l.priority, l.value, l.follow_up_at, l.created_at, p.name AS product_name, u.name AS assignee
               FROM leads l LEFT JOIN products p ON p.id = l.product_id LEFT JOIN users u ON u.id = l.assigned_to_id
              WHERE $where AND l.status <> 'SPAM' AND (l.status NOT IN ('WON','LOST') OR l.updated_at >= NOW() - INTERVAL 60 DAY)
              ORDER BY l.pipeline_order, l.created_at DESC LIMIT 1000",
            $params
        );
        $columns = array_fill_keys(Leads::PIPELINE, []);
        foreach ($rows as $r) {
            $columns[$r['status']][] = $r;
        }
        return $this->screen('leads/pipeline', ['title' => 'Pipeline', 'columns' => $columns]);
    }

    public function move(): Response
    {
        $this->authorize('leads.edit');
        $id = $this->request->int('id');
        $status = $this->request->str('status');
        if (!in_array($status, Leads::PIPELINE, true)) {
            return $this->json(['ok' => false, 'error' => 'Unknown stage.'], 422);
        }
        $lead = DB::first('SELECT * FROM leads WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$lead) {
            return $this->json(['ok' => false, 'error' => 'That lead no longer exists.'], 404);
        }
        Countries::assertAccess((int) $lead['country_id']);
        $order = array_values(array_filter(array_map('intval', explode(',', $this->request->str('order')))));
        DB::transaction(static function () use ($lead, $status, $order): void {
            Leads::changeStatus($lead, $status);
            foreach ($order as $i => $lid) {
                DB::query('UPDATE leads SET pipeline_order = ? WHERE id = ? AND country_id = ? AND status = ?', [$i, $lid, $lead['country_id'], $status]);
            }
        });
        return $this->json(['ok' => true]);
    }
}
