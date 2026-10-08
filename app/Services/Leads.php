<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;

/** CRM vocabulary and shared lead operations. */
final class Leads
{
    public const STATUSES = [
        'NEW' => 'New', 'CONTACTED' => 'Contacted', 'QUALIFIED' => 'Qualified', 'PROPOSAL' => 'Proposal',
        'NEGOTIATION' => 'Negotiation', 'WON' => 'Won', 'LOST' => 'Lost', 'SPAM' => 'Spam',
    ];
    public const PIPELINE = ['NEW', 'CONTACTED', 'QUALIFIED', 'PROPOSAL', 'NEGOTIATION', 'WON', 'LOST'];
    public const PRIORITIES = ['LOW' => 'Low', 'MEDIUM' => 'Medium', 'HIGH' => 'High'];
    public const ACTIVITY_TYPES = ['CALL_LOGGED' => 'Call', 'EMAIL_SENT' => 'Email', 'MEETING_LOGGED' => 'Meeting'];

    public static function activity(int $leadId, string $type, string $summary, array $meta = []): void
    {
        DB::insert('lead_activities', [
            'lead_id' => $leadId,
            'type' => $type,
            'actor_id' => Auth::id(),
            'summary' => mb_substr($summary, 0, 2000),
            'meta' => $meta ?: null,
        ]);
    }

    /** Assign and notify the assignee (if email is set up and it's not yourself). */
    public static function assign(array $lead, ?int $userId): void
    {
        if ((int) ($lead['assigned_to_id'] ?? 0) === (int) $userId) {
            return;
        }
        DB::update('leads', ['assigned_to_id' => $userId], ['id' => $lead['id']]);
        $user = $userId ? DB::first("SELECT id, name, email FROM users WHERE id = ? AND status = 'ACTIVE' AND deleted_at IS NULL", [$userId]) : null;
        self::activity((int) $lead['id'], 'ASSIGNED', $user ? 'Assigned to ' . $user['name'] : 'Unassigned', ['to' => $userId]);
        $me = Auth::user();
        if ($user && $me && (int) $user['id'] !== (int) $me['id'] && Settings::get('email', 'notify_assignment')) {
            $msg = EmailTemplates::render('lead-assigned', [
                'user.name' => $user['name'], 'actor.name' => $me['name'], 'lead.name' => $lead['name'],
                'lead.email' => $lead['email'], 'lead.url' => absolute_url('/admin/leads/' . $lead['id']),
            ]);
            if ($msg) {
                Mailer::send($user['email'], $msg['subject'], $msg['html']);
            }
        }
    }

    public static function changeStatus(array $lead, string $status, ?string $lostReason = null): void
    {
        if (!isset(self::STATUSES[$status]) || $lead['status'] === $status) {
            return;
        }
        $row = ['status' => $status];
        if ($status === 'LOST' && $lostReason !== null) {
            $row['lost_reason'] = $lostReason;
        }
        DB::update('leads', $row, ['id' => $lead['id']]);
        self::activity((int) $lead['id'], 'STATUS_CHANGED', self::STATUSES[$lead['status']] . ' → ' . self::STATUSES[$status], ['from' => $lead['status'], 'to' => $status]);
    }

    /**
     * Turn a won lead into a customer, reusing an existing customer with the
     * same email rather than creating a duplicate.
     */
    public static function convert(array $lead): int
    {
        return DB::transaction(static function () use ($lead): int {
            $customer = DB::first('SELECT id FROM customers WHERE email = ? AND deleted_at IS NULL', [$lead['email']]);
            if ($customer) {
                $cid = (int) $customer['id'];
            } else {
                $cid = DB::insert('customers', [
                    'name' => $lead['name'], 'company' => $lead['company'], 'email' => $lead['email'], 'phone' => $lead['phone'],
                    'status' => 'ACTIVE', 'assigned_to_id' => $lead['assigned_to_id'],
                ]);
            }
            if ($lead['product_id'] && !DB::value('SELECT id FROM customer_products WHERE customer_id = ? AND product_id = ?', [$cid, $lead['product_id']])) {
                DB::insert('customer_products', ['customer_id' => $cid, 'product_id' => $lead['product_id'], 'quantity' => 1, 'starts_at' => date('Y-m-d')]);
            }
            DB::update('leads', ['customer_id' => $cid, 'status' => 'WON'], ['id' => $lead['id']]);
            self::activity((int) $lead['id'], 'CONVERTED', $customer ? 'Linked to existing customer #' . $cid : 'Converted to customer #' . $cid, ['customer' => $cid]);
            return $cid;
        });
    }

    /**
     * WHERE clause for the lead list filters, scoped to the admin's market.
     * @return array{0:string,1:list<mixed>}
     */
    public static function filterSql(array $f, int $countryId): array
    {
        $where = 'l.deleted_at IS NULL AND l.country_id = ?';
        $params = [$countryId];
        if (($f['q'] ?? '') !== '') {
            $where .= ' AND (l.name LIKE ? OR l.email LIKE ? OR l.company LIKE ? OR l.phone LIKE ? OR l.id = ?)';
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], (string) $f['q']) . '%';
            array_push($params, $like, $like, $like, $like, ctype_digit((string) $f['q']) ? (int) $f['q'] : 0);
        }
        if (isset(self::STATUSES[$f['status'] ?? ''])) {
            $where .= ' AND l.status = ?';
            $params[] = $f['status'];
        } elseif (empty($f['include_spam'])) {
            $where .= " AND l.status <> 'SPAM'";
        }
        if (isset(self::PRIORITIES[$f['priority'] ?? ''])) {
            $where .= ' AND l.priority = ?';
            $params[] = $f['priority'];
        }
        if (!empty($f['assignee'])) {
            if ($f['assignee'] === 'none') {
                $where .= ' AND l.assigned_to_id IS NULL';
            } else {
                $where .= ' AND l.assigned_to_id = ?';
                $params[] = (int) $f['assignee'];
            }
        }
        foreach (['product' => 'l.product_id', 'form' => 'l.form_id'] as $k => $col) {
            if (!empty($f[$k])) {
                $where .= " AND $col = ?";
                $params[] = (int) $f[$k];
            }
        }
        if (($f['source'] ?? '') !== '') {
            $where .= ' AND (l.utm_source = ? OR l.source = ?)';
            array_push($params, $f['source'], $f['source']);
        }
        if (($f['campaign'] ?? '') !== '') {
            $where .= ' AND l.utm_campaign = ?';
            $params[] = $f['campaign'];
        }
        if (!empty($f['from']) && strtotime((string) $f['from'])) {
            $where .= ' AND l.created_at >= ?';
            $params[] = date('Y-m-d 00:00:00', strtotime((string) $f['from']));
        }
        if (!empty($f['to']) && strtotime((string) $f['to'])) {
            $where .= ' AND l.created_at <= ?';
            $params[] = date('Y-m-d 23:59:59', strtotime((string) $f['to']));
        }
        return [$where, $params];
    }
}
