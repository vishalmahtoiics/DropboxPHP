<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Audit;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Core\ValidationException;
use App\Http\AdminController;
use App\Services\Money;
use App\Services\Options;

final class CustomerController extends AdminController
{
    public const STATUSES = ['PROSPECT' => 'Prospect', 'ACTIVE' => 'Active', 'INACTIVE' => 'Inactive', 'FORMER' => 'Former'];

    public function index(): Response
    {
        $this->authorize('customers.view');
        $where = 'c.deleted_at IS NULL';
        $params = [];
        $q = $this->request->queryStr('q');
        if ($q !== '') {
            $where .= ' AND (c.name LIKE ? OR c.email LIKE ? OR c.company LIKE ? OR c.phone LIKE ?)';
            array_push($params, "%$q%", "%$q%", "%$q%", "%$q%");
        }
        $status = $this->request->queryStr('status');
        if (isset(self::STATUSES[$status])) {
            $where .= ' AND c.status = ?';
            $params[] = $status;
        }
        $page = max(1, $this->request->int('page', 1));
        $total = (int) DB::value("SELECT COUNT(*) FROM customers c WHERE $where", $params);
        $rows = DB::all(
            "SELECT c.*, u.name AS owner, (SELECT COUNT(*) FROM customer_products cp WHERE cp.customer_id = c.id) AS products,
                    (SELECT MIN(renews_at) FROM customer_products cp WHERE cp.customer_id = c.id AND renews_at >= CURDATE()) AS next_renewal
               FROM customers c LEFT JOIN users u ON u.id = c.assigned_to_id WHERE $where ORDER BY c.created_at DESC LIMIT 30 OFFSET " . (($page - 1) * 30),
            $params
        );
        $counts = array_column(DB::all('SELECT status, COUNT(*) n FROM customers WHERE deleted_at IS NULL GROUP BY status'), 'n', 'status');
        $renewals = (int) DB::value('SELECT COUNT(*) FROM customer_products cp JOIN customers c ON c.id = cp.customer_id WHERE c.deleted_at IS NULL AND cp.renews_at BETWEEN CURDATE() AND CURDATE() + INTERVAL 30 DAY');
        return $this->screen('customers/index', ['title' => 'Customers', 'rows' => $rows, 'total' => $total, 'pageNo' => $page, 'pages' => (int) ceil($total / 30), 'q' => $q, 'status' => $status, 'counts' => $counts, 'renewals' => $renewals]);
    }

    public function create(): Response
    {
        $this->authorize('customers.create');
        return $this->screen('customers/form', ['title' => 'New customer', 'c' => null, 'staff' => Options::staff(), 'narrow' => true]);
    }

    public function store(): Response
    {
        $this->authorize('customers.create');
        $d = $this->data();
        if (DB::value('SELECT id FROM customers WHERE email = ? AND deleted_at IS NULL', [$d['email']])) {
            throw ValidationException::with('email', 'A customer with this email already exists.');
        }
        $id = DB::insert('customers', $d);
        Audit::record('created', 'Customer', $id, $d['name']);
        $this->flash('Customer added.');
        return $this->redirect('/admin/customers/' . $id);
    }

    public function show(string $id): Response
    {
        $this->authorize('customers.view');
        $c = $this->find($this->id($id));
        return $this->screen('customers/show', [
            'title' => $c['name'], 'c' => $c, 'staff' => Options::staff(), 'products' => Options::products(),
            'items' => DB::all('SELECT cp.*, p.name AS product_name FROM customer_products cp JOIN products p ON p.id = cp.product_id WHERE cp.customer_id = ? ORDER BY cp.created_at DESC', [$c['id']]),
            'notes' => DB::all('SELECT n.*, u.name AS author FROM customer_notes n LEFT JOIN users u ON u.id = n.author_id WHERE n.customer_id = ? ORDER BY n.created_at DESC', [$c['id']]),
            'leads' => DB::all('SELECT id, name, status, created_at, value FROM leads WHERE (customer_id = ? OR email = ?) AND deleted_at IS NULL ORDER BY created_at DESC', [$c['id'], $c['email']]),
        ]);
    }

    public function update(string $id): Response
    {
        $this->authorize('customers.edit');
        $c = $this->find($this->id($id));
        $d = $this->data();
        if (DB::value('SELECT id FROM customers WHERE email = ? AND id <> ? AND deleted_at IS NULL', [$d['email'], $c['id']])) {
            throw ValidationException::with('email', 'Another customer uses this email.');
        }
        DB::update('customers', $d, ['id' => $c['id']]);
        Audit::record('updated', 'Customer', $c['id'], $d['name'], array_intersect_key($c, $d), $d);
        $this->flash('Customer saved.');
        return $this->redirect('/admin/customers/' . $c['id']);
    }

    /** @return array<string,mixed> */
    private function data(): array
    {
        $d = $this->validate(['name' => 'required|max:160', 'email' => 'required|email', 'phone' => 'nullable|phone', 'company' => 'nullable|max:200',
            'website' => 'nullable|max:500', 'address' => 'nullable|max:2000', 'gstin' => 'nullable|max:30', 'status' => 'required|in:PROSPECT,ACTIVE,INACTIVE,FORMER', 'assigned_to_id' => 'nullable|int']);
        return ['name' => Sanitizer::line((string) $d['name'], 160), 'email' => $d['email'], 'phone' => $d['phone'] ?: null, 'company' => $d['company'] ?: null,
            'website' => Sanitizer::url((string) $d['website']) ?: null, 'address' => Sanitizer::text((string) $d['address']) ?: null,
            'gstin' => strtoupper((string) $d['gstin']) ?: null, 'status' => $d['status'], 'assigned_to_id' => $d['assigned_to_id'] ?: null];
    }

    public function note(string $id): Response
    {
        $this->authorize('customers.edit');
        $c = $this->find($this->id($id));
        $body = Sanitizer::text($this->request->str('body'), 5000);
        if ($body === '') {
            throw ValidationException::with('body', 'Write a note first.');
        }
        DB::insert('customer_notes', ['customer_id' => $c['id'], 'author_id' => $this->me['id'], 'body' => $body]);
        $this->flash('Note added.');
        return $this->redirect('/admin/customers/' . $c['id']);
    }

    public function addProduct(string $id): Response
    {
        $this->authorize('customers.edit');
        $c = $this->find($this->id($id));
        $d = $this->validate(['product_id' => 'required|int', 'quantity' => 'nullable|int', 'seats' => 'nullable|int', 'unit_price' => 'nullable|max:20', 'starts_at' => 'nullable|date', 'renews_at' => 'nullable|date', 'notes' => 'nullable|max:2000']);
        if (!DB::value('SELECT id FROM products WHERE id = ?', [$d['product_id']])) {
            throw ValidationException::with('product_id', 'Choose a product.');
        }
        DB::insert('customer_products', [
            'customer_id' => $c['id'], 'product_id' => $d['product_id'], 'quantity' => max(1, (int) ($d['quantity'] ?: 1)), 'seats' => $d['seats'],
            'unit_price' => Money::parse($d['unit_price']), 'starts_at' => $d['starts_at'] ? date('Y-m-d', strtotime((string) $d['starts_at'])) : null,
            'renews_at' => $d['renews_at'] ? date('Y-m-d', strtotime((string) $d['renews_at'])) : null, 'notes' => $d['notes'] ?: null,
        ]);
        Audit::record('added-product', 'Customer', $c['id']);
        $this->flash('Subscription added.');
        return $this->redirect('/admin/customers/' . $c['id']);
    }

    public function removeProduct(string $id, string $pid): Response
    {
        $this->authorize('customers.edit');
        $c = $this->find($this->id($id));
        DB::delete('customer_products', ['id' => $this->id($pid), 'customer_id' => $c['id']]);
        $this->flash('Subscription removed.');
        return $this->redirect('/admin/customers/' . $c['id']);
    }

    /** The list's "with selected" actions: set a status or move to the recycle bin. */
    public function bulk(): Response
    {
        $action = $this->request->str('action');
        $ids = $this->bulkIds();
        $status = str_starts_with($action, 'status:') ? substr($action, 7) : null;
        if (!$ids || ($action !== 'delete' && !isset(self::STATUSES[(string) $status]))) {
            $this->flash('Select customers and an action.', 'warning');
            return $this->back('/admin/customers');
        }
        $this->authorize($action === 'delete' ? 'customers.delete' : 'customers.edit');
        [$in, $params] = DB::in($ids);
        $done = 0;
        foreach (DB::all("SELECT id, name FROM customers WHERE id IN ($in) AND deleted_at IS NULL", $params) as $c) {
            if ($action === 'delete') {
                DB::update('customers', ['deleted_at' => now()], ['id' => $c['id']]);
                Audit::record('deleted', 'Customer', $c['id'], $c['name']);
            } else {
                DB::update('customers', ['status' => $status], ['id' => $c['id']]);
                Audit::record('updated', 'Customer', $c['id'], $c['name'] . ' → ' . $status);
            }
            $done++;
        }
        return $this->bulkDone('/admin/customers', $done, $done === 1 ? 'customer' : 'customers');
    }

    public function destroy(string $id): Response
    {
        $this->authorize('customers.delete');
        $c = $this->find($this->id($id));
        DB::update('customers', ['deleted_at' => now()], ['id' => $c['id']]);
        Audit::record('deleted', 'Customer', $c['id'], $c['name']);
        $this->flash('Customer moved to the recycle bin.');
        return $this->redirect('/admin/customers');
    }

    /** @return array<string,mixed> */
    private function find(int $id): array
    {
        $c = DB::first('SELECT c.*, u.name AS owner FROM customers c LEFT JOIN users u ON u.id = c.assigned_to_id WHERE c.id = ? AND c.deleted_at IS NULL', [$id]);
        if (!$c) {
            throw HttpException::notFound();
        }
        return $c;
    }
}
