<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Client;
use App\Models\Collection;
use App\Models\Invoice;
use App\Models\PaymentTerm;
use App\Models\StockOut;
use App\Models\Supplier;
use App\Models\Warranty;
use App\Services\AuthorizationService;
use Throwable;

/**
 * Handles IATMS Billing (Invoices & Collections) and Warranty Tracking & Claims.
 */
final class FinanceBillingController extends Controller
{
    public function invoicesIndex(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canViewIatmsFinance($user)) {
            $this->denyAccess($request, 'Unauthorized access to Invoices.');
        }

        $page = max(1, (int) $request->query('page', '1'));
        $filters = [
            'search' => (string) $request->query('search', ''),
            'invoice_type' => (string) $request->query('invoice_type', ''),
            'status' => (string) $request->query('status', ''),
        ];

        $result = Invoice::paginate($filters, $page, 20);

        $this->render('iatms/invoices/index', [
            'pageTitle' => 'Invoices (Receivable & Payable)',
            'activeNav' => 'iatms_invoices',
            'invoices' => $result['data'],
            'pagination' => $result,
            'filters' => $filters,
            'canManage' => AuthorizationService::canManageIatmsFinance($user),
        ]);
    }

    public function invoiceCreate(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canManageIatmsFinance($user)) {
            $this->denyAccess($request, 'Unauthorized access to create Invoice.');
        }

        $this->render('iatms/invoices/create', [
            'pageTitle' => 'Create Invoice',
            'activeNav' => 'iatms_invoices',
            'nextInvoiceNumber' => Invoice::generateInvoiceNumber(),
            'clients' => Client::allActive(),
            'suppliers' => Supplier::allActive(),
            'paymentTerms' => PaymentTerm::allActive(),
            'deliveries' => StockOut::paginate(['status' => ''], 1, 50)['data'],
        ]);
    }

    public function invoiceStore(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canManageIatmsFinance($user)) {
            $this->denyAccess($request, 'Unauthorized attempt to create Invoice.');
        }

        $post = $request->allPost();
        $descs = $post['item_desc'] ?? [];
        $qtys = $post['item_qty'] ?? [];
        $prices = $post['item_price'] ?? [];

        $items = [];
        if (is_array($descs)) {
            foreach ($descs as $i => $d) {
                $desc = trim((string) $d);
                $qty = (int) ($qtys[$i] ?? 0);
                if ($desc === '' || $qty <= 0) {
                    continue;
                }
                $items[] = [
                    'description' => $desc,
                    'quantity' => $qty,
                    'unit_price' => (float) ($prices[$i] ?? 0),
                ];
            }
        }

        try {
            $res = Invoice::create($post, $items, (int) $user['id']);
            Session::flash('success', "Invoice {$res['invoice_number']} created successfully.");
            Response::redirect('/invoices/' . $res['invoice_id']);
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
            Response::redirect('/invoices/create');
        }
    }

    public function invoiceShow(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canViewIatmsFinance($user)) {
            $this->denyAccess($request, 'Unauthorized access to Invoice.');
        }

        $id = (int) ($params['id'] ?? 0);
        $invoice = Invoice::find($id);
        if (!$invoice) {
            $this->notFound('Invoice not found.');
        }

        $this->render('iatms/invoices/show', [
            'pageTitle' => 'Invoice — ' . $invoice['invoice_number'],
            'activeNav' => 'iatms_invoices',
            'invoice' => $invoice,
            'items' => Invoice::getItems($id),
            'collections' => Collection::forInvoice($id),
            'canManage' => AuthorizationService::canManageIatmsFinance($user),
        ]);
    }

    public function invoiceCancel(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canManageIatmsFinance($user)) {
            $this->denyAccess($request, 'Unauthorized attempt to cancel Invoice.');
        }

        $id = (int) ($params['id'] ?? 0);
        try {
            Invoice::cancel($id, (string) $request->post('reason', ''), (int) $user['id']);
            Session::flash('success', 'Invoice cancelled.');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        Response::redirect("/invoices/{$id}");
    }

    public function collectionsIndex(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canViewIatmsFinance($user)) {
            $this->denyAccess($request, 'Unauthorized access to Collections.');
        }

        $page = max(1, (int) $request->query('page', '1'));
        $filters = [
            'search' => (string) $request->query('search', ''),
            'status' => (string) $request->query('status', ''),
        ];

        $result = Collection::paginate($filters, $page, 20);

        $this->render('iatms/invoices/collections', [
            'pageTitle' => 'Payment Collections & Disbursements',
            'activeNav' => 'iatms_collections',
            'collections' => $result['data'],
            'pagination' => $result,
            'filters' => $filters,
            'openInvoices' => Invoice::getOpenInvoices(),
            'canManage' => AuthorizationService::canManageIatmsFinance($user),
        ]);
    }

    public function collectionStore(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canManageIatmsFinance($user)) {
            $this->denyAccess($request, 'Unauthorized attempt to record Collection.');
        }

        $post = $request->allPost();
        $redirectInvoice = !empty($post['redirect_invoice']) ? (int) $post['invoice_id'] : 0;

        try {
            $res = Collection::recordCollection($post, (int) $user['id']);
            Session::flash('success', "Collection {$res['collection_number']} recorded. Invoice status: {$res['invoice_status']}.");
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        Response::redirect($redirectInvoice > 0 ? "/invoices/{$redirectInvoice}" : '/collections');
    }

    public function collectionVoid(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canManageIatmsFinance($user)) {
            $this->denyAccess($request, 'Unauthorized attempt to void Collection.');
        }

        $id = (int) ($params['id'] ?? 0);
        try {
            $res = Collection::voidCollection($id, (string) $request->post('void_reason', ''), (int) $user['id']);
            Session::flash('success', 'Collection voided and invoice balance restored.');
            Response::redirect('/invoices/' . $res['invoice_id']);
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
            Response::redirect('/collections');
        }
    }

    public function warrantiesIndex(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canAccessIatms($user)) {
            $this->denyAccess($request, 'Unauthorized access to Warranties.');
        }

        $page = max(1, (int) $request->query('page', '1'));
        $filters = [
            'search' => (string) $request->query('search', ''),
            'status' => (string) $request->query('status', ''),
        ];

        $result = Warranty::paginate($filters, $page, 20);

        $this->render('iatms/warranties/index', [
            'pageTitle' => 'Warranty Certificates & Expirations',
            'activeNav' => 'iatms_warranties',
            'warranties' => $result['data'],
            'pagination' => $result,
            'filters' => $filters,
            'canManage' => AuthorizationService::canManageIatmsWarranties($user),
        ]);
    }

    public function warrantyShow(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canAccessIatms($user)) {
            $this->denyAccess($request, 'Unauthorized access to Warranty detail.');
        }

        $id = (int) ($params['id'] ?? 0);
        $warranty = Warranty::find($id);
        if (!$warranty) {
            $this->notFound('Warranty certificate not found.');
        }

        $this->render('iatms/warranties/show', [
            'pageTitle' => 'Warranty — ' . $warranty['warranty_code'],
            'activeNav' => 'iatms_warranties',
            'warranty' => $warranty,
            'canManage' => AuthorizationService::canManageIatmsWarranties($user),
        ]);
    }

    public function warrantyAdjust(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canManageIatmsWarranties($user)) {
            $this->denyAccess($request, 'Unauthorized attempt to adjust Warranty.');
        }

        $id = (int) ($params['id'] ?? 0);
        try {
            Warranty::adjustWarranty(
                $id,
                $request->post('start_date'),
                (int) $request->post('duration_months', '24'),
                (string) $request->post('reason', ''),
                (int) $user['id'],
                $request->post('remarks')
            );
            Session::flash('success', 'Warranty dates adjusted and audit-logged.');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        Response::redirect("/warranties/{$id}");
    }

    public function warrantyClaimStore(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canManageIatmsWarranties($user)) {
            $this->denyAccess($request, 'Unauthorized attempt to create Warranty Claim.');
        }

        $id = (int) ($params['id'] ?? 0);
        try {
            Warranty::createClaim($id, $request->allPost(), (int) $user['id']);
            Session::flash('success', 'Warranty RMA claim logged.');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        Response::redirect("/warranties/{$id}");
    }
}
