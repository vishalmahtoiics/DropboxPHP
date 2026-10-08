<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Core\ValidationException;
use App\Http\AdminController;
use App\Services\Countries;
use App\Services\Csv;
use App\Services\Leads;
use App\Services\Money;
use App\Services\Options;

final class LeadController extends AdminController
{
    private const SORTS = ['created_at', 'name', 'status', 'priority', 'value', 'follow_up_at'];

    /** @return array<string,string> */
    private function filters(): array
    {
        $f = [];
        foreach (['q', 'status', 'priority', 'assignee', 'product', 'form', 'source', 'campaign', 'from', 'to', 'include_spam'] as $k) {
            $f[$k] = $this->request->queryStr($k);
        }
        return $f;
    }

    public function index(): Response
    {
        $this->authorize('leads.view');
        $country = Countries::adminCurrent();
        $f = $this->filters();
        [$where, $params] = Leads::filterSql($f, (int) $country['id']);
        $sort = in_array($this->request->queryStr('sort'), self::SORTS, true) ? $this->request->queryStr('sort') : 'created_at';
        $dir = $this->request->queryStr('dir') === 'asc' ? 'ASC' : 'DESC';
        $page = max(1, $this->request->int('page', 1));
        $per = 30;
        $total = (int) DB::value("SELECT COUNT(*) FROM leads l WHERE $where", $params);
        $rows = DB::all(
            "SELECT l.*, p.name AS product_name, u.name AS assignee_name
               FROM leads l LEFT JOIN products p ON p.id = l.product_id LEFT JOIN users u ON u.id = l.assigned_to_id
              WHERE $where ORDER BY l.`$sort` $dir, l.id DESC LIMIT $per OFFSET " . (($page - 1) * $per),
            $params
        );
        $statusCounts = array_column(DB::all('SELECT status, COUNT(*) n FROM leads WHERE deleted_at IS NULL AND country_id = ? GROUP BY status', [$country['id']]), 'n', 'status');
        $sources = DB::column("SELECT DISTINCT COALESCE(NULLIF(utm_source,''), source) s FROM leads WHERE deleted_at IS NULL AND country_id = ? AND COALESCE(NULLIF(utm_source,''), source) IS NOT NULL ORDER BY s LIMIT 100", [$country['id']]);
        $campaigns = DB::column("SELECT DISTINCT utm_campaign FROM leads WHERE deleted_at IS NULL AND country_id = ? AND utm_campaign IS NOT NULL AND utm_campaign <> '' ORDER BY utm_campaign LIMIT 100", [$country['id']]);
        return $this->screen('leads/index', [
            'title' => 'Leads', 'rows' => $rows, 'total' => $total, 'pageNo' => $page, 'pages' => (int) ceil($total / $per),
            'f' => $f, 'sort' => $sort, 'dir' => strtolower($dir), 'statusCounts' => $statusCounts,
            'staff' => Options::staff(), 'products' => Options::products(), 'forms' => Options::formsById(),
            'sources' => $sources, 'campaigns' => $campaigns,
        ]);
    }

    public function export(): Response
    {
        $this->authorize('leads.export');
        $country = Countries::adminCurrent();
        [$where, $params] = Leads::filterSql($this->filters(), (int) $country['id']);
        $rows = DB::all(
            "SELECT l.*, p.name AS product_name, u.name AS assignee_name, f.name AS form_name, pg.title AS page_title
               FROM leads l LEFT JOIN products p ON p.id = l.product_id LEFT JOIN users u ON u.id = l.assigned_to_id
          LEFT JOIN forms f ON f.id = l.form_id LEFT JOIN pages pg ON pg.id = l.landing_page_id
              WHERE $where ORDER BY l.created_at DESC LIMIT 50000",
            $params
        );
        $csv = Csv::build(
            ['ID', 'Created', 'Name', 'Email', 'Phone', 'Company', 'Job title', 'Status', 'Priority', 'Value', 'Product', 'Assignee', 'Source', 'CTA', 'CTA location',
                'UTM source', 'UTM medium', 'UTM campaign', 'UTM term', 'UTM content', 'First UTM source', 'First UTM medium', 'First UTM campaign',
                'Landing page', 'Landing URL', 'Form', 'Marketing consent', 'Follow-up', 'Message'],
            array_map(static fn ($l) => [$l['id'], $l['created_at'], $l['name'], $l['email'], $l['phone'], $l['company'], $l['job_title'], $l['status'], $l['priority'],
                $l['value'], $l['product_name'], $l['assignee_name'], $l['source'], $l['cta_label'], $l['cta_location'], $l['utm_source'], $l['utm_medium'],
                $l['utm_campaign'], $l['utm_term'], $l['utm_content'], $l['first_utm_source'], $l['first_utm_medium'], $l['first_utm_campaign'],
                $l['page_title'], $l['landing_url'], $l['form_name'], $l['marketing_consent'] ? 'Yes' : 'No', $l['follow_up_at'], $l['message']], $rows)
        );
        Audit::record('exported', 'Lead', null, count($rows) . ' leads exported to CSV (' . $country['name'] . ')');
        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="leads-' . strtolower((string) $country['code']) . '-' . date('Y-m-d') . '.csv"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function bulk(): Response
    {
        $ids = array_values(array_filter(array_map('intval', $this->request->arr('ids'))));
        $action = $this->request->str('action');
        if ($ids === []) {
            $this->flash('Select at least one lead.', 'warning');
            return $this->back('/admin/leads');
        }
        $country = Countries::adminCurrent();
        [$in, $inParams] = DB::in($ids);
        $leads = DB::all("SELECT * FROM leads WHERE id IN ($in) AND country_id = ? AND deleted_at IS NULL", [...$inParams, $country['id']]);
        $done = 0;
        DB::transaction(function () use ($leads, $action, &$done): void {
            foreach ($leads as $lead) {
                if (str_starts_with($action, 'status:')) {
                    $this->authorize('leads.edit');
                    Leads::changeStatus($lead, substr($action, 7));
                } elseif (str_starts_with($action, 'assign:')) {
                    $this->authorize('leads.assign');
                    $uid = substr($action, 7);
                    Leads::assign($lead, $uid === 'none' ? null : (int) $uid);
                } elseif ($action === 'delete') {
                    $this->authorize('leads.delete');
                    DB::update('leads', ['deleted_at' => now()], ['id' => $lead['id']]);
                } else {
                    continue;
                }
                $done++;
            }
        });
        Audit::record('bulk', 'Lead', null, "$action on $done lead(s)");
        $this->flash("$done lead(s) updated.");
        return $this->back('/admin/leads');
    }

    public function create(): Response
    {
        $this->authorize('leads.create');
        return $this->screen('leads/form', ['title' => 'New lead', 'lead' => null, 'staff' => Options::staff(), 'products' => Options::products(), 'narrow' => true]);
    }

    public function store(): Response
    {
        $this->authorize('leads.create');
        $country = Countries::adminCurrent();
        $d = $this->leadData();
        $d['country_id'] = $country['id'];
        $d['source'] = $d['source'] ?: 'Manual entry';
        $id = DB::insert('leads', $d);
        Leads::activity($id, 'CREATED', 'Lead added manually by ' . $this->me['name']);
        Audit::record('created', 'Lead', $id, $d['name']);
        $this->flash('Lead added.');
        return $this->redirect('/admin/leads/' . $id);
    }

    public function show(string $id): Response
    {
        $this->authorize('leads.view');
        $lead = $this->find($this->id($id));
        $lead = DB::first(
            'SELECT l.*, p.name AS product_name, u.name AS assignee_name, f.name AS form_name, pg.title AS page_title, c.name AS customer_name
               FROM leads l LEFT JOIN products p ON p.id = l.product_id LEFT JOIN users u ON u.id = l.assigned_to_id
          LEFT JOIN forms f ON f.id = l.form_id LEFT JOIN pages pg ON pg.id = l.landing_page_id LEFT JOIN customers c ON c.id = l.customer_id
              WHERE l.id = ?',
            [$lead['id']]
        );
        $timeline = DB::all(
            "SELECT 'activity' AS kind, a.type, a.summary AS body, a.created_at, u.name AS actor FROM lead_activities a LEFT JOIN users u ON u.id = a.actor_id WHERE a.lead_id = ?
             UNION ALL
             SELECT 'note', 'NOTE', n.body, n.created_at, u.name FROM lead_notes n LEFT JOIN users u ON u.id = n.author_id WHERE n.lead_id = ?
             ORDER BY created_at DESC",
            [$lead['id'], $lead['id']]
        );
        $submissions = DB::all('SELECT * FROM form_submissions WHERE lead_id = ? ORDER BY created_at DESC', [$lead['id']]);
        $consents = DB::all('SELECT * FROM consent_records WHERE lead_id = ? ORDER BY created_at DESC', [$lead['id']]);
        $others = DB::all('SELECT id, name, status, created_at FROM leads WHERE email = ? AND id <> ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 10', [$lead['email'], $lead['id']]);
        return $this->screen('leads/show', [
            'title' => $lead['name'], 'lead' => $lead, 'timeline' => $timeline, 'submissions' => $submissions, 'consents' => $consents,
            'others' => $others, 'staff' => Options::staff(), 'products' => Options::products(),
        ]);
    }

    public function update(string $id): Response
    {
        $this->authorize('leads.edit');
        $lead = $this->find($this->id($id));
        $d = $this->leadData();
        $status = $this->request->str('status');
        $assignee = $this->request->str('assigned_to_id');
        DB::transaction(function () use ($lead, $d, $status, $assignee): void {
            DB::update('leads', $d, ['id' => $lead['id']]);
            $changed = array_keys(array_diff_assoc(array_map('strval', array_intersect_key($d, $lead)), array_map('strval', array_intersect_key($lead, $d))));
            if ($changed) {
                Leads::activity((int) $lead['id'], 'UPDATED', 'Updated ' . implode(', ', str_replace('_', ' ', $changed)));
            }
            if ($status !== '') {
                Leads::changeStatus($lead, $status, $this->request->str('lost_reason') ?: null);
            }
            if (Auth::can('leads.assign')) {
                Leads::assign($lead, $assignee === '' ? null : (int) $assignee);
            }
            if ($d['follow_up_at'] && $d['follow_up_at'] !== $lead['follow_up_at']) {
                Leads::activity((int) $lead['id'], 'FOLLOW_UP_SET', 'Follow-up set for ' . format_datetime($d['follow_up_at']));
            }
        });
        Audit::record('updated', 'Lead', $lead['id'], $d['name']);
        $this->flash('Lead saved.');
        return $this->redirect('/admin/leads/' . $lead['id']);
    }

    /** @return array<string,mixed> */
    private function leadData(): array
    {
        $d = $this->validate([
            'name' => 'required|max:160', 'email' => 'required|email', 'phone' => 'nullable|phone', 'company' => 'nullable|max:200',
            'job_title' => 'nullable|max:120', 'message' => 'nullable|max:5000', 'priority' => 'required|in:LOW,MEDIUM,HIGH',
            'value' => 'nullable|max:20', 'product_id' => 'nullable|int', 'follow_up_at' => 'nullable|date', 'source' => 'nullable|max:120',
            'campaign' => 'nullable|max:160',
        ]);
        $value = Money::parse($d['value']);
        if ($d['value'] && $value === null) {
            throw ValidationException::with('value', 'Enter an amount like 45000.');
        }
        return [
            'name' => Sanitizer::line((string) $d['name'], 160), 'email' => $d['email'], 'phone' => $d['phone'] ?: null,
            'company' => $d['company'] ?: null, 'job_title' => $d['job_title'] ?: null, 'message' => Sanitizer::text((string) $d['message']) ?: null,
            'priority' => $d['priority'], 'value' => $value, 'product_id' => $d['product_id'] ?: null,
            'follow_up_at' => $d['follow_up_at'] ? date('Y-m-d H:i:s', strtotime((string) $d['follow_up_at'])) : null,
            'source' => $d['source'] ?: null, 'campaign' => $d['campaign'] ?: null,
        ];
    }

    public function note(string $id): Response
    {
        $this->authorize('leads.edit');
        $lead = $this->find($this->id($id));
        $body = Sanitizer::text($this->request->str('body'), 5000);
        if ($body === '') {
            throw ValidationException::with('body', 'Write a note first.');
        }
        DB::insert('lead_notes', ['lead_id' => $lead['id'], 'author_id' => $this->me['id'], 'body' => $body]);
        Leads::activity((int) $lead['id'], 'NOTE_ADDED', 'Note added');
        $this->flash('Note added.');
        return $this->redirect('/admin/leads/' . $lead['id'] . '#timeline');
    }

    /** Log a call, email or meeting. */
    public function activity(string $id): Response
    {
        $this->authorize('leads.edit');
        $lead = $this->find($this->id($id));
        $type = $this->request->str('type');
        if (!isset(Leads::ACTIVITY_TYPES[$type])) {
            throw ValidationException::with('type', 'Choose what happened.');
        }
        $summary = Sanitizer::text($this->request->str('summary'), 2000);
        Leads::activity((int) $lead['id'], $type, Leads::ACTIVITY_TYPES[$type] . ($summary !== '' ? ': ' . $summary : ''));
        if ($lead['status'] === 'NEW') {
            Leads::changeStatus($lead, 'CONTACTED');
        }
        $this->flash(Leads::ACTIVITY_TYPES[$type] . ' logged.');
        return $this->redirect('/admin/leads/' . $lead['id'] . '#timeline');
    }

    /** Record a withdrawal of marketing (or all) consent, kept as evidence. */
    public function consent(string $id): Response
    {
        $this->authorize('leads.manageConsent');
        $lead = $this->find($this->id($id));
        $scope = $this->request->str('scope') === 'all' ? 'all' : 'marketing';
        DB::transaction(static function () use ($lead, $scope): void {
            DB::update('leads', ['marketing_consent' => 0], ['id' => $lead['id']]);
            DB::query('UPDATE consent_records SET withdrawn_at = NOW() WHERE lead_id = ? AND withdrawn_at IS NULL' . ($scope === 'marketing' ? ' AND marketing_consent = 1' : ''), [$lead['id']]);
            Leads::activity((int) $lead['id'], 'UPDATED', $scope === 'all' ? 'All consent withdrawn' : 'Marketing consent withdrawn');
        });
        Audit::record('consent-withdrawn', 'Lead', $lead['id'], $scope);
        $this->flash('Withdrawal recorded. The original consent records are kept as evidence.');
        return $this->redirect('/admin/leads/' . $lead['id']);
    }

    public function convert(string $id): Response
    {
        $this->authorizeAny(['customers.create']);
        $this->authorize('leads.edit');
        $lead = $this->find($this->id($id));
        $customerId = Leads::convert($lead);
        Audit::record('converted', 'Lead', $lead['id'], 'Customer #' . $customerId);
        $this->flash('Lead marked as won and linked to a customer.');
        return $this->redirect('/admin/customers/' . $customerId);
    }

    public function destroy(string $id): Response
    {
        $this->authorize('leads.delete');
        $lead = $this->find($this->id($id));
        DB::update('leads', ['deleted_at' => now()], ['id' => $lead['id']]);
        Audit::record('deleted', 'Lead', $lead['id'], $lead['name']);
        $this->flash('Lead moved to the recycle bin.');
        return $this->redirect('/admin/leads');
    }

    /** @return array<string,mixed> */
    private function find(int $id): array
    {
        $lead = DB::first('SELECT * FROM leads WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$lead) {
            throw HttpException::notFound();
        }
        Countries::assertAccess((int) $lead['country_id']);
        return $lead;
    }
}
