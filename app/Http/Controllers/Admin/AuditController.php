<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\DB;
use App\Core\Response;
use App\Http\AdminController;
use App\Services\Options;

final class AuditController extends AdminController
{
    public function index(): Response
    {
        $this->authorize('audit.view');
        $where = '1 = 1';
        $params = [];
        foreach (['entity' => 'entity', 'action' => 'action'] as $k => $col) {
            if (($v = $this->request->queryStr($k)) !== '') {
                $where .= " AND $col = ?";
                $params[] = $v;
            }
        }
        if ($actor = $this->request->int('actor')) {
            $where .= ' AND actor_id = ?';
            $params[] = $actor;
        }
        if (($q = $this->request->queryStr('q')) !== '') {
            $where .= ' AND (summary LIKE ? OR entity_id = ? OR actor_email LIKE ?)';
            array_push($params, "%$q%", $q, "%$q%");
        }
        foreach (['from' => '>=', 'to' => '<='] as $k => $op) {
            if (($v = $this->request->queryStr($k)) !== '' && strtotime($v)) {
                $where .= " AND created_at $op ?";
                $params[] = date($k === 'from' ? 'Y-m-d 00:00:00' : 'Y-m-d 23:59:59', strtotime($v));
            }
        }
        $page = max(1, $this->request->int('page', 1));
        $total = (int) DB::value("SELECT COUNT(*) FROM audit_logs WHERE $where", $params);
        $rows = DB::all("SELECT * FROM audit_logs WHERE $where ORDER BY id DESC LIMIT 50 OFFSET " . (($page - 1) * 50), $params);
        return $this->screen('staff/audit', [
            'title' => 'Audit log', 'rows' => $rows, 'total' => $total, 'pageNo' => $page, 'pages' => (int) ceil($total / 50),
            'entities' => DB::column('SELECT DISTINCT entity FROM audit_logs ORDER BY entity'),
            'actions' => DB::column('SELECT DISTINCT action FROM audit_logs ORDER BY action'),
            'staff' => Options::staff(),
            'detail' => $this->request->int('show') ? DB::first('SELECT * FROM audit_logs WHERE id = ?', [$this->request->int('show')]) : null,
        ]);
    }
}
