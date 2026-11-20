<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="h5 mb-0 fw-semibold">Payment Collections &amp; Disbursements</h2>
        <div class="text-secondary small">Complete settlement ledger with atomic invoice balance synchronization.</div>
    </div>
    <a href="<?= url('/invoices') ?>" class="btn btn-outline-secondary btn-sm">View Invoices</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Collection No.</th>
                    <th>Invoice No.</th>
                    <th>Party</th>
                    <th>Date</th>
                    <th>Method / Ref</th>
                    <th class="text-end">Amount</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($collections)): ?>
                    <tr><td colspan="7" class="text-center text-secondary py-4">No collections recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($collections as $c): ?>
                        <tr>
                            <td class="font-monospace fw-semibold"><?= e($c['collection_number']) ?></td>
                            <td><a href="<?= url('/invoices/' . (int) $c['invoice_id']) ?>" class="font-monospace"><?= e($c['invoice_number']) ?></a></td>
                            <td><?= e($c['client_name'] ?: ($c['supplier_name'] ?: '—')) ?></td>
                            <td><?= e($c['collection_date']) ?></td>
                            <td><?= ucfirst(str_replace('_', ' ', $c['payment_method'])) ?> <?= !empty($c['reference_number']) ? '(' . e($c['reference_number']) . ')' : '' ?></td>
                            <td class="text-end fw-semibold"><?= e($c['currency']) ?> <?= number_format((float) $c['amount_received'], 2) ?></td>
                            <td class="text-center"><span class="badge <?= $c['status'] === 'cleared' ? 'bg-success' : 'bg-secondary' ?>"><?= ucfirst($c['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
